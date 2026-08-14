<?php

declare(strict_types=1);

/**
 * A standalone PSR-4 autoloader for installations without Composer.
 *
 * Composer is the recommended way to install this library, but plenty of PHP
 * projects are still deployed by copying a folder onto a server. Those users
 * can simply do:
 *
 * ```php
 * require_once __DIR__ . '/smsFreePHP/src/autoload.php';
 * ```
 *
 * If Composer *is* in use, this file is harmless: the class is already
 * loaded by then and the autoloader below never fires.
 */
spl_autoload_register(static function (string $class): void {
    $prefix = 'EdenOhana\\SmsFree\\';

    if (!str_starts_with($class, $prefix)) {
        return;
    }

    $relative = substr($class, strlen($prefix));
    $path = __DIR__ . \DIRECTORY_SEPARATOR . str_replace('\\', \DIRECTORY_SEPARATOR, $relative) . '.php';

    if (is_file($path)) {
        require_once $path;
    }
});
