<?php

namespace Pterodactyl\Http\Controllers\Auth;

use Illuminate\Http\Request;
use Illuminate\Auth\AuthManager;
use PragmaRX\Google2FA\Google2FA;
use Illuminate\Auth\Events\Failed;
use Illuminate\Http\RedirectResponse;
use Pterodactyl\Http\Controllers\Controller;
use Illuminate\Contracts\Auth\Authenticatable;
use Illuminate\Contracts\Encryption\Encrypter;
use Illuminate\Foundation\Auth\AuthenticatesUsers;
use Illuminate\Contracts\Cache\Repository as CacheRepository;
use Pterodactyl\Contracts\Repository\UserRepositoryInterface;
use Pterodactyl\Exceptions\Repository\RecordNotFoundException;
use Illuminate\Contracts\Config\Repository as ConfigRepository;

class LoginController extends Controller
{
    use AuthenticatesUsers;

    /**
     * @var \Illuminate\Auth\AuthManager
     */
    private $auth;

    /**
     * @var \Illuminate\Contracts\Cache\Repository
     */
    private $cache;

    /**
     * @var \Illuminate\Contracts\Config\Repository
     */
    private $config;

    /**
     * @var \Illuminate\Contracts\Encryption\Encrypter
     */
    private $encrypter;

    /**
     * @var \Pterodactyl\Contracts\Repository\UserRepositoryInterface
     */
    private $repository;

    /**
     * @var \PragmaRX\Google2FA\Google2FA
     */
    private $google2FA;

    /**
     * Where to redirect users after login / registration.
     *
     * @var string
     */
    protected $redirectTo = '/';

    /**
     * Lockout time for failed login requests.
     *
     * @var int
     */
    protected $decayMinutes;

    /**
     * After how many attempts should logins be throttled and locked.
     *
     * @var int
     */
    protected $maxAttempts;

    /**
     * LoginController constructor.
     *
     * @param \Illuminate\Auth\AuthManager                              $auth
     * @param \Illuminate\Contracts\Cache\Repository                    $cache
     * @param \Illuminate\Contracts\Config\Repository                   $config
     * @param \Illuminate\Contracts\Encryption\Encrypter                $encrypter
     * @param \PragmaRX\Google2FA\Google2FA                             $google2FA
     * @param \Pterodactyl\Contracts\Repository\UserRepositoryInterface $repository
     */
    public function __construct(
        AuthManager $auth,
        CacheRepository $cache,
        ConfigRepository $config,
        Encrypter $encrypter,
        Google2FA $google2FA,
        UserRepositoryInterface $repository
    ) {
        $this->auth = $auth;
        $this->cache = $cache;
        $this->config = $config;
        $this->encrypter = $encrypter;
        $this->google2FA = $google2FA;
        $this->repository = $repository;

        $this->decayMinutes = $this->config->get('auth.lockout.time');
        $this->maxAttempts = $this->config->get('auth.lockout.attempts');
    }

    /**
     * Handle a login request to the application.
     *
     * @param \Illuminate\Http\Request $request
     * @return \Illuminate\Http\RedirectResponse|\Illuminate\Http\Response
     *
     * @throws \Illuminate\Validation\ValidationException
     */
    public function login(Request $request)
    {
        $username = $request->input($this->username());
        $useColumn = $this->getField($username);

        if ($this->hasTooManyLoginAttempts($request)) {
            $this->fireLockoutEvent($request);
            $this->sendLockoutResponse($request);
        }

        try {
            $user = $this->repository->findFirstWhere([[$useColumn, '=', $username]]);
        } catch (RecordNotFoundException $exception) {
            return $this->sendFailedLoginResponse($request);
        }

        if (! password_verify($request->input('password'), $user->password)) {
            return $this->sendFailedLoginResponse($request, $user);
        }

        if ($user->use_totp) {
            $token = str_random(64);
            $this->cache->put($token, ['user_id' => $user->id, 'valid_credentials' => true], 5);

            return redirect()->route('auth.totp')->with('authentication_token', $token);
        }

        $this->auth->guard()->login($user, true);

        return $this->sendLoginResponse($request);
    }

    /**
     * Handle a TOTP implementation page.
     *
     * @param \Illuminate\Http\Request $request
     * @return \Illuminate\Http\RedirectResponse|\Illuminate\View\View
     */
    public function totp(Request $request)
    {
        $token = $request->session()->get('authentication_token');
        if (is_null($token) || $this->auth->guard()->user()) {
            return redirect()->route('auth.login');
        }

        return view('auth.totp', ['verify_key' => $token]);
    }

    /**
     * Handle a login where the user is required to provide a TOTP authentication
     * token.
     *
     * @param \Illuminate\Http\Request $request
     * @return \Illuminate\Http\RedirectResponse|\Illuminate\Http\Response
     * @throws \PragmaRX\Google2FA\Exceptions\IncompatibleWithGoogleAuthenticatorException
     * @throws \PragmaRX\Google2FA\Exceptions\InvalidCharactersException
     * @throws \PragmaRX\Google2FA\Exceptions\SecretKeyTooShortException
     */
    public function loginUsingTotp(Request $request)
    {
        if (is_null($request->input('verify_token'))) {
            return $this->sendFailedLoginResponse($request);
        }

        try {
            $cache = $this->cache->pull($request->input('verify_token'), []);
            $user = $this->repository->find(array_get($cache, 'user_id', 0));
        } catch (RecordNotFoundException $exception) {
            return $this->sendFailedLoginResponse($request);
        }

        if (is_null($request->input('2fa_token'))) {
            return $this->sendFailedLoginResponse($request, $user);
        }

        if (! $this->google2FA->verifyKey(
            $this->encrypter->decrypt($user->totp_secret),
            $request->input('2fa_token'),
            $this->config->get('pterodactyl.auth.2fa.window')
        )) {
            return $this->sendFailedLoginResponse($request, $user);
        }

        $this->auth->guard()->login($user, true);

        return $this->sendLoginResponse($request);
    }

    /**
     * Get the failed login response instance.
     *
     * @param \Illuminate\Http\Request                        $request
     * @param \Illuminate\Contracts\Auth\Authenticatable|null $user
     * @return \Illuminate\Http\RedirectResponse
     */
    protected function sendFailedLoginResponse(Request $request, Authenticatable $user = null): RedirectResponse
    {
        $this->incrementLoginAttempts($request);
        $this->fireFailedLoginEvent($user, [
            $this->getField($request->input($this->username())) => $request->input($this->username()),
        ]);

        $errors = [$this->username() => trans('auth.failed')];

        if ($request->expectsJson()) {
            return response()->json($errors, 422);
        }

        return redirect()->route('auth.login')
            ->withInput($request->only($this->username()))
            ->withErrors($errors);
    }

    /**
     * Get the login username to be used by the controller.
     *
     * @return string
     */
    public function username()
    {
        return 'user';
    }

    /**
     * Determine if the user is logging in using an email or username,.
     *
     * @param string $input
     * @return string
     */
    private function getField(string $input = null): string
    {
        return str_contains($input, '@') ? 'email' : 'username';
    }

    /**
     * Fire a failed login event.
     *
     * @param \Illuminate\Contracts\Auth\Authenticatable|null $user
     * @param array                                           $credentials
     */
    private function fireFailedLoginEvent(Authenticatable $user = null, array $credentials = [])
    {
        event(new Failed(config('auth.defaults.guard'), $user, $credentials));
    }
}
