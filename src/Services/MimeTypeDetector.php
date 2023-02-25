<?php

namespace De\Idrinth\Tickets\Services;

use BrandEmbassy\FileTypeDetector\Detector;

class MimeTypeDetector
{
    public static function detect(string $data): string
    {
        $tmp = dirname(__DIR__, 2) . '/cache/mime-' . microtime(true) . md5($data);
        if (!file_put_contents($tmp, $data)) {
            error_log('Couldn\'t set data for mime type detection.');
            return false;
        }
        $out = Detector::detectByContent($tmp);        
        unlink($tmp);
        if (!is_array($out)) {
            error_log('Couldn\'t detect mime type.');
            return 'application/octet-stream';
        }
        return $out[2] ?? 'application/octet-stream';
    }
}
