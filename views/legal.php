<article class="legal narrow"><h1><?=e($title)?></h1>
<?php if(($path??'')!=='/about'): ?>
<p class="fine">Operator: <?=e(setting('operator_name','LLM.Giving'))?> · <?=e(setting('operator_country','United States'))?></p>
<p><a class="button secondary" href="mailto:<?=e(setting('operator_email','info@llm.giving'))?>"><?=e(setting('operator_email','info@llm.giving'))?></a></p>
<?php if(str_starts_with($path??'','/legal/')): ?><p class="fine">Policy version: <?=e(terms_version())?></p><?php endif ?>
<?php if(($path??'')==='/legal/licensing'): ?>
<?php if(setting('source_url')): ?><p><a class="button" href="<?=e(setting('source_url'))?>">View application source code</a></p><?php else: ?><p class="notice">The source repository has not been published yet. Its link will appear here when available.</p><?php endif ?>
<p><a href="https://www.gnu.org/licenses/agpl-3.0.html">AGPL-3.0 license</a> · <a href="https://creativecommons.org/licenses/by/4.0/">CC BY 4.0 license</a></p>
<?php endif ?>
<?php endif ?>
<?=prose(str_ireplace('llm.giving','LLM.Giving',$body))?>
<?php if(($path??'')==='/legal/privacy'): $analyticsNotice=google_privacy_notice(); ?><section aria-labelledby="analytics-notice"><h2 id="analytics-notice"><?=e($analyticsNotice['title'])?></h2><?=prose($analyticsNotice['body'])?></section><?php endif ?>
</article>