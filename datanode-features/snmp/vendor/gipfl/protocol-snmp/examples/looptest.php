<?php

use Revolt\EventLoop;

require dirname(__DIR__) . '/vendor/autoload.php';
$suspension = EventLoop::getSuspension();

$repeatId = EventLoop::repeat(1, function (): void {
    print '++ Executing callback created by EventLoop::repeat()' . PHP_EOL;
});

EventLoop::delay(5, function () use ($suspension, $repeatId): void {
    print '++ Executing callback created by EventLoop::delay()' . PHP_EOL;

    EventLoop::cancel($repeatId);
    $suspension->resume(null);

    print '++ Suspension::resume() is async!' . PHP_EOL;
});

print '++ Suspending to event loop...' . PHP_EOL;

$suspension->suspend();

print '++ Script end' . PHP_EOL;
