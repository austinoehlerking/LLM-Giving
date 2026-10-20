<?php
// Only the compiled, explicitly published editorial collection is public.
function blog_posts(): array {
    static $posts;
    if($posts!==null) return $posts;
    $collection=json_decode(file_get_contents(ROOT.'/content/blog/articles.json'),true,512,JSON_THROW_ON_ERROR);
    if(($collection['version']??null)!==1 || !is_array($collection['posts']??null)) throw new RuntimeException('Invalid editorial collection.');
    $posts=[];
    foreach($collection['posts'] as $post) {
        if(!preg_match('/^[a-z0-9]+(?:-[a-z0-9]+)*$/D',$post['slug']??'')) throw new RuntimeException('Invalid editorial slug.');
        $posts[$post['slug']]=$post;
    }
    return $posts;
}
function blog_post(string $slug): array {
    $posts=blog_posts();
    if(!isset($posts[$slug])) fail(404,'Article not found.');
    return $posts[$slug];
}
function blog_url(string $path): string {
    return rtrim(env('APP_URL','http://127.0.0.1:8086'),'/').$path;
}
function blog_inline(array $tokens): string {
    $html='';
    foreach($tokens as $token) {
        if($token['type']==='text') $html.=e($token['text']);
        elseif($token['type']==='strong') $html.='<strong>'.blog_inline($token['children']).'</strong>';
        elseif($token['type']==='link') {
            $url=$token['href'];
            $internal=preg_match('~^/(about|research|submit)$~D',$url);
            $external=filter_var($url,FILTER_VALIDATE_URL) && parse_url($url,PHP_URL_SCHEME)==='https' && parse_url($url,PHP_URL_USER)===null && parse_url($url,PHP_URL_PASS)===null && !preg_match('/[\s\\\\]/',$url);
            if(!$internal && !$external) throw new RuntimeException('Invalid editorial link.');
            $html.='<a href="'.e($url).'"'.($external?' rel="noopener noreferrer"':'').'>'.blog_inline($token['children']).'</a>';
        } else throw new RuntimeException('Invalid editorial text.');
    }
    return $html;
}
function blog_body(array $blocks): string {
    $html='';
    foreach($blocks as $block) {
        if($block['type']==='heading') $html.='<h2 id="'.e($block['id']).'">'.blog_inline($block['content']).'</h2>';
        elseif($block['type']==='paragraph') $html.='<p>'.blog_inline($block['content']).'</p>';
        elseif($block['type']==='quote') $html.='<blockquote><p>'.blog_inline($block['content']).'</p></blockquote>';
        elseif($block['type']==='list') {
            $tag=$block['ordered']?'ol':'ul';$html.='<'.$tag.'>';
            foreach($block['items'] as $item) $html.='<li>'.blog_inline($item).'</li>';
            $html.='</'.$tag.'>';
        } elseif($block['type']==='table') {
            $html.='<div class="blog-table" role="region" aria-label="Comparison table" tabindex="0"><table><thead><tr>';
            foreach($block['header'] as $cell) $html.='<th scope="col">'.blog_inline($cell).'</th>';
            $html.='</tr></thead><tbody>';
            foreach($block['rows'] as $row) {$html.='<tr>';foreach($row as $cell) $html.='<td>'.blog_inline($cell).'</td>';$html.='</tr>';}
            $html.='</tbody></table></div>';
        } else throw new RuntimeException('Invalid editorial block.');
    }
    return $html;
}
function blog_metadata(?array $post=null): array {
    $canonical=blog_url($post?'/blog/'.$post['slug']:'/blog');
    $schema=['@context'=>'https://schema.org','@type'=>$post?'BlogPosting':'Blog','name'=>$post['title']??'The LLM.Giving blog','url'=>$canonical,'publisher'=>['@type'=>'Organization','name'=>'LLM.Giving']];
    if($post) {$schema['headline']=$post['title'];$schema['description']=$post['description'];$schema['mainEntityOfPage']=$canonical;}
    else $schema['blogPost']=array_map(fn($p)=>['@type'=>'BlogPosting','headline'=>$p['title'],'url'=>blog_url('/blog/'.$p['slug'])],array_values(blog_posts()));
    return ['title'=>$post['seoTitle']??'The LLM.Giving blog','description'=>$post['description']??'Practical guides to spare AI usage, volunteer research, citizen science, and AI for good. Find a useful way to contribute with your own AI tools.','canonical'=>$canonical,'socialType'=>$post?'article':'website','structuredData'=>$schema,'blogPage'=>true];
}
function blog_sitemap(): string {
    $paths=['/','/research','/about','/blog'];
    foreach(blog_posts() as $post) $paths[]='/blog/'.$post['slug'];
    $xml='<?xml version="1.0" encoding="UTF-8"?><urlset xmlns="http://www.sitemaps.org/schemas/sitemap/0.9">';
    foreach($paths as $path) $xml.='<url><loc>'.htmlspecialchars(blog_url($path),ENT_XML1|ENT_QUOTES,'UTF-8').'</loc></url>';
    return $xml.'</urlset>';
}