<?php
/** All database-backed fixtures must opt into a local development environment. */
require_once __DIR__.'/../src/bootstrap.php';
require_once __DIR__.'/supporting-fixture.php';
/** Fixture pickup is explicit; real admission tests call wf_submit directly. */
function wf_test_submit(string $rid,string $tid,array $d,string $actor,bool $checkOnly=false,string $method='unknown',?array $consent=null): string {
    $pickup=null;
    if(!one('SELECT id FROM workflow_submissions WHERE research_id=? AND nonce=?',[$rid,$d['submission_key']??''])){
        try{$pickup=wr_claim($rid,$tid,(int)($d['task_revision']??0),$actor);}
        catch(HttpError $error){/* Invalid/stale fixtures must still exercise the original packet validation. */}
    }
    try{return wf_submit($rid,$tid,$d,$actor,$checkOnly,$method,$consent);}
    finally{if($checkOnly&&$pickup&&(wr_live($rid,$tid)['id']??null)===$pickup['id'])wr_release($rid,$tid,$actor);}
}

if (PHP_SAPI !== 'cli' || !in_array(env('APP_ENV'), ['local', 'test', 'testing'], true)
    || !in_array(strtolower(env('DB_HOST', '127.0.0.1')), ['127.0.0.1', 'localhost', '::1', 'db'], true)) {
    fwrite(STDERR, "Tests require CLI, APP_ENV=local/test/testing and a local development database.\n");
    exit(1);
}
putenv('RATE_LIMIT_NAMESPACE=server-fixture-'.uid());