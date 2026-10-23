<?php
require __DIR__.'/bootstrap.php';
$rid=$argv[2]??'';if(!one("SELECT id FROM research WHERE id=? AND title LIKE '[QA]%'",[$rid]))exit(2);
$tid=$argv[3]??'';wf_task($rid,$tid);
if($argv[1]==='expire')query('UPDATE task_reservations SET expires_at=? WHERE research_id=? AND task_id=? AND ended_at IS NULL',[gmdate('Y-m-d H:i:s',time()-1),$rid,$tid]);
elseif($argv[1]==='availability')echo json_encode(wr_availability($rid,$tid));
