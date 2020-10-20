<?php

namespace Pterodactyl\Http\Middleware\Server;

use Closure;
use Illuminate\Http\Request;
use Pterodactyl\Models\Server;
use Illuminate\Contracts\Session\Session;
use Illuminate\Contracts\Routing\ResponseFactory;
use Illuminate\Contracts\Config\Repository as ConfigRepository;
use Pterodactyl\Contracts\Repository\ServerRepositoryInterface;
use Symfony\Component\HttpKernel\Exception\ConflictHttpException;
use Symfony\Component\HttpKernel\Exception\AccessDeniedHttpException;

class AccessingValidServer
{
    /**
     * @var \Illuminate\Contracts\Config\Repository
     */
    private $config;

    /**
     * @var \Pterodactyl\Contracts\Repository\ServerRepositoryInterface
     */
    private $repository;

    /**
     * @var \Illuminate\Contracts\Routing\ResponseFactory
     */
    private $response;

    /**
     * @var \Illuminate\Contracts\Session\Session
     */
    private $session;

    /**
     * AccessingValidServer constructor.
     *
     * @param \Illuminate\Contracts\Config\Repository                     $config
     * @param \Illuminate\Contracts\Routing\ResponseFactory               $response
     * @param \Pterodactyl\Contracts\Repository\ServerRepositoryInterface $repository
     * @param \Illuminate\Contracts\Session\Session                       $session
     */
    public function __construct(
        ConfigRepository $config,
        ResponseFactory $response,
        ServerRepositoryInterface $repository,
        Session $session
    ) {
        $this->config = $config;
        $this->repository = $repository;
        $this->response = $response;
        $this->session = $session;
    }

    /**
     * Determine if a given user has permission to access a server.
     *
     * @param \Illuminate\Http\Request $request
     * @param \Closure                 $next
     * @return \Illuminate\Http\Response|mixed
     *
     * @throws \Illuminate\Auth\AuthenticationException
     * @throws \Pterodactyl\Exceptions\Repository\RecordNotFoundException
     * @throws \Symfony\Component\HttpKernel\Exception\AccessDeniedHttpException
     * @throws \Symfony\Component\HttpKernel\Exception\NotFoundHttpException
     */
    public function handle(Request $request, Closure $next)
    {
        $attributes = $request->route()->parameter('server');
        $isApiRequest = $request->expectsJson() || $request->is(...$this->config->get('pterodactyl.json_routes', []));
        $server = $this->repository->getByUuid($attributes instanceof Server ? $attributes->uuid : $attributes);

        if ($server->suspended) {
            if ($isApiRequest) {
                throw new AccessDeniedHttpException('Server is suspended and cannot be accessed.');
            }

            return $this->response->view('errors.suspended', [], 403);
        }

        // Servers can have install statuses other than 1 or 0, so don't check
        // for a bool-type operator here.
        if ($server->installed !== 1) {
            if ($isApiRequest) {
                throw new ConflictHttpException('Server is still completing the installation process.');
            }

            return $this->response->view('errors.installing', [], 409);
        }

        // Store the server in the session.
        // @todo remove from session. use request attributes.
        $this->session->now('server_data.model', $server);

        // Add server to the request attributes. This will replace sessions
        // as files are updated.
        $request->attributes->set('server', $server);

        return $next($request);
    }
}
