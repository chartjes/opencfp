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

namespace OpenCFP\Provider;

use OpenCFP\Domain\Services\Authentication;
use OpenCFP\Domain\Services\TalkRating\TalkRatingContext;
use OpenCFP\Domain\Services\TalkRating\TalkRatingStrategy;
use Pimple\Container;
use Pimple\ServiceProviderInterface;

class TalkRatingProvider implements ServiceProviderInterface
{
    public function register(Container $app)
    {
        $app[TalkRatingStrategy::class] = function ($app) {
            $strategy = $app->config('application.rating_system') ?: 'yesno';

            return TalkRatingContext::getTalkStrategy($strategy, $app[Authentication::class]);
        };
    }
}
