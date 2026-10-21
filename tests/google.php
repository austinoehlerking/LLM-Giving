<?php
require __DIR__.'/bootstrap.php';
require ROOT.'/src/view.php';
$checks = 0;
function google_check(bool $ok, string $name): void {
    global $checks;
    if (!$ok) throw new RuntimeException($name);
    $checks++;
}
putenv('GOOGLE_ANALYTICS_ID=G-TEST123456');
putenv('GOOGLE_SITE_VERIFICATION=synthetic_verification_token_123456');
putenv('GOOGLE_ANALYTICS_ENABLED=1');
$_SESSION = [];
$_SERVER['REQUEST_METHOD'] = 'GET';
foreach (['/','/research','/about','/contact','/legal/privacy','/blog','/blog/test-article','/research/public-topic-123abc?q=private#secret'] as $route) {
    $_SERVER['REQUEST_URI'] = $route;
    google_check(google_analytics_request(), 'Public route eligible: '.$route);
}
foreach (['/admin','/admin/login','/manage/'.str_repeat('a',32),'/submit','/recover','/research/test/context.json','/research/test/contribute','/research/test/authorize','/research/test/authorize/'.str_repeat('a',32),'/research/test/return/'.str_repeat('a',32),'/research/test/return/'.str_repeat('a',32).'/submit','/research/test/contributions/'.str_repeat('a',32).'/receipt','/research/test/tasks/secret','/file/secret','/sitemap.xml','/blog/%2e%2e','//manage/secret'] as $route) {
    $_SERVER['REQUEST_URI'] = $route;
    google_check(!google_analytics_request(), 'Private or non-HTML route excluded: '.$route);
}
$_SERVER['REQUEST_URI'] = '/';
foreach (['POST','HEAD'] as $method) {
    $_SERVER['REQUEST_METHOD'] = $method;
    google_check(!google_analytics_request(), $method.' excluded');
}
$_SERVER['REQUEST_METHOD'] = 'GET';
foreach (['admin'=>1,'manage'=>['private'=>['hash'=>'secret']]] as $key=>$value) {
    $_SESSION = [$key=>$value];
    google_check(!google_analytics_request(), 'Private '.$key.' session excluded');
}
$_SESSION = [];
foreach (['G-TEST123456','', 'G-X" onload="alert(1)', 'UA-123-1'] as $id) {
    putenv('GOOGLE_ANALYTICS_ID='.$id);
    google_check(google_measurement_id() === ($id === 'G-TEST123456' ? $id : ''), 'ID validation');
}
putenv('GOOGLE_ANALYTICS_ID=G-TEST123456');
putenv('GOOGLE_SITE_VERIFICATION=bad"><script>');
google_check(google_verification_token() === '', 'Invalid ownership token suppressed');
putenv('GOOGLE_SITE_VERIFICATION=synthetic_verification_token_123456');
http_response_code(200);
ob_start(); google_head('home'); $head = ob_get_clean();
google_check(str_contains($head, 'google-site-verification') && str_contains($head, '/assets/analytics.js'), 'Public home has verification and local loader');
google_check(!str_contains($head, 'googletagmanager.com'), 'Server does not load remote Google script');
foreach (['receipt','manage','message','submit','login'] as $view) {
    ob_start(); google_head($view); $head = ob_get_clean();
    google_check($head === '', 'Sensitive view omits integration: '.$view);
}
http_response_code(404);
ob_start(); google_head('research'); $head = ob_get_clean();
google_check($head === '', 'Error responses omit integration');
http_response_code(200);
putenv('GOOGLE_ANALYTICS_ENABLED');
google_check(!google_analytics_request(), 'Local environment defaults to disabled');
$originalEnvironment = env('APP_ENV');
putenv('APP_ENV=production');
google_check(google_analytics_request(), 'Production defaults to automatic public analytics');
putenv('GOOGLE_ANALYTICS_ENABLED=0');
google_check(!google_analytics_request(), 'Global disable override is honored');
putenv('GOOGLE_ANALYTICS_ENABLED');
putenv('APP_ENV='.$originalEnvironment);
echo $checks." Google integration checks passed.\n";
