<?php
require __DIR__.'/bootstrap.php';
$_SESSION['admin']=1;
$action=$argv[1]??'';
if($action==='cleanup') {
 foreach(all("SELECT id FROM research WHERE title LIKE '[QA]%'") as $r) purge_request($r['id'],$r['id']);
 query("DELETE FROM admins WHERE email='browser-qa@example.invalid'");
} elseif($action==='evidence') {
 echo json_encode(rs_test_supplement(['body'=>$argv[2],'methodology'=>$argv[3],'sources'=>$argv[4]])['evidence']);
} elseif($action==='preview') {
 $r=one("SELECT slug FROM research WHERE moderation='Approved' AND status<>'Withdrawn' ORDER BY created_at LIMIT 1");
 echo json_encode($r);
} elseif($action==='lookup') {
 $rid=$argv[2]??'';
 if(!one("SELECT id FROM research WHERE id=? AND title LIKE '[QA]%'",[$rid])) exit(2);
 echo json_encode(['attachments'=>all('SELECT id,stored_name FROM attachments WHERE research_id=?',[$rid]),'contributions'=>all('SELECT id,kind,moderation FROM contributions WHERE research_id=? ORDER BY created_at,id',[$rid]),'feedback'=>all('SELECT id FROM feedback WHERE research_id=?',[$rid]),'versions'=>all('SELECT version FROM research_versions WHERE research_id=?',[$rid])]);
}
