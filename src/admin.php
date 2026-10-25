<?php
if($path==='/admin/login') {
    if($method==='POST') {
        throttle('login-ip',8,900);
        $email=strtolower(text_field($_POST,'email',0,254));
        throttle('login-account:'.hash('sha256',$email),8,900,true);
        $a=one('SELECT * FROM admins WHERE email=?',[$email]);
        $dummy='$2y$10$92IXUNpkjO0rOQ5byMi.Ye4oKoEa3Ro9llC/.og/at2uheWG/igi.';
        $password = $_POST['password'] ?? '';
        if (!is_string($password) || strlen($password) > 4096) fail(422, 'Invalid password field.');
        if(!password_verify($password,$a['password_hash']??$dummy) || !$a) fail(403,'Email or password is incorrect.');
        session_regenerate_id(true); $_SESSION['admin']=$a['id']; $_SESSION['admin_signature']=hash('sha256',$a['password_hash']); $_SESSION['csrf']=bin2hex(random_bytes(32)); redirect('/admin');
    }
    render('login',['title'=>'Administrator sign in']); return;
}
if(!admin()) redirect('/admin/login');
if($path==='/admin/audit'){render('audit-site',['title'=>'Site audit ledger']);return;}
if($path==='/admin/workflow-policy'){
    if($method==='POST'){wf_save_policy(text_field($_POST,'models',2,10000),text_field($_POST,'reason',10,500));flash('Planning policy updated. Existing runs retain their captured policy.');redirect('/admin/workflow-policy');}
    render('workflow-policy',['title'=>'Planning model policy']);return;
}
if($path==='/admin/logout' && $method==='POST') { $_SESSION=[]; session_regenerate_id(true); redirect('/'); }
if($path==='/admin/moderate' && $method==='POST') {
    moderate(text_field($_POST,'type'),text_field($_POST,'id'),text_field($_POST,'state'),moderation_reason($_POST),text_field($_POST,'exception'));
    flash('Moderation decision saved.');
    $back=(string)($_POST['back']??'/admin'); redirect(preg_match('~^/admin(?:/research/[a-f0-9]{32})?$~',$back)?$back:'/admin');
}
if($path==='/admin/settings') {
    if($method==='POST') {
        tx(function() {
        foreach(['archive_days'=>[1,3650],'max_file_mb'=>[1,10],'max_total_mb'=>[1,25],'submission_limit'=>[1,1000]] as $key=>$range) {
            $v=filter_var($_POST[$key]??null,FILTER_VALIDATE_INT);
            if($v===false || $v<$range[0] || $v>$range[1]) fail(422,'Invalid setting: '.$key);
            set_setting($key,(string)$v);
        }
        $driver=text_field($_POST,'mail_driver');$from=text_field($_POST,'mail_from',1,254);
        if(!in_array($driver,['file','mail'],true)||!filter_var($from,FILTER_VALIDATE_EMAIL))fail(422,'Select a supported email adapter and a valid sender address.');
        set_setting('mail_driver',$driver);set_setting('mail_from',$from);
        set_setting('maintenance',isset($_POST['maintenance'])?'1':'0');
        foreach(['home_title','home_intro','notice_template'] as $key) set_setting($key,text_field($_POST,$key,1,5000));
        });
        flash('Site settings saved.'); redirect('/admin/settings');
    }
    render('settings',['title'=>'Site settings']); return;
}
if($path==='/admin/operator') {
    if($method==='POST'){save_operator_details($_POST);flash('Public operator details saved.');redirect('/admin/operator');}
    render('operator',['title'=>'Operator & source code']);return;
}
if($path==='/admin/content') {
    $pages=['about','terms','privacy','licensing','moderation','copyright'];
    if($method==='POST') {
        $key=text_field($_POST,'page'); if(!in_array($key,$pages,true)) fail(422,'Unknown page.');
        save_public_page($key,text_field($_POST,'body',50)); flash('Page saved.'); redirect('/admin/content?page='.$key);
    }
    $key=in_array($_GET['page']??'', $pages,true)?$_GET['page']:'about';
    render('content',['title'=>'Public information pages','pages'=>$pages,'key'=>$key]); return;
}
if($path==='/admin/taxonomy') {
    if($method==='POST') {
        $kind=text_field($_POST,'kind'); $action=text_field($_POST,'action');
        if(!in_array($kind,['categories','tags'],true)) fail(422,'Invalid taxonomy.');
        $id=(int)($_POST['id']??0);
        if($action==='add') insert($kind,['name'=>text_field($_POST,'name',1,$kind==='tags'?60:120)]);
        elseif($action==='rename') { query("UPDATE $kind SET name=? WHERE id=?",[text_field($_POST,'name',1,$kind==='tags'?60:120),$id]); query('UPDATE research SET context_version=context_version+1,updated_at=?',[now()]); }
        elseif($action==='disable' && $kind==='categories') query('UPDATE categories SET enabled=1-enabled WHERE id=?',[$id]);
        elseif($action==='merge') {
            $target=(int)($_POST['target']??0);
            if($target===$id || !one("SELECT id FROM $kind WHERE id=?",[$target])) fail(422,'Choose a different existing destination.');
            tx(function() use($kind,$id,$target) {
                if($kind==='categories') query('UPDATE research SET category_id=?,context_version=context_version+1,updated_at=? WHERE category_id=?',[$target,now(),$id]);
                else {
                    query('UPDATE research r JOIN research_tags rt ON rt.research_id=r.id SET r.context_version=r.context_version+1,r.updated_at=? WHERE rt.tag_id=?',[now(),$id]);
                    query('INSERT IGNORE INTO research_tags(research_id,tag_id) SELECT research_id,? FROM research_tags WHERE tag_id=?',[$target,$id]);
                }
                query("DELETE FROM $kind WHERE id=?",[$id]);
            });
        } else fail(422,'Unsupported action.');
        flash('Categories and tags updated.'); redirect('/admin/taxonomy');
    }
    render('taxonomy',['title'=>'Categories & tags']); return;
}
if($path==='/admin/system') {
    if($method==='POST') {
        $action=text_field($_POST,'action');
        if($action==='archive') {
            $cutoff=gmdate('Y-m-d H:i:s',time()-86400*(int)setting('archive_days','60'));
            foreach(all("SELECT id FROM research WHERE moderation='Approved' AND status NOT IN ('Solved','Paused','Withdrawn','Archived — Inactive') AND activity_at<?",[$cutoff]) as $r) change_status($r['id'],'Archived — Inactive','No meaningful approved activity in the configured inactivity period.','administrator');
        } elseif($action==='backup') {
            if(($_POST['confirm_backup']??'')!=='yes')fail(422,'Confirm that the database backup is private.');
            create_backup();
        } elseif($action==='cleanup') { foreach(all("SELECT id FROM purge_jobs WHERE state<>'complete'") as $j) cleanup_purge($j['id']); query('DELETE FROM rate_limits WHERE expires<?',[time()]); }
        elseif($action==='handoffs') { wf_prune_handoffs(); }
        else fail(422,'Unknown maintenance action.');
        flash('Maintenance completed.'); redirect('/admin/system');
    }
    render('system',['title'=>'System health & maintenance']); return;
}
if(preg_match('~^/admin/files/([a-f0-9]{32})$~',$path,$m)) serve_attachment($m[1],true);
if(preg_match('~^/admin/research/([a-f0-9]{32})/ledger$~',$path,$wm)){
    $r=research($wm[1],false);render('workflow-ledger',['title'=>'Private audit ledger','r'=>$r,'private'=>true]);return;
}
if(preg_match('~^/admin/research/([a-f0-9]{32})/packages/([a-f0-9]{32})$~',$path,$wm)){
    $s=wf_read_snapshot($wm[1],$wm[2],true);header('Content-Type: application/json; charset=utf-8');echo $s['package_json'];return;
}
if(preg_match('~^/admin/research/([a-f0-9]{32})$~',$path,$m)) {
    $r=research($m[1],false); $rid=$r['id'];
    if($method==='POST') {
        $action=text_field($_POST,'action');
        if($action==='workflow-resolve')wf_resolve($rid,text_field($_POST,'response',20,30000),text_field($_POST,'reason',10,500),'administrator',(int)($_POST['round_no']??0));
        elseif($action==='workflow-influence-review')wf_review_influence($rid,text_field($_POST,'result_id',32,32),text_field($_POST,'reason',10,1000),text_field($_POST,'decision',1,20));
        elseif($action==='workflow-remove')wf_remove_result($rid,text_field($_POST,'result_id',32,32),text_field($_POST,'reason',10,500));
        elseif($action==='workflow-restart'){tx(function()use($rid){one('SELECT id FROM research WHERE id=? FOR UPDATE',[$rid]);wf_restart($rid,text_field($_POST,'reason',10,500));});}
        elseif($action==='status') change_status($rid,text_field($_POST,'status'),text_field($_POST,'reason',10),'administrator');
        elseif($action==='finding') add_finding($rid,$_POST);
        elseif($action==='purge') { purge_request($rid,text_field($_POST,'confirmation')); flash('Research purged. Check system maintenance for any file-cleanup retries.'); redirect('/admin'); }
        elseif($action==='notice') mail_notice($rid,'Update on your LLM.Giving request',text_field($_POST,'body',10,10000));
        elseif($action==='tags') { tx(function() use($rid) { set_tags($rid,text_field($_POST,'tags',0,800)); $cat=(int)($_POST['category_id']??0); if(!one('SELECT id FROM categories WHERE id=?',[$cat])) fail(422,'Invalid category.'); query('UPDATE research SET category_id=? WHERE id=?',[$cat,$rid]); touch_context($rid); }); }
        elseif($action==='edit') {
            insert('revisions',['id'=>uid(),'research_id'=>$rid,'title'=>text_field($_POST,'title',8,180),'question'=>text_field($_POST,'question',20),'context'=>text_field($_POST,'context'),'moderation'=>'Pending Approval','moderation_reason'=>'','flags'=>'Administrator-proposed revision','created_at'=>now()]);
        } elseif($action==='link') {
            $other=research(text_field($_POST,'related_id'),false); if($other['id']===$rid) fail(422,'Cannot associate a request with itself.');
            query('INSERT INTO research_links(research_id,related_id,reason) VALUES (?,?,?) ON DUPLICATE KEY UPDATE reason=VALUES(reason)',[$rid,$other['id'],text_field($_POST,'reason',10)]); touch_context($rid);
        } elseif($action==='supersede') {
            $old=text_field($_POST,'old_id'); $new=text_field($_POST,'new_id');
            if($old===$new) fail(422,'Select different contributions.');
            foreach([$old,$new] as $id) if(!one("SELECT id FROM contributions WHERE id=? AND research_id=? AND moderation='Approved'",[$id,$rid])) fail(422,'Contributions must belong to this research and be approved.');
            query('UPDATE contributions SET superseded_by=? WHERE id=?',[$new,$old]); touch_context($rid);
        } else fail(422,'Unknown research action.');
        flash('Research record updated.'); redirect('/admin/research/'.$rid);
    }
    render('admin-research',['title'=>'Manage research','r'=>$r]); return;
}
if($path==='/admin') { render('admin',['title'=>'Research administration']); return; }
fail(404,'Admin page not found.');
