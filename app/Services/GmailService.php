<?php

namespace App\Services;

use Google\Client;
use Google\Service\Gmail;
use Google\Service\Gmail\ModifyMessageRequest;
use Illuminate\Support\Facades\Log;

class GmailService
{
    private ?Client $client = null;
    private ?Gmail $gmail = null;
    private string $credentialsPath;

    public function __construct()
    {
        $this->credentialsPath = base_path('credentials.json');
    }

    public function authenticate(): void
    {
        $this->client = new Client();
        $this->client->setApplicationName('Yape Auto Sync');
        $this->client->setScopes([
            Gmail::GMAIL_READONLY,
            Gmail::GMAIL_MODIFY,
        ]);
        $this->client->setAuthConfig($this->credentialsPath);
        $this->client->setAccessType('offline');
        $this->client->setRedirectUri('http://127.0.0.1:8000/gmail/callback');

        $this->loadToken();
        
        if ($this->client->isAccessTokenExpired()) {
            $this->refreshToken();
        }

        $this->gmail = new Gmail($this->client);
    }

    private function loadToken(): void
    {
        $tokenFile = storage_path('app/gmail_token.json');
        if (file_exists($tokenFile)) {
            $token = json_decode(file_get_contents($tokenFile), true);
            $this->client->setAccessToken($token);
        }
    }

    private function saveToken(): void
    {
        $tokenFile = storage_path('app/gmail_token.json');
        file_put_contents($tokenFile, json_encode($this->client->getAccessToken()));
    }

    public function isAuthenticated(): bool
    {
        $tokenFile = storage_path('app/gmail_token.json');
        return file_exists($tokenFile);
    }

    private function refreshToken(): void
    {
        if ($this->client->getRefreshToken()) {
            $this->client->fetchAccessTokenWithRefreshToken($this->client->getRefreshToken());
            $this->saveToken();
        }
    }

    public function getGmail(): Gmail
    {
        if (!isset($this->gmail)) {
            $this->authenticate();
        }
        return $this->gmail;
    }

    public function getEmailsFromSender(string $sender, ?string $afterDate = null): array
    {
        return $this->getEmailsFromSenders([$sender], $afterDate);
    }

    public function getEmailsFromSenders(array $senders, ?string $afterDate = null): array
    {
        $this->authenticate();

        $parts = [];
        foreach ($senders as $s) {
            $parts[] = "from:{$s}";
        }
        $query = implode(' OR ', $parts);

        if ($afterDate) {
            $query .= " after:{$afterDate}";
        }

        $messages = [];
        $pageToken = null;

        do {
            $optParams = [
                'maxResults' => 100,
                'q' => $query,
            ];

            if ($pageToken) {
                $optParams['pageToken'] = $pageToken;
            }

            $results = $this->gmail->users_messages->listUsersMessages('me', $optParams);
            $messages = array_merge($messages, $results->getMessages() ?: []);
            $pageToken = $results->getNextPageToken();
        } while ($pageToken);

        return $messages;
    }

    public function getEmailBody(string $messageId): ?string
    {
        $attempts = 0;
        $maxAttempts = 2;

        while ($attempts < $maxAttempts) {
            try {
                $message = $this->getGmail()->users_messages->get('me', $messageId, [
                    'format' => 'raw'
                ]);

                $rawData = $message->getRaw();
                $decoded = base64_decode(strtr($rawData, '-_', '+/'));

                return $decoded;
            } catch (\Exception $e) {
                $attempts++;
                Log::warning("GmailService getEmailBody attempt $attempts/$maxAttempts failed for $messageId: " . $e->getMessage());
                if ($attempts >= $maxAttempts) {
                    Log::error("GmailService getEmailBody failed for $messageId after $maxAttempts attempts");
                    return null;
                }
                usleep(500000);
                $this->authenticate();
            }
        }

        return null;
    }

    public function markAsRead(string $messageId): void
    {
        try {
            $modifyRequest = new ModifyMessageRequest();
            $modifyRequest->setRemoveLabelIds(['UNREAD']);
            $this->getGmail()->users_messages->modify('me', $messageId, $modifyRequest);
        } catch (\Exception $e) {
            Log::warning("GmailService markAsRead failed for $messageId: " . $e->getMessage());
        }
    }
}