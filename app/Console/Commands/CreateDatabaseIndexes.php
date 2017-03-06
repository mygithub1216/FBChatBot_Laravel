<?php namespace App\Console\Commands;

use MongoDB\Collection;
use Illuminate\Console\Command;

class CreateDatabaseIndexes extends Command
{

    /**
     * The name and signature of the console command.
     *
     * @var string
     */
    protected $signature = 'db-index:create';

    /**
     * The console command description.
     *
     * @var string
     */
    protected $description = 'Create database indexes.';

    /**
     * Execute the console command.
     */
    public function handle()
    {
        $this->createAutoReplyRuleIndexes();
        $this->createBotIndexes();
        $this->createBroadcastIndexes();
        $this->createMessageRevisionIndexes();
        $this->createSentMessageIndexes();
        $this->createSequenceIndexes();
        $this->createSequenceScheduleIndexes();
        $this->createSubscriberIndexes();
        $this->createTemplateIndexes();
        $this->createUserIndexes();
    }

    private function createAutoReplyRuleIndexes()
    {
        /** @type Collection $collection */
        $collection = \App\Models\AutoReplyRule::raw();
        $collection->createIndex(['bot_id' => 1, 'mode' => 1, 'keyword' => 1]);
    }

    private function createBotIndexes()
    {
        /** @type Collection $collection */
        $collection = \App\Models\Bot::raw();
        $collection->createIndex(['page.id' => 1], ['unique' => true]);
        $collection->createIndex(['users.user_id' => 1, 'enabled' => -1]);
    }

    private function createBroadcastIndexes()
    {
        /** @type Collection $collection */
        $collection = \App\Models\Broadcast::raw();
        $collection->createIndex(['bot_id' => 1]);
        $collection->createIndex(['status' => 1, 'next_send_at' => 1]);
    }

    private function createMessageRevisionIndexes()
    {
        /** @type Collection $collection */
        $collection = \App\Models\MessageRevision::raw();
        $collection->createIndex(['bot_id' => 1, 'message_id' => 1, 'created_at' => 1]);
    }

    private function createSentMessageIndexes()
    {
        /** @type Collection $collection */
        $collection = \App\Models\SentMessage::raw();

        $collection->createIndex(['bot_id' => 1, 'sent_at' => 1]);

        $collection->createIndex(['sent_at' => 1, 'delivered_at' => 1, 'subscriber_id' => 1]);
        $collection->createIndex(['sent_at' => 1, 'read_at' => 1, 'subscriber_id' => 1]);

        $collection->createIndex(['read_at' => 1, 'delivered_at' => 1, 'subscriber_id' => 1]);

        $collection->createIndex(['message_id' => 1, 'sent_at' => 1, 'subscriber_id' => 1]);
        $collection->createIndex(['message_id' => 1, 'delivered_at' => 1, 'subscriber_id' => 1]);
        $collection->createIndex(['message_id' => 1, 'read_at' => 1, 'subscriber_id' => 1]);
    }

    private function createSequenceIndexes()
    {
        /** @type Collection $collection */
        $collection = \App\Models\Sequence::raw();
        $collection->createIndex(['bot_id' => 1]);
        $collection->createIndex(['messages.deleted_at' => 1]);
    }

    private function createSequenceScheduleIndexes()
    {
        /** @type Collection $collection */
        $collection = \App\Models\SequenceSchedule::raw();
        $collection->createIndex(['sent_at' => 1]);
        $collection->createIndex(['sequence_id' => 1, 'subscriber_id' => 1]);
    }

    private function createSubscriberIndexes()
    {
        /** @type Collection $collection */
        $collection = \App\Models\Subscriber::raw();
        $collection->createIndex(['bot_id' => 1, 'facebook_id' => 1]);
        $collection->createIndex(['bot_id' => 1, 'active' => -1]);
        $collection->createIndex(['bot_id' => 1, 'last_subscribed_at' => -1]);
        $collection->createIndex(['bot_id' => 1, 'last_unsubscribed_at' => -1]);
        $collection->createIndex(['bot_id' => 1, 'history.action' => 1, 'history.action_at' => 1]);
    }

    private function createTemplateIndexes()
    {
        /** @type Collection $collection */
        $collection = \App\Models\Template::raw();
        $collection->createIndex(['bot_id' => 1, 'explicit' => -1]);
    }

    private function createUserIndexes()
    {
        /** @type Collection $collection */
        $collection = \App\Models\User::raw();
        $collection->createIndex(['facebook_id' => 1], ['unique' => true]);
    }

}
