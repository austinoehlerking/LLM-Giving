<?php
/** CLI-only local study construction; intentionally generates NO research packets or model provenance. */
require __DIR__.'/bootstrap.php';
$_SESSION['admin'] = 1;
$action = $argv[1] ?? '';
if ($action === 'create') {
    $created = create_research([
        'title'=>'[QA] Orchestrator synthetic sequential study '.uid(),
        'question'=>'Local software walkthrough only: inspect the two fictional observations and explain their stated access limits. This does not investigate a scientific question.',
        'context'=>"SYNTHETIC ORIGINAL INPUT v1. Alpha: observed, note=fictional direct inspection. Beta: unavailable, note=no observation supplied. These are invented QA records, not scientific measurements. Do not invent an observation for Beta. The author is the repository QA fixture; no external publication, DOI or scientific inspection exists.",
        'category_id'=>1, 'terms'=>1, 'external_tools'=>'none', 'setup_needs'=>'', 'calculation_help'=>'prepare_review'
    ]);
    $rid = $created['id'];
    moderate('request', $rid, 'Approved', 'Local synthetic orchestrator software walkthrough; no scientific finding.');
    tx(function () use ($rid) {
        one('SELECT id FROM research WHERE id=? FOR UPDATE', [$rid]);
        // Seed a bounded research-stage scenario without pretending an AI supplied an accepted plan.
        query("UPDATE workflow_tasks SET state='Superseded' WHERE research_id=?", [$rid]);
        $first = wf_new_task($rid, 1, 'qa-alpha', 'research', 'Inspect fictional Alpha observation',
            'Inspect the synthetic original input in the research brief. Describe the stated Alpha observation and its fictional QA-only scope.',
            'A short traceable narrative identifying Alpha as observed, with exact source location and an honest synthetic-evidence limit.',
            'Identify Alpha as observed and state that the supplied record is fictional QA input, not scientific evidence.');
        wf_new_task($rid, 1, 'qa-beta', 'research', 'Inspect fictional Beta access gap',
            'Read the newly accepted Alpha result and the synthetic original input. Distinguish its stated observation from Beta, for which no observation was supplied.',
            'A short traceable narrative retaining the Beta access gap and linking the accepted Alpha result without inventing new data.',
            'Identify Beta as unavailable, preserve the no-observation limit and link the current accepted Alpha result.', [$first]);
        wf_set_state($rid, 'Open for Research', 'Synthetic research-stage fixture constructed for local software testing; no AI plan or scientific verification is claimed.');
        touch_context($rid);
    });
    $r = research($rid);
    echo json_encode(['id'=>$rid, 'slug'=>$r['slug'], 'tasks'=>array_map(fn($t)=>['id'=>$t['id'],'title'=>$t['title'],'state'=>$t['state']], wf_tasks($rid)),
        'scope'=>'Local synthetic study construction only. This fixture generates no packets; actual AI work and deterministic packet tests must be reported separately.'], JSON_THROW_ON_ERROR);
} elseif (in_array($action, ['inspect','purge'], true)) {
    $rid = $argv[2] ?? '';
    if (!one("SELECT id FROM research WHERE id=? AND title LIKE '[QA] Orchestrator synthetic sequential study %'", [$rid])) exit(2);
    if ($action === 'purge') {
        purge_request($rid, $rid);
        echo 'Purged only the explicitly named orchestrator QA study.';
    } else {
        $r = research($rid, false);
        echo json_encode(['id'=>$rid,'slug'=>$r['slug'],'status'=>$r['status'],
            'tasks'=>array_map(fn($t)=>['id'=>$t['id'],'state'=>$t['state'],'revision'=>(int)$t['revision'],'accepted_id'=>$t['accepted_id']],wf_tasks($rid)),
            'results'=>all('SELECT id,task_id,outcome,admission,submission_method FROM workflow_submissions WHERE research_id=? ORDER BY created_at,id',[$rid])], JSON_THROW_ON_ERROR);
    }
} else {
    fwrite(STDERR, "Usage: orchestrator-state.php create|inspect <id>|purge <id>\n");
    exit(2);
}
