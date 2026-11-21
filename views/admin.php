<div class="admin-dashboard">
<div class="admin-heading"><h1>Research admin</h1><a class="button secondary" href="/submit">New request</a></div>
<?php if($adminSection==='catalogue'): ?>
    <?php require __DIR__.'/admin-research-list.php'; ?>
<?php else: ?>
    <?php require __DIR__.'/admin-planner.php'; ?>
    <section id="queue" class="admin-panel">
        <header class="admin-panel-heading">
            <h2>Decisions</h2>
            <nav class="admin-switches" aria-label="Decision status">
                <a href="/admin#queue" <?=$adminWaiting?'':'aria-current="page"'?>>Needs review <span><?=e($decisions['review'])?></span></a>
                <a href="/admin?queue=waiting#queue" <?=$adminWaiting?'aria-current="page"':''?>>Waiting for updates <span><?=e($decisions['waiting'])?></span></a>
            </nav>
        </header>
        <?php if(!$decisions['items']&&!$decisions['held']): ?>
            <p class="admin-empty"><?=$adminWaiting?'No requests are waiting for changes.':'No decisions are waiting.'?></p>
        <?php else: ?>
        <ul class="admin-queue">
            <?php foreach($decisions['held'] as $flag): ?>
            <li>
                <span class="admin-decision-type admin-hold"><?=$flag['admission']==='quarantined'?'Packet on hold':'Historical packet'?></span>
                <div><h3><?=e($flag['title'])?></h3><time><?=e(substr($flag['created_at'],0,16))?> UTC</time></div>
                <a class="button secondary" href="/admin/research/<?=e($flag['research_id'])?>?decision=packet#item-<?=e($flag['id'])?>">Review packet</a>
            </li>
            <?php endforeach ?>
            <?php foreach($decisions['items'] as $item): $kind=$item['kind']; ?>
            <li>
                <span class="admin-decision-type<?= $item['moderation']==='Needs Additional Review'?' admin-hold':''?>"><?=e(ucfirst($kind))?></span>
                <div><h3><?=e($item['title']?:'Proposed update')?></h3><time><?=e(substr($item['created_at'],0,16))?> UTC</time><?php if($item['moderation']==='Needs Additional Review'): ?> · <span class="admin-flag-label">Flagged for review</span><?php endif ?></div>
                <a class="button secondary" href="/admin/research/<?=e($item['research_id'])?><?=$kind==='request'?'':'?decision='.e($kind).'#item-'.e($item['id'])?>"><?=e($adminWaiting?'View '.$kind:'Review '.$kind)?></a>
            </li>
            <?php endforeach ?>
        </ul>
        <?php endif ?>
        <?php if($decisions['pages']>1): ?><nav class="pagination" aria-label="Decision pages">
        <?php for($page=1;$page<=$decisions['pages'];$page++): ?><a href="/admin?<?=e(http_build_query(['queue'=>$adminWaiting?'waiting':'review','queue_page'=>$page]))?>#queue" <?=$page===$decisions['page']?'aria-current="page"':''?>><?=$page?></a><?php endfor ?>
        </nav><?php endif ?>
    </section>
<?php endif ?>
</div>
