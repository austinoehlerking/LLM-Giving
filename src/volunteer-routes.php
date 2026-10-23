<?php
function vs_scope_label(array $configuration): string {
    if ($configuration['scope_kind']==='category') return 'Category: '.(one('SELECT name FROM categories WHERE id=?',[$configuration['scope_id']])['name']??'Unavailable');
    if ($configuration['scope_kind']==='research') {
        try { return 'Research: '.research($configuration['scope_id'])['title']; }
        catch (HttpError) { return 'Selected research is unavailable'; }
    }
    return 'Help wherever needed';
}
function vs_state_label(string $state): string {
    return match($state) {
        'active'=>'Active — ready for eligible work',
        'limit_reached'=>'Assignment limit reached — finish the current task before the deadline',
        'expired'=>'Session expired — no more claims, renewals or submissions',
        'terms_changed'=>'Terms changed — fresh volunteer authorization is required',
        default=>'Session stopped — existing receipts remain available'
    };
}
function vs_form_draft(array $input): array {
    $draft=[];
    foreach (['scope_kind','category_id','research_id','assignment_limit','minutes','model','reasoning','tool','workflow_mode'] as $field) {
        if (isset($input[$field]) && is_scalar($input[$field])) $draft[$field]=(string)$input[$field];
    }
    return $draft;
}
function vs_public_assignment(array $session,array $assignment): array {
    $item=['available'=>false,'outcome'=>'Not submitted','receipt_url'=>null,'reacquire'=>false,'title'=>'Research unavailable','task_url'=>null,'reservation_text'=>'No live reservation'];
    try {
        $grant=vs_assignment_grant($session,$assignment);
        $r=research($assignment['research_id']); $task=wf_task($r['id'],$assignment['task_id']);
        $item['available']=true; $item['title']=$task['title']; $item['task_url']='/research/'.$r['slug'].'/tasks/'.$task['id'];
        if ($grant['result_id']) {
            $result=one("SELECT admission,visibility FROM workflow_submissions WHERE id=? AND research_id=?",[$grant['result_id'],$r['id']]);
            if (!$result || $result['visibility']!=='public') return array_replace($item,['available'=>false,'title'=>'Research unavailable','task_url'=>null]);
            $item['outcome']=match($result['admission']) {'accepted'=>'Accepted','quarantined'=>'Quarantined — awaiting review','rejected'=>'Rejected',default=>'Recorded — inspect receipt'};
            $item['receipt_url']=ar_receipt_url($r,$grant,$grant['result_id']);
            return $item;
        }
        if (!$assignment['ended_at']) {
            $live=wr_live($r['id'],$task['id']);
            if ($live && $live['id']===$assignment['reservation_id']) {
                $item['reservation_text']='Reserved for you until '.$live['expires_at'].' UTC';
                $item['outcome']='In progress — not submitted';
            } elseif ($live) {
                $item['reservation_text']='In progress — reserved until '.$live['expires_at'].' UTC by another holder. Your entered work is preserved.';
            } else {
                try { vs_task_eligible($session,$r,$task); $item['reacquire']=(int)$task['revision']===(int)$assignment['task_revision']; }
                catch (HttpError) {}
                $item['reservation_text']='Reservation expired or released. Reacquire explicitly if this revision is still eligible and unreserved.';
            }
        }
    } catch (HttpError $error) {
        if (!in_array($error->status,[403,404,409,422],true)) throw $error;
    }
    return $item;
}
function vs_private_packet(array $session,array $grant,array $packet): void {
    $encoded=json_encode($packet,JSON_THROW_ON_ERROR);
    if (preg_match('~/volunteer/[a-f0-9]{32}|/return/[a-f0-9]{32}~i',str_replace('\\/','/',$encoded))) fail(422,'Remove private session and return links from the research packet. They are authorization, not evidence.');
    preg_match_all('/[a-f0-9]{64}/i',$encoded,$matches);
    foreach ($matches[0] as $candidate) {
        if (hash_equals($session['secret_hash'],hash('sha256',$candidate)) || hash_equals($grant['secret_hash'],hash('sha256',$candidate)) || in_array($candidate,[$session['secret_hash'],$session['actor_hash']],true)) {
            fail(422,'Remove private session ownership material from the research packet.');
        }
    }
}
function vs_record(string $id,string $assignmentId,array $packet,bool $checkOnly): string {
    return vs_locked($id,function(array $session) use($assignmentId,$packet,$checkOnly) {
        $assignment=vs_assignment($session,$assignmentId);
        $grant=vs_assignment_grant($session,$assignment);
        vs_private_packet($session,$grant,$packet);
        if (!$grant['result_id']) vs_validate_grant(research($assignment['research_id']),$grant);
        // A full session already authorized this stored assignment. The packet supplies neither consent nor actor.
        $_SESSION['browser_returns'][$grant['id']]=$grant['secret_hash'];
        $result=ar_record(research($assignment['research_id']),$grant,$packet,$checkOnly);
        if (!$checkOnly) vs_return_recorded($grant,$result);
        return $result;
    });
}
function vs_assignment_page(array $session,array $assignment,string $method): void {
    $grant=vs_assignment_grant($session,$assignment);
    $r=research($assignment['research_id']); $task=wf_task($r['id'],$assignment['task_id']);
    if ($method==='GET' && $grant['result_id']) redirect(ar_receipt_url($r,$grant,$grant['result_id']));
    $input=''; $error=''; $repair=''; $checked=false;
    $jsonCheck=$method==='POST' && ($_POST['operation']??'')==='check' && str_contains($_SERVER['HTTP_ACCEPT']??'','application/json');
    if ($method==='POST') {
        $input=is_string($_POST['payload']??null)?$_POST['payload']:'';
        try {
            $operation=text_field($_POST,'operation',1,16);
            if ($operation==='reacquire') {
                vs_assignment_action($session['id'],$assignment['id'],'reacquire');
            } elseif (in_array($operation,['check','submit'],true)) {
                throttle('volunteer-packet-'.$operation,$operation==='check'?60:12);
                $text=text_field($_POST,'payload',2,200000);
                try { $packet=json_decode($text,true,32,JSON_THROW_ON_ERROR); }
                catch (JsonException) { fail(422,'Supply the complete JSON object without code fences. Nothing was submitted.'); }
                if (!is_array($packet) || array_is_list($packet)) fail(422,'Supply one complete JSON object.');
                $result=vs_record($session['id'],$assignment['id'],$packet,$operation==='check');
                if ($operation==='submit') redirect(ar_receipt_url($r,$grant,$result));
                $checked=true;
                if ($jsonCheck) {
                    $analysis=$packet; $analysis['human_disclosure']=$grant['consent']['human_disclosure'];
                    header('Content-Type: application/json'); header('Cache-Control: no-store');
                    echo json_encode(['ok'=>true,'message'=>'Packet checks passed. Nothing has been submitted. Submit the unchanged packet and inspect its recorded receipt.','influence_review'=>wf_influence($analysis)['review_status']==='needed','session_reacquire'=>false]);
                    return;
                }
            } else fail(422,'Choose check, submit or explicit reacquisition.');
        } catch (HttpError $caught) {
            http_response_code($caught->status); $error=$caught->getMessage();
            if ($caught->status===422) $repair=wf_repair_prompt($r,$task,$error,true,'/volunteer/'.$session['id'].'/assignments/'.$assignment['id'],true);
            if ($jsonCheck) {
                header('Content-Type: application/json'); header('Cache-Control: no-store');
                $currentItem=vs_public_assignment(vs_read($session['id']),vs_assignment(vs_read($session['id']),$assignment['id']));
                echo json_encode(['ok'=>false,'message'=>$error,'repair_prompt'=>$repair,'session_reacquire'=>$currentItem['reacquire']]);
                return;
            }
        }
    }
    $session=vs_read($session['id']); $assignment=vs_assignment($session,$assignment['id']);
    $grant=vs_assignment_grant($session,$assignment);
    $item=vs_public_assignment($session,$assignment);
    $snapshot=wf_read_snapshot($r['id'],$grant['scope']['context_id']);
    $can=true;
    render('volunteer-assignment',['title'=>'Assigned task result','session'=>$session,'assignment'=>$assignment,'grant'=>$grant,'r'=>$r,'task'=>$task,'snapshot'=>$snapshot,
        'can'=>$can,'error'=>$error,'repair'=>$repair,'checked'=>$checked,'input'=>$input,'reservationText'=>$item['reservation_text'],'reacquire'=>$item['reacquire']]);
}
function vs_route(string $path,string $method): bool {
    if ($path==='/volunteer/orchestrator.txt') {
        if ($method!=='GET') fail(405,'Use GET for public reference instructions.');
        header('Content-Type: text/plain; charset=UTF-8');
        echo vo_instructions();
        return true;
    }
    if ($path==='/volunteer') {
        $draft=vs_form_draft($method==='POST'?$_POST:$_GET); $error='';
        if ($method==='POST') {
            try {
                $configuration=vs_configuration($_POST);
                if (($_POST['operation']??'')==='authorize') {
                    $created=vs_create($_POST);
                    render('volunteer-created',['title'=>'Private volunteer session','created'=>$created]);
                    return true;
                }
                if (($_POST['operation']??'')!=='configure') fail(422,'Choose configure or authorize.');
                render('volunteer-authorize',['title'=>'Authorize volunteer session','draft'=>$draft,'configuration'=>$configuration,'error'=>'']);
                return true;
            } catch (HttpError $caught) {
                http_response_code($caught->status); $error=$caught->getMessage();
                if (isset($configuration) && ($_POST['operation']??'')==='authorize') {
                    render('volunteer-authorize',['title'=>'Authorize volunteer session','draft'=>$draft,'configuration'=>$configuration,'error'=>$error]);
                    return true;
                }
            }
        } elseif ($method!=='GET') fail(405,'Use GET or POST.');
        render('volunteer-setup',['title'=>'Start volunteering','draft'=>$draft,'error'=>$error]);
        return true;
    }
    if (!preg_match('~^/volunteer/([a-f0-9]{32})(?:/(open|assignments/([a-f0-9]{32})))?$~D',$path,$match)) return false;
    $id=$match[1]; $suffix=$match[2]??'';
    if ($suffix==='open') {
        if ($method==='POST') {
            vs_exchange($id,text_field($_POST,'session_secret',64,64));
            redirect('/volunteer/'.$id);
        }
        if ($method!=='GET') fail(405,'Use an explicit POST to open a private session.');
        render('volunteer-open',['title'=>'Open private volunteer session','sessionId'=>$id]);
        return true;
    }
    $session=vs_read($id);
    if (!empty($match[3])) {
        vs_assignment_page($session,vs_assignment($session,$match[3]),$method);
        return true;
    }
    $error='';
    if ($method==='POST') {
        try {
            $operation=text_field($_POST,'operation',1,16);
            if ($operation==='stop') vs_stop($id);
            elseif ($operation==='claim') {
                $assignment=vs_claim_next($id);
                $handoff=$assignment['prepared_handoff'];
                $session=vs_read($id); $item=vs_public_assignment($session,$assignment);
                render('volunteer-handoff',['title'=>'Reserved task handoff','session'=>$session,'assignment'=>$assignment,'handoff'=>$handoff,'reservationText'=>$item['reservation_text']]);
                return true;
            } elseif ($operation==='handoff') {
                $assignment=vs_assignment($session,text_field($_POST,'assignment_id',32,32));
                $handoff=vs_handoff($id,$assignment['id']); $item=vs_public_assignment($session,$assignment);
                render('volunteer-handoff',['title'=>'Reserved task handoff','session'=>$session,'assignment'=>$assignment,'handoff'=>$handoff,'reservationText'=>$item['reservation_text']]);
                return true;
            } elseif (in_array($operation,['renew','release','reacquire'],true)) vs_assignment_action($id,text_field($_POST,'assignment_id',32,32),$operation);
            else fail(422,'Choose an available session action.');
            redirect('/volunteer/'.$id);
        } catch (HttpError $caught) { http_response_code($caught->status); $error=$caught->getMessage(); }
    } elseif ($method!=='GET') fail(405,'Use GET or POST.');
    render('volunteer-session',['title'=>'Private volunteer session','session'=>vs_read($id),'error'=>$error]);
    return true;
}
