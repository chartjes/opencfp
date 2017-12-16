<?php

declare(strict_types=1);

/**
 * Copyright (c) 2013-2017 OpenCFP
 *
 * For the full copyright and license information, please view
 * the LICENSE file that was distributed with this source code.
 *
 * @see https://github.com/opencfp/opencfp
 */

namespace OpenCFP\Test\Unit\Infrastructure\Auth;

use Cartalyst\Sentinel\Sentinel;
use Localheinz\Test\Util\Helper;
use Mockery;
use OpenCFP\Domain\Model\User;
use OpenCFP\Domain\Repository\UserRepository;
use OpenCFP\Domain\Services\IdentityProvider;
use OpenCFP\Infrastructure\Auth\SentinelIdentityProvider;
use PHPUnit\Framework;

/**
 * @covers \OpenCFP\Infrastructure\Auth\SentinelIdentityProvider
 */
final class SentinelIdentityProviderTest extends Framework\TestCase
{
    use Helper;

    public function testIsFinal()
    {
        $this->assertClassIsFinal(SentinelIdentityProvider::class);
    }

    public function testImplementsIdentityProvider()
    {
        $this->assertClassImplementsInterface(IdentityProvider::class, SentinelIdentityProvider::class);
    }

    public function testGetCurrentUserThrowsNotAuthenticatedExceptionWhenNotAuthenticated()
    {
        $sentinel = $this->getSentinel();

        $sentinel
            ->shouldReceive('getUser')
            ->once()
            ->andReturnNull();

        $userRepository = $this->createUserRepositoryMock();

        $userRepository->shouldNotReceive(Mockery::any());

        $provider = new SentinelIdentityProvider(
            $sentinel,
            $userRepository
        );

        $this->expectException(\OpenCFP\Domain\Services\NotAuthenticatedException::class);

        $provider->getCurrentUser();
    }

    public function testGetCurrentUserReturnsUserWhenAuthenticated()
    {
        $id = $this->faker()->randomNumber();

        $sentinelUser = $this->getSentinelUserMock();

        $sentinelUser
            ->shouldReceive('getUserId')
            ->once()
            ->andReturn($id);

        $sentinel = $this->getSentinel();

        $sentinel
            ->shouldReceive('getUser')
            ->once()
            ->andReturn($sentinelUser);

        $user = $this->getUserMock();

        $userRepository = $this->createUserRepositoryMock();

        $userRepository
            ->shouldReceive('findById')
            ->once()
            ->with($id)
            ->andReturn($user);

        $provider = new SentinelIdentityProvider(
            $sentinel,
            $userRepository
        );

        $this->assertSame($user, $provider->getCurrentUser());
    }

    //
    // Factory Methods
    //

    /**
     * @return m\MockInterface|Sentinel
     */
    private function getSentinel()
    {
        return Mockery::mock(Sentinel::class);
    }

    /**
     * @return \Cartalyst\Sentinel\Users\UserInterface|m\MockInterface
     */
    private function getSentinelUserMock()
    {
        return Mockery::mock(\Cartalyst\Sentinel\Users\UserInterface::class);
    }

    /**
     * @return m\MockInterface|UserRepository
     */
    private function createUserRepositoryMock()
    {
        return Mockery::mock(UserRepository::class);
    }

    /**
     * @return m\MockInterface|User
     */
    private function getUserMock()
    {
        return Mockery::mock(User::class);
    }
}
