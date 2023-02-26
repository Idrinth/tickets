<?php

namespace De\Idrinth\Tickets\Resources;

use ScssPhp\ScssPhp\Compiler;

class Styles
{
    private Compiler $scss;

    public function __construct(Compiler $scss) {
        $this->scss = $scss;
    }
    public function run()
    {
        header('Content-type: text/css');
        return $this->scss
            ->compileString('@import("../styles/styles.scss");', dirname(__DIR__,2))
            ->getCss();
    }
}
