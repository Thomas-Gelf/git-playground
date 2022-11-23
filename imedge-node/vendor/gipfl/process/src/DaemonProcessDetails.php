<?php

namespace gipfl\RpcDaemon;

use gipfl\Json\JsonString;
use gipfl\LinuxHealth\Memory;
use gipfl\Process\ProcessInfo;
use gipfl\Process\ProcessList;
use React\ChildProcess\Process;
use gipfl\Cli\Process as CliProcess;

class DaemonProcessDetails
{
    /** @var string */
    protected $instanceUuid;

    /** @var \stdClass */
    protected $info;

    /** @var ProcessList[] */
    protected $processLists = [];

    protected $myArgs;

    protected $myPid;

    public function __construct($instanceUuid)
    {
        $this->instanceUuid = $instanceUuid;
        $this->initialize();
    }

    public function getInstanceUuid()
    {
        return $this->instanceUuid;
    }

    public function getPropertiesToInsert()
    {
        return $this->getPropertiesToUpdate() + (array) $this->info;
    }

    public function getPropertiesToUpdate()
    {
        return [
            'ts_last_update' => static::timestampWithMilliseconds(),
            'ts_stopped'     => null,
            'process_info'   => JsonString::encode($this->collectProcessInfo()),
        ];
    }

    protected static function timestampWithMilliseconds()
    {
        $mTime = explode(' ', microtime());

        return (int) round($mTime[0] * 1000) + (int) $mTime[1] * 1000;
    }

    public function set($property, $value)
    {
        if (\property_exists($this->info, $property)) {
            $this->info->$property = $value;
        } else {
            throw new \InvalidArgumentException("Trying to set invalid daemon info property: $property");
        }
    }

    public function registerProcessList(ProcessList $list)
    {
        $refresh = function (Process $process) {
            $this->refreshProcessInfo();
        };
        $list->on(ProcessList::ON_ATTACHED, $refresh)->on(ProcessList::ON_DETACHED, $refresh);
        $this->processLists[] = $list;

        return $this;
    }

    protected function refreshProcessInfo()
    {
        $this->set('process_info', JsonString::encode($this->collectProcessInfo()));
    }

    protected function collectProcessInfo()
    {
        $info = (object) [$this->myPid => (object) [
            'command' => implode(' ', $this->myArgs),
            'running' => true,
            'memory'  => Memory::getUsageForPid($this->myPid)
        ]];

        foreach ($this->processLists as $processList) {
            foreach ($processList as $process) {
                if ($pid = $process->getPid()) {
                    $info->$pid = ProcessInfo::forProcess($process);
                }
            }
        }

        return $info;
    }

    protected function initialize()
    {
        global $argv;
        CliProcess::getInitialCwd();
        $this->myArgs = $argv;
        $this->myPid = \posix_getpid();
        if (isset($_SERVER['_'])) {
            $self = $_SERVER['_'];
        } else {
            // Process does a better job, but want the relative path (if such)
            $self = $_SERVER['PHP_SELF'];
        }
        $this->info = (object) [
            'instance_uuid_hex'    => $this->instanceUuid,
            'schema_version'       => null,
            'fqdn'                 => \gethostbyaddr(\gethostbyname(\gethostname())),
            'username'             => \posix_getpwuid(\posix_geteuid())['name'],
            'pid'                  => \posix_getpid(),
            'binary_path'          => $self,
            'binary_realpath'      => CliProcess::getBinaryPath(),
            'php_binary_path'      => PHP_BINARY,
            'php_binary_realpath'  => \realpath(PHP_BINARY), // TODO: useless?
            'php_version'          => \phpversion(),
            'php_integer_size'     => PHP_INT_SIZE,
            'running_with_systemd' => 'n',
            'ts_started'           => (int) ((float) $_SERVER['REQUEST_TIME_FLOAT'] * 1000),
            'ts_stopped'           => null,
            'process_info'         => null,
        ];
    }
}
/*

+----------------------+----------------------+------+-----+---------+-------+
| Field                | Type                 | Null | Key | Default | Extra |
+----------------------+----------------------+------+-----+---------+-------+
| instance_uuid_hex    | varchar(32)          | NO   | PRI | NULL    |       |
| schema_version       | smallint(5) unsigned | NO   |     | NULL    |       |
| fqdn                 | varchar(255)         | NO   |     | NULL    |       |
| username             | varchar(64)          | NO   |     | NULL    |       |
| pid                  | int(10) unsigned     | NO   |     | NULL    |       |
| binary_path          | varchar(128)         | NO   |     | NULL    |       |
| binary_realpath      | varchar(128)         | NO   |     | NULL    |       |
| php_binary_path      | varchar(128)         | NO   |     | NULL    |       |
| php_binary_realpath  | varchar(128)         | NO   |     | NULL    |       |
| php_version          | varchar(64)          | NO   |     | NULL    |       |
| php_integer_size     | smallint(6)          | NO   |     | NULL    |       |
| running_with_systemd | enum('y','n')        | NO   |     | NULL    |       |
| ts_started           | bigint(20)           | NO   |     | NULL    |       |
| ts_stopped           | bigint(20)           | YES  |     | NULL    |       |
| ts_last_modification | bigint(20)           | YES  |     | NULL    |       |
| ts_last_update       | bigint(20)           | YES  |     | NULL    |       |
| process_info         | mediumtext           | NO   |     | NULL    |       |
+----------------------+----------------------+------+-----+---------+-------+


 */