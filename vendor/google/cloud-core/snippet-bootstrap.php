<?php

use DG\BypassFinals;
use Google\Cloud\Core\Testing\TestHelpers;

TestHelpers::snippetBootstrap();

date_default_timezone_set('UTC');



if (class_exists(BypassFinals::class)) {
    BypassFinals::enable();
}
