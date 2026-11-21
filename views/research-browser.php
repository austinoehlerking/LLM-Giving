<?php
$filters=$listing['filters']; $categories=discovery_categories();
$categoryOptions=[''=>'All categories']; foreach($categories as $category) $categoryOptions[(string)$category['id']]=$category['name'];
$categoryUrl=function(string $id) use($filters): string { return '/research?'.http_build_query(array_filter(array_merge($filters,['category'=>$id,'page'=>1]),fn($v)=>$v!=='')); };
?>
<section id="research" class="research-library" aria-labelledby="library-title">
<div class="library-title"><?php if(!empty($cataloguePage)): ?><h1 id="library-title">Explore deep research</h1><?php else: ?><h2 id="library-title">Deep research requests</h2><?php endif ?><a href="/about">Your AI tools. Shared research.</a></div>
<div class="library-browse"><aside class="library-categories" aria-label="Research categories"><h3>Categories</h3>
<nav><a href="<?=e($categoryUrl(''))?>" <?=($filters['category']===''?'aria-current="true"':'')?>>All categories <span><?=array_sum(array_column($categories,'request_count'))?></span></a>
<?php foreach($categories as $category): ?><a href="<?=e($categoryUrl((string)$category['id']))?>" <?=($filters['category']===(string)$category['id']?'aria-current="true"':'')?>><?=e($category['name'])?><span><?=e($category['request_count'])?></span></a><?php endforeach ?></nav>
<div class="library-note"><h3>Built for useful findings.</h3><p>Defined objectives.<br>Traceable sources.<br>Independent review.</p></div></aside>
<div class="library-results"><form class="filters library-filters" method="get" action="/research" data-discovery-form>
<div class="library-search"><div><label for="research-query">Search research requests</label><input id="research-query" name="q" type="search" value="<?=e($filters['q'])?>" placeholder="Search research requests…"></div><button class="search-submit">Search research</button></div>
<div class="library-first-result"><?php $rows=array_slice($listing['rows'],0,1); require ROOT.'/views/research-list.php'; ?></div>
<?php if(discovery_has_solved()): ?><p class="library-solved"><a href="/research?status=Solved">Explore solved research</a></p><?php endif ?>
<details class="library-advanced"><summary>Filters</summary>
<div class="external-tool-filter"><?php select_field('External tools','external_tools',[''=>'All tool requirements']+EXTERNAL_ACCESS,$filters['external_tools']); ?></div>
<div class="library-filter-row">
<div><?php select_field('Project type','project_type',[''=>'All project types','research'=>'Research question','research_tool'=>'Build a research tool'],$filters['project_type']); ?></div>
<div class="library-category-select"><?php select_field('Category','category',$categoryOptions,$filters['category']); ?></div>
<div><?php select_field('Research status','status',[''=>'All statuses','needs-research'=>'Needs further research','needs-review'=>'Needs independent review']+array_combine(array_keys(STATUSES),array_keys(STATUSES)),$filters['status']); ?></div>
<div><?php select_field('Sort by','sort',['new'=>'Newest requests','active'=>'Recently active','updated'=>'Recently updated','fewest'=>'Fewest contributions','most'=>'Most contributions','title'=>'Title A–Z'],$filters['sort']); ?></div>
<label class="check"><input type="checkbox" name="no_contributions" value="1" <?=$filters['no_contributions']==='1'?'checked':''?>> <span>No contributions yet</span></label>
</div>
<details class="tag-filter" <?=($filters['tag']!==''?'open':'')?>><summary>Filter by tag</summary><?php field('Tag','tag',$filters['tag']); ?></details>
<button class="secondary" type="submit">Apply filters</button>
</details>
</form>
<div class="library-resultbar"><p role="status"><?=$listing['total']?> matching research request<?=$listing['total']===1?'':'s'?><?php if($filters['tag']!==''): ?> · Tag: <?=e($filters['tag'])?><?php endif ?></p><a href="/research">Reset filters</a></div>
<?php $rows=array_slice($listing['rows'],1); if($rows) require ROOT.'/views/research-list.php'; ?>
<?php if($listing['total']>12): ?><nav class="pagination" aria-label="Results pages"><?php for($p=1;$p<=ceil($listing['total']/12);$p++): ?><a <?=($p===$listing['page']?'aria-current="page"':'')?> href="/research?<?=e(http_build_query(array_merge($filters,['page'=>$p])))?>"><?=$p?></a><?php endfor ?></nav><?php endif ?>
<p class="library-disclaimer">Only approved research is listed. Publication is not factual verification.</p>
</div></div></section>
