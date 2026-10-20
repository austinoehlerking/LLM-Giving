// Runs only these source-controlled synthetic fixtures, never uploaded tool code.
import fs from 'node:fs';import path from 'node:path';import assert from 'node:assert/strict';import {spawnSync} from 'node:child_process';import {root,env} from './runtime.mjs';
for(const actor of ['builder','reviewer']){
 const out=path.join(root,'storage','qa','tool-builds','execution',actor);fs.mkdirSync(out,{recursive:true});
 for(const file of ['distance-tool.mjs','distance-tool-test.mjs'])fs.copyFileSync(path.join(root,'tests','fixtures',file),path.join(out,file));
 const run=spawnSync(process.execPath,['distance-tool-test.mjs'],{cwd:out,env,encoding:'utf8',windowsHide:true,timeout:10000});assert.equal(run.status,0,run.stdout+run.stderr);
 fs.writeFileSync(path.join(out,'run.json'),JSON.stringify({...JSON.parse(run.stdout),command:'node distance-tool-test.mjs',exit_code:run.status,stderr:run.stderr},null,2));
}
console.log('Research-tool pilot: source and all 3 tests executed in separate builder/reviewer processes. Synthetic software QA only.');