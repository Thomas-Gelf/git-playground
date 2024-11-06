<?php

namespace IMEdge\CertificateStore\DiskBased;

use InvalidArgumentException;
use RuntimeException;
use Sop\CryptoEncoding\PEM;

use function bin2hex;
use function file_exists;
use function file_get_contents;
use function file_put_contents;
use function is_dir;
use function mb_check_encoding;
use function mkdir;
use function preg_match;
use function preg_replace;
use function str_contains;
use function unlink;

/**
 * When using this trait, you should implement the DirectoryBaseComponent interface
 */
trait DirectoryHelper
{
    protected string $basedir;

    public function getBaseDir(): string
    {
        return $this->basedir;
    }

    public function hasFile(string $filename): bool
    {
        return file_exists($this->realpath($filename));
    }

    public function readFile(string $filename): ?string
    {
        $realFilename = $this->realpath($filename);
        if (! file_exists($realFilename)) {
            return null;
        }
        $content = @file_get_contents($realFilename);
        if ($content === false) {
            throw new RuntimeException("Failed to read file: $filename");
        }

        return $content;
    }

    public function writeFile(string $filename, string $content, ?int $mode = null): void
    {
        $realFilename = $this->realpath($filename);
        if (false === @file_put_contents($realFilename, $content)) {
            throw new RuntimeException("Failed to write file: $filename");
        }
        if ($mode !== null) {
            chmod($realFilename, $mode);
        }
    }

    public function appendToFile(string $filename, string $content): void
    {
        $realFilename = $this->realpath($filename);
        if (! file_exists($realFilename)) {
            throw new RuntimeException("Cannot append to $filename, file does not exist");
        }
        if (false === @file_put_contents($realFilename, $content, FILE_APPEND)) {
            throw new RuntimeException("Failed to write file: $filename");
        }
    }

    public function removeFile(string $filename): void
    {
        $realFilename = $this->realpath($filename);
        if (! file_exists($realFilename)) {
            return;
        }
        if (!@unlink($realFilename)) {
            throw new RuntimeException("Failed to unlink file: $filename");
        }
    }

    public function readPEM(string $filename): ?PEM
    {
        if ($string = $this->readFile($filename)) {
            return PEM::fromString($string);
        }

        return null;
    }

    /**
     * @param string[] $directories
     */
    protected function initializeDirectoryStructure(string $basedir, array $directories = []): void
    {
        if (! is_dir($basedir)) {
            mkdir($basedir, 0750, true);
        }
        $this->basedir = $basedir;

        foreach ($directories as $sub) {
            $directory = "$basedir/$sub";
            if (! is_dir($directory)) {
                mkdir($directory, 0750);
            }
        }
    }

    protected function realpath(string $filename): string
    {
        self::assertValidFilename($filename);
        return $this->getBaseDir() . '/' . $filename;
    }

    protected static function assertValidFilename(string $filename): void
    {
        if (! preg_match('/^[A-Za-z0-9.\/_ -]+$/u', $filename) || str_contains($filename, '..')) {
            throw new InvalidArgumentException("Got invalid filename: $filename");
        }
    }

    protected static function replaceUnsafeCharacters(string $filename): string
    {
        if (! mb_check_encoding($filename, 'UTF-8')) {
            throw new RuntimeException('Only valid UTF-8 strings are accepted, got 0x' . bin2hex($filename));
        }

        $sanitized = preg_replace('#[/\\\:"*?<>|]#u', '_', $filename);
        if ($sanitized === null) {
            throw new RuntimeException('Failed to sanitize string: 0x' . bin2hex($filename));
        }

        return $sanitized;
    }
}
