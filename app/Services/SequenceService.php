<?php namespace App\Services;

use App\Repositories\Sequence\SequenceScheduleRepositoryInterface;
use Carbon\Carbon;
use App\Models\Bot;
use App\Models\Text;
use App\Models\Sequence;
use App\Models\Subscriber;
use MongoDB\BSON\ObjectID;
use App\Models\AudienceFilter;
use App\Models\SequenceMessage;
use Illuminate\Database\Eloquent\ModelNotFoundException;
use App\Repositories\Sequence\SequenceRepositoryInterface;
use App\Repositories\Template\TemplateRepositoryInterface;
use App\Repositories\Subscriber\SubscriberRepositoryInterface;

class SequenceService
{

    /**
     * @type SequenceRepositoryInterface
     */
    private $sequenceRepo;
    /**
     * @type TemplateRepositoryInterface
     */
    private $templateRepo;
    /**
     * @type TemplateService
     */
    private $templates;
    /**
     * @type SubscriberRepositoryInterface
     */
    private $subscriberRepo;
    /**
     * @var SequenceScheduleRepositoryInterface
     */
    private $sequenceScheduleRepo;

    /**
     * SequenceService constructor.
     *
     * @param SequenceRepositoryInterface         $sequenceRepo
     * @param TimezoneService                     $timezones
     * @param TemplateRepositoryInterface         $templateRepo
     * @param TemplateService                     $templates
     * @param SubscriberRepositoryInterface       $subscriberRepo
     * @param SequenceScheduleRepositoryInterface $sequenceScheduleRepo
     */
    public function __construct(
        TimezoneService $timezones,
        TemplateService $templates,
        SequenceRepositoryInterface $sequenceRepo,
        TemplateRepositoryInterface $templateRepo,
        SubscriberRepositoryInterface $subscriberRepo,
        SequenceScheduleRepositoryInterface $sequenceScheduleRepo
    ) {
        $this->templates = $templates;
        $this->sequenceRepo = $sequenceRepo;
        $this->subscriberRepo = $subscriberRepo;
        $this->templateRepo = $templateRepo;
        $this->sequenceScheduleRepo = $sequenceScheduleRepo;
    }

    /**
     * Return all sequences for page.
     *
     * @param Bot $bot
     *
     * @return \Illuminate\Support\Collection
     */
    public function all(Bot $bot)
    {
        return $this->sequenceRepo->getAllForBot($bot);
    }

    /**
     * Find a sequence for a page.
     *
     * @param             $id
     * @param Bot         $bot
     *
     * @return Sequence
     */
    public function findByIdForBot($id, Bot $bot)
    {
        return $this->sequenceRepo->findByIdForBot($id, $bot);
    }

    /**
     * Find a sequence for page, or thrown an exception if the sequence doesn't exit.
     *
     * @param             $id
     * @param Bot         $page
     *
     * @return Sequence
     */
    public function findByIdForBotOrFail($id, Bot $page)
    {
        if ($sequence = $this->findByIdForBot($id, $page)) {
            return $sequence;
        }
        throw new ModelNotFoundException;
    }

    /**
     * Find a sequence message by ID, throw an exception if it doesn't exist.
     *
     * @param          $id
     * @param Sequence $sequence
     *
     * @return SequenceMessage
     */
    public function findMessageOrFail($id, Sequence $sequence)
    {
        if ($message = $this->sequenceRepo->findSequenceMessageById($id, $sequence)) {
            return $message;
        }

        throw new ModelNotFoundException;
    }

    /**
     * Create a sequence
     *
     * @param array $input
     * @param Bot   $bot
     *
     * @return Sequence
     */
    public function create(array $input, Bot $bot)
    {
        $data = [
            'name'             => $input['name'],
            'bot_id'           => $bot->_id,
            'filter'           => new AudienceFilter(['enabled' => false]),
            'messages'         => $this->defaultSequenceMessages($bot),
            'subscriber_count' => 0,
        ];

        return $this->sequenceRepo->create($data);
    }

    /**
     * Update a sequence.
     *
     * @param      $id
     * @param      $input
     * @param Bot  $bot
     *
     * @return Sequence
     */
    public function update($id, $input, Bot $bot)
    {
        /** @type Sequence $sequence */
        $sequence = $this->findByIdForBotOrFail($id, $bot);

        $sequence->filter = new AudienceFilter($input['filter'], true);

        /** @type SequenceMessage $message */
        list($message, $index) = $this->getFirstSendableMessage($sequence);

        if ($message) {
            $this->scheduleFirstMessageForNewSubscribers($sequence, $message);
        }

        $newSubscribers = $this->subscriberRepo->subscribeToSequenceIfNotUnsubscribed($sequence);

        $data = [
            'name'             => $input['name'],
            'filter'           => $sequence->filter,
            'subscriber_count' => $sequence->subscriber_count + $newSubscribers,
        ];

        if ($message) {
            $data["messages.{$index}.queued"] = $message->queued + $newSubscribers;
        }

        $this->sequenceRepo->update($sequence, $data);

        return $sequence;
    }

    /**
     * Delete a sequence.
     *
     * @param      $id
     * @param Bot  $page
     */
    public function delete($id, $page)
    {
        $sequence = $this->findByIdForBotOrFail($id, $page);
        $this->sequenceRepo->delete($sequence);
    }

    /**
     * Add a new message to a sequences.
     *
     * @param array $input
     * @param int   $sequenceId
     * @param Bot   $bot
     *
     * @return SequenceMessage
     */
    public function createMessage(array $input, $sequenceId, Bot $bot)
    {
        $sequence = $this->findByIdForBotOrFail($sequenceId, $bot);

        $template = $this->templates->createImplicit($input['template']['messages'], $bot->_id);

        $message = new SequenceMessage([
            'template_id' => $template->_id,
            'live'        => array_get($input, 'live', false),
            'conditions'  => $input['conditions']
        ]);

        $this->sequenceRepo->addMessageToSequence($sequence, $message);

        $message->template = $template;

        return $message;
    }

    /**
     * Update a sequence message.
     *
     * @param array $input
     * @param int   $id
     * @param int   $sequenceId
     * @param Bot   $bot
     *
     * @return SequenceMessage
     */
    public function updateMessage(array $input, $id, $sequenceId, Bot $bot)
    {
        $sequence = $this->findByIdForBotOrFail($sequenceId, $bot);
        $message = $this->findMessageOrFail($id, $sequence);

        $template = $this->templates->updateImplicit($message->template_id, $input['template'], $bot);

        $message->name = $input['name'];
        $message->live = array_get($input, 'live', false);
        $message->normalizeConditions($input['conditions']);

        $this->sequenceRepo->updateSequenceMessage($sequence, $message);

        $message->template = $template;

        return $message;
    }

    /**
     * Update a sequence message.
     *
     * @param array $input
     * @param int   $id
     * @param int   $sequenceId
     * @param Bot   $bot
     *
     * @return SequenceMessage
     */
    public function updateMessageConditions(array $input, $id, $sequenceId, Bot $bot)
    {
        $sequence = $this->findByIdForBotOrFail($sequenceId, $bot);
        $message = $this->findMessageOrFail($id, $sequence);
        $message->normalizeConditions($input['conditions']);
        $this->sequenceRepo->updateSequenceMessage($sequence, $message);

        return $message;
    }


    /**
     * Delete a sequence message.
     *
     * @param     $id
     * @param     $sequenceId
     * @param Bot $bot
     *
     * @return SequenceMessage|null
     */
    public function deleteMessage($id, $sequenceId, Bot $bot)
    {
        $sequence = $this->findByIdForBotOrFail($sequenceId, $bot);
        $message = $this->findMessageOrFail($id, $sequence);

        if ($message->queued) {
            $this->sequenceRepo->softDeleteSequenceMessage($sequence, $message);

            return $message;
        }

        $this->sequenceRepo->deleteSequenceMessage($sequence, $message);

        return null;
    }

    /**
     * @param Sequence        $sequence
     * @param SequenceMessage $message
     */
    private function scheduleFirstMessageForNewSubscribers(Sequence $sequence, SequenceMessage $message)
    {
        $subscribers = $this->subscriberRepo->subscribersWhoShouldSubscribeToSequence($sequence);

        if ($subscribers->isEmpty()) {
            return;
        }

        $data = [];
        foreach ($subscribers as $subscriber) {
            $data[] = [
                'sequence_id'   => $sequence->_id,
                'message_id'    => $message->id,
                'subscriber_id' => $subscriber->_id,
                'status'        => 'pending',
                'send_at'       => $this->applyConditions(Carbon::now(), $message),
            ];
        }

        $this->sequenceScheduleRepo->bulkCreate($data);
    }

    /**
     * Schedule the next sequence message to be sent to a subscriber.
     * Schedule message data = send date of previous message + time period to be waited before sending this message
     *
     * @param SequenceMessage $message
     * @param Subscriber      $subscriber
     * @param                 $previousMessagesWasSentAt (or subscribed at for first message).
     *
     * @return SequenceMessageSchedule
     */
    public function scheduleMessage(SequenceMessage $message, Subscriber $subscriber, Carbon $previousMessagesWasSentAt)
    {
        $data = [
            'status'  => 'pending',
            'send_at' => $previousMessagesWasSentAt->copy()->addDays($message->days)
        ];

        return $this->sequenceRepo->createMessageSchedule($data, $message, $subscriber);
    }

    /**
     * @param Bot $bot
     *
     * @return array
     */
    private function defaultSequenceMessages(Bot $bot)
    {
        $templates = $this->getDefaultTemplates($bot);
        $this->templateRepo->bulkCreate($templates);

        $arr = [
            [
                'id'          => new ObjectID(),
                'live'        => false,
                'name'        => 'Introduction content + Unsubscribe instructions',
                'conditions'  => ['wait_for' => ['days' => 1, 'hours' => 0, 'minutes' => 0]],
                'template_id' => $templates[0]['_id'],
            ],
            [
                'id'          => new ObjectID(),
                'live'        => false,
                'name'        => '1st Educational message',
                'conditions'  => ['wait_for' => ['days' => 1, 'hours' => 0, 'minutes' => 0]],
                'template_id' => $templates[1]['_id'],
            ],
            [
                'id'          => new ObjectID(),
                'live'        => false,
                'name'        => '2nd Educational message',
                'conditions'  => ['wait_for' => ['days' => 2, 'hours' => 0, 'minutes' => 0]],
                'template_id' => $templates[2]['_id'],
            ],
            [
                'id'          => new ObjectID(),
                'live'        => false,
                'name'        => '3rd Educational message + Soft sell',
                'conditions'  => ['wait_for' => ['days' => 3, 'hours' => 0, 'minutes' => 0]],
                'template_id' => $templates[3]['_id'],
            ],
            [
                'id'          => new ObjectID(),
                'live'        => false,
                'name'        => '4th Educational message',
                'conditions'  => ['wait_for' => ['days' => 4, 'hours' => 0, 'minutes' => 0]],
                'template_id' => $templates[4]['_id'],
            ],
        ];

        return array_map(function ($item) {
            return new SequenceMessage($item);
        }, $arr);
    }


    /**
     * @param Bot $bot
     *
     * @return array
     */
    private function getDefaultTemplates(Bot $bot)
    {

        return [
            [
                '_id'      => new ObjectID(),
                'bot_id'   => $bot->_id,
                'explicit' => false,
                'messages' => [
                    $this->textMessage("Remind your subscriber who you are and why are they getting messages from you. Then deliver valuable information (don't forget to replace these help messages!)."),
                    $this->textMessage('Good idea to mention how to unsubscribe (they can do this by sending the "stop" message).'),
                ]
            ],

            [
                '_id'      => new ObjectID(),
                'bot_id'   => $bot->_id,
                'explicit' => false,
                'messages' => [
                    $this->textMessage("First messages are the most important. Focus on being extremely useful.")
                ]
            ],


            [
                '_id'      => new ObjectID(),
                'bot_id'   => $bot->_id,
                'explicit' => false,
                'messages' => [
                    $this->textMessage("First messages are the most important. Focus on being extremely useful.")
                ]
            ],


            [
                '_id'      => new ObjectID(),
                'bot_id'   => $bot->_id,
                'explicit' => false,
                'messages' => [
                    $this->textMessage("Make your message educational, but find a soft way to mention your product. Something like a P.S. at the end can be a good way to do it.")
                ]
            ],


            [
                '_id'      => new ObjectID(),
                'bot_id'   => $bot->_id,
                'explicit' => false,
                'messages' => [
                    $this->textMessage("Keep being incredibly useful. Remember that your subscription base is the most important asset. Take time to build the relationship.")
                ]
            ]
        ];
    }

    /**
     * @param $text
     *
     * @return Text
     */
    private function textMessage($text)
    {
        return new Text([
            'id'   => new ObjectID(),
            'text' => $text
        ]);
    }

    /**
     * @param Carbon          $dateTime
     * @param SequenceMessage $message
     *
     * @return Carbon
     */
    private function applyConditions(Carbon $dateTime, SequenceMessage $message)
    {
        $dateTime->addDays($message->conditions['wait_for']['days']);
        $dateTime->addHours($message->conditions['wait_for']['hours']);
        $dateTime->addMinutes($message->conditions['wait_for']['minutes']);

        return $dateTime;
    }

    /**
     * @param Sequence $sequence
     *
     * @return array|null [SequenceMessage, int]
     */
    private function getFirstSendableMessage(Sequence $sequence)
    {
        foreach ($sequence->messages as $i => $temp) {
            if (is_null($temp->deleted_at)) {
                return [$temp, $i];
            }
        }

        return null;
    }

}