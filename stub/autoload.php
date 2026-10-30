<?php

declare(strict_types=1);

// Minimal autoloader so the stub runs with plain `php`, without `composer install`.
spl_autoload_register(static function (string $class): void {
    $prefix = 'MailChannelsStub\\';
    if (str_starts_with($class, $prefix)) {
        $file = __DIR__.'/src/'.str_replace('\\', '/', substr($class, strlen($prefix))).'.php';
        if (is_file($file)) {
            require $file;
        }
    }
});
