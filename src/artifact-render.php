<?php
function ra_n(int|float $number): string {return rtrim(rtrim(number_format((float)$number,4,'.',''),'0'),'.')?:'0';}
function ra_xml(string $text): string {return e(preg_replace('/[\x00-\x08\x0B\x0C\x0E-\x1F]/u','',$text));}
function ra_value(mixed $value): string {return $value===null?'Missing':(is_float($value)?json_encode($value,JSON_PRESERVE_ZERO_FRACTION|JSON_THROW_ON_ERROR):(string)$value);}
function ra_wrap(string $text,int $width=18,int $max=3): array {
    $length=fn($value)=>count(preg_split('//u',$value,-1,PREG_SPLIT_NO_EMPTY));$tokens=[];
    foreach(preg_split('/\s+/u',trim($text),-1,PREG_SPLIT_NO_EMPTY) as $word){$chars=preg_split('//u',$word,-1,PREG_SPLIT_NO_EMPTY);while(count($chars)>$width)$tokens[]=implode('',array_splice($chars,0,$width));if($chars)$tokens[]=implode('',$chars);}
    $lines=[];$line='';foreach($tokens as $word){if($line!==''&&$length($line.' '.$word)>$width){$lines[]=$line;$line=$word;}else $line.=($line===''?'':' ').$word;}if($line!=='')$lines[]=$line;
    if(count($lines)>$max){$lines=array_slice($lines,0,$max);$lines[$max-1].='…';}return $lines;
}
function ra_svg(array $artifact,array $panel,array $datasets,array $state): string {
    $prefix='ra-'.$artifact['id'].'-'.$panel['id'].'-'.$state['id'];$title=ucfirst($artifact['provenance']).' · '.$panel['title'].' · '.$state['label'];$description='Linked claims: '.implode(', ',$artifact['claim_ids']).'. Data: '.implode(', ',$artifact['dataset_ids']).'. Sources: '.implode(', ',$artifact['source_ids']).'. '.$artifact['summary'].' '.$state['explanation'].' Assumptions: '.$artifact['assumptions'].' Limitations: '.$artifact['limitations'];
    $nodeYs=$panel['type']==='diagram'?array_column($panel['nodes'],'y'):[];
    $svgHeight=$nodeYs?max(220,(max($nodeYs)-min($nodeYs))*4.5+190):590;$diagramShift=$nodeYs?48+min($nodeYs)*4.5-104:0;
    $out='<svg xmlns="http://www.w3.org/2000/svg" viewBox="0 0 800 '.ra_n($svgHeight).'" role="img" aria-labelledby="'.$prefix.'-title '.$prefix.'-description"><title id="'.$prefix.'-title">'.ra_xml($title).'</title><desc id="'.$prefix.'-description">'.ra_xml($description).'</desc><rect width="800" height="'.ra_n($svgHeight).'" rx="8" fill="#14212b"/><g font-family="Arial, sans-serif" fill="#edf5f5"><text x="24" y="28" font-size="16">'.ra_xml(ucfirst($artifact['provenance']).' · '.$state['label']).'</text>';
    if($panel['type']==='diagram'){
        $nodes=array_column($panel['nodes'],null,'id');$highlight=[];foreach($state['highlights'] as $h)if($h['panel_id']===$panel['id'])$highlight=$h['node_ids'];
        $out.='<defs><marker id="'.$prefix.'-arrow" viewBox="0 0 10 10" refX="9" refY="5" markerWidth="7" markerHeight="7" orient="auto-start-reverse"><path d="M 0 0 L 10 5 L 0 10 z" fill="#a9bac8"/></marker></defs>';
        foreach($panel['edges'] as $edge){$a=$nodes[$edge['from']];$b=$nodes[$edge['to']];$x1=$a['x']*8;$y1=48+$a['y']*4.5-$diagramShift;$x2=$b['x']*8;$y2=48+$b['y']*4.5-$diagramShift;$dx=$x2-$x1;$dy=$y2-$y1;$length=max(1,sqrt($dx*$dx+$dy*$dy));$offset=min(80,$length*.35);$sx=$x1+$dx/$length*$offset;$sy=$y1+$dy/$length*$offset;$ex=$x2-$dx/$length*$offset;$ey=$y2-$dy/$length*$offset;
            $out.='<line x1="'.ra_n($sx).'" y1="'.ra_n($sy).'" x2="'.ra_n($ex).'" y2="'.ra_n($ey).'" stroke="#a9bac8" stroke-width="2" marker-end="url(#'.$prefix.'-arrow)"/>';
            if($edge['label'])$out.='<text x="'.ra_n(($x1+$x2)/2).'" y="'.ra_n(($y1+$y2)/2-10).'" text-anchor="middle" font-size="12">'.ra_xml($edge['label']).'</text>';
        }
        foreach($nodes as $node){$x=$node['x']*8;$y=48+$node['y']*4.5-$diagramShift;$active=in_array($node['id'],$highlight,true);$out.='<g><title>'.ra_xml($node['label'].': '.$node['detail']).'</title><rect x="'.ra_n($x-72).'" y="'.ra_n($y-36).'" width="144" height="72" rx="9" fill="'.($active?'#324b68':'#21323e').'" stroke="'.($active?'#a5c6ff':'#759098').'" stroke-width="'.($active?'3':'1.5').'"/>';$lines=ra_wrap($node['label']);foreach($lines as $i=>$line)$out.='<text x="'.ra_n($x).'" y="'.ra_n($y-(count($lines)-1)*8+$i*16+5).'" text-anchor="middle" font-size="14">'.ra_xml($line).'</text>';$out.='</g>';}
    }else{
        $dataset=$datasets[$panel['dataset_id']];$columns=array_column($dataset['columns'],null,'key');$keys=array_column($dataset['columns'],'key');$xi=array_search($panel['x'],$keys,true);$rows=ra_rows($dataset,$panel,$state);$ys=[];$xs=[];
        if($panel['chart_type']!=='bar')usort($rows,fn($a,$b)=>($a[$xi]??INF)<=>($b[$xi]??INF));
        foreach($rows as $i=>$row){if($row[$xi]!==null)$xs[]=$panel['chart_type']==='bar'?$i:(float)$row[$xi];foreach($panel['y'] as $y){$yi=array_search($y,$keys,true);if($row[$xi]!==null&&$row[$yi]!==null)$ys[]=(float)$row[$yi];}}
        $ymin=min($ys);$ymax=max($ys);if($panel['chart_type']==='bar'){$ymin=min(0,$ymin);$ymax=max(0,$ymax);}if($ymin===$ymax){$padding=max(1,abs($ymin)*.1);$ymin-=$padding;$ymax+=$padding;}
        $xmin=min($xs);$xmax=max($xs);if($xmin===$xmax){$xmin-=.5;$xmax+=.5;}
        $left=92;$right=760;$top=65;$bottom=365;$height=$bottom-$top;$width=$right-$left;$scaleY=fn($y)=>$bottom-($y-$ymin)/($ymax-$ymin)*$height;
        $scaleX=$panel['chart_type']==='bar'?fn($x)=>$left+($x+.5)/count($rows)*$width:fn($x)=>$left+($x-$xmin)/($xmax-$xmin)*$width;
        for($i=0;$i<=4;$i++){$value=$ymin+($ymax-$ymin)*$i/4;$y=$scaleY($value);$out.='<line x1="'.$left.'" y1="'.ra_n($y).'" x2="'.$right.'" y2="'.ra_n($y).'" stroke="#3c535f"/><text x="80" y="'.ra_n($y+5).'" text-anchor="end" font-size="13">'.ra_xml(sprintf('%.4g',$value)).'</text>';}
        $out.='<line x1="'.$left.'" y1="'.$top.'" x2="'.$left.'" y2="'.$bottom.'" stroke="#c1d0d5"/><line x1="'.$left.'" y1="'.$bottom.'" x2="'.$right.'" y2="'.$bottom.'" stroke="#c1d0d5"/>';
        if($panel['chart_type']==='bar'){
            $every=max(1,(int)ceil(count($rows)/6));foreach($rows as $i=>$row)if($i%$every===0){$label=ra_value($row[$xi]);foreach(ra_wrap($label,count($rows)>6?14:28,2) as $labelIndex=>$labelLine)$out.='<text x="'.ra_n($scaleX($i)).'" y="'.(389+$labelIndex*14).'" text-anchor="middle" font-size="12"><title>'.ra_xml($label).'</title>'.ra_xml($labelLine).'</text>';}
        }else for($i=0;$i<=4;$i++){$value=$xmin+($xmax-$xmin)*$i/4;$out.='<text x="'.ra_n($scaleX($value)).'" y="389" text-anchor="middle" font-size="13">'.ra_xml(sprintf('%.4g',$value)).'</text>';}
        $colors=['#9fc5ff','#fac17d','#91d7bc'];
        foreach($panel['y'] as $series=>$key){$yi=array_search($key,$keys,true);$color=$colors[$series];$path='';$started=false;
            foreach($rows as $i=>$row){if($row[$xi]===null||$row[$yi]===null){$started=false;continue;}$x=$scaleX($panel['chart_type']==='bar'?$i:$row[$xi]);$y=$scaleY($row[$yi]);$tooltip=$columns[$key]['label'].': '.ra_value($row[$yi]).' '.$columns[$key]['unit'].'; '.$columns[$panel['x']]['label'].': '.ra_value($row[$xi]);
                if($panel['chart_type']==='bar'){$group=$width/count($rows);$bar=$group*.72/count($panel['y']);$zero=$scaleY(0);$out.='<rect x="'.ra_n($x-$group*.36+$series*$bar).'" y="'.ra_n(min($y,$zero)).'" width="'.ra_n(max(.5,$bar-1)).'" height="'.ra_n(abs($zero-$y)).'" fill="'.$color.'"><title>'.ra_xml($tooltip).'</title></rect>';}
                else{$path.=($started?' L ':' M ').ra_n($x).' '.ra_n($y);$started=true;$out.='<circle cx="'.ra_n($x).'" cy="'.ra_n($y).'" r="4" fill="'.$color.'"><title>'.ra_xml($tooltip).'</title></circle>';}
            }
            if($panel['chart_type']==='line')$out.='<path d="'.trim($path).'" fill="none" stroke="'.$color.'" stroke-width="2"'.($series?' stroke-dasharray="'.($series===1?'7 4':'2 4').'"':'').'/>';
            $out.='<rect x="92" y="'.(450+$series*40).'" width="14" height="14" fill="'.$color.'"/>';foreach(ra_wrap($columns[$key]['label'].' ('.$columns[$key]['unit'].')',80,2) as $lineIndex=>$legendLine)$out.='<text x="116" y="'.(462+$series*40+$lineIndex*15).'" font-size="13">'.ra_xml($legendLine).'</text>';
        }
        $xColumn=$columns[$panel['x']];$yColumn=$columns[$panel['y'][0]];
        foreach(ra_wrap($xColumn['label'].($xColumn['unit']?' ('.$xColumn['unit'].')':''),80,2) as $lineIndex=>$line)$out.='<text x="426" y="'.(417+$lineIndex*15).'" text-anchor="middle" font-size="14">'.ra_xml($line).'</text>';
        foreach(ra_wrap($yColumn['unit'],34,2) as $lineIndex=>$line){$labelX=24+$lineIndex*15;$out.='<text x="'.$labelX.'" y="215" text-anchor="middle" font-size="13" transform="rotate(-90 '.$labelX.' 215)">'.ra_xml($line).'</text>';}
    }
    return $out.'<text x="776" y="'.ra_n($svgHeight-10).'" text-anchor="end" font-size="11">'.ra_xml('Claims: '.implode(', ',$artifact['claim_ids']).' · Data: '.($artifact['dataset_ids']?implode(', ',$artifact['dataset_ids']):'conceptual')).'</text></g></svg>';
}
function ra_table(array $dataset): void {
    ?><div class="artifact-table" role="region" aria-label="<?=e($dataset['title'])?> — all supplied rows" tabindex="0"><table><caption><?=e($dataset['title'])?> · All supplied rows; null values mean missing data.</caption><thead><tr><?php foreach($dataset['columns'] as $column): ?><th scope="col"><?=e($column['label'])?><?php if($column['unit']): ?> (<?=e($column['unit'])?>)<?php endif ?></th><?php endforeach ?></tr></thead><tbody><?php foreach($dataset['rows'] as $row): ?><tr><?php foreach($row as $cell): ?><td><?=e(ra_value($cell))?></td><?php endforeach ?></tr><?php endforeach ?></tbody></table></div><?php
}
