<?php
/** All database-backed fixtures must opt into a local development environment. */
require_once __DIR__.'/../src/bootstrap.php';
require_once __DIR__.'/supporting-fixture.php';
if (PHP_SAPI !== 'cli' || !in_array(env('APP_ENV'), ['local', 'test', 'testing'], true)
    || !in_array(strtolower(env('DB_HOST', '127.0.0.1')), ['127.0.0.1', 'localhost', '::1', 'db'], true)) {
    fwrite(STDERR, "Tests require CLI, APP_ENV=local/test/testing and a local development database.\n");
    exit(1);
}