<article class="legal narrow"><h1><?=e($title)?></h1>
<?php if(($path??'')!=='/about'): ?>
<p class="fine">Operator: <?=e(setting('operator_name','LLM.Giving'))?> · <?=e(setting('operator_country','United States'))?></p>
<p><a class="button secondary" href="mailto:<?=e(setting('operator_email','info@llm.giving'))?>"><?=e(setting('operator_email','info@llm.giving'))?></a></p>
<?php if(str_starts_with($path??'','/legal/')): ?><p class="fine">Policy version: <?=e(terms_version())?></p><?php endif ?>
<?php if(($path??'')==='/legal/licensing'): ?>
<p><a class="button" href="<?=e(source_repository_url())?>">View application source code</a></p><p>LLM.Giving is an open-source project. Inspect the application code, development history and contribution guidance in the source repository. Research content has separate licensing terms below.</p>
<p><a href="https://www.gnu.org/licenses/agpl-3.0.html">AGPL-3.0 license</a> · <a href="https://creativecommons.org/licenses/by/4.0/">CC BY 4.0 license</a></p>
<?php endif ?>
<?php endif ?>
<?=prose(str_ireplace('llm.giving','LLM.Giving',$body))?>
<?php if(($path??'')==='/legal/privacy'): $analyticsNotice=google_privacy_notice(); ?><section aria-labelledby="analytics-notice"><h2 id="analytics-notice"><?=e($analyticsNotice['title'])?></h2><?=prose($analyticsNotice['body'])?></section><?php endif ?>
<?php if(($path??'')==='/legal/terms'): $resourceNotice=contributor_resource_notice(); ?><section aria-labelledby="contributor-resources"><h2 id="contributor-resources"><?=e($resourceNotice['title'])?></h2><?=prose($resourceNotice['body'])?></section><?php endif ?>
<?php if(($path??'')==='/about'): ?><section aria-labelledby="contribute-with-permission"><h2 id="contribute-with-permission">Contribute with permission</h2><p><?=e(CONTRIBUTOR_RESOURCE_CONFIRMATION)?></p><p>Your required consent checkbox confirms this rule before an authorized handoff or contribution. You are responsible for permissions, provider rules, costs and publication rights. LLM.Giving relies on your confirmation; it does not audit or police external resource use.</p><a href="/legal/terms#contributor-resources">Read the resource-use terms</a></section><?php endif ?>
</article>