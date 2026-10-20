import fs from 'node:fs';
import path from 'node:path';
import {fileURLToPath} from 'node:url';
import {createHash} from 'node:crypto';
const hash=text=>createHash('sha256').update(text,'utf8').digest('hex');
const close=(a,b)=>Math.abs(a-b)<=1e-9+1e-6*Math.max(Math.abs(a),Math.abs(b));
export function calculate(input,phase='complete'){
 if(input.format!=='llm-giving-climate-input-v1')throw Error('Unknown climate input format.');
 if(!Array.isArray(input.quantities)||new Set(input.quantities.map(q=>q.id)).size!==input.quantities.length)throw Error('Input quantities must have unique identifiers.');
 const quantities=Object.fromEntries(input.quantities.map(q=>[q.id,q]));
 const value=(id,unit)=>{const q=quantities[id];if(!q||q.unit!==unit||!Number.isFinite(q.value)||q.value<0)throw Error('Unit error: '+id+' must be '+unit+' with a nonnegative finite value.');return q.value;};
 const outputs=[];const add=(id,value,unit)=>{if(!Number.isFinite(value))throw Error('Non-finite output.');outputs.push({id,value,unit});return value;};
 const factors=input.factors;if(factors.label!=='source-paper GWP100 (34/298)'||factors.ch4!==34||factors.n2o!==298)throw Error('Pilot preserves the source-paper factor convention 34/298.');
 const totals={};for(const treatment of ['control','awd']){
  const methane=add(treatment+'_ch4',value(treatment+'_ch4_c','kg CH4-C/ha/year')*16/12,'kg CH4/ha/year');
  const nitrous=add(treatment+'_n2o',value(treatment+'_n2o_n','kg N2O-N/ha/year')*44/28,'kg N2O/ha/year');
  totals[treatment]=add(treatment+'_co2e',methane*factors.ch4+nitrous*factors.n2o,'kg CO2e/ha/year');
  const published=value(treatment+'_published','kg CO2e/ha/year');if(Math.abs(totals[treatment]-published)/published>0.005)throw Error('Source reproduction differs by more than 0.5%.');
 }
 if(phase==='complete'){
  add('delta_awd_minus_control',totals.awd-totals.control,'kg CO2e/ha/year');
  add('reduction_percent',100*(1-totals.awd/totals.control),'percent');
  for(const treatment of ['control','awd']){const yieldKg=value(treatment+'_yield','kg grain/ha');if(yieldKg<=0)throw Error('Yield must be positive.');add(treatment+'_intensity',totals[treatment]/yieldKg,'kg CO2e/kg grain (annual allocation)');}
  add('yield_loss_percent',100*(1-value('awd_yield','kg grain/ha')/value('control_yield','kg grain/ha')),'percent');
  for(const extra of [5,10,15])add('illustrative_n2o_'+extra,-100*factors.ch4+extra*factors.n2o,'kg CO2e (illustrative)');
 }
 return {format:'llm-giving-climate-output-v1',phase,factor_basis:factors.label,outputs,limitations:'Arithmetic reproduction from rounded source means, not field replication or a new climate model. Annual gas budget allocated to one harvest; not the paper growing-season intensity. Sensitivity inputs are invented. No confidence interval, adoption recommendation or credit eligibility. Broader lifecycle terms remain unknown.'};
}
if(process.argv[1]&&path.resolve(process.argv[1])===fileURLToPath(import.meta.url)){
 try{
  const args=process.argv.slice(2);const get=name=>{const i=args.indexOf(name);return i<0?null:args[i+1];};
  const inputFile=get('--input'),out=get('--out');if(!inputFile||!out)throw Error('Usage: node climate-model.mjs --input inputs.json --out path/to/output [--stop-after baseline] [--resume checkpoint.json]');

  const inputText=fs.readFileSync(inputFile,'utf8');const input=JSON.parse(inputText);let prior=null;const resume=get('--resume');
  if(resume){prior=JSON.parse(fs.readFileSync(resume,'utf8'));if(prior.format!=='llm-giving-climate-checkpoint-v1'||prior.input_sha256!==hash(inputText)||prior.code_sha256!==hash(fs.readFileSync(new URL(import.meta.url),'utf8')))throw Error('Checkpoint input/code identity mismatch.');const baseline=calculate(input,'baseline');if(JSON.stringify(baseline)!==JSON.stringify(prior.baseline))throw Error('Checkpoint baseline does not reproduce.');}
  if(get('--stop-after')&&get('--stop-after')!=='baseline')throw Error('Only --stop-after baseline is supported.');
  const phase=get('--stop-after')==='baseline'?'baseline':'complete';const result={...calculate(input,phase),environment:{node:process.version,platform:process.platform,arch:process.arch}};fs.mkdirSync(out,{recursive:true});
  fs.writeFileSync(path.join(out,'outputs.json'),JSON.stringify(result,null,2)+'\n');
  const checkpoint={format:'llm-giving-climate-checkpoint-v1',input_sha256:hash(inputText),code_sha256:hash(fs.readFileSync(new URL(import.meta.url),'utf8')),baseline:calculate(input,'baseline'),completed:'Source-paper baseline and elemental-to-gas conversions executed.',remaining:phase==='baseline'?'Run paired yield intensity and labeled sign-changing sensitivity.':'No outstanding arithmetic; independent scientific review remains required.'};
  fs.writeFileSync(path.join(out,'checkpoint.json'),JSON.stringify(checkpoint,null,2)+'\n');
  console.log(JSON.stringify({phase,resumed:!!prior,outputs:result.outputs.length,checkpoint:'checkpoint.json'}));
 }catch(error){console.error(error.message);process.exitCode=1;}
}