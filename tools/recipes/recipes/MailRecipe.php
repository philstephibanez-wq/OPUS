<?php

declare(strict_types=1);

namespace Opus\Recipe\Recipes;

use ASAP\Recipe\RecipeAssertionFailedException;
use ASAP\Recipe\RecipeContext;
use ASAP\Recipe\RecipeInterface;

/** PUBLIC RECIPE: validate mail domain plus real Mailpit SMTP/API delivery. */
final class MailRecipe implements RecipeInterface
{
    public function name(): string { return 'mail'; }

    public function run(RecipeContext $context): array
    {
        $mail = new \ASAP\Mail\Mail('recipe@example.org', 'Opus recipe mail', 'OPUS_MAIL_BODY_OK');
        $context->assert($mail->to === 'recipe@example.org', 'OPUS_MAIL_TO_INVALID_AFTER_CONSTRUCT');
        $context->assert($mail->subject === 'Opus recipe mail', 'OPUS_MAIL_SUBJECT_INVALID_AFTER_CONSTRUCT');
        $context->assert(str_contains($mail->body, 'OPUS_MAIL_BODY_OK'), 'OPUS_MAIL_BODY_INVALID_AFTER_CONSTRUCT');

        try {
            new \ASAP\Mail\Mail('invalid-address', 'bad', 'bad');
            $context->assert(false, 'OPUS_MAIL_INVALID_ADDRESS_DID_NOT_FAIL');
        } catch (\InvalidArgumentException) {
        }

        try {
            (new \ASAP\Mail\PhpMailer())->send($mail);
            $context->assert(false, 'OPUS_MAIL_UNCONFIGURED_TRANSPORT_DID_NOT_FAIL');
        } catch (\RuntimeException $exception) {
            $context->assert($exception->getMessage() === 'OPUS_PHPMAILER_RUNTIME_NOT_CONFIGURED', 'OPUS_MAIL_TRANSPORT_FAILURE_CODE_INVALID', $exception->getMessage());
        }

        $subject = 'Opus Mailpit Recipe ' . $context->runId();
        $body = 'OPUS_MAILPIT_BODY_OK ' . $context->runId();
        $this->assertMailpitAvailable();
        $this->sendSmtp('robot@example.org', $subject, $body);
        $message = $this->waitForMailpitSubject($subject, 10.0);
        $raw = json_encode($message, JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES) ?: '';
        $context->assert(str_contains($raw, 'robot@example.org'), 'OPUS_MAILPIT_TO_MISSING', $raw);
        $context->assert(str_contains($raw, $subject), 'OPUS_MAILPIT_SUBJECT_MISSING', $raw);
        $context->assert(str_contains($raw, $body), 'OPUS_MAILPIT_BODY_MISSING', $raw);

        return ['OPUS_MAILPIT_AVAILABLE_OK', 'OPUS_MAIL_SEND_OK', 'OPUS_MAIL_RECEIVED_OK', 'OPUS_MAIL_CONTENT_OK', 'OPUS_MAIL_OK'];
    }

    private function mailpitHttpBase(): string
    {
        return rtrim((string)(getenv('OPUS_RECIPE_MAILPIT_HTTP') ?: 'http://127.0.0.1:8025'), '/');
    }

    private function mailpitSmtpHost(): string
    {
        return (string)(getenv('OPUS_RECIPE_MAILPIT_SMTP_HOST') ?: '127.0.0.1');
    }

    private function mailpitSmtpPort(): int
    {
        return (int)((string)(getenv('OPUS_RECIPE_MAILPIT_SMTP_PORT') ?: '1025'));
    }

    private function assertMailpitAvailable(): void
    {
        $messages = @file_get_contents($this->mailpitHttpBase() . '/api/v1/messages?limit=1');
        if (!is_string($messages)) {
            throw RecipeAssertionFailedException::because('OPUS_MAILPIT_HTTP_API_UNAVAILABLE', $this->mailpitHttpBase() . '/api/v1/messages');
        }
        $socket = @fsockopen($this->mailpitSmtpHost(), $this->mailpitSmtpPort(), $errno, $errstr, 3.0);
        if (!is_resource($socket)) {
            throw RecipeAssertionFailedException::because('OPUS_MAILPIT_SMTP_UNAVAILABLE', $this->mailpitSmtpHost() . ':' . $this->mailpitSmtpPort() . ' :: ' . $errstr);
        }
        fclose($socket);
    }

    private function sendSmtp(string $to, string $subject, string $body): void
    {
        $socket = @fsockopen($this->mailpitSmtpHost(), $this->mailpitSmtpPort(), $errno, $errstr, 5.0);
        if (!is_resource($socket)) {
            throw RecipeAssertionFailedException::because('OPUS_MAILPIT_SMTP_CONNECT_FAILED', $errstr);
        }
        stream_set_timeout($socket, 5);
        $this->expect($socket, '220', 'BANNER');
        $this->command($socket, 'HELO opus-recipe.local', '250', 'HELO');
        $this->command($socket, 'MAIL FROM:<opus-recipe@example.org>', '250', 'MAIL_FROM');
        $this->command($socket, 'RCPT TO:<' . $to . '>', '250', 'RCPT_TO');
        $this->command($socket, 'DATA', '354', 'DATA');
        $message = "From: Opus Recipe <opus-recipe@example.org>\r\n"
            . 'To: <' . $to . ">\r\n"
            . 'Subject: ' . $subject . "\r\n"
            . "MIME-Version: 1.0\r\nContent-Type: text/plain; charset=UTF-8\r\n\r\n"
            . str_replace(["\r", "\n."], ['', "\n.."], $body) . "\r\n.";
        $this->command($socket, $message, '250', 'BODY');
        fwrite($socket, "QUIT\r\n");
        fclose($socket);
    }

    private function expect($socket, string $expected, string $step): void
    {
        $line = fgets($socket, 4096);
        if (!is_string($line) || !str_starts_with($line, $expected)) {
            throw RecipeAssertionFailedException::because('OPUS_MAILPIT_SMTP_UNEXPECTED_' . $step, trim((string)$line));
        }
    }

    private function command($socket, string $command, string $expected, string $step): void
    {
        fwrite($socket, $command . "\r\n");
        $this->expect($socket, $expected, $step);
    }

    /** @return array<string,mixed> */
    private function waitForMailpitSubject(string $subject, float $seconds): array
    {
        $deadline = microtime(true) + $seconds;
        do {
            $message = $this->findMailpitSubject($subject);
            if ($message !== null) {
                return $message;
            }
            usleep(250000);
        } while (microtime(true) < $deadline);
        throw RecipeAssertionFailedException::because('OPUS_MAIL_NOT_RECEIVED_FAIL', $subject);
    }

    /** @return array<string,mixed>|null */
    private function findMailpitSubject(string $subject): ?array
    {
        $json = $this->httpJson($this->mailpitHttpBase() . '/api/v1/messages?limit=200');
        $messages = [];
        if (isset($json['messages']) && is_array($json['messages'])) { $messages = $json['messages']; }
        if (isset($json['Messages']) && is_array($json['Messages'])) { $messages = $json['Messages']; }
        foreach ($messages as $message) {
            if (!is_array($message)) { continue; }
            $detail = $this->mailpitDetail($message);
            $raw = json_encode([$message, $detail], JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES) ?: '';
            if (str_contains($raw, $subject)) {
                return ['summary' => $message, 'detail' => $detail, 'raw' => $raw];
            }
        }
        return null;
    }

    /** @param array<string,mixed> $message @return array<string,mixed> */
    private function mailpitDetail(array $message): array
    {
        $id = (string)($message['ID'] ?? $message['Id'] ?? $message['id'] ?? '');
        if ($id === '') { return []; }
        return $this->httpJson($this->mailpitHttpBase() . '/api/v1/message/' . rawurlencode($id));
    }

    /** @return array<string,mixed> */
    private function httpJson(string $url): array
    {
        $body = @file_get_contents($url);
        if (!is_string($body)) {
            throw RecipeAssertionFailedException::because('OPUS_MAILPIT_HTTP_READ_FAILED', $url);
        }
        $decoded = json_decode($body, true);
        if (!is_array($decoded)) {
            throw RecipeAssertionFailedException::because('OPUS_MAILPIT_HTTP_JSON_INVALID', $url);
        }
        return $decoded;
    }
}
