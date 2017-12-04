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

namespace OpenCFP\Domain\Services\TalkRating;

interface TalkRatingStrategy
{
    public function isValidRating(int $rating): bool;

    /**
     * @throws TalkRatingException
     */
    public function rate(int $talkId, int $rating);

    /**
     * The name of the rating system, Used in the views to determine what kind of rating system to display
     */
    public function getRatingName(): string;
}
