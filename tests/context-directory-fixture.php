<?php
/** Read a required v2 dependency from its pinned public handoff, without substituting live context. */
function qa_required_dependency(string $rid,string $tid,string $prompt,string $name): array {
    $marker='UNTRUSTED TASK CONTEXT DIRECTORY — '.TC_VERSION."\n";
    $parts=explode($marker,$prompt,2);
    if(count($parts)!==2)throw new RuntimeException('Missing versioned task context directory.');
    $directory=json_decode(explode("\n",$parts[1],2)[0],true,64,JSON_THROW_ON_ERROR);
    $unsigned=$directory;unset($unsigned['directory_sha256'],$unsigned['end_marker']);
    $digest=hash('sha256',tc_encode($unsigned));
    if($directory['research_id']!==$rid||$directory['task_id']!==$tid||$directory['directory_sha256']!==$digest||$directory['end_marker']!=='END TASK CONTEXT '.$digest||!str_contains($parts[1],"\nEND OF UNTRUSTED TASK CONTEXT DIRECTORY."))throw new RuntimeException('Task context directory binding or integrity failed.');
    $snapshot=wf_read_snapshot($rid,$directory['context_id']);
    if($directory['context_sha256']!==$snapshot['sha256']||$directory['full_public_history']['sha256']!==$snapshot['sha256'])throw new RuntimeException('Task context must bind its exact immutable history.');
    $entries=array_values(array_filter($directory['required'],fn($entry)=>$entry['name']===$name));
    if(count($entries)!==1)throw new RuntimeException('Expected one required dependency: '.$name);
    $entry=$entries[0];$resource=tc_read_dependency($rid,$directory['context_id'],$entry['digest'],$tid);$body=$resource['body'];
    if(hash('sha256',$body)!==$entry['digest']||strlen($body)!==$entry['byte_length']||count(rtx_parts($body))!==$entry['part_count'])throw new RuntimeException('Required dependency bytes failed integrity: '.$name);
    return ['directory'=>$directory,'entry'=>$entry,'body'=>$body,'value'=>json_decode($body,true,64,JSON_THROW_ON_ERROR)];
}
