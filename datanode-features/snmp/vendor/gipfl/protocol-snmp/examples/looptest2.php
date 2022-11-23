<?php

use Revolt\EventLoop;

require dirname(__DIR__) . '/vendor/autoload.php';

if (\stream_set_blocking(STDIN, false) !== true) {
    \fwrite(STDERR, "Unable to set STDIN to non-blocking" . PHP_EOL);
    exit(1);
}

print "Write something and hit enter" . PHP_EOL;

$suspension = EventLoop::getSuspension();

$readableId = EventLoop::onReadable(STDIN, function ($id, $stream) use ($suspension): void {
    EventLoop::cancel($id);

    $chunk = \fread($stream, 8192);
    if ($chunk === false) {
        throw new RuntimeException('Got nothing from stream');
    }

    print "Read " . \strlen($chunk) . " bytes" . PHP_EOL;

    $suspension->resume(null);
});

$timeoutId = EventLoop::delay(5, function () use ($readableId, $suspension) {
    EventLoop::cancel($readableId);

    print "Timeout reached" . PHP_EOL;

    $suspension->resume(null);
});

$suspension->suspend();

EventLoop::cancel($readableId);
EventLoop::cancel($timeoutId);