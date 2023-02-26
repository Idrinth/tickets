<?php

namespace De\Idrinth\Tickets\Services;

use BrandEmbassy\FileTypeDetector\Detector;
use BrandEmbassy\FileTypeDetector\FileInfo;

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
        if (!($out instanceof FileInfo)) {
            error_log('Couldn\'t detect mime type.');
            return 'application/octet-stream';
        }
        return $out->getMimeType();
    }
}
