<?php

namespace App\Mail\Transport;

use Illuminate\Support\Facades\Http;
use Symfony\Component\Mailer\Exception\TransportException;
use Symfony\Component\Mailer\SentMessage;
use Symfony\Component\Mailer\Transport\AbstractTransport;
use Symfony\Component\Mime\Address;
use Symfony\Component\Mime\Email;

class MailtrapApiTransport extends AbstractTransport
{
    public function __construct(private readonly string $token, private readonly int $timeout = 15)
    {
        parent::__construct();
    }

    public function __toString(): string
    {
        return 'mailtrap-api';
    }

    protected function doSend(SentMessage $message): void
    {
        $email = $message->getOriginalMessage();
        if ($this->token === '' || ! $email instanceof Email) {
            throw new TransportException('Configure MAILTRAP_API_TOKEN before sending email.');
        }
        $address = fn (Address $item) => ['email' => $item->getAddress(), 'name' => $item->getName()];
        $payload = [
            'from' => $address($email->getFrom()[0]),
            'to' => array_map($address, $email->getTo()),
            'subject' => $email->getSubject(),
        ];
        foreach (['cc' => $email->getCc(), 'bcc' => $email->getBcc()] as $field => $items) {
            if ($items) {
                $payload[$field] = array_map($address, $items);
            }
        }
        if ($email->getReplyTo()) {
            $payload['reply_to'] = $address($email->getReplyTo()[0]);
        }
        if ($email->getHtmlBody() !== null) {
            $payload['html'] = $email->getHtmlBody();
        }
        if ($email->getTextBody() !== null) {
            $payload['text'] = $email->getTextBody();
        }
        foreach ($email->getAttachments() as $attachment) {
            $payload['attachments'][] = [
                'content' => base64_encode($attachment->getBody()),
                'filename' => $attachment->getFilename(),
                'type' => $attachment->getMediaType().'/'.$attachment->getMediaSubtype(),
                'disposition' => $attachment->getDisposition(),
            ];
        }
        try {
            // No automatic transport retries: an ambiguous timeout may already have sent the email.
            $response = Http::withToken($this->token)->acceptJson()->timeout($this->timeout)
                ->connectTimeout(8)->withOptions(['allow_redirects' => false])
                ->post('https://send.api.mailtrap.io/api/send', $payload);
        } catch (\Throwable) {
            throw new TransportException('Mailtrap API connection failed.');
        }
        if (! $response->successful() || $response->json('success') !== true) {
            throw new TransportException('Mailtrap rejected the email (HTTP '.$response->status().'). Check domain verification, token permissions and quota.');
        }
        $id = $response->json('message_ids.0');
        if (is_string($id)) {
            $message->setMessageId($id);
        }
    }
}
