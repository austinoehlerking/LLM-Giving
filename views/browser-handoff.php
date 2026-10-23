<?php $base='/research/'.$r['slug'];$task=$issued['task']??null;$grant=$issued['grant']??null;$reservationMessage??=''; ?>
<div class="return-start"><h1>Give this handoff to your AI</h1>
<?php if(!empty($error)): ?><p class="warning" role="alert"><?=e($error)?></p><?php endif ?>
<?php if($task): ?><form id="pickup-handoff" method="post" action="<?=e($base)?>/pickup/return/<?=e($issued['id'])?>" data-reservation-form><?=csrf()?><input type="hidden" name="task_revision" value="<?=e($grant['scope']['revision']??$task['revision'])?>"><?php $reservationForm='pickup-handoff';include __DIR__.'/task-reservation.php'; ?><?php endif ?>
<div class="actions primary-actions"><button type="button" hidden data-copy-pickup="authorized-handoff">Copy AI handoff</button><a class="button secondary" href="<?=e($base)?><?=e($task?'/tasks/'.$task['id']:'')?>"><?=e($task?'Manage task':'Back to research')?></a></div>
<p class="fine" data-pickup-status role="status">The complete handoff is ready below. Save it privately. Recopying keeps the existing reservation and expiry.</p>
<label for="authorized-handoff">Complete handoff with private authorization supplement</label><textarea id="authorized-handoff" name="pickup_handoff" readonly rows="10"><?=e($issued['handoff'])?></textarea>
<?php if($task): ?></form><?php endif ?>
<p class="fine" data-pickup-help>Your AI uses its private return link to submit with browser control and report the recorded receipt. Keep that link out of public research. This page is shown once; save the complete text privately. New authorization is required to recover a lost private link.</p>
<?php if($issued['archive_id']): ?><p><a data-issued-archive href="<?=e($base)?>/issued/<?=e($issued['archive_id'])?>">Read archived research instructions (without private authorization)</a></p><?php endif ?>
<p class="fine"><?=e(CONTRIBUTOR_RESOURCE_CONFIRMATION)?> Your confirmation is self-attested; this handoff does not grant resource, installation or spending permission.</p>
</div>
