<?php
class VolunteerNoWork extends HttpError {}
/** Bounded usefulness bonuses; unbounded aging prevents new work permanently displacing old tasks. */
function vq_candidates(array $session,int $offset=0): array {
    $where=$session['scope_kind']==='research'?' AND r.id=?':' AND r.discovery_listed=1';
    $params=[$session['id'],wr_stamp()];
    if($session['scope_kind']==='research')$params[]=$session['scope_id'];
    elseif($session['scope_kind']==='category'){$where.=' AND r.category_id=?';$params[]=$session['scope_id'];}
    $offset=max(0,$offset);
    return all("SELECT t.id,t.research_id,t.kind,t.created_at,
        EXISTS(SELECT 1 FROM volunteer_assignments a WHERE a.session_id=? AND a.research_id=t.research_id AND a.outcome='accepted') AS continuity,
        (SELECT COUNT(*) FROM workflow_tasks d WHERE d.research_id=t.research_id AND d.round_no=t.round_no AND d.visibility='public' AND d.state='Waiting' AND JSON_CONTAINS(d.dependencies,JSON_QUOTE(t.id))) AS unlocks,
        GREATEST(0,TIMESTAMPDIFF(HOUR,t.created_at,?))/24.0 AS age_days
        FROM workflow_tasks t JOIN workflow_projects p ON p.research_id=t.research_id AND p.round_no=t.round_no
        JOIN research r ON r.id=t.research_id
        WHERE t.state='Ready' AND t.visibility='public' AND r.moderation='Approved'
        AND r.status NOT IN ('Withdrawn','Paused','Solved','Archived — Inactive','Review Complete','Blocked','Needs Clarification')".$where."
        ORDER BY (age_days + IF(t.kind='review',4,0) + 2*continuity + LEAST(unlocks,3)) DESC,t.created_at,t.id LIMIT 50 OFFSET ".$offset,$params);
}
function vq_reason(array $candidate): string {
    $reasons=[];
    if($candidate['kind']==='review')$reasons[]='Independent review is waiting';
    if((int)$candidate['continuity'])$reasons[]='continues this session’s research';
    if((int)$candidate['unlocks'])$reasons[]='can advance dependent work';
    $reasons[]='waiting time counts toward priority';
    return ucfirst(implode('; ',$reasons)).'. Eligibility and reservations are checked again at pickup.';
}
