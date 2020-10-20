<?php

namespace Tests\Traits;

use Mockery;
use Mockery\MockInterface;
use GuzzleHttp\Exception\RequestException;

trait MocksRequestException
{
    /**
     * @var \GuzzleHttp\Exception\RequestException|\Mockery\Mock
     */
    private $exception;

    /**
     * @var mixed
     */
    private $exceptionResponse;

    /**
     * Configure the exception mock to work with the Panel's default exception
     * handler actions.
     *
     * @param string $abstract
     * @param null   $response
     */
    protected function configureExceptionMock(string $abstract = RequestException::class, $response = null)
    {
        $this->getExceptionMock($abstract)->shouldReceive('getResponse')->andReturn(value($response));
    }

    /**
     * Return a mocked instance of the request exception.
     *
     * @param string $abstract
     * @return \Mockery\MockInterface
     */
    protected function getExceptionMock(string $abstract = RequestException::class): MockInterface
    {
        return $this->exception ?? $this->exception = Mockery::mock($abstract);
    }
}
