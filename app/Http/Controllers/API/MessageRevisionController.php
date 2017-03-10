<?php namespace App\Http\Controllers\API;

use Common\Services\MessageRevisionService;
use Common\Transformers\BaseTransformer;
use Common\Transformers\MessageTransformer;

class MessageRevisionController extends APIController
{

    /**
     * @type MessageRevisionService
     */
    private $messageRevisions;


    /**
     * MessageRevisionController constructor.
     * @param MessageRevisionService $messageRevisions
     */
    public function __construct(MessageRevisionService $messageRevisions)
    {
        $this->messageRevisions = $messageRevisions;
    }


    public function index($messageId)
    {
        $revisions = $this->messageRevisions->getRevisionsWithStatsForMessage($messageId, $this->bot());

        return $this->collectionResponse($revisions);
    }

    /**
     * @return BaseTransformer
     */
    protected function transformer()
    {
        return new MessageTransformer();
    }
}
