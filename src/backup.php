<?php
/** Write every byte or fail; short writes must never be reported as a valid backup. */
function backup_write($handle, string $text): void {
    $offset = 0;
    $length = strlen($text);
    while ($offset < $length) {
        $written = fwrite($handle, substr($text, $offset));
        if ($written === false || $written === 0) {
            throw new RuntimeException('Backup write failed.');
        }
        $offset += $written;
    }
}

function create_backup(): array {
    require_admin();
    if (db()->inTransaction()) throw new RuntimeException('Start backups outside an existing transaction.');
    if (!is_dir(storage('backups'))) mkdir(storage('backups'), 0700);
    $name = 'database-'.gmdate('Ymd-His').'-'.substr(uid(), 0, 8).'.sql';
    $path = storage('backups/'.$name);
    $handle = fopen($path, 'xb');
    if (!$handle) fail(503, 'Could not create private backup.');
    try {
        db()->exec('SET TRANSACTION ISOLATION LEVEL REPEATABLE READ');
        db()->exec('START TRANSACTION WITH CONSISTENT SNAPSHOT');
        backup_write($handle, "-- Private LLM.Giving logical backup; contains confidential records\nSET NAMES utf8mb4;\nSET FOREIGN_KEY_CHECKS=0;\n");
        // Discover tables in the dedicated application database so new migrations cannot
        // silently disappear from backups (including rate-limit and archive-budget state).
        foreach (all("SHOW FULL TABLES WHERE Table_type = 'BASE TABLE'") as $entry) {
            $table = '`'.str_replace('`', '``', array_values($entry)[0]).'`';
            $ddl = one('SHOW CREATE TABLE '.$table);
            backup_write($handle, 'DROP TABLE IF EXISTS '.$table.";\n".$ddl['Create Table'].";\n");
            $rows = query('SELECT * FROM '.$table);
            while ($row = $rows->fetch()) {
                $values = array_map(fn($value) => $value === null ? 'NULL' : db()->quote((string)$value), array_values($row));
                backup_write($handle, 'INSERT INTO '.$table.' VALUES ('.implode(',', $values).");\n");
            }
        }
        foreach (all('SHOW TRIGGERS') as $trigger) {
            $nameSql = '`'.str_replace('`', '``', $trigger['Trigger']).'`';
            $ddl = one('SHOW CREATE TRIGGER '.$nameSql);
            // Remove the source database account so restoration can use its own account.
            $statement = preg_replace('/DEFINER=`(?:``|[^`])*`@`(?:``|[^`])*`\s+/', '', $ddl['SQL Original Statement']);
            backup_write($handle, $statement.";\n");
        }
        backup_write($handle, "SET FOREIGN_KEY_CHECKS=1;\n");
        if (!fflush($handle)) throw new RuntimeException('Backup flush failed.');
        if (!fclose($handle)) throw new RuntimeException('Backup close failed.');
        db()->commit();
        clearstatcache(true, $path);
        $bytes = filesize($path);
        set_setting('backup_last_at', now());
        set_setting('backup_last_bytes', (string)$bytes);
        return ['name' => $name, 'bytes' => $bytes];
    } catch (Throwable $error) {
        if (db()->inTransaction()) db()->rollBack();
        if (is_resource($handle)) fclose($handle);
        if (is_file($path)) unlink($path);
        throw $error;
    }
}