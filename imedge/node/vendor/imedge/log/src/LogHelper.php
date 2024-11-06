<?php

namespace IMEdge\Log;

use Psr\Log\LoggerInterface;
use Revolt\EventLoop;
use Throwable;

class LogHelper
{
    public static function logEventloopErrors(LoggerInterface $logger, string $prefix): void
    {
        EventLoop::setErrorHandler(function (Throwable $e) use ($logger, $prefix) {
            $logger->error($prefix . $e->getMessage() . ' - ' . $e->getFile() . ':' . $e->getLine());
            debug_print_backtrace();
        });
    }

    public static function catchStdOut(LoggerInterface $logger): void
    {
        ob_start();
        EventLoop::repeat(1, function () use ($logger) {
            if ($out = ob_get_clean()) {
                $logger->error('Got unwanted output: ' . var_export($out, true));
            }
            ob_start();
        });
    }
}
