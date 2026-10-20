import fs from 'node:fs';
import path from 'node:path';
import {spawn,spawnSync} from 'node:child_process';
import {randomBytes} from 'node:crypto';
import {root,env,runPhp,spawnPhp,ready,portOpen} from './runtime.mjs';
const action=process.argv[2]||'start';
const dbDir=path.join(root,'storage','db');
const localFile=path.join(root,'storage','local-runtime.json');
const maria=process.env.MARIADB_BIN||path.join(process.env.USERPROFILE||process.env.HOME||'', 'scoop/apps/mariadb/current/bin');
if(process.platform!=='win32')throw new Error('The managed native launcher requires Windows. Use the portable PHP or Docker setup in README.md.');
if(fs.existsSync(path.join(root,'.env'))||env.APP_ENV||env.DB_HOST)runPhp(['tests/bootstrap.php']);
async function database(){
 if(await portOpen(3318)){if(!fs.existsSync(localFile))throw new Error('Port 3318 occupied by an unmanaged service.');return;}
 if(!fs.existsSync(dbDir)) {
  const rootPassword=randomBytes(24).toString('hex');
  const r=spawnSync(path.join(maria,'mariadb-install-db.exe'),['--datadir='+dbDir,'--password='+rootPassword,'--port=3318'],{env,encoding:'utf8',windowsHide:true});
  if(r.status!==0)throw new Error('Local MariaDB initialization failed: '+r.stderr+r.stdout);
  fs.writeFileSync(localFile,JSON.stringify({rootPassword}),{mode:0o600});
 }
 const log=fs.openSync(path.join(root,'storage','logs','mariadb.log'),'a');
 const child=spawn(path.join(maria,'mariadbd.exe'),['--no-defaults','--datadir='+dbDir,'--port=3318','--bind-address=127.0.0.1','--max-allowed-packet=32M','--console'],{env,cwd:root,detached:true,stdio:['ignore',log,log],windowsHide:true});
 child.unref();
 for(let i=0;i<100;i++){if(await portOpen(3318))return;await new Promise(r=>setTimeout(r,150));}
 throw new Error('Local MariaDB failed to start; see storage/logs/mariadb.log.');
}
function initialize(){
 if(!fs.existsSync(path.join(root,'.env'))) {
  const rootPassword=JSON.parse(fs.readFileSync(localFile)).rootPassword;
  const password=randomBytes(24).toString('hex');
  const opts=path.join(root,'storage','db-client.cnf');
  fs.writeFileSync(opts,'[client]\nuser=root\npassword='+rootPassword+'\nhost=127.0.0.1\nport=3318\n',{mode:0o600});
  const sql="CREATE DATABASE IF NOT EXISTS llm_giving CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci; CREATE USER IF NOT EXISTS 'llm_giving'@'127.0.0.1' IDENTIFIED BY '"+password+"'; GRANT ALL ON llm_giving.* TO 'llm_giving'@'127.0.0.1';";
  const r=spawnSync(path.join(maria,'mariadb.exe'),['--defaults-extra-file='+opts],{input:sql,env,encoding:'utf8',windowsHide:true});
  fs.unlinkSync(opts);if(r.status!==0)throw new Error('Database provisioning failed: '+r.stderr);
  const example=fs.readFileSync(path.join(root,'.env.example'),'utf8');
  fs.writeFileSync(path.join(root,'.env'),example.replace('DB_PASSWORD=','DB_PASSWORD='+password),{mode:0o600});
 }
 process.stdout.write(runPhp(['bin/console.php','migrate']));
 process.stdout.write(runPhp(['bin/console.php','workflow-upgrade']));
}
if(action==='setup'){await database();initialize();console.log('Local database ready at 127.0.0.1:3318. Initialize an administrator with the CLI; no default login is created.');}
else if(action==='start'){
 await database();initialize();const child=spawnPhp(['-S','127.0.0.1:8086','-t','public','public/router.php']);
 process.on('SIGINT',()=>{child.kill();process.exit();});process.on('SIGTERM',()=>{child.kill();process.exit();});
 await ready('http://127.0.0.1:8086/',child);console.log('LLM.Giving running at http://127.0.0.1:8086. Ctrl+C stops the web server.');await new Promise(resolve=>child.on('exit',resolve));
}else throw new Error('Usage: node tools/dev.mjs setup|start');
