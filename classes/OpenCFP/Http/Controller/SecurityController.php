<?php

namespace OpenCFP\Http\Controller;

use Silex\Application;
use OpenCFP\Domain\Services\Login;
use Symfony\Component\HttpFoundation\Request;

class SecurityController extends BaseController
{
    use FlashableTrait;

    public function indexAction()
    {
        return $this->render('login.twig');
    }

    public function processAction(Request $req, Application $app)
    {
        try {
            $page = new Login($app['sentry']);

            if ($page->authenticate($req->get('email'), $req->get('password'))) {

                $user = $app['sentry']->getUser();

                if ($user->hasPermission('admin')) {
                    return $this->redirectTo('admin');
                }
                else{
                    return $this->redirectTo('dashboard');
                }
            }

            $errorMessage = $page->getAuthenticationMessage();

            $template_data = array(
                'email' => $req->get('email'),
            );
            $code = 400;
        } catch (Exception $e) {
            $errorMessage = $e->getMessage();
            $template_data = array(
                'email' => $req->get('email'),
            );
            $code = 400;
        }

        // Set Success Flash Message
        $this->app['session']->set('flash', array(
            'type' => 'error',
            'short' => 'Error',
            'ext' => $errorMessage,
        ));

        $template_data['flash'] = $this->getFlash($app);

        return $this->render('login.twig', $template_data, $code);
    }

    public function outAction()
    {
        $this->app['sentry']->logout();

        return $this->redirectTo('homepage');
    }
}
