import fs from 'node:fs';import path from 'node:path';import {randomBytes} from 'node:crypto';import {root} from '../tools/runtime.mjs';
export async function agentAccessQa({page,ctx,base,runPhp,check,visibleActions,report}){
 const out=path.join(root,'storage/qa/agent-access');fs.mkdirSync(out,{recursive:true});
 const f=JSON.parse(runPhp(['tests/agent-access-fixture.php']));runPhp(['tests/agent-access-fixture.php','revoke',f.session_id]);
 const route='/volunteer/'+f.session_id,sizes=[{width:1366,height:768},{width:390,height:844},{width:375,height:667}];
 async function shot(name){const file=path.join(out,name+'.png');await page.screenshot({path:file,fullPage:false,mask:[page.locator('#agent-start:visible')]});report.screenshots.push(file);}
 async function initial(url,name,selector){for(const size of sizes){await page.setViewportSize(size);await page.goto(base+url);await shot(name+'-'+size.width+'x'+size.height);await visibleActions(page,selector,name+' '+size.width);}}
 async function enlarged(url,name){await page.setViewportSize(sizes[0]);await page.goto(base+url);await page.evaluate(()=>{const fonts=[...document.querySelectorAll('body,body *')].filter(e=>e instanceof HTMLElement).map(e=>[e,parseFloat(getComputedStyle(e).fontSize)]);for(const [e,s] of fonts)if(Number.isFinite(s))e.style.fontSize=s*2+'px';});await shot(name+'-200-percent-text');check(name+' 200% text reflows',await page.evaluate(()=>document.documentElement.scrollWidth<=innerWidth+1));}
 try{
 await page.goto(f.link);await page.waitForURL('**'+route);
 await initial(route+'/agent','connect','[data-agent-actions] button,[data-agent-actions] a');
 await page.getByRole('button',{name:'Enable agent access',exact:true}).click();
 check('Human enables API through CSRF protected form',await page.locator('#agent-start').count()===1);
 await shot('connection-created-375x667');await visibleActions(page,'[data-agent-actions] button,[data-agent-actions] a','Connection created 375');
 const prompt=await page.locator('#agent-start').inputValue(),token=prompt.match(/Bearer (llmg_[a-f0-9]{32}_[a-f0-9]{64})/)[1];
 await page.getByRole('button',{name:'Copy start message',exact:true}).click();
 check('Copy supplies one private start message',await page.evaluate(()=>navigator.clipboard.readText())===prompt);
 const api=async(tail='',data)=>{const res=await fetch(base+'/api/agent/v1/sessions/'+f.session_id+tail,{method:data===undefined?'GET':'POST',headers:{Authorization:'Bearer '+token,...(data===undefined?{}:{'Content-Type':'application/json'})},...(data===undefined?{}:{body:JSON.stringify(data)})});return {status:res.status,body:await res.json()};};
 const claim=await api('/claim',{claim_key:randomBytes(16).toString('hex')});check('Copied connection claims actual authorized task',claim.status===200);const aid=claim.body.data.id;
 await initial(route,'dashboard','[data-session-actions] button,[data-session-actions] a');
 const notes=route+'/assignments/'+aid+'/notes';
 await initial(notes,'notes','[data-notes-actions] button,[data-notes-actions] a');
 await page.getByLabel('Next useful step').fill('Synthetic browser continuation. Check original evidence.');
 await page.getByRole('button',{name:'Save research notes',exact:true}).click();
 check('Real save confirms notes without submitting',await page.getByText('Research notes saved. No result was submitted.',{exact:true}).isVisible());
 const saved=await api('/assignments/'+aid+'/notes');check('Agent resumes notes entered through human UI',saved.body.data.version===1&&saved.body.data.notes.next_step==='Synthetic browser continuation. Check original evidence.');
 await page.getByRole('link',{name:'Continue assignment',exact:true}).click();check('Continue opens actual result form',new URL(page.url()).pathname===route+'/assignments/'+aid&&await page.locator('textarea').count()>0);
 await initial(route+'/agent','connection-enabled','[data-agent-actions] button,[data-agent-actions] a');
 check('Subsequent GET never reveals raw credential',await page.locator('#agent-start').count()===0);
 await enlarged(route+'/agent','connect');await enlarged(notes,'notes');await enlarged(route,'dashboard');
 await page.goto(base+route+'/agent');await page.getByRole('button',{name:'Revoke agent access',exact:true}).click();check('Human revoke invalidates real API credential',(await api()).status===401);
 const outsider=await ctx.browser().newContext();try{const p=await outsider.newPage();const r=await p.goto(base+notes);check('Unrelated browser cannot read saved work',r.status()===403);}finally{await outsider.close();}
 console.log('Agent browser flow passed; private credentials excluded from reports.');
 }finally{runPhp(['tests/agent-access-fixture.php','cleanup',f.session_id]);}
}
