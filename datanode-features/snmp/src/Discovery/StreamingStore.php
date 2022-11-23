<?php

namespace IcingaFeature\Snmp\Discovery;

use gipfl\Json\JsonString;
use RuntimeException;

class StreamingStore
{
    /**
     * @var ?resource
     */
    protected $dump = null;
    protected int $count;

    public function __construct(string $filename)
    {
        $dump = fopen($filename, 'w');
        if ($dump === false) {
            throw new RuntimeException("Cannot open '$filename' for writing");
        }
        $this->dump = $dump;
        $this->count = 0;
        fwrite($this->dump, "{\n");
    }

    public function append(string $ip, $content): void
    {
        if ($this->count !== 0) {
            fwrite($this->dump, ",\n");
        }
        fwrite($this->dump, JsonString::encode($ip) . ': ' . JsonString::encode($content));
        $this->count++;
    }

    public function close(): void
    {
        if ($this->dump) {
            fwrite($this->dump, "\n}\n");
            fclose($this->dump);
            $this->dump = null;
        }
    }

    public function __destruct()
    {
        $this->close();
    }
}
