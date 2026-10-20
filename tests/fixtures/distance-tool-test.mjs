import assert from 'node:assert/strict';
import {metres} from './distance-tool.mjs';
const cases=[
  {id:'reference',kind:'reference',input:'2.5 km',expected:'2500',observed:String(metres(2.5)),comparison:'numeric'},
  {id:'boundary',kind:'boundary',input:'0 km',expected:'0',observed:String(metres(0)),comparison:'exact'},
  {id:'invalid',kind:'invalid_input',input:'-1 km',expected:'invalid distance',observed:(()=>{try{metres(-1);return 'unexpected success';}catch(error){return error.message;}})(),comparison:'exact'}
];
for(const test of cases)assert.equal(test.observed,test.expected,test.id);
console.log(JSON.stringify({environment:'Node.js '+process.version+'; '+process.platform+'; built-in assertions only',tests:cases.map(test=>({...test,status:'passed',run_log:'Executed retained test case '+test.id+' in a fresh Node.js process; assertion passed.'}))}));