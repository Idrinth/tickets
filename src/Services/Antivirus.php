<?php

namespace De\Idrinth\Tickets\Services;

use Appwrite\ClamAV\Pipe;

class Antivirus
{
    private Pipe $clam;

    public function __construct(Pipe $clam)
    {
        $this->clam = $clam;
    }

    public function sclean(string $data): bool
    {
        $tmp = dirname(__DIR__, 2) . '/cache/clamav-' . microtime(true) . md5($data);
        if (!file_put_contents($tmp, $data)) {
            error_log('Couldn\'t set data for ClamAV.');
            return false;
        }
        $return = $this->fclean($tmp);
        unlink($tmp);
        return $return;
    }

    public function fclean(string $file): bool
    {
        $tmp = dirname(__DIR__, 2) . '/cache/clamav-' . microtime(true) . md5($file);
        copy($file, $tmp);
        $return = $this->clean($tmp);
        unlink($tmp);
        return $return;
    }

    private function clean(string $file): bool
    {
        if (!$this->clam->ping()) {
            error_log('ClamAV not found!');
            return false;
        }
        if (!chmod($file, 0777)) {
            error_log('Couldn\'t change access for ClamAV.');
            return false;
        }
        $return = $this->clam->fileScan($file);
        if (!$return) {
            error_log('ClamAV found an issue with an uploaded file.');
        }
        return $return;
    }
}
