<?php

namespace IMEdge\Log\Writer;

use IMEdge\Log\LogLevel;
use IMEdge\systemd\NotificationSocket;

class JournaldLogger implements LogWriter
{
    public const JOURNALD_SOCKET = '/run/systemd/journal/socket';

    protected NotificationSocket $socket;
    protected array $extraFields = [];

    public function __construct(?string $socket = null)
    {
        $this->socket = new NotificationSocket($socket ?: self::JOURNALD_SOCKET);
    }

    public function setIdentifier(string $identifier): void
    {
        $this->setExtraField('SYSLOG_IDENTIFIER', $identifier);
    }

    public function setExtraField(string $name, ?string $value): void
    {
        if ($value === null) {
            unset($this->extraFields[$name]);
        } else {
            $this->extraFields[$name] = $value;
        }
    }

    public function write(string $level, string $message, array $context = []): void
    {
        $this->socket->send([
            'MESSAGE' => $message,
            'PRIORITY' => LogLevel::mapNameToNumeric($level),
        ] + $context + $this->extraFields);
    }
}
