<?php

namespace IcingaDataNode\Daemon;

interface DaemonComponent
{
    public function start(): void;

    public function stop(): void;
}
