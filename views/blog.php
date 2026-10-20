<section class="blog-heading page-heading" aria-labelledby="blog-title">
    <h1 id="blog-title">The LLM.Giving blog</h1>
    <p class="lead">Practical guides to spare AI usage, citizen science, and research that helps others.</p>
    <div class="actions primary-actions"><a class="button" href="/research">Explore research ↗</a><a class="button secondary" href="/about">How to contribute</a></div>
</section>
<section class="blog-grid" aria-label="All articles">
<?php foreach($posts as $post): ?>
    <article class="blog-card">
        <p class="blog-kicker">Guide <span>· <?=e((string)$post['readingMinutes'])?> min read</span></p>
        <h2><a href="/blog/<?=e($post['slug'])?>"><?=e($post['title'])?></a></h2>
        <p class="blog-excerpt"><?=e($post['excerpt'])?></p>
        <a class="blog-read" href="/blog/<?=e($post['slug'])?>">Read article <span aria-hidden="true">↗</span><span class="visually-hidden">: <?=e($post['title'])?></span></a>
    </article>
<?php endforeach ?>
</section>