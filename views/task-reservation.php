<?php
if(empty($grant))$reservation=mp_state($r,$task);
else{
    try{
        // Use the current scoped capability, never ownership inferred only from the actor's live lease.
        $authority=sr_authority($r,$grant['id']);$grant=$authority['grant'];$status=$authority['status'];
        if($status['recorded'])fail(409,'This authorization already has a recorded receipt.');
        $owned=$status['can_renew'];$available=$status['can_reacquire']&&!$owned;
        $message=match(true){
            $owned=>'Reserved for you until '.$status['lease_expires_at'].' UTC',
            $available=>'No active reservation. Reacquire this assignment before submitting; your entered work and signed identifiers are unchanged.',
            $status['permission']!=='active'=>$status['permission_reason'],
            $status['lease']==='other_holder'=>'In progress — reserved until '.$status['lease_expires_at'].' UTC. This assignment cannot displace its holder.',
            $status['lease']==='other_assignment'=>'A different assignment now holds this task. This authorization cannot manage its reservation.',
            default=>'The original reservation is unavailable or cannot be proven. Use private recovery controls; do not replace signed identifiers.'
        };
        $reservation=['eligible'=>$owned||$available,'owned'=>$owned,'available'=>$available,'expired'=>$status['lease']==='expired','message'=>$message];
    }catch(HttpError $e){$reservation=['eligible'=>false,'owned'=>false,'available'=>false,'expired'=>false,'message'=>$e->getMessage().' Keep your entered work; do not replace signed identifiers.'];}
}
?>
<aside class="task-reservation" data-reservation-panel>
<p class="fine" data-reservation-status role="status" aria-live="polite"><strong><?=e($reservation['message'])?></strong></p>
<?php if(!empty($reservationMessage)): ?><p class="fine" data-reservation-message><?=e($reservationMessage)?></p><?php endif ?>
<div class="actions reservation-actions"><?php if($reservation['owned']): ?><button class="secondary" form="<?=e($reservationForm)?>" name="operation" value="reservation-renew" formnovalidate>Renew reservation</button><button class="secondary" form="<?=e($reservationForm)?>" name="operation" value="reservation-release" formnovalidate>Release task</button>
<?php elseif($reservation['available']): ?><button class="secondary" form="<?=e($reservationForm)?>" name="operation" value="reservation-claim" formnovalidate><?=e($reservation['expired']||!empty($grant)?'Reacquire reservation':'Reserve task')?></button><?php endif ?>

<?php if(!empty($grant)&&empty($grant['result_id'])):?><button class="secondary" form="<?=e($reservationForm)?>" formaction="/research/<?=e($r['slug'])?>/return/<?=e($grant['id'])?>/continue" formmethod="post" formnovalidate>Continue this reserved task with my AI</button><?php endif ?>
<?php if(!empty($grant['result_id'])): ?><a class="button secondary" href="<?=e(ar_receipt_url($r,$grant,$grant['result_id']))?>">Open recorded receipt</a><?php endif ?>
<?php if($reservation['eligible']&&!$reservation['owned']): ?><button class="secondary" form="<?=e($reservationForm)?>" name="operation" value="reservation-status" formnovalidate>Check availability</button><?php endif ?></div>
<?php if(!empty($reservationHandoff)): ?><details open><summary>Complete research handoff (private authorization stays separate)</summary><textarea readonly rows="8" aria-label="Reservation research handoff"><?=e($reservationHandoff)?></textarea></details><?php endif ?>
</aside>
