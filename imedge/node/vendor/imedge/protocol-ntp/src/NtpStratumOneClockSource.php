<?php

namespace IMEdge\Protocol\NTP;

enum NtpStratumOneClockSource: string
{
    case GOES = 'GOES';
    case GPS = 'GPS';
    case GAL = 'GAL';
    case PPS = 'PPS';
    case IRIG = 'IRIG';
    case WWVB = 'WWVB';
    case DCF = 'DCF';
    case DCF_A = 'DCFa'; // amplitude modulated
    case DCF_P = 'DCFp'; // phase modulated
    case HBG = 'HBG';
    case MSF = 'MSF';
    case JJY = 'JJY';
    case LORC = 'LORC';
    case TDF = 'TDF';
    case CHU = 'CHU';
    case WWV = 'WWV';
    case WWVH = 'WWVH';
    case NIST = 'NIST';
    case ACTS = 'ACTS';
    case USNO = 'USNO';
    case PTB = 'PTB';

    // RFC 4330:
    case LOCL = 'LOCL';
    case OMEG = 'OMEG';
    case CESM = 'CESM';
    case RBDM = 'RBDM';

    public function getDescription(): string
    {
        // RFC5905, Figure 12: Reference Identifiers
        // https://www.iana.org/assignments/ntp-parameters/ntp-parameters.xhtml
        // RFC 4330
        return match ($this) {
            self::GOES => 'Geosynchronous Orbit Environment Satellite',
            self::GPS  => 'Global Position System',
            self::GAL  => 'Galileo Positioning System',
            self::PPS  => 'Generic pulse-per-second',// calibrated quartz clock or other pulse-per-second source
            self::IRIG => 'Inter-Range Instrumentation Group',
            self::WWVB => 'LF Radio WWVB Ft. Collins, CO 60 kHz', // Boulder (US) Radio 60 kHz
            self::DCF  => 'LF Radio DCF77 Mainflingen, DE 77.5 kHz', // Mainflingen (Germany) Radio 77.5 kHz
            self::DCF_A => 'LF Radio DCF77 Mainflingen, DE 77.5 kHz, amplitude modulated',
            self::DCF_P => 'LF Radio DCF77 Mainflingen, DE 77.5 kHz, phase modulated',
            // Hint: Check Meinberg documentation, they seem to have other suffixes like 'i'
            self::HBG  => 'LF Radio HBG Prangins, HB 75 kHz',
            self::MSF  => 'LF Radio MSF Anthorn, UK 60 kHz', // Rugby (UK) Radio 60 kHz
            self::JJY  => 'LF Radio JJY Fukushima, JP 40 kHz, Saga, JP 60 kHz',
            self::LORC => 'MF Radio LORAN C station, 100 kHz', // LORAN-C radionavigation system
            self::TDF  => 'MF Radio Allouis, FR 162 kHz', // Allouis (France) Radio 164 kHz
            self::CHU  => 'HF Radio CHU Ottawa, Ontario', // Ottawa (Canada) Radio 3330, 7335, 14670 kHz
            self::WWV  => 'HF Radio WWV Ft. Collins, CO', // Ft. Collins (US) Radio 2.5, 5, 10, 15, 20 MHz
            self::WWVH => 'HF Radio WWVH Kauai, HI', // Kauai Hawaii (US) Radio 2.5, 5, 10, 15 MHz
            self::NIST => 'NIST telephone modem',
            self::ACTS => 'NIST telephone modem',
            self::USNO => 'USNO telephone modem',
            // PTB (Germany) telephone modem service, laut RFC: 'European telephone modem'?
            self::PTB  => 'Physikalisch-Technische Bundesanstalt',

            // RFC 4330
            self::LOCL => 'uncalibrated local clock', // ...used as a primary reference for a subnet without
                                                      // external means of synchronization'
            self::OMEG => 'OMEGA radionavigation system',
            self::CESM => 'Calibrated Cesium clock',
            self::RBDM => 'Calibrated Rubidium clock',
        };
    }
}
