import fs from 'node:fs';
import path from 'node:path';
import {spawnSync} from 'node:child_process';
import {assertLocalEnvironment} from './runtime.mjs';
assertLocalEnvironment();
for(const args of [['tools/source-check.mjs'],['tests/source-tooling.mjs'],['tests/error-http.mjs'],['tools/build-blog.mjs','--check'],['tests/blog-build.mjs'],['tests/calculation-model.mjs'],['tools/tool-build-pilot.mjs'],['tests/artifact-controls.mjs'],['tests/request-setup.mjs'],['tests/ai-selection.mjs']]) {
 const result=spawnSync(process.execPath,args,{encoding:'utf8'});
 if(result.status!==0)throw new Error(result.stdout+result.stderr);
 process.stdout.write(result.stdout);
}
import {root,runPhp} from './runtime.mjs';
let count=0;
for(const dir of ['src','views','public','bin','tests','tools']){
 if(!fs.existsSync(path.join(root,dir)))continue;
 for(const entry of fs.readdirSync(path.join(root,dir),{recursive:true}))if(entry.endsWith('.php')){runPhp(['-l',path.join(dir,entry)]);count++;}
}
console.log(count+' PHP files passed syntax validation.');
process.stdout.write(runPhp(['tests/google.php']));
process.stdout.write(runPhp(['tests/legal-policy.php']));
process.stdout.write(runPhp(['tests/integration.php']));
process.stdout.write(runPhp(['tests/discovery.php']));
process.stdout.write(runPhp(['tests/blog.php']));
process.stdout.write(runPhp(['tests/workflow.php']));
process.stdout.write(runPhp(['tests/evidence.php']));
process.stdout.write(runPhp(['tests/resources.php']));
process.stdout.write(runPhp(['tests/calculations.php']));
process.stdout.write(runPhp(['tests/research-tools.php']));
process.stdout.write(runPhp(['tests/artifacts.php']));
process.stdout.write(runPhp(['tests/quarantine.php']));
process.stdout.write(runPhp(['tests/handoffs.php']));
process.stdout.write(runPhp(['tests/browser-return.php']));
process.stdout.write(runPhp(['tests/volunteer-sessions.php']));
process.stdout.write(runPhp(['tests/citation.php']));
process.stdout.write(runPhp(['tests/requirements.php']));
process.stdout.write(runPhp(['tests/forks.php']));
process.stdout.write(runPhp(['tests/fork-resources.php']));
process.stdout.write(runPhp(['tests/fork-discovery.php']));
process.stdout.write(runPhp(['tests/ai-selection.php']));
process.stdout.write(runPhp(['tests/external-requirements.php']));
const artifactHttp=spawnSync(process.execPath,['tests/artifact-http.mjs'],{encoding:'utf8'});
if(artifactHttp.status!==0)throw new Error(artifactHttp.stdout+artifactHttp.stderr);
process.stdout.write(artifactHttp.stdout);
const toolHttp=spawnSync(process.execPath,['tests/tool-http.mjs'],{encoding:'utf8'});
if(toolHttp.status!==0)throw new Error(toolHttp.stdout+toolHttp.stderr);
process.stdout.write(toolHttp.stdout);
