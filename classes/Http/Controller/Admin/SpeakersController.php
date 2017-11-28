<?php

namespace OpenCFP\Http\Controller\Admin;

use Illuminate\Database\Capsule\Manager as Capsule;
use OpenCFP\Domain\Model\User;
use OpenCFP\Domain\Services\AirportInformationDatabase;
use OpenCFP\Domain\Services\Pagination;
use OpenCFP\Domain\Speaker\SpeakerProfile;
use OpenCFP\Http\Controller\BaseController;
use OpenCFP\Http\Controller\FlashableTrait;
use OpenCFP\Infrastructure\Auth\Contracts\AccountManagement;
use OpenCFP\Infrastructure\Auth\Contracts\Authentication;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\Security\Csrf\CsrfToken;

class SpeakersController extends BaseController
{
    use FlashableTrait;

    public function indexAction(Request $req)
    {
        $search = $req->get('search');

        /** @var AccountManagement $accounts */
        $accounts        = $this->service(AccountManagement::class);
        $adminUsers      = $accounts->findByRole('Admin');
        $adminUserIds    = \array_column($adminUsers, 'id');
        $reviewerUsers   = $accounts->findByRole('Reviewer');
        $reviewerUserIds = \array_column($reviewerUsers, 'id');

        $rawSpeakers = User::search($search)->get();

        $airports    = $this->service(AirportInformationDatabase::class);
        $rawSpeakers = $rawSpeakers->map(function ($speaker) use ($airports, $adminUserIds, $reviewerUserIds) {
            try {
                $airport = $airports->withCode($speaker['airport']);

                $speaker['airport'] = [
                    'code'    => $airport->code,
                    'name'    => $airport->name,
                    'country' => $airport->country,
                ];
            } catch (\Exception $e) {
                $speaker['airport'] = [
                    'code'    => null,
                    'name'    => null,
                    'country' => null,
                ];
            }

            $speaker['is_admin'] = \in_array($speaker['id'], $adminUserIds);
            $speaker['is_reviewer'] = \in_array($speaker['id'], $reviewerUserIds);

            return $speaker;
        })->toArray();

        // Set up our page stuff
        $pagerfanta = new Pagination($rawSpeakers);
        $pagerfanta->setCurrentPage($req->get('page'));
        $pagination = $pagerfanta->createView('/admin/speakers?');

        $templateData = [
            'airport'    => $this->app->config('application.airport'),
            'arrival'    => \date('Y-m-d', $this->app->config('application.arrival')),
            'departure'  => \date('Y-m-d', $this->app->config('application.departure')),
            'pagination' => $pagination,
            'speakers'   => $pagerfanta->getFanta(),
            'page'       => $pagerfanta->getCurrentPage(),
            'search'     => $search ?: '',
        ];

        return $this->render('admin/speaker/index.twig', $templateData);
    }

    public function viewAction(Request $req)
    {
        $speaker_details = User::find($req->get('id'));

        if (!$speaker_details instanceof User) {
            $this->service('session')->set('flash', [
                'type'  => 'error',
                'short' => 'Error',
                'ext'   => 'Could not find requested speaker',
            ]);

            return $this->app->redirect($this->url('admin_speakers'));
        }

        $airports = $this->service(AirportInformationDatabase::class);

        try {
            $airport = $airports->withCode($speaker_details->airport);

            $speaker_details->airport = [
                'code'    => $airport->code,
                'name'    => $airport->name,
                'country' => $airport->country,
            ];
        } catch (\Exception $e) {
            $speaker_details->airport = [
                'code'    => null,
                'name'    => null,
                'country' => null,
            ];
        }

        $talks = $speaker_details->talks()->get();

        // Build and render the template
        $templateData = [
            'airport'    => $this->app->config('application.airport'),
            'arrival'    => \date('Y-m-d', $this->app->config('application.arrival')),
            'departure'  => \date('Y-m-d', $this->app->config('application.departure')),
            'speaker'    => new SpeakerProfile($speaker_details),
            'talks'      => $talks,
            'photo_path' => '/uploads/',
            'page'       => $req->get('page'),
        ];

        return $this->render('admin/speaker/view.twig', $templateData);
    }

    public function deleteAction(Request $req)
    {
        $csrfTokenManager = $this->service('csrf.token_manager');
        $csrfToken        = new CsrfToken('admin_speaker_delete', $req->get('token'));

        if (!$csrfTokenManager->isTokenValid($csrfToken)) {
            return $this->redirectTo('admin_speakers');
        }

        /** @var Capsule $capsule */
        $capsule = $this->service(Capsule::class);
        $capsule->getConnection()->beginTransaction();

        try {
            $user = User::findorFail($req->get('id'));
            $user->delete($req->get('id'));
            $ext   = 'Successfully deleted the requested user';
            $type  = 'success';
            $short = 'Success';
            $capsule->getConnection()->commit();
        } catch (\Exception $e) {
            $capsule->getConnection()->rollBack();
            $ext   = 'Unable to delete the requested user';
            $type  = 'error';
            $short = 'Error';
        }

        // Set flash message
        $this->service('session')->set('flash', [
            'type'  => $type,
            'short' => $short,
            'ext'   => $ext,
        ]);

        return $this->redirectTo('admin_speakers');
    }

    public function demoteAction(Request $req)
    {
        /** @var AccountManagement $accounts */
        $accounts = $this->service(AccountManagement::class);
        $role     = $req->get('role');

        if ($this->service(Authentication::class)->userId() == $req->get('id')) {
            $this->service('session')->set('flash', [
                'type'  => 'error',
                'short' => 'Error',
                'ext'   => 'Sorry, you cannot remove yourself as ' . $role . '.',
            ]);

            return $this->redirectTo('admin_speakers');
        }

        $csrfTokenManager = $this->service('csrf.token_manager');
        $csrfToken        = new CsrfToken('admin_speaker_demote', $req->get('token'));

        if (!$csrfTokenManager->isTokenValid($csrfToken)) {
            return $this->redirectTo('admin_speakers');
        }

        try {
            $user = $accounts->findById($req->get('id'));
            $accounts->demoteFrom($user->getLogin(), $role);

            $this->service('session')->set('flash', [
                'type'  => 'success',
                'short' => 'Success',
                'ext'   => '',
            ]);
        } catch (\Exception $e) {
            $this->service('session')->set('flash', [
                'type'  => 'error',
                'short' => 'Error',
                'ext'   => 'We were unable to remove the ' . $role . '. Please try again.',
            ]);
        }

        return $this->redirectTo('admin_speakers');
    }

    public function promoteAction(Request $req)
    {
        /* @var AccountManagement $accounts */
        $accounts = $this->service(AccountManagement::class);
        $role     = $req->get('role');

        $csrfTokenManager = $this->service('csrf.token_manager');
        $csrfToken        = new CsrfToken('admin_speaker_promote', $req->get('token'));

        if (!$csrfTokenManager->isTokenValid($csrfToken)) {
            $this->service('session')->set('flash', [
                'type'  => 'error',
                'short' => 'Error',
                'ext'   => 'We were unable to promote the ' . $role . '. Please try again.',
            ]);

            return $this->redirectTo('admin_speakers');
        }

        try {
            $user = $accounts->findById($req->get('id'));
            if ($user->hasAccess(\strtolower($role))) {
                $this->service('session')->set('flash', [
                    'type'  => 'error',
                    'short' => 'Error',
                    'ext'   => 'User already is in the ' . $role . ' group.',
                ]);

                return $this->redirectTo('admin_speakers');
            }

            $accounts->promoteTo($user->getLogin(), $role);

            $this->service('session')->set('flash', [
                'type'  => 'success',
                'short' => 'Success',
                'ext'   => '',
            ]);
        } catch (\Exception $e) {
            $this->service('session')->set('flash', [
                'type'  => 'error',
                'short' => 'Error',
                'ext'   => 'We were unable to promote the ' . $role . '. Please try again.',
            ]);
        }

        return $this->redirectTo('admin_speakers');
    }
}
