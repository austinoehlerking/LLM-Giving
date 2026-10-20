<?php
require __DIR__.'/bootstrap.php';
require ROOT.'/src/view.php';
require ROOT.'/src/blog.php';
$checks=[];
function blog_expect(bool $ok,string $name): void { global $checks;if(!$ok)throw new RuntimeException($name);$checks[]=$name; }
blog_expect(count(blog_posts())===4,'Exactly four reviewed articles are public');
foreach(blog_posts() as $post) {
    $html=blog_body($post['blocks']);
    blog_expect(str_contains($html,'AI usage') && str_contains($html,'unused AI usage'),'Audience terminology survives rendering: '.$post['slug']);
    blog_expect(substr_count($html,'<h2 ')===count($post['headings']),'All article sections render: '.$post['slug']);
    blog_expect(!str_contains($html,'<script') && !str_contains($html,'keywordValidation'),'No markup execution or editorial notes: '.$post['slug']);
    $meta=blog_metadata($post);
    blog_expect(str_ends_with($meta['canonical'],'/blog/'.$post['slug']) && $meta['structuredData']['@type']==='BlogPosting','Article metadata matches route: '.$post['slug']);
    blog_expect(!isset($meta['structuredData']['datePublished']) && !isset($meta['structuredData']['author']),'No invented publication date or named author: '.$post['slug']);
}
blog_expect(str_contains(blog_inline([['type'=>'text','text'=>'<img src=x onerror=alert(1)>']]),'&lt;img'),'Editorial raw HTML is escaped');
foreach(['javascript:alert(1)','//example.org','https://user:pass@example.org','/admin'] as $href) {
    try {blog_inline([['type'=>'link','href'=>$href,'children'=>[['type'=>'text','text'=>'unsafe']]]]);throw new LogicException('Unsafe URL accepted');}
    catch(RuntimeException $error) {blog_expect($error->getMessage()==='Invalid editorial link.','Unsafe editorial URL rejected: '.$href);}
}
foreach(['../.env','not-an-article','tokenmaxxing-meaning'] as $slug) {
    try {blog_post($slug);throw new RuntimeException('Unknown article accepted');}
    catch(HttpError $error) {blog_expect($error->status===404,'Unknown article has no file access: '.$slug);}
}
$sitemap=blog_sitemap();
blog_expect(substr_count($sitemap,'<url>')===8 && !str_contains($sitemap,'/admin') && !str_contains($sitemap,'/manage'),'Sitemap contains only fixed public pages and approved articles');
file_put_contents(storage('qa/blog-unit.json'),json_encode(['passed'=>count($checks),'checks'=>$checks],JSON_PRETTY_PRINT));
echo count($checks)." blog rendering and publication-boundary checks passed.\n";