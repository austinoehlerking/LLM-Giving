<?php
/** Private operational helpers; never reuse these aggregates in public discovery. */
function admin_research_usage(array $ids): array {
    require_admin();
    $ids=array_values(array_unique($ids));
    if(count($ids)>50)throw new InvalidArgumentException('Usage is limited to one admin listing page.');
    $out=[];
    foreach($ids as $id){
        if(!is_string($id)||!preg_match('/^[a-f0-9]{32}$/D',$id))throw new InvalidArgumentException('Invalid research identifier.');
        $out[$id]=['workflow_total'=>0,'supplementary_total'=>0,'admission'=>[],'visibility'=>[],'moderation'=>[]];
    }
    if(!$ids)return $out;
    $slots=implode(',',array_fill(0,count($ids),'?'));
    // Aggregate each record family separately: joining both would multiply their counts.
    foreach(all('SELECT research_id,admission,visibility,COUNT(*) n FROM workflow_submissions WHERE research_id IN ('.$slots.') GROUP BY research_id,admission,visibility',$ids) as $row){
        $u=&$out[$row['research_id']];$n=(int)$row['n'];$u['workflow_total']+=$n;
        foreach(['admission','visibility'] as $key)$u[$key][$row[$key]]=($u[$key][$row[$key]]??0)+$n;
        unset($u);
    }
    foreach(all('SELECT research_id,moderation,COUNT(*) n FROM contributions WHERE research_id IN ('.$slots.') GROUP BY research_id,moderation',$ids) as $row){
        $u=&$out[$row['research_id']];$n=(int)$row['n'];$u['supplementary_total']+=$n;$u['moderation'][$row['moderation']]=$n;unset($u);
    }
    return $out;
}
function admin_usage_breakdown(array $counts): string {
    if(!$counts)return 'None recorded';
    ksort($counts);$parts=[];
    foreach($counts as $state=>$count)$parts[]=$count.' '.($state==='quarantined'?'quarantined (awaiting admin)':$state);
    return implode(' · ',$parts);
}
function admin_set_discovery_listing(string $rid,bool $listed,string $reason): bool {
    require_admin();
    if(strlen(trim($reason))<10||strlen($reason)>500)fail(422,'Explain the listing change in 10–500 characters.');
    return tx(function()use($rid,$listed,$reason){
        $r=one('SELECT id,discovery_listed FROM research WHERE id=? FOR UPDATE',[$rid])??fail(404,'Research not found.');
        if((bool)$r['discovery_listed']===$listed)return false;
        query('UPDATE research SET discovery_listed=? WHERE id=?',[(int)$listed,$rid]);
        // No content/status/moderation change, context invalidation or reservation revocation.
        audit_event($rid,'Discovery listing changed',$reason,'research',$rid,$r['discovery_listed']?'Listed':'Unlisted',$listed?'Listed':'Unlisted','','administrator','Administrator',
            ['discovery_listed'=>(int)$listed,'administrator_id'=>(string)$_SESSION['admin']],'private');
        return true;
    });
}
