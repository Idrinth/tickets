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

    public function clean(string $data): bool
    {
        if (!$this->clam->ping()) {
            error_log('ClamAV not found!');
            return false;
        }
        $tmp = sys_get_temp_dir() . '/clamav-' . microtime(true);
        if (!$tmp) {
            error_log('Couldn\'t prepare data for ClamAV.');
        }
        if (!file_put_contents($tmp, $data)) {
            error_log('Couldn\'t write data for ClamAV.');
            return false;
        }
        if (!chmod($tmp, 0777)) {
            error_log('Couldn\'t change access for ClamAV.');
            return false;
        }
        sleep(1);
        $return = $this->clam->fileScan($tmp);
        if (!$return) {
            error_log('ClamAV found an issue with an uploaded file.');
        }
        unlink($tmp);
        return $return;
    }
}
