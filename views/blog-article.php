<article class="blog-document">
    <header class="blog-heading page-heading">
        <a class="blog-back" href="/blog">← All articles</a>
        <h1><?=e($post['title'])?></h1>
        <p class="lead"><?=e($post['excerpt'])?></p>
        <p class="blog-reading"><?=e((string)$post['readingMinutes'])?> min read <span aria-hidden="true">·</span> LLM.Giving</p>
        <div class="actions primary-actions"><a class="button" href="#article-body">Read article ↓</a><a class="button secondary" href="/research">Explore research ↗</a></div>
    </header>
    <details class="blog-contents"><summary>In this article</summary><nav aria-label="Article sections"><?php foreach($post['headings'] as $heading): ?><a href="#<?=e($heading['id'])?>"><?=e($heading['text'])?></a><?php endforeach ?></nav></details>
    <div id="article-body" class="blog-body" tabindex="-1"><?=blog_body($post['blocks'])?></div>
</article>
<section class="blog-related" aria-labelledby="related-title"><h2 id="related-title">Keep exploring</h2><div class="blog-grid">
<?php foreach($post['relatedSlugs'] as $slug): $related=blog_post($slug); ?>
    <article class="blog-card"><p class="blog-kicker"><?=e((string)$related['readingMinutes'])?> min read</p><h3><a href="/blog/<?=e($related['slug'])?>"><?=e($related['title'])?></a></h3><p class="blog-excerpt"><?=e($related['excerpt'])?></p><a class="blog-read" href="/blog/<?=e($related['slug'])?>">Read article <span aria-hidden="true">↗</span><span class="visually-hidden">: <?=e($related['title'])?></span></a></article>
<?php endforeach ?>
</div></section>