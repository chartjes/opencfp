<?php

namespace OpenCFP\Http\Controller\Admin;

use OpenCFP\Http\Controller\BaseController;
use Silex\Application;
use Symfony\Component\HttpFoundation\Request;
use Pagerfanta\View\TwitterBootstrap3View;

class AdminsController extends BaseController
{
    use AdminAccessTrait;

    private function indexAction(Request $req, Application $app)
    {
        $adminGroup = $app['sentry']->getGroupProvider()->findByName('Admin');
        $adminUsers = $app['sentry']->findAllUsersInGroup($adminGroup);

        // Set up our page stuff
        $adapter = new \Pagerfanta\Adapter\ArrayAdapter($adminUsers->toArray());
        $pagerfanta = new \Pagerfanta\Pagerfanta($adapter);
        $pagerfanta->setMaxPerPage(20);
        $pagerfanta->getNbResults();

        if ($req->get('page') !== null) {
            $pagerfanta->setCurrentPage($req->get('page'));
        }

        // Create our default view for the navigation options
        $routeGenerator = function ($page) {
            return '/admin/admins?page=' . $page;
        };
        $view = new TwitterBootstrap3View();
        $pagination = $view->render(
            $pagerfanta,
            $routeGenerator,
            array('proximity' => 3)
        );

        $template = $app['twig']->loadTemplate('admin/admins/index.twig');
        $templateData = array(
            'pagination' => $pagination,
            'speakers' => $pagerfanta,
            'page' => $pagerfanta->getCurrentPage()
        );

        return $template->render($templateData);
    }

    private function removeAction(Request $req, Application $app)
    {
        $admin = $app['sentry']->getUser();

        if ($admin->getId() == $req->get('id')) {

            $app['session']->set('flash', array(
                    'type' => 'error',
                    'short' => 'Error',
                    'ext' => 'Sorry, you cannot remove yourself as Admin.',
                ));

            return $app->redirect($app['url'] . '/admin/admins');
        }

        $mapper = $app['spot']->mapper('OpenCFP\Entity\User');
        $user_data = $mapper->get($req->get('id'))->toArray();
        $user = $app['sentry']->getUserProvider()->findByLogin($user_data['email']);

        $adminGroup = $app['sentry']->getGroupProvider()->findByName('Admin');
        $response = $user->removeGroup($adminGroup);

        if ($response == true) {
            $app['session']->set('flash', array(
                    'type' => 'success',
                    'short' => 'Success',
                    'ext' => 'Successfully removed the Admin!',
                ));
        }

        if ($response == false) {
            $app['session']->set('flash', array(
                    'type' => 'error',
                    'short' => 'Error',
                    'ext' => 'We were unable to remove the Admin. Please try again.',
                ));
        }

        return $app->redirect($app['url'] . '/admin/admins');
    }
}
