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

namespace OpenCFP\Http\Controller\Admin;

use OpenCFP\Domain\Services\Authentication;
use OpenCFP\Domain\Services\Pagination;
use OpenCFP\Domain\Services\TalkRating\TalkRatingStrategy;
use OpenCFP\Domain\Talk\TalkFilter;
use OpenCFP\Domain\Talk\TalkHandler;
use OpenCFP\Domain\ValidationException;
use OpenCFP\Http\Controller\BaseController;
use Symfony\Component\HttpFoundation\Request;

class TalksController extends BaseController
{
    public function indexAction(Request $req)
    {
        /* @var Authentication $auth */
        $auth = $this->service(Authentication::class);

        $adminUserId   = $auth->userId();
        $options       = [
            'order_by' => $req->get('order_by'),
            'sort'     => $req->get('sort'),
        ];

        $formattedTalks = $this->service(TalkFilter::class)->getTalks(
            $adminUserId,
            $req->get('filter'),
            $options
        );

        // Set up our page stuff
        $perPage    = (int) $req->get('per_page') ?: 20;
        $pagerfanta = new Pagination($formattedTalks, $perPage);

        $pagerfanta->setCurrentPage($req->get('page'));
        $pagination = $pagerfanta->createView('/admin/talks?', $req->query->all());

        /** @var TalkRatingStrategy $talkRatingStrategy */
        $talkRatingStrategy = $this->service(TalkRatingStrategy::class);

        $templateData = [
            'pagination'   => $pagination,
            'talks'        => $pagerfanta->getFanta(),
            'ratingSystem' => $talkRatingStrategy->getRatingName(),
            'page'         => $pagerfanta->getCurrentPage(),
            'current_page' => $req->getRequestUri(),
            'totalRecords' => \count($formattedTalks),
            'filter'       => $req->get('filter'),
            'per_page'     => $perPage,
            'sort'         => $req->get('sort'),
            'order_by'     => $req->get('order_by'),
        ];

        return $this->render('admin/talks/index.twig', $templateData);
    }

    public function viewAction(Request $req)
    {
        /** @var TalkHandler $handler */
        $handler = $this->service(TalkHandler::class)
            ->grabTalk((int) $req->get('id'));

        if (!$handler->view()) {
            $this->service('session')->set('flash', [
                'type'  => 'error',
                'short' => 'Error',
                'ext'   => 'Could not find requested talk',
            ]);

            return $this->app->redirect($this->url('admin_talks'));
        }

        /** @var TalkRatingStrategy $talkRatingStrategy */
        $talkRatingStrategy = $this->service(TalkRatingStrategy::class);

        return $this->render('admin/talks/view.twig', [
            'talk'         => $handler->getProfile(),
            'ratingSystem' => $talkRatingStrategy->getRatingName(),
        ]);
    }

    public function rateAction(Request $req)
    {
        try {
            $this->validate([
                'rating' => 'required|integer',
            ]);

            return $this->service(TalkHandler::class)
                ->grabTalk((int) $req->get('id'))
                ->rate((int) $req->get('rating'));
        } catch (ValidationException $e) {
            return false;
        }
    }

    /**
     * Set Favorited Talk [POST]
     *
     * @param Request $req Request Object
     *
     * @return bool
     */
    public function favoriteAction(Request $req)
    {
        return $this->service(TalkHandler::class)
            ->grabTalk((int) $req->get('id'))
            ->setFavorite($req->get('delete') == null);
    }

    /**
     * Set Selected Talk [POST]
     *
     * @param Request $req Request Object
     *
     * @return bool
     */
    public function selectAction(Request $req)
    {
        return $this->service(TalkHandler::class)
            ->grabTalk((int) $req->get('id'))
            ->select($req->get('delete') != true);
    }

    public function commentCreateAction(Request $req)
    {
        $talkId = (int) $req->get('id');

        $this->service(TalkHandler::class)
            ->grabTalk($talkId)
            ->commentOn($req->get('comment'));

        $this->service('session')->set('flash', [
                'type'  => 'success',
                'short' => 'Success',
                'ext'   => 'Comment Added!',
            ]);

        return $this->app->redirect($this->url('admin_talk_view', ['id' => $talkId]));
    }
}
