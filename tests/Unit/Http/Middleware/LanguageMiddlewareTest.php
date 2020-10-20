<?php

namespace Tests\Unit\Http\Middleware;

use Mockery as m;
use Pterodactyl\Models\User;
use Illuminate\Foundation\Application;
use Pterodactyl\Http\Middleware\LanguageMiddleware;

class LanguageMiddlewareTest extends MiddlewareTestCase
{
    /**
     * @var \Illuminate\Foundation\Application|\Mockery\Mock
     */
    private $appMock;

    /**
     * Setup tests.
     */
    public function setUp()
    {
        parent::setUp();

        $this->appMock = m::mock(Application::class);
    }

    /**
     * Test that a language is defined via the middleware for guests.
     */
    public function testLanguageIsSetForGuest()
    {
        $this->request->shouldReceive('user')->withNoArgs()->andReturnNull();
        $this->appMock->shouldReceive('setLocale')->with('en')->once()->andReturnNull();

        $this->getMiddleware()->handle($this->request, $this->getClosureAssertions());
    }

    /**
     * Test that a language is defined via the middleware for a user.
     */
    public function testLanguageIsSetWithAuthenticatedUser()
    {
        $user = factory(User::class)->make(['language' => 'de']);

        $this->request->shouldReceive('user')->withNoArgs()->andReturn($user);
        $this->appMock->shouldReceive('setLocale')->with('de')->once()->andReturnNull();

        $this->getMiddleware()->handle($this->request, $this->getClosureAssertions());
    }

    /**
     * Return an instance of the middleware using mocked dependencies.
     *
     * @return \Pterodactyl\Http\Middleware\LanguageMiddleware
     */
    private function getMiddleware(): LanguageMiddleware
    {
        return new LanguageMiddleware($this->appMock);
    }
}
