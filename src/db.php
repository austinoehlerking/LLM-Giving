<?php
function db(): PDO {
    static $pdo;
    return $pdo ??= new PDO('mysql:host='.env('DB_HOST','127.0.0.1').';port='.env('DB_PORT','3318').';dbname='.env('DB_NAME','llm_giving').';charset=utf8mb4', env('DB_USER','llm_giving'), env('DB_PASSWORD'), [
        PDO::ATTR_ERRMODE=>PDO::ERRMODE_EXCEPTION, PDO::ATTR_DEFAULT_FETCH_MODE=>PDO::FETCH_ASSOC, PDO::ATTR_EMULATE_PREPARES=>false
    ]);
}
function query(string $sql, array $params=[]): PDOStatement { $s=db()->prepare($sql); $s->execute($params); return $s; }
function one(string $sql, array $params=[]): ?array { return query($sql,$params)->fetch() ?: null; }
function all(string $sql, array $params=[]): array { return query($sql,$params)->fetchAll(); }
function tx(callable $fn): mixed {
    if(db()->inTransaction()) return $fn();
    db()->beginTransaction(); $GLOBALS['transaction_files']=[];
    try { $v=$fn(); db()->commit(); $GLOBALS['transaction_files']=[]; return $v; }
    catch(Throwable $e) {
        if(db()->inTransaction()) db()->rollBack();
        foreach($GLOBALS['transaction_files']??[] as $path) if(is_file($path)) unlink($path);
        $GLOBALS['transaction_files']=[]; throw $e;
    }
}
function insert(string $table, array $row): string {
    // Table/column identifiers are developer constants, never request input.
    $cols=array_keys($row);
    query('INSERT INTO '.$table.' ('.implode(',',$cols).') VALUES ('.implode(',',array_fill(0,count($cols),'?')).')',array_values($row));
    return (string)db()->lastInsertId();
}
function setting(string $key, string $default=''): string {
    return one('SELECT value FROM settings WHERE name=?',[$key])['value'] ?? $default;
}
function set_setting(string $key,string $value): void { query('INSERT INTO settings(name,value) VALUES (?,?) ON DUPLICATE KEY UPDATE value=VALUES(value)',[$key,$value]); }
