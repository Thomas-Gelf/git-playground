<?php

namespace IMEdge\Protocol\NTP;

/**
 * Source:
 *
 * https://www.iana.org/assignments/ntp-parameters/ntp-parameters.xhtml
 *
 * Codes beginning with the character "X" are reserved for experimentation and
 * development. IANA cannot assign them
 */
enum KissOfDeath: string
{
    case ACST = 'ACST';
    case AUTH = 'AUTH';
    case AUTO = 'AUTO';
    case BCST = 'BCST';
    case CRYP = 'CRYP';
    case DENY = 'DENY';
    case DROP = 'DROP';
    case RSTR = 'RSTR';
    case INIT = 'INIT';
    case MCST = 'MCST';
    case NKEY = 'NKEY';
    case NTSN = 'NTSN';
    case RATE = 'RATE';
    case RMOT = 'RMOT';
    case STEP = 'STEP';

    public function getDescription(): string
    {
        return match ($this) {
            // RFC5905, if not commented otherwise
            self::ACST => 'The association belongs to a unicast server',
            self::AUTH => 'Server authentication failed',
            self::AUTO => 'Autokey sequence failed',
            self::BCST => 'The association belongs to a broadcast server',
            self::CRYP => 'Cryptographic authentication or identification failed',
            self::DENY => 'Access denied by remote server',
            self::DROP => 'Lost peer in symmetric mode',
            self::RSTR => 'Access denied due to local policy',
            self::INIT => 'The association has not yet synchronized for the first time',
            self::MCST => 'The association belongs to a dynamically discovered server ',
            self::NKEY => 'No key found. Either the key was never installed or is not trusted',
            self::NTSN => 'Network Time Security (NTS) negative-acknowledgment (NAK)', // RFC8915, Section 5.7
            self::RATE => 'Rate exceeded. The server has temporarily denied access because the client exceeded the rate'
                        . ' threshold',
            self::RMOT => 'Alteration of association from a remote host running ntpdc',
            self::STEP => 'A step change in system time has occurred, but the association has not yet resynchronized',
        };
    }
}
