<?php if(!$rows): ?><div class="empty"><h3>No matching research requests.</h3><p>Try a broader search or reset your filters.</p><div class="actions"><a class="button secondary" href="/research">Reset filters</a><a class="button" href="/submit">Submit Research Request</a></div></div><?php endif ?>
<div class="research-list compact-cards"><?php foreach($rows as $item): ?><article class="research-row research-card" data-research-id="<?=e($item['id'])?>"><?php $access=external_requirements($item)['external_tools']??'unspecified';if(!in_array($access,['unspecified','none'],true)): ?><span class="status"><?=e(EXTERNAL_ACCESS[$access]??'External tools required')?></span><?php endif ?>
<div class="row-top"><span class="category"><?=e($item['category'])?></span><?=status_badge($item['status'])?></div>
<h3><a href="/research/<?=e($item['slug'])?>"><?=e($item['title'])?></a></h3>
<?php if(rt_is_tool($item)): ?><p class="fine"><strong>Build a research tool</strong></p><?php endif ?>
<p class="card-summary"><?=e(research_excerpt($item['question'],155))?></p>
<?php if($item['tags']): ?><div class="tags"><?php foreach(array_slice($item['tags'],0,3) as $tag): ?><a href="/research?tag=<?=urlencode($tag['name'])?>"><?=e($tag['name'])?></a><?php endforeach ?></div><?php endif ?>
<div class="card-bottom"><div><span><?=e($item['contribution_count'])?> contribution<?=$item['contribution_count']==1?'':'s'?> · <?=e($item['review_count'])?> review<?=$item['review_count']==1?'':'s'?></span><span>Added <?=e(substr($item['created_at'],0,10))?> · Updated <?=e(substr($item['updated_at'],0,10))?></span></div><a href="/research/<?=e($item['slug'])?>" aria-label="Read brief: <?=e($item['title'])?>">Read brief ↗</a></div>
</article><?php endforeach ?></div>
