<?php

declare(strict_types=1);

namespace ASAP\LOG;

final class Log
{
    private array $messages = [];
    private array $records = [];

    public function add(string $message, string $level = 'INFO', array $context = []): void
    {
        if (trim($message) === '') {
            throw new \InvalidArgumentException('ASAP_LOG_MESSAGE_EMPTY');
        }

        $level = strtoupper(trim($level));

        if ($level === '') {
            throw new \InvalidArgumentException('ASAP_LOG_LEVEL_EMPTY');
        }

        $this->messages[] = $message;
        $this->records[] = [
            'level' => $level,
            'message' => $message,
            'context' => $context,
        ];
    }

    public function info(string $message): void
    {
        $this->add($message, 'INFO');
    }

    public function warning(string $message): void
    {
        $this->add($message, 'WARNING');
    }

    public function error(string $message): void
    {
        $this->add($message, 'ERROR');
    }

    public function entries(): array
    {
        return $this->messages;
    }

    public function messages(): array
    {
        return $this->messages;
    }

    public function records(): array
    {
        return $this->records;
    }
}
