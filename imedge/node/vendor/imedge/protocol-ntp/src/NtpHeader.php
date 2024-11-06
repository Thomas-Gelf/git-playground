<?php

namespace IMEdge\Protocol\NTP;

use JsonSerializable;
use RuntimeException;
use stdClass;

class NtpHeader implements JsonSerializable
{
    protected const EMPTY_32 = "\x00\x00\x00\x00";
    protected const EMPTY_64 = "\x00\x00\x00\x00\x00\x00\x00\x00";

    public NtpLeapIndicator $leapIndicator = NtpLeapIndicator::NO_WARNING;
    public ?NtpStratum $stratum = null;

    /**
     * 8-bit signed integer representing the maximum interval between successive messages, in log2 seconds
     *
     * Suggested default limits for minimum and maximum poll intervals are 6 and 10, respectively.
     */
    public int $pollInterval = 0;
    /**
     * 8-bit signed integer representing the precision of the system clock, in log2 seconds
     *
     * For instance, a value of -18 corresponds to a precision of about one microsecond.
     * The precision can be determined when the service first starts up as the minimum
     * time of several iterations to read the system clock.
     */
    public int $precision = 0;
    // Hint: root is a stratum 1 server
    /**
     * Total round-trip delay to the reference clock, in NTP short format
     */
    public ?NtpShortFormat $rootDelay = null;
    /**
     * Total dispersion to the reference clock, in NTP short format
     */
    public ?NtpShortFormat $rootDispersion = null;

    /** Identifier of the particular reference source */
    public string $referenceIdentifier = '';

    /** Local time at which the local clock was last set or corrected */
    public ?NtpTimestampFormat $referenceTimestamp = null;
    /** local time at which the request departed from the client for the service host */
    public ?NtpTimestampFormat $originateTimestamp = null;
    /** Local time at which the reply departed from the service host for the client */
    public ?NtpTimestampFormat $receiveTimestamp = null;
    /** Local time at which the reply departed from the service host for the client */
    public ?NtpTimestampFormat $transmitTimestamp = null;

    /** @internal */
    public bool $isIpv6 = false;

    public function __construct(
        public readonly NtpAssociationMode $ntpMode,
        public readonly NtpVersion $version,
    ) {
    }

    public static function parse(string $binary): NtpHeader
    {
        $res = unpack(
            'abyte1/Cstratum/Cpoll/cprecision/n2delay/n2disp/a4ident/N2Tref/N2Torg/N2Trcv/N2Ttrans',
            $binary
        );
        if ($res === false) {
            throw new RuntimeException('Failed to parse NTP header');
        }
        // Hint: there might be other fields, optional 32bit Key Identifier, optional 128bit Message Digest
        $self = new NtpHeader(NtpAssociationMode::parse($res['byte1']), NtpVersion::parse($res['byte1']));
        $self->leapIndicator = NtpLeapIndicator::parse($res['byte1']);
        $self->stratum = NtpStratum::from($res['stratum']);
        $self->pollInterval = $res['poll'];
        $self->precision = $res['precision'];
        $self->rootDelay = new NtpShortFormat($res['delay1'], $res['delay2']);
        $self->rootDispersion = new NtpShortFormat($res['disp1'], $res['disp2']);
        $self->referenceIdentifier = rtrim($res['ident'], "\0");
        $self->referenceTimestamp = NtpTimestampFormat::optional($res['Tref1'], $res['Tref2']);
        $self->originateTimestamp = NtpTimestampFormat::optional($res['Torg1'], $res['Torg2']);
        $self->receiveTimestamp = NtpTimestampFormat::optional($res['Trcv1'], $res['Trcv2']);
        $self->transmitTimestamp = NtpTimestampFormat::optional($res['Ttrans1'], $res['Ttrans2']);

        return $self;
    }

    public function __toString(): string
    {
        return chr($this->leapIndicator->value << 6 | $this->version->value << 3 | $this->ntpMode->value)
            . ($this->stratum?->toPacket() ?? "\x00")
            . pack('C', $this->pollInterval)
            . pack('c', $this->precision)
            . ($this->rootDispersion ?? self::EMPTY_32)
            . ($this->rootDispersion ?? self::EMPTY_32)
            . str_pad($this->referenceIdentifier, 4, "\x00")
            . ($this->referenceTimestamp ?? self::EMPTY_64)
            . ($this->originateTimestamp ?? self::EMPTY_64)
            . ($this->receiveTimestamp ?? self::EMPTY_64)
            . ($this->transmitTimestamp ?? self::EMPTY_64)
            ;
    }

    public function jsonSerialize(): stdClass
    {
        $timezone = new \DateTimeZone(date_default_timezone_get());

        return (object) [
            'ntpVersion'      => $this->version->value,
            'associationMode' => $this->ntpMode->name,
            'leapIndicator'   => $this->leapIndicator->name,
            'stratum'         => $this->stratum?->value,
            'pollInterval'    => $this->pollInterval,
            'precision'       => $this->precision,
            'precisionMs'     => 2 ** $this->precision * 1_000,
            'rootDelay'       => $this->rootDelay?->toSeconds(),
            'rootDispersion'  => $this->rootDispersion?->toSeconds(),
            'referenceIdentifier' => NtpReferenceIdentifierInfo::describe($this, $this->isIpv6),
            'referenceTimestamp'  => $this->referenceTimestamp?->toDateTime($timezone),
            'originateTimestamp'  => $this->originateTimestamp?->toDateTime($timezone),
            'receiveTimestamp'    => $this->receiveTimestamp?->toDateTime($timezone),
            'transmitTimestamp'   => $this->transmitTimestamp?->toDateTime($timezone),
        ];
    }
}
