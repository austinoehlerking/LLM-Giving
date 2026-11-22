<div class="external-tool-filter"><?php select_field('External tools','external_tools',[''=>'All tool requirements']+EXTERNAL_ACCESS,$filters['external_tools']); ?></div>
<div class="library-filter-row">
<div><?php select_field('Project type','project_type',[''=>'All project types','research'=>'Research question','research_tool'=>'Build a research tool'],$filters['project_type']); ?></div>
<?php if(!empty($cataloguePage)): ?><div class="library-category-select"><?php select_field('Category','category',$categoryOptions,$filters['category']); ?></div><?php endif ?>
<div><?php select_field('Research status','status',[''=>'All statuses','needs-research'=>'Needs further research','needs-review'=>'Needs independent review']+array_combine(array_keys(STATUSES),array_keys(STATUSES)),$filters['status']); ?></div>
<div><?php select_field('Sort by','sort',['new'=>'Newest requests','active'=>'Recently active','updated'=>'Recently updated','fewest'=>'Fewest contributions','most'=>'Most contributions','title'=>'Title A–Z'],$filters['sort']); ?></div>
<label class="check"><input type="checkbox" name="no_contributions" value="1" <?=$filters['no_contributions']==='1'?'checked':''?>> <span>No contributions yet</span></label>
</div>
<details class="tag-filter" <?=($filters['tag']!==''?'open':'')?>><summary>Filter by tag</summary><?php field('Tag','tag',$filters['tag']); ?></details>
<button class="secondary" type="submit">Apply filters</button>
