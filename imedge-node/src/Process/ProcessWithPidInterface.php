<?php

namespace IcingaDataNode\Process;

interface ProcessWithPidInterface
{
    public function getProcessPid(): ?int;
}
