'use strict';
document.querySelectorAll('[data-artifact-widget]').forEach(widget=>{
 const states=[...widget.querySelectorAll('[data-ra-state]')],select=widget.querySelector('[data-ra-select]'),range=widget.querySelector('[data-ra-range]'),previous=widget.querySelector('[data-ra-prev]'),next=widget.querySelector('[data-ra-next]'),play=widget.querySelector('[data-ra-play]'),reset=widget.querySelector('[data-ra-reset]'),status=widget.querySelector('[data-ra-status]'),motion=widget.querySelector('[data-ra-motion]');
 if(states.length<2)return;
 const reduced=matchMedia('(prefers-reduced-motion: reduce)');let index=0,timer=null;
 const labels=states.map(state=>state.querySelector('h2')?.textContent||'State');
 const pause=()=>{if(timer!==null)clearInterval(timer);timer=null;if(play){play.textContent='Play';play.setAttribute('aria-pressed','false');}};
 const show=value=>{index=Math.max(0,Math.min(states.length-1,Number(value)||0));states.forEach((state,i)=>state.hidden=i!==index);if(select)select.value=String(index);if(range)range.value=String(index);if(previous)previous.disabled=index===0;if(next)next.disabled=index===states.length-1;if(status)status.textContent=labels[index]+' · '+(index+1)+' of '+states.length;};
 const updateMotion=()=>{if(play){play.disabled=reduced.matches;if(reduced.matches)pause();}if(motion)motion.hidden=!reduced.matches;};
 [select,range,previous,next,play,reset].filter(Boolean).forEach(control=>control.disabled=false);
 select?.addEventListener('change',()=>{pause();show(select.value);});range?.addEventListener('input',()=>{pause();show(range.value);});previous?.addEventListener('click',()=>{pause();show(index-1);});next?.addEventListener('click',()=>{pause();show(index+1);});reset?.addEventListener('click',()=>{pause();show(0);});
 play?.addEventListener('click',()=>{if(timer!==null){pause();return;}if(reduced.matches)return;if(index===states.length-1)show(0);play.textContent='Pause';play.setAttribute('aria-pressed','true');timer=setInterval(()=>{show(index+1);if(index===states.length-1)pause();},1400);});
 document.addEventListener('visibilitychange',()=>{if(document.hidden)pause();});reduced.addEventListener('change',updateMotion);show(0);updateMotion();
});
