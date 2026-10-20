<?php
/** Private disaster-recovery bundle. Run from CLI; never expose this file as a web route. */
declare(strict_types=1);
if (PHP_SAPI !== 'cli') { http_response_code(404); exit(1); }
require __DIR__.'/../src/bootstrap.php';

try {
    $_SESSION['admin'] = 1;
    $backup = create_backup();
    $directory = storage('backups');
    $name = 'bundle-'.gmdate('Ymd-His').'-'.substr(uid(), 0, 8).'.tar.gz';
    $temporary = $directory.'/'.$name.'.partial';
    $target = $directory.'/'.$name;
    if (!is_file(ROOT.'/.env')) throw new RuntimeException('Private environment file is required.');
    $command = ['tar', '-czf', $temporary, '-C', ROOT, '.env', 'storage/uploads', 'storage/private', 'storage/backups/'.$backup['name']];
    $process = proc_open($command, [0=>['pipe','r'], 1=>['pipe','w'], 2=>['pipe','w']], $pipes);
    if (!is_resource($process)) throw new RuntimeException('Could not start archive creation.');
    fclose($pipes[0]);
    stream_get_contents($pipes[1]); fclose($pipes[1]);
    $error = stream_get_contents($pipes[2]); fclose($pipes[2]);
    $status = proc_close($process);
    if ($status !== 0 || !is_file($temporary) || filesize($temporary) < 1) {
        if (is_file($temporary)) unlink($temporary);
        throw new RuntimeException('Archive creation failed; SQL export retained. '.$error);
    }
    chmod($temporary, 0600);
    if (!rename($temporary, $target)) throw new RuntimeException('Could not finalize backup bundle.');
    $bundles = glob($directory.'/bundle-*.tar.gz');
    rsort($bundles, SORT_STRING);
    foreach (array_slice($bundles, 14) as $old) {
        if (is_file($old) && !is_link($old) && !unlink($old)) throw new RuntimeException('Could not rotate old generated bundle.');
    }
    // Only this run's SQL export is removed after it is safely included in the bundle.
    if (!unlink($directory.'/'.$backup['name'])) throw new RuntimeException('Bundle saved; standalone export cleanup failed.');
    set_setting('backup_bundle_at', now());
    echo 'Private backup bundle saved: '.$name.' ('.filesize($target).' bytes). Latest 14 bundles retained.'.PHP_EOL;
} catch (Throwable $error) {
    fwrite(STDERR, $error->getMessage().PHP_EOL);
    exit(1);
}