<?php
declare(strict_types=1);
require __DIR__.'/../src/bootstrap.php';
if(PHP_SAPI!=='cli') exit(1);
$command=$argv[1]??'help';
try {
 if($command==='migrate') {
  query('CREATE TABLE IF NOT EXISTS migrations (name VARCHAR(100) PRIMARY KEY, applied_at DATETIME NOT NULL) ENGINE=InnoDB');
  foreach(glob(ROOT.'/migrations/*.sql') as $file) {
   $name=basename($file); if(one('SELECT name FROM migrations WHERE name=?',[$name])) continue;
   // MariaDB DDL commits implicitly. Run each migration once; backups are required before production upgrades.
   foreach(explode(';',file_get_contents($file)) as $sql) if(trim($sql)) db()->exec($sql);
   insert('migrations',['name'=>$name,'applied_at'=>now()]); echo "Applied $name\n";
  }
  foreach(['Science & Research','Environment & Climate','Health & Public Health','Education','Technology & Open Source','Engineering','Business & Economics','Accessibility','Humanities','Personal Research','Other'] as $name) query('INSERT IGNORE INTO categories(name) VALUES (?)',[$name]);
 } elseif($command==='admin') {
  $email=strtolower($argv[2]??''); if(!filter_var($email,FILTER_VALIDATE_EMAIL)) throw new RuntimeException('Usage: php bin/console.php admin email@example.test (password via stdin)');
  $password=rtrim(stream_get_contents(STDIN),"\r\n");
  if(strlen($password)<14) throw new RuntimeException('Use a password with at least 14 characters.');
  $hash=password_hash($password,defined('PASSWORD_ARGON2ID')?PASSWORD_ARGON2ID:PASSWORD_DEFAULT);
  query('INSERT INTO admins(email,password_hash) VALUES (?,?) ON DUPLICATE KEY UPDATE password_hash=VALUES(password_hash)',[$email,$hash]);
  echo "Administrator initialized or password replaced.\n";
 } elseif($command==='workflow-upgrade') {
  $_SESSION['admin']=1;
  foreach(all('SELECT id,moderation,status FROM research') as $r){
   if($r['status']==='Awaiting First Research'){query("UPDATE research SET status='Needs Research Plan' WHERE id=?",[$r['id']]);touch_context($r['id'],false);audit_event($r['id'],'Planning workflow enabled','The project now begins with an AI-assisted planning task.','research',$r['id'],$r['status'],'Needs Research Plan','Initial topic approval is still required before public work.','system','LLM.Giving',[],$r['moderation']==='Approved'?'public':'private');}
   if($r['moderation']==='Approved'&&$r['status']!=='Withdrawn')wf_start($r['id']);
  }
  echo "Existing topics are ready for the planning workflow.\n";
 } elseif($command==='seed') {
  if(env('APP_ENV')==='production') throw new RuntimeException('Development seeds are disabled in production.');
  if(one("SELECT id FROM research WHERE title LIKE '[Demo]%'")) { echo "Development examples already exist.\n"; exit; }
  $cat=one("SELECT id FROM categories WHERE name='Environment & Climate'")['id']; $_SESSION['admin']=1;
  foreach([
   ['[Demo] How can a small library reduce summer cooling demand?','Which low-cost passive cooling measures could a small community library investigate before investing in mechanical upgrades? Compare evidence, limits and climate-dependent tradeoffs.','building-design, climate, community'],
   ['[Demo] What makes an open-source guide easier to navigate?','What repeatable methods help volunteers evaluate the accessibility and navigation of an open-source documentation site? Identify practical tests and their limitations.','accessibility, open-source'],
   ['[Demo] Measuring the impact of a neighborhood repair café','How could a volunteer-run repair café measure waste avoided without overstating environmental benefits? Propose a transparent, reproducible method.','measurement, circular-economy']
  ] as $i=>$row) {
   $created=create_research(['title'=>$row[0],'question'=>$row[1],'category_id'=>$cat,'context'=>'DEVELOPMENT EXAMPLE. This is synthetic demonstration content, not completed research or a verified factual claim.','success'=>'A documented comparison with verifiable sources, uncertainties and practical next steps.','tags'=>$row[2],'terms'=>1,'display_name'=>'Development example']);
   moderate('request',$created['id'],'Approved','Clearly labelled synthetic development example.');
  }
  echo "Created labelled development examples.\n";
 } elseif($command==='archive') {
  $_SESSION['admin']=1; $cutoff=gmdate('Y-m-d H:i:s',time()-86400*(int)setting('archive_days','60'));
  foreach(all("SELECT id FROM research WHERE moderation='Approved' AND status NOT IN ('Solved','Paused','Withdrawn','Archived — Inactive') AND activity_at<?",[$cutoff]) as $r) change_status($r['id'],'Archived — Inactive','No meaningful approved activity within the configured period.','administrator');
  echo "Inactivity maintenance complete.\n";
 } elseif($command==='handoffs-prune') {
  echo wf_prune_handoffs()." unused handoff archives removed. Submitted evidence retained.\n";
 } elseif($command==='backup') {
  $_SESSION['admin']=1; create_backup(); echo "Private database backup written under storage/backups. Back up uploads separately.\n";
 } else echo "Commands: migrate | seed | admin EMAIL (password on stdin) | archive | handoffs-prune | backup\n";
} catch(Throwable $e) { fwrite(STDERR,$e->getMessage()."\n"); exit(1); }
