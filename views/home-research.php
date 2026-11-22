<?php
$filters=$listing['filters'];
$categoryOptions=[''=>'All categories'];
foreach(discovery_categories() as $category) $categoryOptions[(string)$category['id']]=$category['name'];
?>
<section id="research" class="home-research" aria-labelledby="library-title">
    <h2 id="library-title">Deep research requests</h2>
    <form class="home-research-toolbar" method="get" action="/#research" data-discovery-form>
        <div class="home-research-search">
            <label class="visually-hidden" for="research-query">Search research requests</label>
            <input id="research-query" name="q" type="search" value="<?=e($filters['q'])?>" placeholder="Search research requests…">
            <button aria-label="Search research" type="submit"><span aria-hidden="true"></span></button>
        </div>
        <div class="home-research-category"><?php select_field('Category','category',$categoryOptions,$filters['category']); ?></div>
        <details class="home-research-filters">
            <summary>Filters</summary>
            <div class="home-research-filter-panel"><?php require ROOT.'/views/research-filter-fields.php'; ?></div>
        </details>
    </form>
    <?php if($listing['rows']): ?>
    <div class="home-research-list">
        <?php foreach($listing['rows'] as $item):
            $brief='/research/'.$item['slug'];
            $task=mp_next($item,kind:'research');
        ?>
        <article class="home-research-row" data-research-id="<?=e($item['id'])?>">
            <div>
                <h3><?=e($item['title'])?></h3>
                <?php if($task): ?><p class="home-research-next">Next task: <?=e($task['title'])?></p><?php endif ?>
            </div>
            <div class="home-research-actions">
                <a href="<?=e($brief.'#question')?>" aria-label="Read brief: <?=e($item['title'])?>">Read brief</a>
                <?php if($task): ?><a class="button" href="<?=e($brief.'/authorize/'.$task['id'])?>" aria-label="Start researching: <?=e($item['title'])?>">Start researching</a><?php endif ?>
            </div>
        </article>
        <?php endforeach ?>
    </div>
    <?php else: ?>
    <div class="empty">
        <h3>No matching research requests.</h3>
        <p>Try a broader search or reset your filters.</p>
        <a class="button" href="/submit">Submit Research Request</a>
    </div>
    <?php endif ?>
    <div class="library-resultbar">
        <p role="status"><?=$listing['total']?> matching research request<?=$listing['total']===1?'':'s'?><?php if($filters['tag']!==''): ?> · Tag: <?=e($filters['tag'])?><?php endif ?></p>
        <?php if(discovery_has_solved()): ?><a href="/research?status=Solved">Explore solved research</a><?php endif ?>
        <a href="/#research">Reset filters</a>
    </div>
    <?php if($listing['total']>12): ?>
    <nav class="pagination" aria-label="Results pages">
        <?php for($p=1;$p<=ceil($listing['total']/12);$p++): ?>
        <a <?=($p===$listing['page']?'aria-current="page"':'')?> href="/?<?=e(http_build_query(array_merge($filters,['page'=>$p])))?>#research"><?=$p?></a>
        <?php endfor ?>
    </nav>
    <?php endif ?>
</section>
