<?php

namespace VisStudio\Drivers\Telegram;

use Illuminate\Http\Request;
use Illuminate\Support\Collection;
use Symfony\Component\HttpFoundation\ParameterBag;
use VisStudio\Drivers\HttpDriver;
use VisStudio\Interfaces\HttpInterface;
use VisStudio\Messages\Incoming;

class TelegramDriver extends HttpDriver
{
    /**
     * Name of Driver
     */
    public const DRIVER_NAME = 'Telegram';

    /**
     * API_URL
     */
    public const API_URL = 'https://api.telegram.org/bot';
    const FILE_API_URL = 'https://api.telegram.org/file/bot';

    /**
     * Request Vars (Curl)
     *
     * @var HttpInterface
     */
    protected HttpInterface $http;

    /**
     * Configuration telegram driver
     *
     * @var Collection
     */
    protected Collection $config;

    /**
     * Answer Driver
     *
     * @var string|false|resource|null
     */
    protected string|false|null $content;

    /**
     * Request endpoint for send message
     *
     * @var string
     */
    protected string $endpoint = 'sendMessage';

    /**
     * Query Parameters (if exist)
     * Example: ?test=1
     *
     * @var Collection
     */
    protected Collection $queryParameters;

    /**
     * Messages
     *
     * @var array
     */
    protected array $messages = [];

    final public function __construct(Request $request, array $config, HttpInterface $http)
    {
        $this->http = $http;
        $this->config = Collection::make($config);
        $this->content = $request->getContent();
        $this->buildPayload($request);
    }

    /**
     * Receive Name of Driver
     *
     * @return string
     */
    public static function getName(): string
    {
        return self::DRIVER_NAME;
    }

    /**
     * Create
     *
     * @param Request $request
     */
    public function buildPayload(Request $request)
    {
        # Get content from bot object
        # Payload var can be found: VisStudio\Drivers\HttpDriver
        # It is located in the main directory of the framework
        $this->payload = new ParameterBag((array)json_decode($this->content), true);

        # Receive message from object
        $message = $this->payload->get('message');

        # If key Message not exist, receive EditMessage
        if (empty($message)) $message = $this->payload->get('edited_message');

        # If Key EditMessage not exist, check ChannelPost
        if (empty($message)) {
            # Receive ChannelPost
            $message = $this->payload->get('channel_post');
            # And set ID From key is zero
            $message['from'] = ['id' => 0];
        }

        # Save Message object on Event.
        # Event Var can be found: VisStudio\Drivers\HttpDriver
        # It is located in the main directory of the framework
        $this->event = $message;
        # Receive telegram configuration from global configuration
        $this->config = Collection::make($this->config->get('telegram'));
        # Receive query (Example: ?test=1)
        $this->queryParameters = Collection::make($request->query);
    }

    public function getUser()
    {
        // TODO
    }

    /**
     * Determine if the request is for this driver.
     * We check the key that are in the object
     *
     * @return bool
     */
    public function matchesRequest(): bool
    {
        $noAttachments = $this->event->keys()->filter(function ($key) {
            return in_array($key, ['audio', 'voice', 'video', 'photo', 'location', 'contact', 'document']);
        })->isEmpty();

        return $noAttachments
            && (!is_null($this->event->get('from')) || !is_null($this->payload->get('callback_query')))
            && !is_null($this->payload->get('update_id'));
    }

    public function loadMessages()
    {
        # If callback_query exist in object
        if ($this->payload->has('callback_query')) {
            # Receive Callback Query
            $callback = Collection::make($this->payload->get('callback_query'));
            $message = [
                /**
                 * @class VisStudio\Messages\Incoming\IncomingMessage
                 * @vendor vis-studio
                 *
                 * @param string $message
                 * @param string $sender
                 * @param string $recipient
                 * @param mixed|null $payload
                 * @param string $botId = ''
                 */
                new IncomingMessage(
                    $callback->get('data'),
                    $callback->get('from')['id'],
                    $callback->get('message')['chat']['id'],
                    $callback->get('message')
                )
            ];
        } else {
            # Receive event all
            $event = $this->event->all();

            $message = [
                /**
                 * @class VisStudio\Messages\Incoming\IncomingMessage
                 * @vendor vis-studio
                 *
                 * @param string $message
                 * @param string $sender
                 * @param string $recipient
                 * @param mixed|null $payload
                 * @param string $botId = ''
                 */
                new IncomingMessage(
                    $this->event->get('text'),
                    $event['from']['id'] ?? null,
                    $event['chat']['id'] ?? null,
                    $this->event
                )
            ];
        }

        # Saving message
        $this->messages = $message;
    }

    /**
     * Is bot?
     *
     * @return false
     */
    public function isBot(): bool
    {
        return false;
    }

    /**
     * Typing Messages
     *
     * @param IncomingMessage $matchingMessage
     */
    public function types(IncomingMessage $matchingMessage)
    {
        $params = [
            'chat_id' => $matchingMessage->getRecipient(),
            'action'  => 'typing',
        ];

        return $this->http->post($this->buildApiUrl('sendChatAction'), [], $params);
    }

    /**
     * Generate the Telegram API url for the given endpoint.
     *
     * @param $endpoint
     * @return string
     */
    protected function buildApiUrl($endpoint): string
    {
        return self::API_URL.$this->config->get('token').'/'.$endpoint;
    }
}

