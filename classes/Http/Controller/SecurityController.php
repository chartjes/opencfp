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

namespace OpenCFP\Http\Controller;

use OpenCFP\Domain\Services\Authentication;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\Routing\Generator\UrlGeneratorInterface;
use Twig_Environment;

class SecurityController extends BaseController
{
    /**
     * @var Authentication
     */
    private $authentication;

    public function __construct(
        Authentication $authentication,
        Twig_Environment $twig,
        UrlGeneratorInterface $urlGenerator
    ) {
        $this->authentication = $authentication;

        parent::__construct($twig, $urlGenerator);
    }

    public function indexAction()
    {
        return $this->render('security/login.twig', [
            'email' => null,
        ]);
    }

    public function processAction(Request $request)
    {
        try {
            $this->authentication->authenticate($request->get('email'), $request->get('password'));

            return $this->redirectTo('dashboard');
        } catch (\Exception $e) {
            $request->getSession()->set('flash', [
                'type'  => 'error',
                'short' => 'Error',
                'ext'   => $e->getMessage(),
            ]);

            $templateData = [
                'email' => $request->get('email'),
                'flash' => $this->app['session']->get('flash'),
            ];

            return $this->render('security/login.twig', $templateData, Response::HTTP_BAD_REQUEST);
        }
    }

    public function outAction()
    {
        $this->authentication->logout();

        return $this->redirectTo('homepage');
    }
}
