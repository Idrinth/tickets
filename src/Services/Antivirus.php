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
            return false;
        }
        $tmp = tempnam(sys_get_temp_dir(), 'clamav');
        file_put_contents($tmp, $data);
        $return = $this->clam->fileScan($tmp);
        unlink($tmp);
        return $return;
    }
}
