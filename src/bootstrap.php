<?php
declare(strict_types=1);
const ROOT = __DIR__ . '/..';
date_default_timezone_set('UTC');
foreach (is_file(ROOT.'/.env') ? file(ROOT.'/.env', FILE_IGNORE_NEW_LINES|FILE_SKIP_EMPTY_LINES) : [] as $line) {
    if (str_starts_with(trim($line), '#') || !str_contains($line, '=')) continue;
    [$k,$v] = explode('=', $line, 2);
    if (getenv(trim($k)) === false) putenv(trim($k).'='.trim($v));
}
function env(string $key, string $default=''): string { return getenv($key) === false ? $default : (string)getenv($key); }
function storage(string $path=''): string { return ROOT.'/storage/'.$path; }
foreach (['','sessions','uploads','mail','logs','qa','private'] as $dir) if (!is_dir(storage($dir))) mkdir(storage($dir),0700,true);
require __DIR__.'/db.php';
require __DIR__.'/security.php';
require __DIR__.'/domain.php';
require __DIR__.'/citation.php';
require __DIR__.'/research-requirements.php';
require __DIR__.'/submissions.php';
require __DIR__.'/files.php';
require __DIR__.'/prompt.php';
require __DIR__.'/backup.php';
require __DIR__.'/workflow.php';
require __DIR__.'/workflow-routes.php';
