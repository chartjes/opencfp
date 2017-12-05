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

namespace OpenCFP\Test\Integration\Http\Controller;

use Mockery as m;
use OpenCFP\Application;
use OpenCFP\Domain\Services\AccountManagement;
use OpenCFP\Environment;
use OpenCFP\Infrastructure\Auth\UserInterface;
use Symfony\Component\HttpFoundation\Request;

/**
 * @group db
 * @coversNothing
 */
final class ForgotControllerTest extends \PHPUnit\Framework\TestCase
{
    protected $app;

    protected function setUp()
    {
        $this->app                 = new Application(__DIR__ . '/../../../..', Environment::testing());
        $this->app['session.test'] = true;
        \ob_start();
        $this->app->run();
        \ob_end_clean();
    }

    /**
     * Test that index action displays a form that allows the user to reset
     * their password
     *
     * @test
     */
    public function indexDisplaysCorrectForm()
    {
        $controller = $this->app[\OpenCFP\Http\Controller\ForgotController::class];
        $response   = $controller->indexAction();

        // Get the form object and verify things look correct
        $this->assertContains(
            '<form id="forgot"',
            (string) $response
        );
        $this->assertContains(
            '<input type="hidden" id="forgot_form__token"',
            (string) $response
        );
        $this->assertContains(
            '<input type="email" id="forgot_form_email"',
            (string) $response
        );
    }

    /**
     * @test
     */
    public function sendResetDisplaysCorrectMessage()
    {
        $accounts = m::mock(AccountManagement::class);
        $accounts->shouldReceive('findByLogin')->andReturn($this->createUser());
        unset($this->app[AccountManagement::class]);
        $this->app[AccountManagement::class] = $accounts;

        // Override our reset_emailer service
        $resetEmailer = m::mock(\OpenCFP\Domain\Services\ResetEmailer::class);
        $resetEmailer->shouldReceive('send')->andReturn(true);
        $this->app['reset_emailer'] = $resetEmailer;

        // We need to create a replacement form.factory to return a form we control
        $formFactory = m::mock(\Symfony\Component\Form\FormFactoryInterface::class);
        $formFactory->shouldReceive('createBuilder->getForm')->andReturn($this->createForm('valid'));
        $this->app['form.factory'] = $formFactory;

        $controller = $this->app[\OpenCFP\Http\Controller\ForgotController::class];
        $request    = new Request();
        $request->setSession($this->app['session']);
        $controller->sendResetAction($request);

        // As long as the email validates as being a potential email, the flash message should indicate success
        $flashMessage = $this->app['session']->get('flash');
        $this->assertContains(
            'If your email was valid, we sent a link to reset your password to',
            $flashMessage['ext']
        );
    }

    /**
     * @test
     */
    public function invalidResetFormTriggersErrorMessage()
    {
        $formFactory = m::mock(\Symfony\Component\Form\FormFactoryInterface::class);
        $formFactory->shouldReceive('createBuilder->getForm')->andReturn($this->createForm('not valid'));
        $this->app['form.factory'] = $formFactory;

        $controller = $this->app[\OpenCFP\Http\Controller\ForgotController::class];
        $request    = new Request();
        $request->setSession($this->app['session']);
        $controller->sendResetAction($request);

        $flashMessage = $this->app['session']->get('flash');
        $this->assertContains(
            'Please enter a properly formatted email address',
            $flashMessage['ext']
        );
    }

    /**
     * @test
     */
    public function resetPasswordNotFindingUserCorrectlyDisplaysMessage()
    {
        $formFactory = m::mock(\Symfony\Component\Form\FormFactoryInterface::class);
        $formFactory->shouldReceive('createBuilder->getForm')->andReturn($this->createForm('valid'));
        $this->app['form.factory'] = $formFactory;

        $controller = $this->app[\OpenCFP\Http\Controller\ForgotController::class];
        $request    = new Request();
        $request->setSession($this->app['session']);
        $controller->sendResetAction($request);

        $flashMessage = $this->app['session']->get('flash');
        $this->assertContains(
            'If your email was valid, we sent a link to reset your password to',
            $flashMessage['ext']
        );
    }

    /**
     * @test
     */
    public function resetPasswordHandlesNotSendingResetEmailCorrectly()
    {
        $accounts = m::mock(AccountManagement::class);
        $accounts->shouldReceive('findByLogin')->andReturn($this->createUser());
        unset($this->app[AccountManagement::class]);
        $this->app[AccountManagement::class] = $accounts;

        // Override our reset_emailer service
        $resetEmailer = m::mock(\OpenCFP\Domain\Services\ResetEmailer::class);
        $resetEmailer->shouldReceive('send')->andReturn(false);
        $this->app['reset_emailer'] = $resetEmailer;

        // We need to create a replacement form.factory to return a form we control
        $formFactory = m::mock(\Symfony\Component\Form\FormFactoryInterface::class);
        $formFactory->shouldReceive('createBuilder->getForm')->andReturn($this->createForm('valid'));
        $this->app['form.factory'] = $formFactory;

        $controller = $this->app[\OpenCFP\Http\Controller\ForgotController::class];
        $request    = new Request();
        $request->setSession($this->app['session']);
        $controller->sendResetAction($request);

        // As long as the email validates as being a potential email, the flash message should indicate success
        $flashMessage = $this->app['session']->get('flash');
        $this->assertContains(
            'We were unable to send your password reset request. Please try again',
            $flashMessage['ext']
        );
    }

    private function createUser(): UserInterface
    {
        $user = m::mock(UserInterface::class);
        $user->shouldReceive('getResetPasswordCode');
        $user->shouldReceive('getId');

        return $user;
    }

    private function createForm($validStatus): \OpenCFP\Http\Form\ForgotForm
    {
        $isValid = ($validStatus == 'valid');
        $form    = m::mock(\OpenCFP\Http\Form\ForgotForm::class);
        $form->shouldReceive('handleRequest');
        $form->shouldReceive('isValid')->andReturn($isValid);
        $data = ['email' => 'test@opencfp.org'];
        $form->shouldReceive('getData')->andReturn($data);

        return $form;
    }
}
