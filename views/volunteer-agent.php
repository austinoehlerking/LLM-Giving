<?php $base='/volunteer/'.$session['id'];$state=vs_state($session); ?>
<div class="volunteer-start">
<h1>Connect your agent</h1>
<p>Give your AI one message to work through this session’s API.</p>
<?php if($error):?><p class="warning" role="alert"><?=e($error)?></p><?php endif ?>
<div class="actions primary-actions" data-agent-actions>
<?php if($prompt):?><button type="button" data-copy-input="agent-start">Copy start message</button><?php endif ?>
<?php if(in_array($state,['active','limit_reached'],true)):?><form method="post"><?=csrf()?><button name="operation" value="enable"><?= $enabled?'Replace agent connection':'Enable agent access' ?></button></form><?php endif ?>
<?php if($enabled):?><form method="post"><?=csrf()?><button class="secondary" name="operation" value="revoke">Revoke agent access</button></form><?php endif ?>
<a class="button secondary" href="<?=e($base)?>">Session dashboard</a>
</div>

<?php if($prompt):?><label for="agent-start">Private start message — copy and send to your AI</label><textarea id="agent-start" rows="5" readonly autocomplete="off" spellcheck="false"><?=e($prompt)?></textarea><p>Save this message privately. It is shown only now; replacing the connection invalidates the previous message.</p><?php else:?><p role="status"><?= $enabled?'Agent access is enabled.':'Agent access is not enabled.' ?></p><?php endif ?>
<p><?=e(vs_limit_label($session))?>. <?=e(vs_scope_label($session))?>.</p>
<p>Enabling grants API access to this existing session’s work and saved notes. Your consent, worker requirements, task limits and independent-review checks still apply. Stop ends permission to work; revoking disconnects the agent.</p>
<p>Your AI needs HTTPS request tools. No browser control, account signup or installation is needed for API participation. Its research tools must still match the task.</p>
<p><a href="/agents/guide">Read the agent guide</a></p>
</div>
