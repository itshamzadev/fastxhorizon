<?php

// PHPUnit must never inherit the application's cached production/local
// configuration. The previous cached config caused tests to use MySQL instead
// of the SQLite settings declared in phpunit.xml.
$_SERVER['APP_ENV'] = 'testing';
$_ENV['APP_ENV'] = 'testing';
$_SERVER['DB_CONNECTION'] = 'sqlite';
$_ENV['DB_CONNECTION'] = 'sqlite';
$_SERVER['DB_DATABASE'] = ':memory:';
$_ENV['DB_DATABASE'] = ':memory:';
$_SERVER['CACHE_DRIVER'] = 'array';
$_ENV['CACHE_DRIVER'] = 'array';
$_SERVER['SESSION_DRIVER'] = 'array';
$_ENV['SESSION_DRIVER'] = 'array';
$_SERVER['QUEUE_CONNECTION'] = 'sync';
$_ENV['QUEUE_CONNECTION'] = 'sync';

putenv('APP_ENV=testing');
putenv('DB_CONNECTION=sqlite');
putenv('DB_DATABASE=:memory:');
putenv('CACHE_DRIVER=array');
putenv('SESSION_DRIVER=array');
putenv('QUEUE_CONNECTION=sync');

$configCache = dirname(__DIR__) . '/bootstrap/cache/config.php';
if (is_file($configCache) && !unlink($configCache)) {
    fwrite(STDERR, "Unable to remove cached application config before tests.\n");
    exit(1);
}

require dirname(__DIR__) . '/vendor/autoload.php';
