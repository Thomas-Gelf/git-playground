<?php

namespace IMEdge\Log\Writer;

use Amp\ByteStream\WritableResourceStream;
use IMEdge\CliScreen\AnsiScreen;

class AmpStreamWriter implements LogWriter
{
    protected const DEFAULT_SEPARATOR = PHP_EOL;

    protected const COLORS = [
        'emergency' => 'purple',
        'alert'     => 'lightpurple',
        'critical'  => 'red',
        'error'     => 'lightred',
        'warning'   => 'brown',
        'notice'    => 'darkgray', // darkgray is fat, black isn't
        'info'      => 'black',
        'debug'     => 'lightgray',
    ];

    protected const ICONS = [
        /*
         // Other possible icons
        'emergency' => '‼',
        'alert'     => '😲',
        'critical'  => '‼',
        'critical'  => '🚑',
        'error'     => '✖',
        'error'     => '×',
        'error'     => '😱',
        'warning'   => '⚠',
        'notice'    => '📍',
        'notice'    => '✅',
        'info'      => 'ℹ',
        'info'      => '🧐',
        'info'      => 'ℹ️',
        'debug'     => ' ',
        'debug'     => '🐞',
        */
        'emergency' => '🆘',
        'alert'     => '‼',
        'critical'  => '🔥',
        'error'     => '❌',
        'warning'   => '⚠️ ',
        'notice'    => '✔ ',
        'info'      => '🛈 ',
        'debug'     => '🪲',
    ];

    protected string $separator = self::DEFAULT_SEPARATOR;
    protected bool $isTty;
    protected bool $isUtf8 = false;
    protected ?AnsiScreen $screen = null;

    public function __construct(public WritableResourceStream $stream)
    {
        $this->isTty = stream_isatty($this->stream->getResource());
        if ($this->isTty) {
            $this->screen = new AnsiScreen();
            if ($this->screen->isUtf8()) {
                $this->isUtf8 = true;
            }
        }
    }

    public function setSeparator(string $separator): void
    {
        $this->separator = $separator;
    }

    public function write(string $level, string $message, array $context = []): void
    {
        if ($this->screen) {
            $levelString = $level;
            if ($this->isUtf8) {
                // $levelString =
                //     $this->screen->colorize(self::ICONS[$level], self::COLORS[$level]) . ' ' . $levelString;
                $levelString = self::ICONS[$level] . ' ' . $levelString;
            } else {
                $levelString = $this->screen->colorize($levelString, self::COLORS[$level]);
            }
            if (!empty($context)) {
                $message = sprintf($message, ...$context);
            }
            $this->stream->write(
                $levelString . ": $message" . $this->separator
            );
        } else {
            $this->stream->write("$level: $message" . $this->separator);
        }
    }
}
