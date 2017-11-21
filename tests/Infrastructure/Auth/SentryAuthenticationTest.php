<?php

namespace OpenCFP\Test\Infrastructure\Auth;

use OpenCFP\Infrastructure\Auth\SentryAccountManagement;
use OpenCFP\Infrastructure\Auth\SentryAuthentication;
use OpenCFP\Test\BaseTestCase;
use OpenCFP\Test\Helper\DataBaseInteraction;
use OpenCFP\Test\Helper\SentryTestHelpers;

/**
 * @covers \OpenCFP\Infrastructure\Auth\SentryAuthentication
 * @group db
 */
class SentryAuthenticationTest extends BaseTestCase
{
    use DataBaseInteraction;
    use SentryTestHelpers;

    /**
     * @var SentryAuthentication
     */
    private $sut;

    protected function setUp()
    {
        parent::setUp();

        $accounts = new SentryAccountManagement($this->getSentry());

        $accounts->create('test@example.com', 'secret');
        $accounts->activate('test@example.com');

        $this->sut = new SentryAuthentication($this->getSentry());
    }

    /**
     * @test
     */
    public function existing_user_can_authenticate()
    {
        $this->sut->authenticate('test@example.com', 'secret');
        $this->assertTrue($this->sut->check());

        $user = $this->sut->user();

        $this->assertEquals('test@example.com', $user->getLogin());
    }

    /**
     * @test
     */
    public function userIdWorks()
    {
        $this->sut->authenticate('test@example.com', 'secret');
        $this->assertSame(1, $this->sut->userId());
    }

    /**
     * @test
     */
    public function checkWorks()
    {
        $this->assertFalse($this->sut->check());
        $this->sut->authenticate('test@example.com', 'secret');
        $this->assertTrue($this->sut->check());
    }

    /**
     * @test
     */
    public function guestWorks()
    {
        $this->assertTrue($this->sut->guest());
        $this->sut->authenticate('test@example.com', 'secret');
        $this->assertFalse($this->sut->guest());
    }
}
