<?php namespace App\Transformers;

use App\Models\SequenceMessage;

class SequenceMessageTransformer extends BaseTransformer
{

    protected $availableIncludes = ['template'];

    public function transform(SequenceMessage $message)
    {
        return [
            'id'         => $message->id,
            'name'       => $message->name,
            'conditions' => $message->conditions,
            'order'      => $message->order,
            'live'       => $message->live,
            'is_deleted' => false,
            'queued'     => 0,
        ];
    }
}