<?php

namespace App\Service;

use Symfony\Contracts\HttpClient\HttpClientInterface;

class TelegramService
{
    private string $botToken;
    private HttpClientInterface $http;

    public function __construct(HttpClientInterface $http, string $botToken)
    {
        $this->http = $http;
        $this->botToken = $botToken;
    }

    public function sendMessage(string $chatId, string $text): void
    {
        $this->http->request('POST', "https://api.telegram.org/bot{$this->botToken}/sendMessage", [
            'json' => ['chat_id' => $chatId, 'text' => $text]
        ]);
    }

    public function createInviteLink(string $chatId): string
    {
        $response = $this->http->request('POST', "https://api.telegram.org/bot{$this->botToken}/createChatInviteLink", [
            'json' => ['chat_id' => $chatId]
        ]);
        return $response->toArray()['result']['invite_link'];
    }

    // ✅ NOUVEAU : message de bienvenue personnalisé
    public function sendWelcome(string $chatId, string $userName, string $eventTitle): void
    {
        $text = "👋 Bienvenue *{$userName}* dans le groupe de discussion de l'événement *{$eventTitle}* !\n\n"
            . "🎉 Vous pouvez maintenant discuter avec les autres participants.\n"
            . "📅 Bonne préparation et à bientôt !";

        $this->http->request('POST', "https://api.telegram.org/bot{$this->botToken}/sendMessage", [
            'json' => [
                'chat_id'    => $chatId,
                'text'       => $text,
                'parse_mode' => 'Markdown'
            ]
        ]);
    }
}