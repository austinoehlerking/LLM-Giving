import fs from 'node:fs';
import path from 'node:path';
import {env,root} from '../tools/runtime.mjs';

const sizes=[{width:1366,height:768},{width:390,height:844},{width:375,height:667}];
const tasks=[{
  key:'compare-methods',title:'Compare published library attendance measures',
  objective:'Compare how public library evaluation reports count participants.',
  deliverable:'An annotated comparison table of reported measures and limitations.',
  acceptance:'Cite two accessible reports and explain denominator differences.',
  evidence_needs:'Use local public library or municipal programme evaluation reports.',
  stopping_rule:'Stop after identifying two documented methods or a reproducible search gap.',
  computational:false,builds_tool:false,dependencies:[]
}];

export async function adminDashboardQa({page,ctx,base,runPhp,check,visibleActions,report,password,browser}){
  const out=path.join(root,'storage','qa','admin-dashboard');
  fs.mkdirSync(out,{recursive:true});
  const token=env.QA_LOCAL_PLANNER_TOKEN;
  if(!/^[a-f0-9]{64}$/.test(token??''))throw Error('Set the isolated planner test token.');
  const errors=[];
  page.on('pageerror',error=>errors.push(error.message));
  const api=async(action,data={},secret=token)=>{
    const response=await fetch(base+'/api/local-planner/v1',{method:'POST',
      headers:{'Content-Type':'application/json',Authorization:'Bearer '+secret},body:JSON.stringify({action,...data})});
    return {status:response.status,...await response.json()};
  };
  const refresh=async(expected)=>{
    await page.evaluate(()=>document.dispatchEvent(new Event('visibilitychange')));
    await page.getByText(expected,{exact:true}).waitFor();
  };
  let fixture;
  try {
    fixture=JSON.parse(runPhp(['tests/admin-dashboard-state.php','seed']));
    runPhp(['bin/console.php','admin','admin-dashboard-qa@example.invalid'],password);
    await page.goto(base+'/admin/login');
    await page.getByLabel('Email').fill('admin-dashboard-qa@example.invalid');
    await page.getByLabel('Password').fill(password);
    await page.getByRole('button',{name:'Sign in',exact:true}).click();
    const screenshots=[];
    for(const size of sizes){
      await page.setViewportSize(size);
      const response=await page.goto(base+'/admin');
      check('Decision workspace loaded '+size.width,response.status()===200);
      const file=path.join(out,'initial-'+size.width+'x'+size.height+'.png');
      await page.screenshot({path:file,fullPage:false});
      screenshots.push(file);report.screenshots.push(file);
      await visibleActions(page,'.admin-workspace-bar nav > a,.admin-heading a,[data-planner-run],.admin-bulk-actions button,.admin-queue > li:nth-child(1 of li:has([name=approve_request])) :is([name=approve_request],.admin-select-request,.admin-queue-actions a)','Admin decisions '+size.width);
    }
    console.log('INSPECT DASHBOARD INITIAL: '+screenshots.join(' | '));
    if(env.QA_INSPECT_FIRST==='admin-dashboard')await new Promise(resolve=>{process.stdin.resume();process.stdin.once('data',()=>{process.stdin.pause();resolve();});});
    const first=page.getByRole('listitem').filter({has:page.getByRole('heading',{name:fixture.titles.held,exact:true})});
    check('Held packet is a compact decision with no exposed submission form',await first.getByRole('link',{name:'Review submission',exact:true}).count()===1&&await page.locator('#human-influence form').count()===0);
    check('Waiting and withdrawn records stay out of decisions',await page.getByText(fixture.titles.waiting,{exact:true}).count()===0&&await page.getByText(fixture.titles.withdrawn,{exact:true}).count()===0);
    await first.getByRole('link',{name:'Review submission',exact:true}).click();
    check('Packet link opens the exact disclosure and safe decision controls',new URL(page.url()).hash==='#item-'+fixture.packet&&await page.locator('#item-'+fixture.packet+' button[value=approve]').isVisible());
    await page.goto(base+'/admin');
    await page.getByRole('link',{name:/Waiting for updates/}).click();
    check('Waiting view retains real deferred decisions',await page.getByText(fixture.titles.waiting,{exact:true}).count()===1&&await page.getByText(fixture.titles.review,{exact:true}).count()===0);
    await page.getByRole('link',{name:'All research',exact:true}).click();
    await page.getByLabel('Search title or ID').fill(fixture.ids.withdrawn);
    await page.getByRole('button',{name:'Find requests',exact:true}).click();
    check('Research catalogue retains withdrawn record inspection',await page.getByRole('row').filter({has:page.getByRole('link',{name:fixture.titles.withdrawn,exact:true})}).innerText().then(text=>text.includes('Withdrawn')));

    await page.goto(base+'/admin');
    const csrf=await page.locator('[data-admin-planner] [name=csrf]').inputValue();
    check('Disconnected state is explicit',await page.getByText('ChatGPT disconnected',{exact:true}).isVisible());
    const guest=await browser.newContext();
    try {
      const response=await guest.request.get(base+'/admin/planner/status',{maxRedirects:0});
      check('Connection status requires an admin session',response.status()===303&&response.headers().location==='/admin/login');
    }finally{await guest.close();}
    check('Planner run route rejects missing CSRF',(await ctx.request.post(base+'/admin/planner/run',{form:{}})).status()===403);
    check('Runner heartbeat rejects failures of authentication',(await api('connection',{signed_in:true},'invalid')).status===401);
    check('A signed-in worker has nothing to do before the admin click',(await api('connection',{signed_in:true})).run_id===null);
    await refresh('ChatGPT connected');
    check('Authenticated worker heartbeat updates the live dashboard',await page.getByText('ChatGPT connected',{exact:true}).isVisible());
    await api('connection',{signed_in:false});
    await refresh('ChatGPT sign-in required');
    check('Signed-out local worker is reported explicitly',await page.getByText('ChatGPT sign-in required',{exact:true}).isVisible());
    await api('connection',{signed_in:true});

    await page.getByRole('button',{name:'Run research plans',exact:true}).click();
    check('Admin click queues the actual two approved requests',await page.getByText('Planning queued for 2 requests.',{exact:true}).isVisible());
    const control=await api('connection',{signed_in:true});
    check('Bounded run identity is issued to the authenticated local listener',/^[a-f0-9]{32}$/.test(control.run_id??''));
    await ctx.request.post(base+'/admin/planner/run',{form:{csrf}});
    check('Duplicate click keeps the same run',(await api('connection',{signed_in:true})).run_id===control.run_id);
    runPhp(['tests/admin-dashboard-state.php','approve-later']);
    const completed=[];
    for(let i=0;i<2;i++){
      const response=await api('claim',{run_id:control.run_id});
      const job=response.job;
      check('Authorized claim returns an approved captured request',response.status===200&&[fixture.ids.one,fixture.ids.two].includes(job?.research_id)&&!completed.includes(job.research_id));
      check('A second scoped worker cannot overtake the active lease',(await api('claim',{run_id:control.run_id})).job===null);
      const finished=await api('finish',{research_id:job.research_id,lease:job.lease,run_id:'013579bd-0246-48ac-81a2-aaaaaaaaaaaa',tasks});
      check('Existing planner endpoint validates and completes the queued request',finished.status===200&&finished.recorded===true);
      completed.push(job.research_id);
    }
    check('Later approvals remain outside the captured run',(await api('claim',{run_id:control.run_id})).job===null&&(await api('connection',{signed_in:true})).run_id===null);
    await page.goto(base+'/research/'+fixture.slug+'/work');
    await page.getByRole('link',{name:'Help with the next task',exact:true}).click();
    check('A real Ready research task opens on the public path',new URL(page.url()).pathname.includes('/tasks/')&&await page.getByRole('heading',{name:tasks[0].title,exact:true}).isVisible());
    await page.goto(base+'/admin');
    check('Pending run button re-enables for later work',await page.getByRole('button',{name:'Run research plans',exact:true}).isEnabled()&&await page.getByText('1 approved request ready to plan.',{exact:true}).isVisible());

    for(const size of [sizes[0],sizes[2]]){
      await page.setViewportSize(size);
      await page.goto(base+'/admin');
      await page.evaluate(()=>{
        const sizes=[...document.querySelectorAll('body,body *')].map(el=>[el,parseFloat(getComputedStyle(el).fontSize)]);
        for(const [el,font] of sizes)if(Number.isFinite(font))el.style.fontSize=font*2+'px';
      });
      const file=path.join(out,'reflow-200-'+size.width+'.png');
      await page.screenshot({path:file,fullPage:false});report.screenshots.push(file);
      check('200% admin text reflows '+size.width,await page.evaluate(()=>document.documentElement.scrollWidth<=innerWidth+1));
    }
    const sessionCookie=(await ctx.cookies(base)).find(cookie=>cookie.name==='llmgiving');
    if(!sessionCookie)throw Error('Expected an authenticated QA browser session.');
    const sessionActivity=mode=>JSON.parse(runPhp(['tests/admin-dashboard-state.php','session-activity',mode],sessionCookie.value));
    const idleSince=sessionActivity('idle');
    const pollStatuses=[];
    for(let i=0;i<3;i++){
      const response=await ctx.request.get(base+'/admin/planner/status',{maxRedirects:0});
      pollStatuses.push(response.status());
    }
    check('Repeated successful planner polls leave the idle deadline unchanged',pollStatuses.every(status=>status===200)&&sessionActivity('read')===idleSince);
    const navigation=await page.goto(base+'/admin');
    check('Real admin navigation renews the idle deadline',navigation.status()===200&&sessionActivity('read')>idleSince);

    const expiredCsrf=await page.locator('[data-admin-planner] [name=csrf]').inputValue();
    sessionActivity('expired');
    const expiredPoll=await ctx.request.get(base+'/admin/planner/status',{maxRedirects:0});
    check('Expired admin session cannot read planner status',expiredPoll.status()===303&&expiredPoll.headers().location==='/admin/login');
    const expiredRun=await ctx.request.post(base+'/admin/planner/run',{form:{csrf:expiredCsrf},maxRedirects:0});
    check('Expired admin session cannot queue planning with its prior CSRF token',expiredRun.status()===403&&(await api('connection',{signed_in:true})).run_id===null);
    await page.goto(base+'/admin/login');
    await page.getByLabel('Email').fill('admin-dashboard-qa@example.invalid');
    await page.getByLabel('Password').fill(password);
    await page.getByRole('button',{name:'Sign in',exact:true}).click();
    await page.waitForURL(base+'/admin');
    const approveRow=title=>page.getByRole('listitem').filter({has:page.getByRole('heading',{name:title,exact:true})});
    const states=()=>JSON.parse(runPhp(['tests/admin-dashboard-state.php','approval-status']));
    const approvalCsrf=await page.locator('.admin-approvals [name=csrf]').inputValue();
    const staleSnapshot=await page.locator('.admin-approvals [name=snapshot]').inputValue();
    check('Bulk approval requires CSRF',(await ctx.request.post(base+'/admin/requests/approve',{form:{approval:'all'}})).status()===403);
    check('Flagged requests and held submissions have no quick approval',await approveRow(fixture.titles.flagged).getByRole('button',{name:'Approve',exact:true}).count()===0&&await approveRow(fixture.titles.held).getByRole('checkbox').count()===0);
    await api('connection',{signed_in:false});
    await approveRow(fixture.titles.review).getByRole('button',{name:'Approve',exact:true}).click();
    check('One-click approval records a private automatic plan while signed out',await page.getByText('1 research request approved. 1 research plan queued.',{exact:true}).isVisible()&&states().review.queued&&(await api('connection',{signed_in:false})).run_id===null);
    const firstAuto=await api('connection',{signed_in:true});
    check('Reconnecting takes the approved request without the manual run action',/^[a-f0-9]{32}$/.test(firstAuto.run_id??''));
    const firstJob=(await api('claim',{run_id:firstAuto.run_id})).job;
    check('Automatic dispatch excludes earlier manually queued work',firstJob?.research_id===fixture.ids.review);

    await page.getByRole('checkbox',{name:'Select '+fixture.titles.selected,exact:true}).check();
    await page.getByRole('button',{name:'Approve Selected',exact:true}).click();
    check('Approve Selected leaves unselected requests alone',states().selected.queued&&states().detail.moderation==='Pending Approval');
    await approveRow(fixture.titles.detail).getByRole('link',{name:'Review request',exact:true}).click();
    await page.getByRole('button',{name:'Approve and prepare tasks',exact:true}).click();
    check('Approval from request details queues the plan',await page.getByText('Research request approved. Research plan queued.',{exact:true}).isVisible()&&states().detail.queued);
    check('Approvals during a plan do not interrupt its lease',(await api('connection',{signed_in:true})).run_id===firstAuto.run_id&&(await api('claim',{run_id:firstAuto.run_id})).job===null);

    const result=await api('finish',{research_id:firstJob.research_id,lease:firstJob.lease,run_id:'013579bd-0246-48ac-81a2-aaaaaaaaaaaa',tasks});
    check('Automatically dispatched plan becomes real Ready research',result.recorded===true&&states().review.status==='Open for Research');
    check('The first run retains its original scope',(await api('claim',{run_id:firstAuto.run_id})).job===null);
    const nextAuto=(await api('connection',{signed_in:true})).run_id;
    const nextJob=(await api('claim',{run_id:nextAuto})).job;
    check('Approval during an active run is picked up automatically in a later run',nextAuto!==firstAuto.run_id&&[fixture.ids.selected,fixture.ids.detail].includes(nextJob?.research_id));

    await page.goto(base+'/admin');
    const invalid=await ctx.request.post(base+'/admin/requests/approve',{form:{csrf:approvalCsrf,approve_request:fixture.ids.flagged}});
    check('Direct quick-approve attempts also leave flagged requests untouched',invalid.ok()&&states().flagged.moderation==='Needs Additional Review');
    const beforeBulk=JSON.parse(runPhp(['tests/admin-dashboard-state.php','add-bulk']));
    const stale=await ctx.request.post(base+'/admin/requests/approve',{form:{csrf:approvalCsrf,approval:'all',snapshot:staleSnapshot}});
    check('Changing the queue invalidates an earlier Approve All snapshot',await stale.text().then(text=>text.includes('The queue changed.'))&&states()['bulk-1'].moderation==='Pending Approval');
    await page.goto(base+'/admin');
    check('Approve All count covers requests beyond this page',beforeBulk.length===21&&await page.getByRole('button',{name:'Approve All (21)',exact:true}).isVisible()&&await page.getByRole('navigation',{name:'Decision pages'}).count()===1);
    await page.getByRole('button',{name:'Approve All (21)',exact:true}).click();
    const afterBulk=states();
    check('Approve All queues every routine request across pages',await page.getByText('21 research requests approved. 21 research plans queued.',{exact:true}).isVisible()&&Object.entries(afterBulk).filter(([key,record])=>key.startsWith('bulk-')&&record.queued&&record.moderation==='Approved').length===21);
    check('Bulk approval preserves individual decisions',afterBulk.flagged.moderation==='Needs Additional Review'&&afterBulk.waiting.moderation==='Changes Requested'&&await page.getByRole('link',{name:'Review submission',exact:true}).count()===1);
    check('No dashboard script exceptions',errors.length===0);
    report.adminPlannerScope='Real isolated admin surface and authenticated HTTP dispatch, using synthetic worker plans and heartbeat states. No production access or inference.';
  }finally{
    if(fixture)runPhp(['tests/admin-dashboard-state.php','cleanup']);
  }
}
