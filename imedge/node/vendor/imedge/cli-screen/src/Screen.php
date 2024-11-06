<?php

namespace IMEdge\CliScreen;

use function array_key_exists;
use function exec;
use function explode;
use function getenv;
use function mb_strlen;
use function preg_match;
use function setlocale;
use function strlen;

/**
 * Base class providing minimal CLI Screen functionality. While classes
 * extending this one (read: AnsiScreen) should implement all the fancy cool
 * things, this base class makes sure that your code will still run in
 * environments with no ANSI or similar support
 *
 * ```php
 * $screen = Screen::instance();
 * echo $screen->center($screen->underline('Hello world'));
 * ```
 */
class Screen
{
    protected ?bool $isUtf8 = null;

    /**
     * Get a new Screen instance.
     *
     * For now this is limited to either a very basic Screen implementation as
     * a fall-back or an AnsiScreen implementation with more functionality
     *
     * @return AnsiScreen|Screen
     */
    public static function factory(): Screen
    {
        if (! defined('STDOUT')) {
            return new Screen();
        }
        if (\function_exists('posix_isatty') && \posix_isatty(STDOUT)) {
            return new AnsiScreen();
        } else {
            return new Screen();
        }
    }

    /**
     * Center the given string horizontally on the current screen
     */
    public function center(string $string): string
    {
        $len = $this->strlen($string);
        $width = (int) \floor(($this->getColumns() + $len) / 2) - $len;

        return \str_repeat(' ', $width) . $string;
    }

    /**
     * Clear the screen
     *
     * Impossible for non-ANSI screens, so let's output a newline for now
     */
    public function clear(): string
    {
        return "\n";
    }

    /**
     * Colorize the given text. Has no effect on a basic Screen, all colors
     * will be accepted. It's prefectly legal to provide background or foreground
     * only
     *
     * Returns the very same string, eventually enriched with related ANSI codes
     */
    public function colorize(string $text, ?string $fgColor = null, ?string $bgColor = null): string
    {
        return $text;
    }

    /**
     * Generate $count newline characters
     */
    public function newlines(int $count = 1): string
    {
        return \str_repeat(PHP_EOL, $count);
    }

    /**
     * Calculate the visible length of a given string. While this is simple on
     * a non-ANSI-screen, such implementation will be required to strip control
     * characters to get the correct result
     */
    public function strlen(string $string): int
    {
        if ($this->isUtf8()) {
            return mb_strlen($string, 'UTF-8');
        } else {
            return strlen($string);
        }
    }

    /**
     * Underline the given text - if possible
     */
    public function underline(string $text): string
    {
        return $text;
    }

    /**
     * Get the number of currently available columns. Please note that this
     * might chance at any time while your program is running
     */
    public function getColumns(): int
    {
        $cols = (int) getenv('COLUMNS');
        if (! $cols) {
            // stty -a ?
            $cols = (int) exec('tput cols');
        }
        if (! $cols) {
            $cols = 80;
        }

        return $cols;
    }

    /**
     * Get the number of currently available rows. Please note that this
     * might chance at any time while your program is running
     */
    public function getRows(): int
    {
        $rows = (int) getenv('ROWS');
        if (! $rows) {
            // stty -a ?
            $rows = (int) exec('tput lines');
        }
        if (! $rows) {
            $rows = 25;
        }

        return $rows;
    }

    /**
     * Whether we're on a UTF-8 screen. We assume latin1 otherwise, there is no
     * support for additional encodings
     */
    public function isUtf8(): bool
    {
        if ($this->isUtf8 === null) {
            // null should equal 0 here, however seems to equal '' on some systems:
            $current = setlocale(LC_ALL, '0');
            if ($current === false) {
                return false;
            }

            $parts = explode(';', $current);
            $lc_parts = [];
            foreach ($parts as $part) {
                if (!str_contains($part, '=')) {
                    continue;
                }
                list($key, $val) = explode('=', $part, 2);
                $lc_parts[$key] = $val;
            }

            $this->isUtf8 = array_key_exists('LC_CTYPE', $lc_parts)
                && preg_match('~\.UTF-8$~i', $lc_parts['LC_CTYPE']);
        }

        return $this->isUtf8;
    }
}
