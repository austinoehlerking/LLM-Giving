<section data-research-transport data-base="<?=e($transportBase)?>" data-private="<?=$private?'1':'0'?>">
<h1><?=$private?'Private packet recovery':'Complete research transport'?></h1>
<p><?=$private?'Keep a complete packet privately for up to seven days. Resume exact parts after a disconnect.':'Retrieve the complete original text in ordered, byte-verified parts.'?> Finalize confirms delivery only. Check and Submit remain separate actions.</p>
<p data-transport-status role="status" aria-live="polite">Ready. No research result has been submitted.</p>
<?php if($private): ?>
<div class="actions primary-actions"><button type="button" data-transport-input>Retrieve complete task context</button><button type="button" data-transport-output>Save complete packet</button></div>
<label for="transport-packet">Complete worker-authored JSON packet</label><textarea id="transport-packet" rows="5" spellcheck="false" autocomplete="off" placeholder="Paste the complete packet, or choose its UTF-8 text file below."></textarea>
<label for="transport-file">UTF-8 packet file (optional, up to 4 MiB)</label><input id="transport-file" type="file" accept=".json,.txt,application/json,text/plain">
<input type="hidden" data-transport-csrf value="<?=e($_SESSION['csrf'])?>">
<label for="transport-binding">Public task binding (contains no private credential)</label><textarea id="transport-binding" readonly rows="3" spellcheck="false"><?=e(rtx_json($binding))?></textarea>
<p data-transport-final hidden>Complete output finalized privately. Nothing submitted. Return to the existing form or use these existing Check/Submit controls; current authorization and all server checks still apply.</p>
<form method="post" action="<?=e($returnPath)?>" data-transport-submit hidden><?=csrf()?><input type="hidden" name="transport_id" value=""><div class="actions"><button name="operation" value="check">Check complete packet</button><button name="operation" value="submit">Submit complete packet</button></div></form>
<?php if(function_exists('wf_packet_contract')): ?><details><summary>Shared packet format and limits</summary><textarea readonly rows="8" aria-label="Authoritative versioned packet contract"><?=e(rtx_json(wf_packet_contract(wf_task($binding['research_id'],$binding['task_id']),wf_read_snapshot($binding['research_id'],$binding['context_id']))))?></textarea></details><?php endif ?>
<h2>Recover a checkpoint</h2><ul data-transport-streams><?php foreach($streams as $stream): ?><li><button type="button" data-resume-stream="<?=e($stream['id'])?>"><?=e(ucfirst($stream['direction']).' · '.$stream['state'].' · until '.$stream['expires_at'].' UTC')?></button> <button type="button" data-delete-stream="<?=e($stream['id'])?>">Delete private checkpoint</button></li><?php endforeach ?></ul>
<details><summary>Manual ordered-part transfer</summary><p>Send CSRF-protected form POSTs here: operation=begin, direction=output, manifest=complete manifest JSON. The response identifies the private stream. POST to that stream with operation=put, index and body_base64. The server acknowledges original bytes. Use operation=finalize after every part arrives. Conflicting duplicates require a new stream; finalize never submits. For input, begin with direction=input, retrieve every declared part/resource, acknowledge its exact SHA-256, then finalize. Never send private URLs to a research-only worker.</p></details>
<?php else: ?>
<div class="actions primary-actions"><button type="button" data-transport-read>Retrieve and verify all parts</button></div>
<label for="transport-manifest">Versioned manifest: byte lengths, ordered parts and SHA-256</label><textarea id="transport-manifest" readonly rows="5" spellcheck="false"><?=e(rtx_json($manifest))?></textarea>
<p><a href="<?=e($transportBase)?>/manifest">Read manifest JSON</a></p>
<?php endif ?>
<div data-transport-resources></div><details data-transport-result><summary>Verified received text</summary><label for="transport-received">Complete original text, only shown after whole-stream verification</label><textarea id="transport-received" readonly rows="12" spellcheck="false" autocomplete="off"></textarea></details>
<noscript><p>JavaScript is unavailable. Read the manifest JSON and retrieve each numbered part through the listed links. Verify each UTF-8 byte length and SHA-256 and the whole concatenation before acknowledging completeness. The native original Check/Submit form is still available. Never use a truncated preview.</p></noscript>
</section>
<script src="/assets/research-transport.js" defer></script>