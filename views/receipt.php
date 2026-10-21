<?php $request=research($receipt['id'],false); ?>
<section class="narrow receipt" data-management-receipt>
 <span class="status"><?=e($request['moderation'])?></span>
 <h1><?=!empty($submitted)?'Research request submitted':'Your replacement management link'?></h1>
 <div class="management-warning" role="note" aria-labelledby="management-save-heading">
  <h2 id="management-save-heading"><svg viewBox="0 0 24 24" aria-hidden="true" focusable="false"><path d="M12 3 2 21h20L12 3Z" fill="none" stroke="currentColor" stroke-width="2" stroke-linejoin="round"/><path d="M12 9v5" stroke="currentColor" stroke-width="2" stroke-linecap="round"/><circle cx="12" cy="17.5" r="1" fill="currentColor"/></svg>Save your access link now</h2>
  <p id="management-link-help">Save this private link now. If you lose it, you lose access to your request.</p>
  <label for="management-link">Your private management link</label>
  <input id="management-link" value="<?=e($receipt['link'])?>" readonly spellcheck="false" autocomplete="off" aria-describedby="management-link-help management-copy-status">
  <div class="actions primary-actions">
   <button type="button" data-management-copy aria-haspopup="dialog" aria-controls="management-save-dialog" hidden>Copy management link</button>
   <a class="button secondary" href="<?=e($receipt['link'])?>">Manage my request</a>
  </div>
  <p id="management-copy-status" class="management-copy-status" role="status" aria-live="polite"></p>
  <noscript><p>Select and copy the complete link manually before leaving this page.</p></noscript>
 </div>
 <p class="receipt-title"><?=e($request['title'])?></p>
 <p class="receipt-next"><?=!empty($submitted)?'Next: an administrator reviews your request before it appears in the research library.':'Your previous management link no longer works.'?></p>
 <p class="fine">Keep this link private. It lets you check progress, propose updates, respond to clarification, pause or withdraw your request.</p>
 <?php if(!empty($request['email'])): ?><p class="fine">If needed, <a href="/recover">request a replacement link</a> using the private email you provided.</p><?php endif ?>
 <p class="fine">Research ID: <?=e($receipt['id'])?></p>
</section>
<dialog class="management-save-dialog" id="management-save-dialog" aria-labelledby="management-dialog-title" aria-describedby="management-save-explanation management-save-copy-note">
 <form method="dialog">
  <h2 id="management-dialog-title" tabindex="-1" autofocus>Have you saved your management link?</h2>
  <p id="management-save-explanation">Confirm you copied your management link and saved it somewhere safe. If you lose this link, you lose access to your request.</p>
  <?php if(!empty($request['email'])): ?><p class="fine">You can request a replacement using the private email you provided.</p><?php endif ?>
  <p id="management-save-copy-note" role="status" aria-live="polite"></p>
  <label class="check" for="management-saved-confirmation"><input id="management-saved-confirmation" type="checkbox" required><span>I confirm I have copied and saved my management link.</span></label>
  <div class="dialog-actions">
   <button type="button" class="secondary" data-management-copy hidden>Copy link now</button>
   <button type="button" class="secondary" id="management-return-to-link">Return to link</button>
   <button type="submit">Confirm saved</button>
  </div>
 </form>
</dialog>
