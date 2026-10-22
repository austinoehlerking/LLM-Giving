<?php $support=$researchEvidence['supporting_materials']??null; ?>
<section id="supporting-materials"><h2>Supporting materials</h2>
<?php if(!$support): ?><p>Historical record: no structured supporting-material inventory was collected. Completeness has not been inferred.</p>
<?php else: ?><p>Work performed: <?=e(str_replace('_',' ',$support['work_performed']))?>. Materials and access declarations require independent review.</p>
<?php foreach($support['items'] as $material): ?><article class="ledger-entry"><h3><?=e(ucwords(str_replace('_',' ',$material['category'])))?> · <?=e(str_replace('_',' ',$material['status']))?></h3><?=prose($material['details'])?><?php if($material['access_instructions']): ?><p><strong>Access and reproduction:</strong> <?=e($material['access_instructions'])?></p><?php endif ?><ul><?php foreach($material['refs'] as $ref): ?><li><?=e($ref)?></li><?php endforeach ?></ul></article><?php endforeach ?>
<?php if($support['files']): ?><h3>Retained supporting files</h3><ul><?php foreach($support['files'] as $file): ?><li><?=e($file['name'])?> · <?=e($file['rights'])?><?php if($admission==='accepted'): try{rs_read($r['id'],$result['id']); ?><a href="<?=e($base)?>/results/<?=e($result['id'])?>/resources/files/<?=e($file['id'])?>">Download inert text</a><?php }catch(HttpError){} endif ?></li><?php endforeach ?></ul><?php endif ?>
<?php endif ?>
<?php foreach($researchEvidence['material_reviews']??[] as $audit): ?><article class="task-brief"><h3>Independent material audit · <?=e($audit['verdict'])?></h3><a href="<?=e($base)?>/results/<?=e($audit['result_id'])?>#supporting-materials">Audited result</a><?=prose($audit['reason'])?><p>Inspected references: <?=e(implode(', ',$audit['checked_refs']))?></p></article><?php endforeach ?>
</section>
