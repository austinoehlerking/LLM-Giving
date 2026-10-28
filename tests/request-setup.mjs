import fs from 'node:fs';import path from 'node:path';import assert from 'node:assert/strict';import {root,runPhp} from '../tools/runtime.mjs';import {setupSchema,setupPrompt,parseSetupDraft} from '../public/assets/request-setup.js';
const config={instructions:fs.readFileSync(path.join(root,'templates/request-setup.txt'),'utf8'),categories:[{id:42,name:'Science & Research'}],policy:[{model:'fixture-model',efforts:['max','ultra'],ultra_tool:'Codex'}],registry:{'fixture-model':{efforts:['low','medium','high','xhigh','max','ultra'],modes:[]}},maxText:100000};
const checks=[];const check=(name,value)=>{assert.ok(value,name);checks.push(name);};const rejects=(name,draft)=>{assert.throws(()=>parseSetupDraft(typeof draft==='string'?draft:JSON.stringify(draft),config));checks.push(name);};
const draft={...setupSchema(),title:'Compare reliable dataset conversion methods',question:'Which bounded methods make a scientific dataset conversion reproducible and independently verifiable?',category:'Science & Research',context:'User-provided research context; no invented findings.',success:'A documented comparison with sources, uncertainty and stopping boundaries.',tags:'data, reproducibility'};
let parsed=parseSetupDraft(JSON.stringify(draft),config);check('Complete research draft maps category names to current IDs',parsed.category_id==='42'&&parsed.title===draft.title&&parsed.project_type==='research');check('No consent/private/admin controls enter normalized fields',!Object.hasOwn(parsed,'terms')&&!Object.hasOwn(parsed,'email')&&!Object.hasOwn(parsed,'format'));

const tagCases=[
 ['60-byte tag with 12 unique entries',['a'.repeat(60),...Array.from({length:11},(_,i)=>'tag'+i)].join(',')],
 ['61-byte ASCII tag','a'.repeat(61)],
 ['13 unique tags',Array.from({length:13},(_,i)=>'tag'+i).join(',')],
 ['60-byte multibyte tag','é'.repeat(30)],['62-byte multibyte tag','é'.repeat(31)],
 ['60-byte emoji tag','🌊'.repeat(15)],['64-byte emoji tag','🌊'.repeat(16)],
 ['PHP trimming, empty and zero filtering',' \tdata, data, ,0,\v DATA ,data\r\n '],
 ['Unicode spaces and forms stay distinct','é,e\u0301,\u00a0data\u00a0,data,\fdata\f'],
 ['many repeated tags count once',Array(20).fill('repeated').join(',')],
 ['800 raw bytes before deduplication','a,'.repeat(399)+'aa'],
 ['801 raw bytes rejected before deduplication','a,'.repeat(399)+'aaa'],
 ['PHP edge NUL trimming','\0 data,\0other\0 \0'],['embedded NUL rejected','data,an\0other'],
];
// Exercise the actual unchanged PHP text_field/set_tags functions without a database.
// Capture their tag inserts only; this verifies the browser contract against its authority.
const serverTags=JSON.parse(runPhp(['-r',"require 'src/security.php';require 'src/domain.php';function query($sql,$values=[]){if(str_starts_with($sql,'INSERT IGNORE INTO tags'))$GLOBALS['seen'][]=$values[0];}$out=[];foreach(json_decode(stream_get_contents(STDIN),true,512,JSON_THROW_ON_ERROR) as $value){$GLOBALS['seen']=[];try{set_tags('fixture',text_field(['tags'=>$value],'tags',0,800));$out[]=['tags'=>$GLOBALS['seen']];}catch(HttpError $e){$out[]=['error'=>$e->status];}}echo json_encode($out,JSON_THROW_ON_ERROR);"],JSON.stringify(tagCases.map(([,tags])=>tags))));
for(const [index,[name,tags]] of tagCases.entries()){
 const authoritative=serverTags[index];
 if(authoritative.error)rejects('Server-matched tag rejection: '+name,{...draft,tags});
 else check('Server-matched tag normalization: '+name,parseSetupDraft(JSON.stringify({...draft,tags}),config).tags===authoritative.tags.join(', '));
}
check('Tag normalization preserves case and drops only PHP-filtered entries',parseSetupDraft(JSON.stringify({...draft,tags:tagCases[7][1]}),config).tags==='data, DATA');
check('Prompt states unique tag count, per-tag bytes and raw total limit',config.instructions.includes('12 unique entries')&&config.instructions.includes('60 UTF-8 bytes')&&config.instructions.includes('800 UTF-8 bytes combined before removing duplicates'));

const external=parseSetupDraft(JSON.stringify({...draft,calculation_help:'run',external_tools:'free',required_software:'PySCF',setup_needs:'Download required'}),config);check('External requirements survive guided setup and enable execution',external.required_software==='PySCF'&&external.calculation_help==='run'&&external.external_tools==='free');
const tool={...draft,project_type:'research_tool',calculation_help:'run'};for(const key of Object.keys(tool).filter(key=>key.startsWith('tool_')))tool[key]='Explicit bounded tool requirement with reproducible reference tests and honest limitations.';
parsed=parseSetupDraft(JSON.stringify(tool),config);check('Tool draft retains all five required tool fields and computational opt-in',parsed.calculation_help==='run'&&!Object.hasOwn(parsed,'computational_models')&&Object.keys(parsed).filter(key=>key.startsWith('tool_')).length===5);check('Single fenced JSON output is accepted safely',parseSetupDraft('```json\n'+JSON.stringify(draft)+'\n```',config).title===draft.title);
for(const value of ['not JSON','[]','null','"text"','{"title":"partial"}'])rejects('Reject malformed/incomplete output: '+value,value);
for(const key of ['terms','csrf','email','attachments','moderation','display_name','__proto__','constructor','fork_nonce','parent_id','snapshot_id','background_json'])rejects('Reject non-public setup control '+key,JSON.stringify(draft).slice(0,-1)+','+JSON.stringify(key)+':"injected"}');
rejects('Unsupported output format rejected',{...draft,format:'workflow-result'});rejects('Unsupported project type rejected',{...draft,project_type:'private'});rejects('Boolean strings rejected',{...draft,computational_models:'true'});rejects('Unknown category rejected',{...draft,category:'Invented category'});rejects('Structured data cannot replace text field',{...draft,context:{html:'<script>'}});rejects('Title minimum enforced',{...draft,title:'Tiny'});rejects('UTF-8 byte limit matches server for multibyte titles',{...draft,title:'🌊'.repeat(50)});rejects('Question minimum enforced',{...draft,question:'too short'});rejects('Tool scope must be complete',{...tool,tool_validation_plan:''});rejects('Research cannot carry hidden tool requirements',{...draft,tool_requirements:'Unexpected tool requirement stored under a research project.'});rejects('Unknown model rejected',{...draft,required_model:'invented-model'});rejects('Exact lower effort cannot block planning',{...draft,reasoning_rule:'exact',required_reasoning:'high'});rejects('Unknown reasoning order rejected',{...draft,reasoning_rule:'minimum',required_reasoning:'super'});rejects('Unused reasoning value rejected',{...draft,reasoning_rule:'none',required_reasoning:'max'});rejects('Null bytes rejected',{...draft,question:draft.question+'\0'});rejects('Oversized paste rejected','x'.repeat(1500001));
check('Minimum lower floor allows mandatory Max planning',parseSetupDraft(JSON.stringify({...draft,reasoning_levels:['high'],reasoning_or_higher:['high']}),config).reasoning_or_higher.includes('high'));check('Configured exact Max restriction accepted',parseSetupDraft(JSON.stringify({...draft,required_models:['fixture-model'],reasoning_levels:['max']}),config).required_models[0]==='fixture-model');
const prompt=setupPrompt(config,{...tool,email:'PRIVATE_EMAIL_SENTINEL',csrf:'PRIVATE_CSRF_SENTINEL',display_name:'PRIVATE_PROFILE_SENTINEL'});
const completedDraftLabel=fs.readFileSync(path.join(root,'views/request-setup.php'),'utf8').match(/<label for="paste-setup-prompt">([^<]+)<\/label>/)?.[1];
for(const current of [draft,tool]){
 const generated=setupPrompt(config,current);
 check('Generated '+current.project_type+' instructions name the current completed-draft JSON input',!!completedDraftLabel&&generated.includes('Paste the completed JSON draft into ‘'+completedDraftLabel+'’')&&!generated.includes('Paste setup prompt'));
}
check('Prompt contains bounded interview and complete output schema',prompt.includes('Ask one short question at a time')&&prompt.includes('REQUIRED FINAL OUTPUT TEMPLATE')&&prompt.includes('invalid-input')&&prompt.includes('independent reproduction'));
const plastic='Effects of plastic pollution on the biological carbon pump in the deep ocean';
const plasticDraft={...setupSchema(),title:plastic,question:plastic,category:'Science & Research'};
const plasticResult=parseSetupDraft('```json\n'+JSON.stringify(plasticDraft,null,2)+'\n```',config);
rejects('Truncated generated JSON cannot partially import','```json\n'+JSON.stringify(plasticDraft).slice(0,-1)+'\n```');
rejects('Multiple code blocks cannot silently import the first object','```json\n'+JSON.stringify(plasticDraft)+'\n```\n```json\n'+JSON.stringify(plasticDraft)+'\n```');
check('Exact plastic topic imports without added scope or mandatory calculations',plasticResult.question===plastic&&plasticResult.title===plastic&&plasticResult.success===''&&plasticResult.context===''&&plasticResult.project_type==='research'&&plasticResult.calculation_help==='maybe'&&plasticResult.setup_needs==='None specified');
check('Prompt composition ends at depth choice without overriding supplied topic',setupPrompt(config,plasticDraft).endsWith('Do not invent a completed request from empty fields.')&&setupPrompt(config,plasticDraft).includes('Begin with the exact quick/in-depth choice'));
check('Setup revision preserves compatible JSON contract',config.instructions.includes('conversation revision 4 (JSON contract v3)')&&plasticDraft.format==='llm-giving-request-setup-v3');
check('Prompt adapts to current type, brief, categories and configured model policy',prompt.includes('"project_type": "research_tool"')&&prompt.includes(tool.question)&&prompt.includes('Science & Research')&&prompt.includes('fixture-model'));
check('Private identity/contact/token values omitted from copied prompt',!prompt.includes('PRIVATE_EMAIL_SENTINEL')&&!prompt.includes('PRIVATE_CSRF_SENTINEL')&&!prompt.includes('PRIVATE_PROFILE_SENTINEL'));
check('Ordinary research prompt omits stale hidden tool requirements',!setupPrompt(config,{project_type:'research',tool_requirements:'STALE_TOOL_SCOPE_SENTINEL'}).includes('STALE_TOOL_SCOPE_SENTINEL'));
assert.throws(()=>parseSetupDraft(JSON.stringify({...draft,format:'llm-giving-request-setup-v1',computational_models:false,software_licensing:'old restriction',computing_resources:'old restriction'}),config),/fresh setup prompt/);checks.push('Old setup drafts receive explicit refresh guidance');
check('Maybe is the default without a redundant execution flag',setupSchema().calculation_help==='maybe'&&!Object.hasOwn(setupSchema(),'computational_models'));
for(const mode of ['unspecified','maybe','prepare_review','run'])check('Calculation preference round trips: '+mode,parseSetupDraft(JSON.stringify({...draft,calculation_help:mode}),config).calculation_help===mode);
const noTools=parseSetupDraft(JSON.stringify({...draft,external_tools:'none',required_software:{stale:'ignored'},setup_needs:''}),config);
check('No dependency drops stale hidden software and unknown setup',noTools.required_software===''&&noTools.setup_needs==='None specified');
for(const key of ['software_licensing','computing_resources','computational_models'])rejects('Removed setup field rejected: '+key,{...draft,[key]:'obsolete'});
check('Copied prompt excludes stale software and removed questions',!setupPrompt(config,{external_tools:'none',required_software:'STALE_SOFTWARE',software_licensing:'OBSOLETE_LICENSE',computing_resources:'OBSOLETE_COMPUTE'}).match(/STALE_SOFTWARE|OBSOLETE_LICENSE|OBSOLETE_COMPUTE|"software_licensing"|"computing_resources"|"computational_models"/));
check('Interview defaults Maybe and asks only about concrete dependencies',prompt.includes('without another generic permission question')&&prompt.includes('only when a concrete dependency matters')&&prompt.includes('"None specified"'));
check('Prompt prohibits fabricated evidence and automatic submission',prompt.includes('Do not invent citations')&&prompt.includes('Do not claim the request has been submitted'));
fs.mkdirSync(path.join(root,'storage/qa/setup-assist'),{recursive:true});fs.writeFileSync(path.join(root,'storage/qa/setup-assist/unit-checks.json'),JSON.stringify({checks},null,2));console.log(checks.length+' request setup prompt/import checks passed.');