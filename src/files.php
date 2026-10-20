<?php
function validate_uploads(array $files): array {
    if(empty($files['name'])) return [];
    if(!is_array($files['name'])) fail(422,'Invalid upload form.');
    if(count($files['name'])>10) fail(422,'At most 10 attachments per submission.');
    $out=[]; $total=0;
    $per=(int)setting('max_file_mb',env('MAX_FILE_MB','10'))*1024*1024;
    $max=(int)setting('max_total_mb',env('MAX_TOTAL_MB','25'))*1024*1024;
    foreach($files['name'] as $i=>$name) {
        if (!is_string($name) || !is_int($files['error'][$i] ?? null) || !is_string($files['tmp_name'][$i] ?? null)) {
            fail(422, 'Invalid upload form.');
        }
        $error=$files['error'][$i]??UPLOAD_ERR_NO_FILE; if($error===UPLOAD_ERR_NO_FILE) continue;
        if($error!==UPLOAD_ERR_OK) fail(422,'An attachment could not be uploaded; check its size.');
        $tmp=$files['tmp_name'][$i]; if(!is_uploaded_file($tmp)) fail(422,'Invalid upload source.');
        $size=filesize($tmp); $total+=$size;
        if(!$size || $size>$per || $total>$max) fail(422,'Attachment size limit exceeded.');
        if(!class_exists('finfo')) fail(503,'File validation is unavailable. Ask the administrator to enable fileinfo.');
        $mime=(new finfo(FILEINFO_MIME_TYPE))->file($tmp);
        $ext=strtolower(pathinfo($name,PATHINFO_EXTENSION));
        $allowed=['txt'=>'text/plain','md'=>'text/plain','pdf'=>'application/pdf','png'=>'image/png','jpg'=>'image/jpeg','jpeg'=>'image/jpeg'];
        if(!isset($allowed[$ext]) || $mime!==$allowed[$ext]) fail(422,'Only validated plain text, Markdown, PDF, PNG and JPEG files are accepted.');
        $head=file_get_contents($tmp,false,null,0,10485760);
        if(preg_match('/<\?(?:php|=)|<script|<html|<svg|javascript:|\/JavaScript|\/JS\b|\/Launch|\/EmbeddedFile|\/OpenAction|\/AA\b/i',$head)) fail(422,'This attachment contains unsupported active or embedded content.');
        if($mime==='application/pdf' && (!str_starts_with($head,'%PDF-') || !str_contains($head,'%%EOF'))) fail(422,'Invalid PDF document.');
        $scan='Not scanned — manual review required';
        $scanner=env('CLAMSCAN_PATH');
        if($scanner!=='') {
            if(!is_file($scanner)) fail(503,'Configured malware scanner is unavailable.');
            $proc=proc_open([$scanner,'--no-summary',$tmp],[1=>['pipe','w'],2=>['pipe','w']],$pipes);
            if(!is_resource($proc)) fail(503,'Malware scanner could not run.');
            stream_get_contents($pipes[1]); stream_get_contents($pipes[2]); fclose($pipes[1]); fclose($pipes[2]); $code=proc_close($proc);
            if($code===1) fail(422,'Malware scanner rejected this attachment.');
            if($code!==0) fail(503,'Malware scanner failed; upload blocked.');
            $scan='Scanned — no threat detected';
        }
        $out[]=['tmp'=>$tmp,'original_name'=>substr(basename(str_replace('\\','/',$name)),0,190),'mime'=>$mime,'bytes'=>$size,'scan_status'=>$scan];
    }
    return $out;
}
function store_uploads(string $rid,array $files,?string $cid=null,?string $fid=null): void {
    $written=[];
    one('SELECT id FROM research WHERE id=? FOR UPDATE',[$rid]) ?? fail(404,'Research not found.');
    $current=(int)one('SELECT COALESCE(SUM(bytes),0) bytes FROM attachments WHERE research_id=?',[$rid])['bytes'];
    if($current+array_sum(array_column($files,'bytes'))>(int)setting('max_total_mb',env('MAX_TOTAL_MB','25'))*1024*1024) fail(422,'This research request exceeds its total attachment storage limit.');
    try {
        foreach($files as $f) {
            $id=uid(); $name=$id.'.blob'; $path=storage('uploads/'.$name);
            if(in_array($f['mime'],['image/png','image/jpeg'],true)) {
                if(!function_exists('imagecreatefromstring')) fail(503,'Image re-encoding is unavailable. Enable GD or submit a text/PDF file.');
                $info=getimagesize($f['tmp']);
                if(!$info || $info[0]*$info[1]>16000000) fail(422,'Image dimensions exceed the 16 megapixel limit.');
                $im=imagecreatefromstring(file_get_contents($f['tmp'])); if(!$im) fail(422,'Invalid image content.');
                $ok=$f['mime']==='image/png'?imagepng($im,$path):imagejpeg($im,$path,90);
                if(!$ok) throw new RuntimeException('Image storage failed');
            } elseif(!move_uploaded_file($f['tmp'],$path)) throw new RuntimeException('Upload storage failed');
            $written[]=$path; $GLOBALS['transaction_files'][]=$path;
            insert('attachments',['id'=>$id,'research_id'=>$rid,'contribution_id'=>$cid,'feedback_id'=>$fid,'original_name'=>$f['original_name'],'stored_name'=>$name,'mime'=>$f['mime'],'bytes'=>filesize($path),'sha256'=>hash_file('sha256',$path),'moderation'=>'Pending Approval','scan_status'=>$f['scan_status'],'moderation_reason'=>'','flags'=>'Download only; publication requires separate attachment approval.','created_at'=>now()]);
        }
    } catch(Throwable $e) { foreach($written as $path) if(is_file($path)) unlink($path); throw $e; }
}
function public_attachments(string $rid): array {
    research($rid);
    return all("SELECT a.id,a.research_id,a.contribution_id,a.feedback_id,a.original_name,a.mime,a.bytes,a.sha256 FROM attachments a WHERE a.research_id=? AND a.moderation='Approved' AND (a.contribution_id IS NULL OR EXISTS(SELECT 1 FROM contributions c WHERE c.id=a.contribution_id AND c.moderation='Approved' AND (c.target_id IS NULL OR EXISTS(SELECT 1 FROM contributions t WHERE t.id=c.target_id AND t.moderation='Approved')))) AND (a.feedback_id IS NULL OR EXISTS(SELECT 1 FROM feedback f WHERE f.id=a.feedback_id AND f.moderation='Approved' AND (f.target_id IS NULL OR EXISTS(SELECT 1 FROM contributions c WHERE c.id=f.target_id AND c.moderation='Approved')))) ORDER BY a.created_at,a.id",[$rid]);
}
function serve_attachment(string $id,bool $private=false): never {
    $a=one('SELECT * FROM attachments WHERE id=?',[$id])??fail(404,'Attachment not found.');
    if($private) require_admin();
    else {
        $allowed=array_column(public_attachments($a['research_id']),'id');
        if(!in_array($id,$allowed,true)) fail(404,'Attachment not found.');
    }
    $path=storage('uploads/'.basename($a['stored_name']));
    if(!is_file($path)) fail(404,'Attachment unavailable.');
    header('Content-Type: application/octet-stream');
    header("Content-Security-Policy: sandbox; default-src 'none'");
    header("Content-Disposition: attachment; filename=\"download\"; filename*=UTF-8''".rawurlencode($a['original_name']));
    header('Content-Length: '.filesize($path)); readfile($path); exit;
}
