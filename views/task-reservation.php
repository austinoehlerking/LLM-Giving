<?php $reservation=mp_state($r,$task,$grant['actor_hash']??null);
if(!empty($grant)){try{ar_validate($r,$grant);}catch(HttpError $e){$reservation=['eligible'=>false,'owned'=>false,'available'=>false,'expired'=>false,'message'=>$e->getMessage().' Keep your entered work; do not replace signed identifiers.'];}}
?>
<aside class="task-reservation" data-reservation-panel>
<p class="fine" data-reservation-status role="status" aria-live="polite"><strong><?=e($reservation['message'])?></strong></p>
<?php if(!empty($reservationMessage)): ?><p class="fine" data-reservation-message><?=e($reservationMessage)?></p><?php endif ?>
<div class="actions reservation-actions"><?php if($reservation['owned']): ?><button class="secondary" form="<?=e($reservationForm)?>" name="operation" value="reservation-renew" formnovalidate>Renew reservation</button><button class="secondary" form="<?=e($reservationForm)?>" name="operation" value="reservation-release" formnovalidate>Release task</button>
<?php elseif($reservation['available']): ?><button class="secondary" form="<?=e($reservationForm)?>" name="operation" value="reservation-claim" formnovalidate><?=e($reservation['expired']||!empty($grant)?'Reacquire reservation':'Reserve task')?></button><?php endif ?>

<?php if($reservation['eligible']&&!$reservation['owned']): ?><button class="secondary" form="<?=e($reservationForm)?>" name="operation" value="reservation-status" formnovalidate>Check availability</button><?php endif ?></div>
<?php if(!empty($reservationHandoff)): ?><details open><summary>Complete research handoff (private authorization stays separate)</summary><textarea readonly rows="8" aria-label="Reservation research handoff"><?=e($reservationHandoff)?></textarea></details><?php endif ?>
</aside>
