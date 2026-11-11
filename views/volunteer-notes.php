<?php $base='/volunteer/'.$session['id'];$labels=['hypothesis'=>'Working hypothesis','findings'=>'Findings so far','attempted_approaches'=>'Approaches tried','open_questions'=>'Open questions','next_step'=>'Next useful step']; ?>
<div class="volunteer-start">
<h1>Resume saved research</h1>
<p>Private working notes for this assignment. Saving does not submit a result or extend permission.</p>
<?php if($error):?><p class="warning" role="alert"><?=e($error)?></p><?php endif ?>
<?php if($saved):?><p role="status">Research notes saved. No result was submitted.</p><?php endif ?>
<form method="post">
<?=csrf()?><input type="hidden" name="version" value="<?=e($notes['version'])?>">
<div class="actions primary-actions" data-notes-actions><button>Save research notes</button><a class="button secondary" href="<?=e($base)?>/assignments/<?=e($assignment['id'])?>">Continue assignment</a><a class="button secondary" href="<?=e($base)?>">Session dashboard</a></div>
<?php foreach($labels as $field=>$label):?><label for="note-<?=e($field)?>"><?=e($label)?></label><textarea id="note-<?=e($field)?>" name="<?=e($field)?>" rows="2" maxlength="4000"><?=e($notes['notes'][$field])?></textarea><?php endforeach ?>
</form>
<?php if($unsaved):?><section><h2>Your unsaved changes</h2><p>The form above shows the latest saved version. Compare your changes below before editing it; nothing was overwritten.</p><?php foreach($unsaved as $field=>$value):?><label for="unsaved-<?=e($field)?>"><?=e($labels[$field])?></label><textarea id="unsaved-<?=e($field)?>" readonly><?=e($value)?></textarea><?php endforeach ?></section><?php endif ?>
<?php if($notes['expires_at_utc']):?><p>Saved-work window ends <?=e($notes['expires_at_utc'])?> UTC. Copy any notes you need to retain.</p><?php endif ?>
<p>These notes are unfinished research, not accepted evidence. Continue only after checking current task, lease, consent and session state. After Stop or expiry, use the existing private recovery flow for fresh human authorization.</p>
</div>
