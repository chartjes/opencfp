<?php

namespace OpenCFP\Test\Unit\Infrastructure\Auth;

use Cartalyst\Sentry\Sentry;
use Cartalyst\Sentry\Users;
use Mockery as m;
use OpenCFP\Domain\Model;
use OpenCFP\Domain\Speaker\SpeakerRepository;
use OpenCFP\Infrastructure\Auth\Contracts\IdentityProvider;
use OpenCFP\Infrastructure\Auth\SentryIdentityProvider;
use OpenCFP\Test\Helper\Faker\GeneratorTrait;

/**
 * @covers \OpenCFP\Infrastructure\Auth\SentryIdentityProvider
 */
class SentryIdentityProviderTest extends \PHPUnit\Framework\TestCase
{
    use GeneratorTrait;

    public function testImplementsIdentityProvider()
    {
        $sentry            = $this->getSentryMock();
        $speakerRepository = $this->getSpeakerRepositoryMock();

        $provider = new SentryIdentityProvider(
            $sentry,
            $speakerRepository
        );

        $this->assertInstanceOf(IdentityProvider::class, $provider);
    }
    
    public function testGetCurrentUserThrowsNotAuthenticatedExceptionWhenNotAuthenticated()
    {
        $sentry = $this->getSentryMock();

        $sentry
            ->shouldReceive('getUser')
            ->once()
            ->andReturnNull();

        $speakerRepository = $this->getSpeakerRepositoryMock();

        $speakerRepository->shouldNotReceive(m::any());

        $provider = new SentryIdentityProvider(
            $sentry,
            $speakerRepository
        );

        $this->expectException(\OpenCFP\Domain\Services\NotAuthenticatedException::class);

        $provider->getCurrentUser();
    }

    public function testGetCurrentUserReturnsUserWhenAuthenticated()
    {
        $id = $this->getFaker()->randomNumber();

        $sentryUser =  $this->getSentryUserMock();

        $sentryUser
            ->shouldReceive('getId')
            ->once()
            ->andReturn($id);

        $sentry = $this->getSentryMock();

        $sentry
            ->shouldReceive('getUser')
            ->once()
            ->andReturn($sentryUser);

        $user = $this->getUserMock();

        $speakerRepository = $this->getSpeakerRepositoryMock();

        $speakerRepository
            ->shouldReceive('findById')
            ->once()
            ->with($id)
            ->andReturn($user);

        $provider = new SentryIdentityProvider(
            $sentry,
            $speakerRepository
        );

        $this->assertSame($user, $provider->getCurrentUser());
    }

    //
    // Factory Methods
    //

    /**
     * @return m\MockInterface|Sentry
     */
    private function getSentryMock(): Sentry
    {
        return m::mock(Sentry::class);
    }

    /**
     * @return m\MockInterface|Users\UserInterface
     */
    private function getSentryUserMock(): Users\UserInterface
    {
        return m::mock(Users\UserInterface::class);
    }

    /**
     * @return m\MockInterface|SpeakerRepository
     */
    private function getSpeakerRepositoryMock(): SpeakerRepository
    {
        return m::mock(SpeakerRepository::class);
    }

    /**
     * @return m\MockInterface|Model\User
     */
    private function getUserMock()
    {
        return m::mock(Model\User::class);
    }
}
