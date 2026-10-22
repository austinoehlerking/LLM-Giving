<?php $fork=$fork??rf_context($r['id']);if($fork): ?>
<section class="fork-background" data-fork-background aria-label="Inherited research background">
<?php if(!$fork['available']): ?><p class="warning"><?=e($fork['notice'])?></p>
<?php else: $bg=$fork['background']; ?>
<p class="fork-origin"><strong>Forked from</strong> <a href="/research/<?=e($fork['parent']['slug'])?>"><?=e($fork['parent']['title'])?></a> · Context v<?=e($fork['context_version'])?></p>
<p class="fine">Inherited background, not new results. This request has its own review, management link, plan and findings.</p>
<?php foreach($fork['warnings'] as $warning): ?><p class="warning"><?=e($warning)?></p><?php endforeach ?>
<details><summary>Review exactly what is inherited (<?=count($bg['contributions'])+count($bg['results'])?> research records, <?=count($bg['findings'])?> findings)</summary>
<?php $renderBackground=static function(array $bg)use(&$renderBackground){require ROOT.'/views/fork-records.php';};$renderBackground($bg); ?>
<dl class="metadata"><dt>Source research ID</dt><dd><?=e($fork['parent']['id'])?></dd><dt>Context version</dt><dd><?=e($fork['context_version'])?></dd><dt>Immutable context ID</dt><dd><?=e($fork['snapshot_id'])?></dd><dt>Context SHA-256</dt><dd><?=e($fork['snapshot_sha256'])?></dd><dt>Background SHA-256</dt><dd><?=e($fork['background_sha256'])?></dd><dt>Captured (UTC)</dt><dd><?=e($fork['captured_at'])?></dd></dl>
<p class="fine">Later source changes are flagged above; this version is never silently replaced. Source removal or revocation blocks public access to this fork and its inherited packages and downloads.</p>
</details>
<?php endif ?></section>
<?php endif;unset($fork); ?>
