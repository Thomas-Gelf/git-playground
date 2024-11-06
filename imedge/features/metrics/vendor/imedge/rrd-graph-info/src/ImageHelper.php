<?php

namespace IMEdge\RrdGraphInfo;

use InvalidArgumentException;

use function base64_encode;

class ImageHelper
{
    public static function createInlineImage(string $rawImage, string $contentType): string
    {
        if ($contentType === 'image/svg+xml') {
            // SVGs are valid UTF-8 and consume less space w/o base64
            return "data:$contentType;utf8," . self::prepareSvgDataString($rawImage);
        }

        return "data:$contentType;base64," . base64_encode($rawImage);
    }

    public static function getContentTypeForFormat(string $format): string
    {
        switch (strtoupper($format)) {
            case 'SVG':
                return 'image/svg+xml';
            case 'PNG':
                return 'image/png';
            case 'JSON':
            case 'JSONTIME':
                return 'application/json';
            case 'CSV':
            case 'SSV':
            case 'TSV':
                return 'text/csv';
            case 'XML':
            case 'XMLENUM':
                return 'application/xml';
            case 'PDF':
                return 'application/pdf';
            case 'EPS':
                return 'application/postscript';
            default:
                throw new InvalidArgumentException(
                    sprintf(
                        'Image format %s is not supported',
                        $format
                    )
                );
        }
    }

    /**
     * Removes newlines, quotes single quotes, then replaces double with single
     * quotes and finally only escapes a very few essential characters (<, >, #)
     *
     * @param string $svg
     * @return string
     */
    public static function prepareSvgDataString(string $svg): string
    {
        // Hint: as long as the Input is valid UTF8, there should no need for a mb-function here
        return str_replace([
            "\r",
            "\n",
            "'",
            '"',
            '<',
            '>',
            '#',
        ], [
            '',
            '',
            '%27', // urlencode("'"),
            "'",
            '%3C', // urlencode('<'),
            '%3E', // urlencode('>'),
            '%23', // urlencode('#'),
        ], $svg);
    }


    /**
     * Reverse operation for prepareSvgDataString()
     */
    public static function decodeSvgDataString(string $svg): string
    {
        return str_replace([
            '',
            '',
            '%27', // urlencode("'"),
            "'",
            '%3C', // urlencode('<'),
            '%3E', // urlencode('>'),
            '%23', // urlencode('#'),
        ], [
            "\r",
            "\n",
            "'",
            '"',
            '<',
            '>',
            '#',
        ], $svg);
    }
}
