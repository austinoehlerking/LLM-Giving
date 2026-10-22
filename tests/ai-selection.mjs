import assert from 'node:assert/strict';
import {parseChoices,matchesChoice} from '../public/assets/ai-selection.js';
import {setupSchema,parseSetupDraft} from '../public/assets/request-setup.js';
const registry={
 'gpt-6-astra':{efforts:['low','medium','high','xhigh','max','ultra'],modes:[]},
 'claude-fable-5-1':{efforts:['low','medium','high','xhigh','max'],modes:['ultracode']}
};
const config={registry,policy:[{model:'gpt-6-astra',efforts:['max','ultra'],ultra_tool:'Codex'},{model:'claude-fable-5-1',efforts:['max']}],categories:[{id:1,name:'Science'}],maxText:100000};
let checks=0;const ok=(value,message)=>{assert.ok(value,message);checks++;};
const parse=(models=[],levels=[],higher=[])=>parseChoices({required_models:models,reasoning_levels:levels,reasoning_or_higher:higher},config);
const any=parse();ok(matchesChoice(any,registry,'other','unknown'),'Any is unrestricted');
const union=parse(['gpt-6-astra','claude-fable-5-1'],['low','max']);
for(const model of Object.keys(registry))for(const effort of ['low','medium','high','xhigh','max','ultra'])ok(matchesChoice(union,registry,model,effort)===['low','max'].includes(effort),'Exact OR alternatives '+model+effort);
const mode=parse(['claude-fable-5-1'],['high','ultracode'],['high']);
for(const effort of ['low','medium','high','xhigh','max','ultra'])ok(matchesChoice(mode,registry,'claude-fable-5-1',effort,'ultracode','Claude Code')===['high','xhigh','max'].includes(effort),'Mode AND floor '+effort);
ok(!matchesChoice(mode,registry,'claude-fable-5-1','max'),'Mode is required');
ok(!matchesChoice(mode,registry,'claude-fable-5-1','max','ultracode','Codex'),'Tool must support mode');
for(const args of [[['any','gpt-6-astra']],[[],['any','max']],[[],['high']],[['claude-fable-5-1'],['ultra']],[['gpt-6-astra'],['ultracode']],[[],['max'],['ultra']],[[],['max'],['high']]]){
 assert.throws(()=>parse(...args));checks++;
}
const draft={...setupSchema(),title:'Synthetic AI selection import',question:'How can model and effort requirements survive a setup JSON round trip?',category:'Science',required_models:['claude-fable-5-1'],reasoning_levels:['high','ultracode'],reasoning_or_higher:['high']};
const result=parseSetupDraft(JSON.stringify(draft),config);
ok(result.required_models[0]==='claude-fable-5-1'&&result.reasoning_levels.includes('ultracode')&&result.reasoning_or_higher[0]==='high','Current import retains separate mode and minimum');
const {ai_selection_version,required_models,reasoning_levels,reasoning_or_higher,...rest}=draft;
for(const rule of ['exact','minimum','none']){
 const legacy={...rest,format:'llm-giving-request-setup-v2',required_model:'gpt-6-astra',reasoning_rule:rule,required_reasoning:rule==='none'?'':'max'};
 const imported=parseSetupDraft(JSON.stringify(legacy),config);
 ok(imported.required_models[0]==='gpt-6-astra'&&(rule==='none'?imported.reasoning_levels.length===0:imported.reasoning_levels[0]==='max')&&(rule==='minimum'?imported.reasoning_or_higher[0]==='max':imported.reasoning_or_higher.length===0),'Historical setup imports '+rule);
}
const oldTop={...rest,format:'llm-giving-request-setup-v2',required_model:'gpt-6-astra',reasoning_rule:'minimum',required_reasoning:'ultra'};
const top=parseSetupDraft(JSON.stringify(oldTop),config);ok(top.reasoning_levels[0]==='ultra'&&top.reasoning_or_higher.length===0,'Historical minimum Ultra becomes equivalent exact top level');
assert.throws(()=>parseSetupDraft(JSON.stringify({...oldTop,required_models:[]}),config));checks++;
console.log(checks+' AI selection JavaScript matching/import checks passed.');
