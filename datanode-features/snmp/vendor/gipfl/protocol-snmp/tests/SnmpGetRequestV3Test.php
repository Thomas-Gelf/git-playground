<?php

namespace gipfl\Tests\Protocol\Snmp;

use gipfl\Protocol\Snmp\GetRequest;
use gipfl\Protocol\Snmp\Snmpv3Header;
use gipfl\Protocol\Snmp\SnmpV3Message;
use gipfl\Protocol\Snmp\Snmpv3ScopedPdu;
use gipfl\Protocol\Snmp\Usm\AuthKey;
use PHPUnit\Framework\TestCase;

class SnmpGetRequestV3Test extends TestCase
{
    public function xtestIt()
    {
        $message = new SnmpV3Message(
            new Snmpv3Header(123),
            '',
            new Snmpv3ScopedPdu(
                new GetRequest([], 123),
                '',
                ''
            )
        );
    }

    public function testMd5(): void
    {
        $this->checkAlgo(
            'md5',
            '9f af 32 83 88 4e 92 83 4e bc 98 47 d8 ed d9 63',
            '52 6f 5e ed 9f cc e2 6f 89 64 c2 93 07 87 d8 2b'
        );
    }
    public function testSha1(): void
    {
        $this->checkAlgo(
            'sha1',
            '9f b5 cc 03 81 49 7b 37 93 52 89 39 ff 78 8d 5d 79 14 52 11',
            '66 95 fe bc 92 88 e3 62 82 23 5f c7 15 1f 12 84 97 b3 8f 3f'
        );
    }

    public function testSha224(): void
    {
        $this->checkAlgo(
            'sha224',
            '28 2a 58 67 ee 9a ac 63 9a d5 9d f9 57 2c 7d 3a c0 fb c1 3a 90 5b 6d f0 7d bb f0 0b',
            '0b d8 82 7c 6e 29 f8 06 5e 08 e0 92 37 f1 77 e4 10 f6 9b 90 e1 78 2b e6 82 07 56 74'
        );
    }

    public function testSha256(): void
    {
        $this->checkAlgo(
            'sha256',
            'ab 51 01 4d 1e 07 7f 60 17 df 2b 12 be e5 f5 aa 72 99 31 77 e9 bb 56 9c 4d ff 5a 4c a0 b4 af ac',
            '89 82 e0 e5 49 e8 66 db 36 1a 6b 62 5d 84 cc cc 11 16 2d 45 3e e8 ce 3a 64 45 c2 d6 77 6f 0f 8b'
        );
    }

    public function testSha384(): void
    {
        $this->checkAlgo(
            'sha384',
            'e0 6e cc df 2c 68 a0 6e d0 34 72 3c 9c 26 e0 db 3b 66 9e 1e 2e fe d4 91'
            . ' 50 b5 53 77 a2 e9 8f 38 3c 86 fb 83 68 57 44 46 54 b2 87 c9 3f 51 ff 64',
            '3b 29 8f 16 16 4a 11 18 42 79 d5 43 2b f1 69 e2 d2 a4 83 07 de 02 b3 d3'
            . ' f7 e2 b4 f3 6e b6 f0 45 5a 53 68 9a 39 37 ee a0 73 19 a6 33 d2 cc ba 78'
        );
    }

    public function testSha512(): void
    {
        $this->checkAlgo(
            'sha512',
            '7e 43 96 de 5a ad c7 7b e8 53 81 9b 98 c9 40 62 65 b3 a9 c3 7c c3 17 65 69 84 7a 4e 4f 6f ba 63'
            . ' dd 3a 73 d0 49 24 d3 1a 63 f9 5a 60 1f 93 85 af 6b e4 ed 1b 37 f8 7d 04 0f 7c 6e d6 f8 d3 8a 91',
            '22 a5 a3 6c ed fc c0 85 80 7a 12 8d 7b c6 c2 38 21 67 ad 6c 0d bc 5f df f8 56 74 0f 3d 84 c0 99'
            . ' ad 1e a8 7a 8d b0 96 71 4d 97 88 bd 54 40 47 c9 02 1e 42 29 ce 27 e4 c0 a6 92 50 ad fc ff bb 0b'
        );
    }

    protected function checkAlgo(string $algo, string $intermediate, string $expected): void
    {
        $pass = 'maplesyrup';
        $engineId = self::unHex('00 00 00 00 00 00 00 00 00 00 00 02');
        $this->assertEquals($intermediate, self::niceHex(AuthKey::intermediate($algo, $pass)));
        $this->assertEquals($expected, self::niceHex(AuthKey::hash($algo, $pass, $engineId)));
    }

    protected static function unHex(string $string): string
    {
        return hex2bin(str_replace(' ', '', $string));
    }

    protected static function niceHex(string $string): string
    {
        return implode(' ', array_map(bin2hex(...), str_split($string)));
    }

    public function xtestParse()
    {
        $string = '303e0201033011020475478895020300ffe30401040201030410300e0400020100020100040004000400301404000400a00e02042ceb03d60201000201003000';
    }
}
