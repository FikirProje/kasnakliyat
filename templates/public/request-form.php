<?php $formPage = $page==='appointment' ? 'appointment' : 'quote'; ?>
<?php if($errors):?><div class="notice error" role="alert"><ul><?php foreach($errors as $error):?><li><?=e($error)?></li><?php endforeach?></ul></div><?php endif?>
<form method="post" action="<?=e($root.($formPage==='appointment'?'randevu/':'teklif-al/'))?>" enctype="multipart/form-data" class="request-form">
<?=csrf_field()?>
<div class="honeypot" aria-hidden="true"><label>Website<input name="website" autocomplete="off" tabindex="-1"></label></div>
<div class="form-row"><label><?=e(t('field_name',$lang))?> <span>*</span><input required name="name" maxlength="120" autocomplete="name" value="<?=e(is_string($_POST['name']??null)?$_POST['name']:'')?>"></label><label><?=e(t('field_phone',$lang))?> <span>*</span><input required type="tel" name="phone" maxlength="60" autocomplete="tel" value="<?=e(is_string($_POST['phone']??null)?$_POST['phone']:'')?>"></label></div>
<label><?=e(t('field_email',$lang))?> <span>*</span><input required type="email" name="email" maxlength="254" autocomplete="email" value="<?=e(is_string($_POST['email']??null)?$_POST['email']:'')?>"></label>
<div class="form-row"><?php foreach(['origin','destination'] as $field):?><label><?=e(t('field_'.$field,$lang))?> <span>*</span><input required name="<?=$field?>" maxlength="240" value="<?=e(is_string($_POST[$field]??null)?$_POST[$field]:'')?>"></label><?php endforeach?></div>
<label><?=e(t('field_cargo',$lang))?> <span>*</span><input required name="cargo" maxlength="240" value="<?=e(is_string($_POST['cargo']??null)?$_POST['cargo']:'')?>"></label>
<div class="form-row"><label><?=e(t('field_date',$lang))?> <span>*</span><input required type="date" name="preferred_date" min="<?=date('Y-m-d')?>" value="<?=e(is_string($_POST['preferred_date']??null)?$_POST['preferred_date']:'')?>"></label><label><?=e(t('field_slot',$lang))?><?=$formPage==='appointment'?' <span>*</span>':''?><input type="time" name="slot" <?=$formPage==='appointment'?'required':''?> value="<?=e(is_string($_POST['slot']??null)?$_POST['slot']:'')?>"></label></div>
<label><?=e(t('field_notes',$lang))?><textarea name="notes" rows="4" maxlength="5000"><?=e(is_string($_POST['notes']??null)?$_POST['notes']:'')?></textarea></label>
<label><?=e(t('field_photo',$lang))?><input type="file" name="photo" accept="image/jpeg,image/png,image/webp"><small><?=e(t('photo_hint',$lang))?></small></label>
<label class="checkbox"><input type="checkbox" name="consent" value="1" required <?=($_POST['consent']??'')==='1'?'checked':''?>><span><?=e(t('consent',$lang))?> <a href="<?=e($root)?>gizlilik/" target="_blank" rel="noopener"><?=e(t('privacy_title',$lang))?> <?=public_icon('arrow-up')?></a></span></label>
<button type="submit" class="button"><?=e(t($formPage==='quote'?'submit_quote':'submit_appointment',$lang))?> <span aria-hidden="true"><?=public_icon('arrow-up')?></span></button><p class="form-footnote"><?=e(t('no_auto_confirmation',$lang))?></p>
</form>
