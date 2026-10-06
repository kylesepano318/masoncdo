<?php

namespace App\Mail\Transport;

use Illuminate\Support\Facades\Http;
use Symfony\Component\Mailer\Exception\TransportException;
use Symfony\Component\Mailer\SentMessage;
use Symfony\Component\Mailer\Transport\AbstractTransport;

class GmailApiTransport extends AbstractTransport
{
    private ?string $accessToken = null;

    private int $expiresAt = 0;

    public function __construct(
        private readonly string $clientId,
        private readonly string $clientSecret,
        private readonly string $refreshToken,
        private readonly int $timeout = 8,
    ) {
        parent::__construct();
    }

    public function __toString(): string
    {
        return 'gmail-api';
    }

    protected function doSend(SentMessage $message): void
    {
        $token = $this->token();
        try {
            $response = Http::withToken($token)->acceptJson()
                ->timeout($this->timeout)->connectTimeout($this->timeout)
                ->withOptions(['allow_redirects' => false])
                ->post('https://gmail.googleapis.com/gmail/v1/users/me/messages/send', [
                    'raw' => rtrim(strtr(base64_encode($message->toString()), '+/', '-_'), '='),
                ]);
        } catch (\Throwable) {
            throw new TransportException('Gmail API connection failed. Check connectivity and try again.');
        }
        if (! $response->successful() || ! is_string($response->json('id')) || $response->json('id') === '') {
            if ($response->status() === 401) {
                $this->accessToken = null;
            }
            throw new TransportException('Gmail API rejected the email (HTTP '.$response->status().'). Check sender, Gmail permissions and sending limits.');
        }
        $message->setMessageId($response->json('id'));
    }

    private function token(): string
    {
        if ($this->accessToken !== null && time() < $this->expiresAt) {
            return $this->accessToken;
        }
        if ($this->clientId === '' || $this->clientSecret === '' || $this->refreshToken === '') {
            throw new TransportException('Gmail API credentials are missing. Configure GMAIL_CLIENT_ID, GMAIL_CLIENT_SECRET and GMAIL_REFRESH_TOKEN.');
        }
        try {
            $response = Http::asForm()->acceptJson()
                ->timeout($this->timeout)->connectTimeout($this->timeout)
                ->withOptions(['allow_redirects' => false])
                ->post('https://oauth2.googleapis.com/token', [
                    'client_id' => $this->clientId,
                    'client_secret' => $this->clientSecret,
                    'refresh_token' => $this->refreshToken,
                    'grant_type' => 'refresh_token',
                ]);
        } catch (\Throwable) {
            throw new TransportException('Gmail OAuth connection failed. Check connectivity and try again.');
        }
        $token = $response->json('access_token');
        if (! $response->successful() || ! is_string($token) || $token === '') {
            throw new TransportException('Gmail authorization failed. Reauthorize the sender and update its refresh token.');
        }
        $this->accessToken = $token;
        $this->expiresAt = time() + max(0, (int) $response->json('expires_in', 3600) - 60);

        return $token;
    }
}
