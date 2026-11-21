const panel=document.querySelector('[data-admin-planner]');
if(panel) {
  const connection=panel.querySelector('[data-planner-connection]');
  const work=panel.querySelector('[data-planner-work]');
  const run=panel.querySelector('[data-planner-run]');
  let loading=false;
  let stopped=false;
  async function refresh() {
    if(loading||stopped||document.hidden)return;
    loading=true;
    try {
      const response=await fetch('/admin/planner/status',{cache:'no-store',redirect:'error',signal:AbortSignal.timeout(8000)});
      if(!response.ok)throw new Error('Planner status unavailable');
      const status=await response.json();
      const labels={'connected':'ChatGPT connected','sign-in-required':'ChatGPT sign-in required','disconnected':'ChatGPT disconnected'};
      if(!Object.hasOwn(labels,status.connection))throw new Error('Unexpected planner status');
      connection.dataset.state=status.connection;
      connection.textContent=labels[status.connection];
      work.textContent=status.label;
      run.disabled=!status.configured||status.active||!status.pending;
    } catch {
      connection.dataset.state='unknown';
      connection.textContent='Connection status unavailable';
      work.textContent='Refresh this page to check the planner.';
    } finally { loading=false; }
  }
  const timer=setInterval(refresh,12000);
  document.addEventListener('visibilitychange',refresh);
  window.addEventListener('pagehide',()=>{stopped=true;clearInterval(timer);},{once:true});
}
