<?php
require __DIR__.'/bootstrap.php';
function input_denied(callable $operation): void {
    try { $operation(); } catch (HttpError $error) { if (in_array($error->status, [403, 422], true)) return; throw $error; }
    throw new RuntimeException('Malformed input was accepted.');
}
$_SESSION = [];
$_POST = [];
input_denied(fn() => check_csrf());
$_SESSION['csrf'] = 'known-test-token';
$_POST['csrf'] = ['known-test-token'];
input_denied(fn() => check_csrf());
$_POST['csrf'] = 'known-test-token';
check_csrf();
input_denied(fn() => text_field(['title' => "invalid\xFF"], 'title'));
input_denied(fn() => text_field(['title' => ['array']], 'title'));
if (text_field(['title' => 'Valid Unicode — café'], 'title') !== 'Valid Unicode — café') throw new RuntimeException('Unicode input changed.');
$memory = fopen('php://memory', 'w+b');
backup_write($memory, "Unicode backup — café\n");
rewind($memory);
if (stream_get_contents($memory) !== "Unicode backup — café\n") throw new RuntimeException('Backup bytes changed.');
fclose($memory);
input_denied(fn() => validate_uploads(['name' => [['nested']], 'error' => [UPLOAD_ERR_OK], 'tmp_name' => [['nested']]]));
input_denied(fn() => validate_uploads(['name' => ['file.txt'], 'error' => ['0'], 'tmp_name' => ['unused']]));
class FailedBackupStream {
    public $context;
    public function stream_open($path, $mode, $options, &$openedPath): bool { return true; }
    public function stream_write($data): int { return 0; }
}
stream_wrapper_register('failedbackup', FailedBackupStream::class);
$failed = fopen('failedbackup://test', 'wb');
$rejected = false;
try { backup_write($failed, 'must not silently succeed'); } catch (RuntimeException) { $rejected = true; }
fclose($failed);
stream_wrapper_unregister('failedbackup');
if (!$rejected) throw new RuntimeException('Failed backup write was accepted.');
echo "10 input and backup-write boundary checks passed.\n";