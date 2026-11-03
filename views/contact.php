<article class="contact" aria-labelledby="contact-title">
<h1 id="contact-title">Contact LLM.Giving</h1>
<p class="contact-intro">Questions, feedback, or something to report? Send a message.</p>
<?php if($sent): ?><div class="contact-status" role="status" tabindex="-1" autofocus>Thanks — your message has been sent.</div><?php endif ?>
<?php if($errors || $error): ?><div class="contact-errors" role="alert" tabindex="-1" autofocus><h2>Check your message</h2>
<?php if($error): ?><p><?=e($error)?></p><?php endif ?>
<?php if($errors): ?><ul><?php foreach($errors as $key=>$message): ?><li><a href="#contact-<?=e($key)?>"><?=e($message)?></a></li><?php endforeach ?></ul><?php endif ?>
</div><?php endif ?>
<form method="post" action="/contact" class="contact-form">
<?=csrf()?><input type="hidden" name="contact_attempt" value="<?=e($attempt)?>">
<div class="contact-row">
<?php foreach(['name'=>['Your name (optional)','text','Alex','name'],'email'=>['Your email','email','you@example.com','email']] as $key=>$field): ?>
<div><label for="contact-<?=e($key)?>"><?=e($field[0])?></label>
<input id="contact-<?=e($key)?>" name="<?=e($key)?>" type="<?=e($field[1])?>" autocomplete="<?=e($field[3])?>" placeholder="<?=e($field[2])?>" maxlength="<?=e(CONTACT_LIMITS[$key])?>" value="<?=e($values[$key])?>" <?=$key==='email'?'required':''?> <?=isset($errors[$key])?'aria-invalid="true" aria-describedby="contact-'.$key.'-error"':''?>>
<?php if(isset($errors[$key])): ?><p id="contact-<?=e($key)?>-error" class="contact-field-error"><?=e($errors[$key])?></p><?php endif ?></div>
<?php endforeach ?>
</div>
<label for="contact-message">Message</label>
<textarea id="contact-message" name="message" placeholder="How can we help?" maxlength="10000" required <?=isset($errors['message'])?'aria-invalid="true" aria-describedby="contact-message-error"':''?>><?=e($values['message'])?></textarea>
<?php if(isset($errors['message'])): ?><p id="contact-message-error" class="contact-field-error"><?=e($errors['message'])?></p><?php endif ?>
<button type="submit" <?=$pending?'disabled':''?>>Send message</button>
</form>
<div class="contact-details"><p>Prefer email? <?php if($operator['valid']): ?><a href="mailto:<?=e($operator['email'])?>"><?=e($operator['email'])?></a><?php else: ?>Email is currently unavailable.<?php endif ?></p>
<p><?=e($operator['name'])?> · <?=e($operator['country'])?></p>
<p class="contact-categories">General questions, privacy, copyright, and moderation.</p></div>
</article>
