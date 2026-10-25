/** Structural controller-side diagnostic only. Inputs are caller-supplied native-record projections.
 * Never authenticates their origin/provider-effective settings, grants consent or replaces site policy.
 * No I/O, model calls, logging, research computation or credential persistence. */
export function checkConfiguredLaunch(record, expected) {
 const problems=[];const object=value=>value!==null&&typeof value==='object'&&!Array.isArray(value);
 const text=value=>typeof value==='string'&&value.trim()!==''&&value.length<=200;
 if(!object(record)||!object(expected))return result(['Supply a native record and expected assignment.']);
 if(record.origin!=='native_controller_record')problems.push('Bare prompt, packet or human assertions are not native launch evidence.');
 if(record.provenance_claim!==undefined&&record.provenance_claim!=='configured_launch')problems.push('Launch admission cannot establish provider-verified/effective settings.');
 for(const field of ['task_id','context_id'])if(!text(expected[field])||record.assignment?.[field]!==expected[field])problems.push('Missing, stale or mismatched assignment '+field+'.');
 if(!Number.isInteger(expected.task_revision)||expected.task_revision<1||record.assignment?.task_revision!==expected.task_revision)problems.push('Missing, stale or mismatched task revision.');
 if(!text(expected.model)||!text(expected.thinking))problems.push('Expected exact model/thinking is required; this helper does not choose website policy.');
 const create=record.create;
 const settings=request=>object(request)&&text(request.model)&&text(request.thinking)&&request.model===expected.model&&request.thinking===expected.thinking;
 if(!settings(create?.request))problems.push('Original request must explicitly select the expected exact model/thinking.');
 const response=create?.response;
 const admitted=value=>object(value)&&value.isError===false&&value.admissionOutcome==='started'&&text(value.threadId)&&text(value.turnId);
 if(!admitted(response))problems.push('Original native create needs its successful started admission and task/turn IDs.');
 const conflicts=value=>object(value)&&((value.model!==undefined&&value.model!==expected.model)||(value.thinking!==undefined&&value.thinking!==expected.thinking));
 if(conflicts(response))problems.push('Returned settings conflict with the explicit launch request.');
 const returnedTurns=new Set(text(response?.turnId)?[response.turnId]:[]);let activeTurn=response?.turnId;
 if(expected.worker_thread_id!==undefined&&(!text(expected.worker_thread_id)||response?.threadId!==expected.worker_thread_id))problems.push('The original admission belongs to a different worker.');
 if(record.observed_fallback===true||create?.request?.fallback===true||response?.fallback===true)problems.push('Known fallback does not qualify.');
 const continuations=record.continuations??[];let pending=false;
 if(!Array.isArray(continuations))problems.push('Continuation records must be an array.');
 else for(const [index,turn]of continuations.entries()){
  if(!object(turn)||!settings(turn.request)||turn.request.threadId!==response?.threadId){problems.push('Continuation '+index+' must address the same worker with explicit unchanged model/thinking.');continue;}
  if(turn.request.fallback===true||turn.response?.fallback===true)problems.push('Continuation '+index+' records fallback.');
  // A future response cannot be required before sending that explicit same-settings follow-up.
  if(turn.response==null){pending=true;if(index!==continuations.length-1)problems.push('Only the final outgoing follow-up may await its response.');continue;}
  const received=turn.response;const sameWorker=object(received)&&received.isError===false&&received.threadId===response?.threadId&&text(received.turnId)&&!conflicts(received);
  if(received?.admissionOutcome==='steered'){
   // Native input can be admitted into the already-active turn; it is not a fresh turn.
   if(!sameWorker||received.turnId!==activeTurn||!returnedTurns.has(received.turnId))problems.push('Continuation '+index+' steering must bind the already admitted active turn.');
  }else if(admitted(received)&&sameWorker&&!returnedTurns.has(received.turnId)){returnedTurns.add(received.turnId);activeTurn=received.turnId;}
  else problems.push('Continuation '+index+' failed or has a stale/mismatched returned task/turn/settings.');
 }
 return result(problems,pending);
 function result(errors,pendingFollowup=false){return {qualifiesConfiguredLaunch:errors.length===0,canSendSameSettingsFollowup:errors.length===0,pendingFollowup,basis:errors.length?'':'self-reported controller-recorded configured launch',providerEffectiveReadback:'unavailable',providerVerified:false,problems:errors};}
}
