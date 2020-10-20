<?php

namespace Pterodactyl\Http\Controllers\Api\Application;

use Illuminate\Http\Request;
use Webmozart\Assert\Assert;
use Illuminate\Http\Response;
use Illuminate\Container\Container;
use Pterodactyl\Http\Controllers\Controller;
use Pterodactyl\Extensions\Spatie\Fractalistic\Fractal;
use Pterodactyl\Transformers\Api\Application\BaseTransformer;

abstract class ApplicationApiController extends Controller
{
    /**
     * @var \Illuminate\Http\Request
     */
    protected $request;

    /**
     * @var \Pterodactyl\Extensions\Spatie\Fractalistic\Fractal
     */
    protected $fractal;

    /**
     * ApplicationApiController constructor.
     */
    public function __construct()
    {
        Container::getInstance()->call([$this, 'loadDependencies']);

        // Parse all of the includes to use on this request.
        $includes = collect(explode(',', $this->request->input('include', '')))->map(function ($value) {
            return trim($value);
        })->filter()->toArray();

        $this->fractal->parseIncludes($includes);
        $this->fractal->limitRecursion(2);
    }

    /**
     * Perform dependency injection of certain classes needed for core functionality
     * without littering the constructors of classes that extend this abstract.
     *
     * @param \Pterodactyl\Extensions\Spatie\Fractalistic\Fractal $fractal
     * @param \Illuminate\Http\Request                            $request
     */
    public function loadDependencies(Fractal $fractal, Request $request)
    {
        $this->fractal = $fractal;
        $this->request = $request;
    }

    /**
     * Return an instance of an application transformer.
     *
     * @param string $abstract
     * @return \Pterodactyl\Transformers\Api\Application\BaseTransformer
     */
    public function getTransformer(string $abstract)
    {
        /** @var \Pterodactyl\Transformers\Api\Application\BaseTransformer $transformer */
        $transformer = Container::getInstance()->make($abstract);
        $transformer->setKey($this->request->attributes->get('api_key'));

        Assert::isInstanceOf($transformer, BaseTransformer::class);

        return $transformer;
    }

    /**
     * Return a HTTP/204 response for the API.
     *
     * @return \Illuminate\Http\Response
     */
    protected function returnNoContent(): Response
    {
        return new Response('', Response::HTTP_NO_CONTENT);
    }
}
