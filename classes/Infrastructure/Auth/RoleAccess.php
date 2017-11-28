<?php

namespace OpenCFP\Infrastructure\Auth;

use OpenCFP\Infrastructure\Auth\Contracts\Authentication;
use OpenCFP\Infrastructure\Auth\Contracts\UserAccess;
use Silex\Application;
use Symfony\Component\HttpFoundation\RedirectResponse;

class RoleAccess implements UserAccess
{
    /**
     * If a user doesn't have access to a page they get redirected, otherwise nothing happens
     *
     * @param Application $app
     * @param string      $role Role to check against Defaults to admin for security reasons
     *
     * @return RedirectResponse|void
     */
    public static function userHasAccess(Application $app, $role = 'admin')
    {
        /** @var Authentication $auth */
        $auth = $app[Authentication::class];
        if (!$auth->check()) {
            return $app->redirect('/dashboard');
        }

        $user = $auth->user();
        if (!$user->hasAccess($role)) {
            return $app->redirect('/dashboard');
        }
    }
}
