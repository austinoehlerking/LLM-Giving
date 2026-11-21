<section class="admin-panel admin-planner" aria-labelledby="planner-heading" data-admin-planner>
    <div class="admin-planner-info">
        <h2 id="planner-heading">Research planner</h2>
        <div>
            <p class="admin-connection" data-planner-connection data-state="<?=e($plannerStatus['connection'])?>"><?=e(match($plannerStatus['connection']){
                'connected'=>'ChatGPT connected','sign-in-required'=>'ChatGPT sign-in required',default=>'ChatGPT disconnected'
            })?></p>
            <p class="admin-planner-work" data-planner-work><?=e(admin_planner_line($plannerStatus))?></p>
        </div>
    </div>
    <div class="admin-planner-actions">
        <form method="post" action="/admin/planner/run"><?=csrf()?><button data-planner-run <?=(!$plannerStatus['configured']||$plannerStatus['active']||!$plannerStatus['pending'])?'disabled':''?>>Run research plans</button></form>
        <details class="admin-planner-setup"><summary>Connection setup</summary>
            <div><p>On the computer that runs Codex, sign in with ChatGPT and start the listener from your LLM.Giving checkout. Keep it running to process plans automatically after approval.</p>
            <pre>codex login
node tools/local-planner.mjs --listen</pre>
            <p>The listener uses the planner credential already configured on that computer.</p></div>
        </details>
    </div>
</section>
