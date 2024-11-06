<?php

namespace IMEdge\Log\Writer;

use IMEdge\JsonRpc\JsonRpcConnection;

use function iconv;

class JsonRpcConnectionWriter implements LogWriter
{
    protected const DEFAULT_RPC_METHOD = 'logger.log';
    protected string $method = self::DEFAULT_RPC_METHOD;

    public function __construct(
        protected JsonRpcConnection $connection,
        protected array $defaultContext = []
    ) {
    }

    public function setMethod(string $method): void
    {
        $this->method = $method;
    }

    public function write(string $level, string $message, array $context = []): void
    {
        $message = iconv('UTF-8', 'UTF-8//IGNORE', $message);
        $this->connection->notification($this->method, [
            'level'    => $level,
            // 'timestamp' => microtime(true),
            'message'  => $message,
            'context'  => $this->defaultContext + $context,
        ]);
    }
}
