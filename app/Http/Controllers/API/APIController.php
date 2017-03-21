<?php namespace App\Http\Controllers\API;

use Common\Models\Bot;
use Illuminate\Http\Request;
use Common\Services\BotService;
use Common\Http\Controllers\APIController as BaseAPIController;

abstract class APIController extends BaseAPIController
{

    /**
     * @type Bot
     */
    protected $bot;

    /**
     * @type Bot
     */
    protected $enabledBot;

    /**
     * @type Bot
     */
    protected $disabledBot;

    /**
     * Parses the request for the bot id, and fetches the bot from the database.
     * @param bool $enabled
     * @return Bot|null
     */
    protected function fetchBot($enabled = null)
    {
        $request = app('request');

        $botId = $this->getBotIdFromUrlParameters($request);

        if (! $botId) {
            $this->response->errorBadRequest("Bot Not Specified.");
        }

        /** @type BotService $botService */
        $botService = app(BotService::class);

        return $botService->findByIdAndStatusForUser($botId, $this->user(), $enabled);
    }

    /**
     * @return Bot
     */
    protected function bot()
    {
        // If the bot has been already fetched, return it.
        if ($this->bot) {
            return $this->bot;
        }

        if ($bot = $this->fetchBot()) {
            return $this->bot = $bot;
        }

        return $this->response->errorNotFound();
    }

    /**
     * @return Bot
     */
    protected function enabledBot()
    {
        // If the bot has been already fetched, return it.
        if ($this->enabledBot) {
            return $this->enabledBot;
        }

        if ($bot = $this->fetchBot(true)) {
            return $this->enabledBot = $bot;
        }

        return $this->response->errorNotFound();
    }

    /**
     * @return Bot
     */
    protected function disabledBot()
    {
        // If the bot has been already fetched, return it.
        if ($this->disabledBot) {
            return $this->disabledBot;
        }

        if ($bot = $this->fetchBot(false)) {
            return $this->disabledBot = $bot;
        }

        return $this->response->errorNotFound();
    }

    /**
     * The bot id is always provided either through a GET parameter called "botId".
     * Or through a route parameter called "id"
     * @param Request $request
     * @return mixed
     */
    protected function getBotIdFromUrlParameters(Request $request)
    {
        $routeParameters = $request->route()[2];

        $botId = array_get($routeParameters, 'botId');

        if (! $botId) {
            $botId = array_get($routeParameters, 'id');

            return $botId;
        }

        return $botId;
    }
}