<?php

namespace IMEdge\Protocol\NTP;

/**
 * NTP Extension Field Types
 *
 * Source: https://www.iana.org/assignments/ntp-parameters/ntp-parameters.xhtml
 */
enum NtpExtensionFieldType: string
{
    case NO_OP = "\x00\x02";
    case UNIQUE_ID = "\x01\x04";
    case NTS_COOKIE = "\x02\x04";
    case NTS_COOKIE_PLACEHOLDER = "\x03\x04";
    case NTS_AUTHENTICATOR = "\x04\x04";
    case NO_OP_RESPONSE = "\x80\x02";
    case NO_OP_ERROR_RESPONSE = "\xc0\x02";
    case ASSOCIATION_MESSAGE_REQUEST = "\x01\x02";
    case ASSOCIATION_MESSAGE_RESPONSE = "\x81\x02";
    case ASSOCIATION_MESSAGE_ERROR_RESPONSE = "\xc1\x02";
    case CERTIFICATE_MESSAGE_REQUEST = "\x02\x02";
    case CERTIFICATE_MESSAGE_RESPONSE = "\x82\x02";
    case CERTIFICATE_MESSAGE_ERROR_RESPONSE = "\xc2\x02";
    case COOKIE_MESSAGE_REQUEST = "\x03\x02";
    case COOKIE_MESSAGE_RESPONSE = "\x83\x02";
    case COOKIE_MESSAGE_ERROR_RESPONSE = "\xc3\x02";
    case AUTOKEY_MESSAGE_REQUEST = "\x04\x02";
    case AUTOKEY_MESSAGE_RESPONSE = "\x84\x02";
    case AUTOKEY_MESSAGE_ERROR_RESPONSE = "\xc4\x02";
    case LEAP_SECONDS_MESSAGE_REQUEST = "\x05\x02";
    case LEAP_SECONDS_MESSAGE_RESPONSE = "\x85\x02";
    case LEAP_SECONDS_MESSAGE_ERROR_RESPONSE = "\xc5\x02";
    case SIGN_MESSAGE_REQUEST = "\x06\x02";
    case SIGN_RESPONSE = "\x86\x02";
    case SIGN_ERROR_RESPONSE = "\xc6\x02";
    case IFF_MESSAGE_REQUEST = "\x07\x02";
    case IFF_RESPONSE = "\x87\x02";
    case IFF_ERROR_RESPONSE = "\xc7\x02";
    case GQ_MESSAGE_REQUEST = "\x08\x02";
    case GQ_RESPONSE = "\x88\x02";
    case GQ_ERROR_RESPONSE = "\xc8\x02";
    case MV_MESSAGE_REQUEST = "\x09\x02";
    case MV_RESPONSE = "\x89\x02";
    case MV_ERROR_RESPONSE = "\xc9\x02";
    case CHECKSUM_COMPLEMENT = "\x20\x05";

    public function getDescription(): string
    {
        return match ($this) {
            // RFC5905, if not commented otherwise
            self::NO_OP => 'No-Operation Request',
            self::UNIQUE_ID => 'Unique Identifier',
            self::NTS_COOKIE => 'NTS Cookie', // RFC8915, Section 5.3
            self::NTS_COOKIE_PLACEHOLDER => 'NTS Cookie Placeholder', // RFC8915, Section 5.4
            self::NTS_AUTHENTICATOR => 'NTS Authenticator and Encrypted Extension Fields',  // RFC8915, Section 5.6
            self::NO_OP_RESPONSE => 'No-Operation Response',
            self::NO_OP_ERROR_RESPONSE => 'No-Operation Error Response',
            self::ASSOCIATION_MESSAGE_REQUEST => 'Association Message Request',
            self::ASSOCIATION_MESSAGE_RESPONSE => 'Association Message Response',
            self::ASSOCIATION_MESSAGE_ERROR_RESPONSE => 'Association Message Error Response',
            self::CERTIFICATE_MESSAGE_REQUEST => 'Certificate Message Request',
            self::CERTIFICATE_MESSAGE_RESPONSE => 'Certificate Message Response',
            self::CERTIFICATE_MESSAGE_ERROR_RESPONSE => 'Certificate Message Error Response',
            self::COOKIE_MESSAGE_REQUEST => 'Cookie Message Request',
            self::COOKIE_MESSAGE_RESPONSE => 'Cookie Message Response',
            self::COOKIE_MESSAGE_ERROR_RESPONSE => 'Cookie Message Error Response',
            self::AUTOKEY_MESSAGE_REQUEST => 'Autokey Message Request',
            self::AUTOKEY_MESSAGE_RESPONSE => 'Autokey Message Response',
            self::AUTOKEY_MESSAGE_ERROR_RESPONSE => 'Autokey Message Error Response',
            self::LEAP_SECONDS_MESSAGE_REQUEST => 'Leapseconds Message Request',
            self::LEAP_SECONDS_MESSAGE_RESPONSE => 'Leapseconds Message Response',
            self::LEAP_SECONDS_MESSAGE_ERROR_RESPONSE => 'Leapseconds Message Error Response',
            self::SIGN_MESSAGE_REQUEST => 'Sign Message Request',
            self::SIGN_RESPONSE => 'Sign Message Response',
            self::SIGN_ERROR_RESPONSE => 'Sign Message Error Response',
            self::IFF_MESSAGE_REQUEST => 'IFF Identity Message Request',
            self::IFF_RESPONSE => 'IFF Identity Message Response',
            self::IFF_ERROR_RESPONSE => 'IFF Identity Message Error Response',
            self::GQ_MESSAGE_REQUEST => 'GQ Identity Message Request',
            self::GQ_RESPONSE => 'GQ Identity Message Response',
            self::GQ_ERROR_RESPONSE => 'GQ Identity Message Error Response',
            self::MV_MESSAGE_REQUEST => 'MV Identity Message Request',
            self::MV_RESPONSE => 'MV Identity Message Response',
            self::MV_ERROR_RESPONSE => 'MV Identity Message Error Response',
            self::CHECKSUM_COMPLEMENT => 'Checksum Complement', // RFC7821
        };
    }
}
