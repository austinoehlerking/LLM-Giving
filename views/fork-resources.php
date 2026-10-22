<details class="fork-resources"><summary>Inherited resources, source attributions &amp; retained materials</summary>
<p class="fine">Pinned metadata and original links. Third-party rights remain unchanged; downloads depend on the original result's current eligibility. Sources are background for this new request, including rejected and limited-access records with their original labels.</p>
<?php foreach($bg['resources']['sources'] as $group): foreach($group['contributions'] as $entry): $s=$entry['source'];$sourcePath='/research/'.$bg['question']['slug'];$resultPath=$sourcePath.($entry['contribution']?'/contributions/':'/results/').$entry['result_id'];$resultView=$entry['contribution']?$sourcePath.'#contribution-'.$entry['result_id']:$resultPath; ?>
<article><h4><?=e($s['title'])?></h4><p><?=e(($s['authors']??'')?:'Authors not recorded')?> · <?=e(($s['publication_date']??'')?:'Date not recorded')?> · <?=e(($s['publisher']??'')?:'Publisher not recorded')?></p>
<p>DOI: <?=e(($s['doi']??'')?:'Not recorded')?> · Edition: <?=e(($s['edition']??'')?:'Not recorded')?> · Access: <?=e($s['access'])?> · Declared use: <?=e($s['use']??'Not collected')?></p>
<?php if($s['url']): ?><a class="text-action" href="<?=e($s['url'])?>" rel="noreferrer">Original source</a><?php endif ?>
<?=prose($s['access_note'])?><?=prose($s['metadata_note']??'Detailed metadata was not collected.')?>
<p class="fine">Attributed to <?=e($entry['display_name']?:'Anonymous volunteer')?> · <?=e($entry['created_at'])?> UTC</p>
<a class="text-action" href="<?=e($resultView)?>">Original contributing record and current status</a>
<?php foreach($entry['uses'] as $use): ?><p><?=e(ucfirst($use['kind']).': '.$use['title'])?></p><?php if($use['kind']==='claim'): ?><p><?=e($use['relation'].' · '.$use['locator'])?></p><blockquote><?=e($use['extraction'])?></blockquote><?=prose($use['explanation'])?>
<?php elseif($use['kind']==='dataset'): ?><a class="text-action" href="<?=e($resultPath)?>/resources/datasets/<?=e($use['id'])?>.csv">Original complete table (CSV)</a>
<?php elseif($use['kind']==='calculation'): ?><a class="text-action" href="<?=e($resultPath)?>/calculations/<?=e($use['id'])?>/bundle.json">Original calculation inputs, code and outputs</a><?php endif ?><?php endforeach ?>
</article>
<?php endforeach;endforeach ?>
<?php foreach($bg['resources']['materials'] as $material): $path='/research/'.$bg['question']['slug'].($material['contribution']?'/contributions/':'/results/').$material['result_id']; ?><article><h4>Retained materials · <?=e($material['title'])?></h4><?php foreach($material['files'] as $file): ?><p><a class="text-action" href="<?=e($path)?>/resources/files/<?=e($file['id'])?>"><?=e($file['name'])?></a> · <?=e($file['category'])?> · <?=e($file['rights'])?></p><?php endforeach ?></article><?php endforeach ?>
<?php foreach($bg['resources']['historical_metadata'] as $record): ?><p class="fine"><?=e($record['note'])?> · <?=e($record['result_id'])?></p><?php endforeach ?>
</details>
