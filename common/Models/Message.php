<?php namespace Common\Models;

abstract class Message extends ArrayModel
{

    public $type;
    /** @type \MongoDB\BSON\ObjectID */
    public $id;
    public $readonly;

    /**
     * @param array $message
     * @param bool  $strict
     * @return Message
     */
    public static function factory($message, $strict = false)
    {
        /** @type Message $model */
        $model = "Common\\Models\\" . studly_case($message['type']);

        return new $model($message, $strict);
    }
}
