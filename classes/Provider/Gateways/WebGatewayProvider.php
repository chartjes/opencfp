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

namespace OpenCFP\Provider\Gateways;

use Illuminate\Database\Capsule\Manager as Capsule;
use OpenCFP\Domain\CallForPapers;
use OpenCFP\Domain\Services\AccountManagement;
use OpenCFP\Domain\Services\AirportInformationDatabase;
use OpenCFP\Domain\Services\Authentication;
use OpenCFP\Domain\Talk\TalkFilter;
use OpenCFP\Domain\Talk\TalkHandler;
use OpenCFP\Http\Controller\Admin;
use OpenCFP\Http\Controller\DashboardController;
use OpenCFP\Http\Controller\ForgotController;
use OpenCFP\Http\Controller\PagesController;
use OpenCFP\Http\Controller\ProfileController;
use OpenCFP\Http\Controller\Reviewer;
use OpenCFP\Http\Controller\SecurityController;
use OpenCFP\Http\Controller\SignupController;
use OpenCFP\Http\Controller\TalkController;
use OpenCFP\Http\Controller\TalkFormController;
use OpenCFP\Http\Controller\TalkProcessingController;
use OpenCFP\Http\View\TalkHelper;
use OpenCFP\Infrastructure\Auth\CsrfValidator;
use OpenCFP\Infrastructure\Auth\RoleAccess;
use OpenCFP\Infrastructure\Auth\SpeakerAccess;
use Pimple\Container;
use Pimple\ServiceProviderInterface;
use Silex\Api\BootableProviderInterface;
use Silex\Application;
use Silex\ControllerCollection;
use Symfony\Component\HttpFoundation\RedirectResponse;
use Symfony\Component\HttpFoundation\Request;
use Twig_Environment;
use Twig_SimpleFunction;

class WebGatewayProvider implements BootableProviderInterface, ServiceProviderInterface
{
    public function register(Container $app)
    {
        // Controllers
        $app[DashboardController::class] = function ($app) {
            return new DashboardController($app['application.speakers'], $app['twig'], $app['url_generator']);
        };
        $app[ForgotController::class] = function ($app) {
            return new ForgotController(
                $app['form.factory'],
                $app[AccountManagement::class],
                $app['reset_emailer'],
                $app['twig'],
                $app['url_generator']
            );
        };
        $app[PagesController::class] = function ($app) {
            return new PagesController($app['twig'], $app['url_generator']);
        };
        $app[ProfileController::class] = function ($app) {
            return new ProfileController(
                $app[Authentication::class],
                $app['purifier'],
                $app['profile_image_processor'],
                $app['twig'],
                $app['url_generator']
            );
        };
        $app[SecurityController::class] = function ($app) {
            return new SecurityController(
                $app[Authentication::class],
                $app['twig'],
                $app['url_generator']
            );
        };
        $app[SignupController::class] = function ($app) {
            return new SignupController(
                $app[Authentication::class],
                $app[AccountManagement::class],
                $app[CallForPapers::class],
                $app['twig'],
                $app['url_generator']
            );
        };
        $app[TalkFormController::class] = function ($app) {
            return new TalkFormController(
                $app[Authentication::class],
                $app[TalkHelper::class],
                $app[CallForPapers::class],
                $app['twig'],
                $app['url_generator']
            );
        };
        $app[TalkProcessingController::class] = function ($app) {
            return new TalkProcessingController(
                $app[Authentication::class],
                $app[TalkHelper::class],
                $app[CallForPapers::class],
                $app['purifier'],
                $app['mailer'],
                $app['twig'],
                $app['url_generator'],
                $app->config('application.email'),
                $app->config('application.title'),
                $app->config('application.enddate')
            );
        };
        $app[TalkController::class] = function ($app) {
            return new TalkController(
                $app['application.speakers'],
                $app['twig'],
                $app['url_generator']
            );
        };

        // Admin controllers
        $app[Admin\DashboardController::class] = function ($app) {
            return new Admin\DashboardController(
                $app[Authentication::class],
                $app['twig'],
                $app['url_generator']
            );
        };
        $app[Admin\ExportsController::class] = function ($app) {
            return new Admin\ExportsController($app['twig'], $app['url_generator'], $app['session']);
        };
        $app[Admin\SpeakersController::class] = function ($app) {
            $controller = new Admin\SpeakersController(
                $app[Authentication::class],
                $app[AccountManagement::class],
                $app[AirportInformationDatabase::class],
                $app[Capsule::class],
                $app['twig'],
                $app['url_generator'],
                $app->config('application.airport'),
                $app->config('application.arrival'),
                $app->config('application.departure')
            );

            return $controller;
        };
        $app[Admin\TalksController::class] = function ($app) {
            return new Admin\TalksController(
                $app[Authentication::class],
                $app[TalkFilter::class],
                $app[TalkHandler::class],
                $app['twig'],
                $app['url_generator']
            );
        };

        // Reviewer
        $app[Reviewer\DashboardController::class] = function ($app) {
            return new Reviewer\DashboardController($app[Authentication::class], $app['twig'], $app['url_generator']);
        };
        $app[Reviewer\SpeakersController::class] = function ($app) {
            return new Reviewer\SpeakersController(
                $app['twig'],
                $app['url_generator'],
                $app->config('reviewer.users') ?: []
            );
        };
        $app[Reviewer\TalksController::class] = function ($app) {
            return new Reviewer\TalksController(
                $app[Authentication::class],
                $app[TalkFilter::class],
                $app[TalkHandler::class],
                $app['twig'],
                $app['url_generator']
            );
        };
    }

    public function boot(Application $app)
    {
        // @codingStandardsIgnoreStart
        /* @var $web ControllerCollection */
        $web = $app['controllers_factory'];

        $app->before(new RequestCleaner($app['purifier']));
        $app->before(function (Request $request, Container $app) {
            /* @var Twig_Environment $twig */
            $twig = $app['twig'];

            $twig->addGlobal('current_page', $request->getRequestUri());
            $twig->addGlobal('cfp_open', $app[CallForPapers::class]->isOpen());

            $twig->addFunction(new Twig_SimpleFunction('active', function ($route) use ($app, $request) {
                return $app['url_generator']->generate($route) == $request->getRequestUri();
            }));

            // Authentication
            if ($app[Authentication::class]->isAuthenticated()) {
                $twig->addGlobal('user', $app[Authentication::class]->user());
                $twig->addGlobal('user_is_admin', $app[Authentication::class]->user()->hasAccess('admin'));
                $twig->addGlobal('user_is_reviewer', $app[Authentication::class]->user()->hasAccess('reviewer'));
            }

            if ($app['session']->has('flash')) {
                $twig->addGlobal('flash', $app['session']->get('flash'));
                $app['session']->set('flash', null);
            }
        }, Application::EARLY_EVENT);

        if ($app->config('application.secure_ssl')) {
            $app->requireHttps();
        }

        $asSpeaker = function () use ($app) {
            return SpeakerAccess::userHasAccess($app[Authentication::class]);
        };

        $csrfChecker = function (Request $request) use ($app) {
            $checker = $app[CsrfValidator::class];

            if (!$checker->isValid($request)) {
                return new RedirectResponse('/dashboard');
            }
        };

        $web->get('/', 'OpenCFP\Http\Controller\PagesController::showHomepage')
            ->bind('homepage');
        $web->get('/package', 'OpenCFP\Http\Controller\PagesController::showSpeakerPackage')
            ->bind('speaker_package');
        $web->get('/ideas', 'OpenCFP\Http\Controller\PagesController::showTalkIdeas')
            ->bind('talk_ideas');

        // User Dashboard
        $web->get('/dashboard', 'OpenCFP\Http\Controller\DashboardController::showSpeakerProfile')
            ->bind('dashboard');

        // Talks
        $web->get('/talk/edit/{id}', 'OpenCFP\Http\Controller\TalkFormController::editAction')
            ->bind('talk_edit')->before($asSpeaker)->before($csrfChecker);
        $web->get('/talk/create', 'OpenCFP\Http\Controller\TalkFormController::createAction')
            ->bind('talk_new')->before($asSpeaker);
        $web->post('/talk/create', 'OpenCFP\Http\Controller\TalkProcessingController::processCreateAction')
            ->bind('talk_create')->before($asSpeaker)->before($csrfChecker);
        $web->post('/talk/update', 'OpenCFP\Http\Controller\TalkProcessingController::updateAction')
            ->bind('talk_update')->before($asSpeaker)->before($csrfChecker);
        $web->post('/talk/delete', 'OpenCFP\Http\Controller\TalkFormController::deleteAction')
            ->bind('talk_delete')->before($asSpeaker)->before($csrfChecker);
        $web->get('/talk/{id}', 'OpenCFP\Http\Controller\TalkController::viewAction')
            ->bind('talk_view')->before($asSpeaker);

        // Login/Logout
        $web->get('/login', 'OpenCFP\Http\Controller\SecurityController::indexAction')
            ->bind('login');
        $web->post('/login', 'OpenCFP\Http\Controller\SecurityController::processAction')
            ->bind('login_check');
        $web->get('/logout', 'OpenCFP\Http\Controller\SecurityController::outAction')
            ->bind('logout');

        // Create Account
        $web->get('/signup', 'OpenCFP\Http\Controller\SignupController::indexAction')
            ->bind('user_new');
        $web->post('/signup', 'OpenCFP\Http\Controller\SignupController::processAction')
            ->bind('user_create');
        $web->get('/signup/success', 'OpenCFP\Http\Controller\SignupController::successAction')
            ->bind('user_success');

        // Edit Profile/Account
        $web->get('/profile/edit/{id}', 'OpenCFP\Http\Controller\ProfileController::editAction')
            ->bind('user_edit')->before($asSpeaker);
        $web->post('/profile/edit', 'OpenCFP\Http\Controller\ProfileController::processAction')
            ->bind('user_update')->before($asSpeaker);

        // Change/forgot Password
        $web->get('/profile/change_password', 'OpenCFP\Http\Controller\ProfileController::passwordAction')
            ->bind('password_edit')->before($asSpeaker);
        $web->post('/profile/change_password', 'OpenCFP\Http\Controller\ProfileController::passwordProcessAction')
            ->bind('password_change')->before($asSpeaker);
        $web->get('/forgot', 'OpenCFP\Http\Controller\ForgotController::indexAction')
            ->bind('forgot_password');
        $web->post('/forgot', 'OpenCFP\Http\Controller\ForgotController::sendResetAction')
            ->bind('forgot_password_create');
        $web->get('/forgot_success', 'OpenCFP\Http\Controller\ForgotController::successAction')
            ->bind('forgot_password_success');
        $web->post('/reset', 'OpenCFP\Http\Controller\ForgotController::resetAction')
            ->bind('reset_password_create');
        $web->get('/reset/{user_id}/{reset_code}', 'OpenCFP\Http\Controller\ForgotController::processResetAction')
            ->bind('reset_password');
        $web->post('/updatepassword', 'OpenCFP\Http\Controller\ForgotController::updatePasswordAction')
            ->bind('password_update');

        /** @var ControllerCollection $admin */
        $admin = $app['controllers_factory'];
        $admin->before(function () use ($app) {
            return RoleAccess::userHasAccess($app[Authentication::class], 'admin');
        });

        // Admin Routes
        $admin->get('/', 'OpenCFP\Http\Controller\Admin\DashboardController::indexAction')
            ->bind('admin');

        // Admin::Talks
        $admin->get('/talks', 'OpenCFP\Http\Controller\Admin\TalksController::indexAction')
            ->bind('admin_talks');
        $admin->get('/talks/{id}', 'OpenCFP\Http\Controller\Admin\TalksController::viewAction')
            ->bind('admin_talk_view');
        $admin->post('/talks/{id}/favorite', 'OpenCFP\Http\Controller\Admin\TalksController::favoriteAction')
            ->bind('admin_talk_favorite');
        $admin->post('/talks/{id}/select', 'OpenCFP\Http\Controller\Admin\TalksController::selectAction')
            ->bind('admin_talk_select');
        $admin->post('/talks/{id}/comment', 'OpenCFP\Http\Controller\Admin\TalksController::commentCreateAction')
            ->bind('admin_talk_comment_create');
        $admin->post('/talks/{id}/rate', 'OpenCFP\Http\Controller\Admin\TalksController::rateAction')
            ->bind('admin_talk_rate');

        // Admin::Speakers
        $admin->get('/speakers', 'OpenCFP\Http\Controller\Admin\SpeakersController::indexAction')
            ->bind('admin_speakers');
        $admin->get('/speakers/{id}', 'OpenCFP\Http\Controller\Admin\SpeakersController::viewAction')
            ->bind('admin_speaker_view');
        $admin->get('/speakers/{id}/promote', 'OpenCFP\Http\Controller\Admin\SpeakersController::promoteAction')
            ->bind('admin_speaker_promote')->before($csrfChecker);
        $admin->get('/speakers/{id}/demote', 'OpenCFP\Http\Controller\Admin\SpeakersController::demoteAction')
            ->bind('admin_speaker_demote')->before($csrfChecker);
        $admin->get('/speakers/delete/{id}', 'OpenCFP\Http\Controller\Admin\SpeakersController::deleteAction')
            ->bind('admin_speaker_delete')->before($csrfChecker);

        // CSV Exports
        $admin->get('/export/csv', 'OpenCFP\Http\Controller\Admin\ExportsController::attributedTalksExportAction')
            ->bind('admin_export_csv');
        $admin->get('/export/csv/anon', 'OpenCFP\Http\Controller\Admin\ExportsController::anonymousTalksExportAction')
            ->bind('admin_export_csv_anon');
        $admin->get('/export/csv/selected', 'OpenCFP\Http\Controller\Admin\ExportsController::selectedTalksExportAction')
            ->bind('admin_export_csv_selected');
        $admin->get('/export/csv/emails', 'OpenCFP\Http\Controller\Admin\ExportsController::emailExportAction')
            ->bind('admin_export_csv_emails');
        $app->mount('/admin/', $admin);

        /** @var ControllerCollection $reviewer */
        $reviewer = $app['controllers_factory'];
        $reviewer->before(function () use ($app) {
            return RoleAccess::userHasAccess($app[Authentication::class], 'reviewer');
        });

        //Reviewer Routes
        $reviewer->get('/', 'OpenCFP\Http\Controller\Reviewer\DashboardController::indexAction')
            ->bind('reviewer');

        // Reviewer::Talks
        $reviewer->get('/talks', 'OpenCFP\Http\Controller\Reviewer\TalksController::indexAction')
            ->bind('reviewer_talks');
        $reviewer->get('/talks/{id}', 'OpenCFP\Http\Controller\Reviewer\TalksController::viewAction')
            ->bind('reviewer_talk_view');
        $reviewer->post('/talks/{id}/favorite', 'OpenCFP\Http\Controller\Reviewer\TalksController::favoriteAction')
            ->bind('reviewer_talk_favorite');
        $reviewer->post('/talks/{id}/comment', 'OpenCFP\Http\Controller\Reviewer\TalksController::commentCreateAction')
            ->bind('reviewer_talk_comment_create');
        $reviewer->post('/talks/{id}/rate', 'OpenCFP\Http\Controller\Reviewer\TalksController::rateAction')
            ->bind('reviewer_talk_rate');

        // Reviewer::Speakers
        $reviewer->get('/speakers', 'OpenCFP\Http\Controller\Reviewer\SpeakersController::indexAction')
             ->bind('reviewer_speakers');
        $reviewer->get('/speakers/{id}', 'OpenCFP\Http\Controller\Reviewer\SpeakersController::viewAction')
            ->bind('reviewer_speaker_view');

        $app->mount('/reviewer/', $reviewer);

        $app->mount('/', $web);
        // @codingStandardsIgnoreEnd
    }
}
