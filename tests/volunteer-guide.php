<?php
declare(strict_types=1);
// Pure template/link/configuration contract checks: no database, HTTP server or browser.
const ROOT=__DIR__.'/..';
$checks=0;
function guide_check(bool $condition,string $message): void { global $checks; if(!$condition) throw new RuntimeException($message); $checks++; }
function guide_render(string $view): string { ob_start(); require ROOT.'/views/'.$view.'.php'; return (string)ob_get_clean(); }
function guide_dom(string $html): DOMDocument { $previous=libxml_use_internal_errors(true); $dom=new DOMDocument(); $dom->loadHTML('<?xml encoding="UTF-8">'.$html); libxml_clear_errors(); libxml_use_internal_errors($previous); return $dom; }
$html=guide_render('volunteer-quick-start');
$dom=guide_dom($html); $xpath=new DOMXPath($dom);
guide_check($xpath->query('//article[@aria-labelledby="quick-start-title"]')->length===1,'Guide has a named article');
guide_check($xpath->query('//h1')->length===1,'Guide has one main heading');
guide_check($xpath->query('//section[@aria-labelledby="five-steps"]/ol/li')->length===5,'Primary path has five steps');
$actions=$xpath->query('//*[@data-guide-actions]/a');
guide_check($actions->length===2,'Initial action area offers session and single-task entry');
guide_check($actions->item(0)->getAttribute('href')==='/volunteer'&&$actions->item(1)->getAttribute('href')==='/research','Primary actions use implemented public routes');
guide_check($xpath->query('//script|//form|//input|//textarea')->length===0,'Public guide does not collect or embed authorization');
$ids=[];foreach($xpath->query('//*[@id]') as $node){$id=$node->getAttribute('id');guide_check(!isset($ids[$id]),'No duplicate guide ID: '.$id);$ids[$id]=true;}
foreach($xpath->query('//a[@href]') as $node){$href=$node->getAttribute('href');guide_check(str_starts_with($href,'/')||str_starts_with($href,'#'),'Guide links stay within the site');if(str_starts_with($href,'#'))guide_check(isset($ids[substr($href,1)]),'In-page guide destination exists: '.$href);else guide_check(in_array(explode('#',$href)[0],['/volunteer','/research','/legal/licensing','/legal/terms'],true),'Guide links target known public destinations');}
guide_check(!preg_match('~/volunteer/[a-f0-9]{32}|/return/[a-f0-9]{32}|#session:~i',$html),'Guide contains no private capability links');
$partial=guide_dom(guide_render('volunteer-guide-link'));
guide_check((new DOMXPath($partial))->query('//a[@href="/volunteer/quick-start"]')->length===1,'Reusable contextual link resolves to the guide');
$index=file_get_contents(ROOT.'/public/index.php');
$route=<<<'ROUTE'
if($path==='/volunteer/quick-start' && $method==='GET')
ROUTE;
guide_check(str_contains($index,$route)&&str_contains($index,"render('volunteer-quick-start'"),'Guide has a fixed GET-only route');
guide_check(strpos($index,$route)<strpos($index,'if(vs_route('),'Guide route dispatch precedes private session routing');
foreach(['volunteer-setup','volunteer-session'] as $view)guide_check(str_contains(file_get_contents(ROOT.'/views/'.$view.'.php'),'volunteer-guide-link.php'),'Existing session hook exposes guide: '.$view);
foreach(['home','legal','research','workflow-task','browser-authorize','browser-handoff'] as $view)guide_check(str_contains(file_get_contents(ROOT.'/views/'.$view.'.php'),'/volunteer/quick-start'),'Public/contextual entry exists: '.$view);
// These labels must still exist in the actual controls described by the guide.
$labels=[
'volunteer-setup'=>['Where would you like to help?','Task assignments','Session minutes','Worker configuration (advanced)','Continue to authorization','Exact worker model','Actual reasoning effort','AI tool or harness name','Workflow mode'],
'volunteer-authorize'=>['Authorize session','Software assignments (optional)'],
'volunteer-created'=>['Open private dashboard'],
'volunteer-orchestrator'=>['Copy orchestrator handoff','Complete private handoff for manual copying'],
'volunteer-session'=>['Activity and recorded outcomes','Read recorded receipt','Stop session','Reacquire this assignment','Session ends'],
'browser-authorize'=>['Authorize your AI','Public human-input declaration','Copy authorized handoff'],
'browser-handoff'=>['Copy AI handoff','Complete handoff with private authorization supplement'],
'workflow-task'=>['Authorize AI browser submission','Copy task prompt','Manual fallback'],
'workflow-submit'=>['Check packet','Submit task result','Copy AI repair prompt','Manually submitted — fallback'],
];
$guideText=html_entity_decode(strip_tags($html),ENT_QUOTES|ENT_HTML5,'UTF-8');
foreach($labels as $view=>$names){$source=html_entity_decode(file_get_contents(ROOT.'/views/'.$view.'.php'),ENT_QUOTES|ENT_HTML5,'UTF-8');foreach($names as $name){guide_check(str_contains($source,$name),'Implemented UI label exists: '.$view.' / '.$name);guide_check(str_contains($guideText,$name),'Guide uses the implemented label: '.$name);}}
echo $checks." volunteer quick-start template/link/label checks passed. Browser workflows and geometry require independent QA.\n";
