<?php

namespace OpenCFP\Domain\Services\TalkRating;

use Illuminate\Database\Eloquent\Model;
use OpenCFP\Domain\Model\TalkMeta;
use OpenCFP\Infrastructure\Auth\Contracts\Authentication;

abstract class TalkRating implements TalkRatingStrategy
{
    /**
     * @var int
     */
    protected $adminId;

    /**
     * @var TalkMeta
     */
    protected $meta;

    public function __construct(TalkMeta $meta, Authentication $auth)
    {
        $this->adminId = $auth->userId();
        $this->meta    = $meta;
    }

    final public function rate(int $talkId, int $rating)
    {
        if (!$this->isValidRating($rating)) {
            throw TalkRatingException::invalidRating($rating);
        }
        $this->saveRating($talkId, $rating);
    }

    private function saveRating(int $talkId, int $rating)
    {
        $meta         = $this->fetchMetaInfo($talkId);
        $meta->rating = $rating;
        $meta->save();
    }

    private function fetchMetaInfo(int $talkId): Model
    {
        return $this->meta->firstOrCreate([
            'admin_user_id' => $this->adminId,
            'talk_id'       => $talkId,
        ]);
    }
}
