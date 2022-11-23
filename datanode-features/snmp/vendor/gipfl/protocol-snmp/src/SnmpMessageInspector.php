<?php

namespace gipfl\Protocol\Snmp;

use gipfl\Protocol\Snmp\Usm\UserBasedSecurityModel;

class SnmpMessageInspector
{
    public static function dump(SnmpMessage $message): void
    {
        echo static::getDump($message);
    }

    public static function getDump(SnmpMessage $message): string
    {
        $result = sprintf("Version: %s\n", $message->getVersion());
        if ($message instanceof SnmpV1Message) {
            $result .= sprintf("Community: %s\n", $message->community);
        } elseif ($message instanceof SnmpV3Message) {
            if ($message->securityParameters instanceof UserBasedSecurityModel) {
                $result .= sprintf("Engine time: %s\n", $message->securityParameters->engineTime);
                $result .= sprintf("Engine ID: %s\n", $message->securityParameters->engineId);
            }
        }

        foreach ($message->getPdu()->varBinds as $varBind) {
            $result .= sprintf(
                "%s: %s\n",
                $varBind->oid,
                $varBind->value->getReadableValue()
            );
        }

        return $result;
    }
}
