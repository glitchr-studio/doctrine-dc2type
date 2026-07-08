<?php

// The package is either checked out standalone (CI: own vendor/) or installed
// inside a host application's vendor/glitchr/doctrine-dc2type (dev workflow).
foreach ([
    __DIR__ . '/../vendor/autoload.php',
    __DIR__ . '/../../../autoload.php',
] as $autoload) {
    if (file_exists($autoload)) {
        $loader = require $autoload;

        // A host application's autoloader does not know about this package's
        // autoload-dev section, so register the test namespace ourselves.
        if ($loader instanceof \Composer\Autoload\ClassLoader) {
            $loader->addPsr4('Doctrine\\Composer\\Tests\\', __DIR__);
        }

        return;
    }
}

throw new RuntimeException('No composer autoloader found. Run "composer install" first.');
