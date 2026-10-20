<?php $finding=wf_findings($r['id']);if($finding): $findingMeta=json_decode($finding['metadata'],true);$overview=wf_findings_overview($finding); ?>
<section class="task-brief findings-panel" id="research-findings">
<div class="row-top"><span class="category">Research findings</span><span class="status"><?=e($r['status']==='Review Complete'?'Review cycle complete':'Awaiting independent verification')?></span></div>
<h2>What the research found</h2>

<p class="fine">Updated <?=e($finding['created_at'])?> UTC · Contributor assessment, not established scientific fact.</p>
<div class="findings-summary"><h3>Summary</h3><?=prose($overview['summary'])?></div>
<?php if($overview['takeaways']): ?><h3>Key takeaways</h3><ul class="findings-takeaways"><?php foreach($overview['takeaways'] as $takeaway): ?><li><?php if($takeaway['title']): ?><strong><?=e($takeaway['title'])?></strong><?php endif ?><span><?=e($takeaway['text'])?></span></li><?php endforeach ?></ul><p class="fine">Selected passages from the synthesis. Read the full research for qualifications and supporting evidence.</p><?php endif ?>
<?php $citationResult=$finding;include __DIR__.'/citation.php'; ?>
<nav class="actions findings-navigation" aria-label="Research details"><a class="button secondary" href="#full-research">Full research</a><a class="button secondary" href="#research-sources">Sources</a><a class="button secondary" href="<?=e($base)?>/results/<?=e($finding['id'])?>">Acceptance checks &amp; review record</a><a class="text-action" href="<?=e($base)?>/ledger">Evidence history</a></nav>
<details id="full-research" class="findings-detail research-disclosure"><summary>Full research</summary><?=prose($finding['body'])?><h3>How it was investigated</h3><?=prose($finding['methodology'])?></details>
<details id="research-sources" class="findings-detail research-disclosure"><summary>Sources &amp; search record</summary><?=prose($finding['sources'])?><p><a href="<?=e($base)?>/results/<?=e($finding['id'])?>#research-evidence">Read claim-to-source evidence and source audits</a></p></details>
<details id="research-limitations" class="findings-detail research-disclosure"><summary>Limitations</summary><?=prose($finding['limitations'])?></details>
<details id="research-next-steps" class="findings-detail research-disclosure"><summary>Recommended next steps</summary><?=prose($finding['next_action'])?></details>
<?php if(empty($findingMeta['acceptance_checks'])): ?><p class="notice">This result predates evidence-linked acceptance checks. Its original review remains in the history; no new checks have been inferred.</p><?php endif ?>
<?php $hasReview=false; foreach(wf_tasks($r['id']) as $reviewTask): if($reviewTask['kind']!=='review'||$reviewTask['state']!=='Completed'||$reviewTask['visibility']!=='public')continue;$reviewResult=one("SELECT id,reason,created_at FROM workflow_submissions WHERE id=? AND research_id=? AND visibility='public'",[$reviewTask['accepted_id'],$r['id']]);if(!$reviewResult)continue; if(!$hasReview): $hasReview=true; ?><section class="findings-detail"><h3>Independent review</h3><?php endif ?><p><a href="<?=e($base)?>/results/<?=e($reviewResult['id'])?>"><?=e($reviewResult['reason'])?></a> · <?=e($reviewResult['created_at'])?> UTC</p><?php endforeach; if($hasReview): ?></section><?php endif ?>
</section><?php endif ?>
