<?php

namespace IcingaDataNode;

use RuntimeException;

abstract class FilesystemUtil
{
    public static function requireDirectory(string $directory, bool $recursive = false, int $mode = 0755): void
    {
        if (! @is_dir($directory)) {
            if (!@mkdir($directory, $mode, $recursive)) {
                throw new RuntimeException("Unable do create '$directory'");
            }
        }
        if (! @is_writable($directory)) {
            throw new RuntimeException("Cannot write to '$directory'");
        }
    }
}
