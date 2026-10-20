<?php $citationText=research_citation($r,$citationResult??null);$citationId='citation-'.($citationResult['id']??$r['id']); ?>
<section class="research-citation" aria-label="Research citation">
<div class="actions"><button class="secondary" data-copy-input="<?=e($citationId)?>" data-copy-citation>Cite this research</button><a href="https://creativecommons.org/licenses/by/4.0/">CC BY 4.0 · attribution required</a></div>
<details><summary>View citation &amp; reuse guidance</summary><label for="<?=e($citationId)?>">Copyable citation</label><textarea id="<?=e($citationId)?>" readonly rows="4"><?=e($citationText)?></textarea><p class="fine">Credit the named contributors and LLM.Giving, link to this result and the license, and indicate changes. Cite the underlying scientific sources too. This citation does not imply endorsement or independent verification.</p></details>
</section>
