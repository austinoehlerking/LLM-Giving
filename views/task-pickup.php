<?php $base='/research/'.$r['slug']; ?>
<div class="return-start"><a class="back" href="<?=e($base)?>/work">← Research tasks</a><h1><?=e($archive?'Task handoff':'No task picked up')?></h1>
<?php if($error): ?><p class="warning" role="alert"><?=e($error)?> No new pickup succeeded.</p><?php endif ?>
<?php if($error&&$task): ?><p class="fine"><?=e(mp_state($r,$task)['message'])?></p><a href="<?=e($base)?>/tasks/<?=e($task['id'])?>">Return to task and manage any existing reservation</a><?php endif ?>
<?php if($archive&&$task): $grant=null;$reservationForm='pickup-handoff'; ?>
<form id="pickup-handoff" method="post" action="<?=e($base)?>/tasks/<?=e($task['id'])?>/pickup" data-reservation-form>
<?=csrf()?><input type="hidden" name="task_revision" value="<?=e($task['revision'])?>"><input type="hidden" name="handoff_id" value="<?=e($archive['id'])?>">
<?php include __DIR__.'/task-reservation.php'; ?>
<div class="actions primary-actions"><button type="button" hidden data-copy-pickup="pickup-text">Copy task prompt</button><a class="button secondary" href="<?=e($base)?>/tasks/<?=e($task['id'])?>">Return to task</a></div>
<p class="fine" data-pickup-status role="status">The complete handoff is ready for copying below. Recopying does not renew. Manual copying is operational activity; only manual result delivery is labeled “Manually submitted”.</p>
<label for="pickup-text">Complete task handoff</label><textarea id="pickup-text" readonly rows="10"><?=e($archive['body'])?></textarea>
</form><p class="fine">A reservation does not authorize AI browser submission. <a href="<?=e($base)?>/authorize/<?=e($task['id'])?>">Authorize your AI</a> if it will return the result using a separate browser. <a href="<?=e($base)?>/issued/<?=e($archive['id'])?>">Read archived research instructions</a>.</p>
<?php else: ?><p>Nothing has been assigned. Read the workflow or choose another available study.</p><a class="button" href="<?=e($base)?>/work">View task availability</a><?php endif ?>
</div>
