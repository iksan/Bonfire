<p class="lead"><?php echo lang('em_template_note'); ?></p>

<?php echo form_open(SITE_AREA .'/settings/emailer/template'); ?>

	<fieldset>
		<legend><?php echo lang('em_header'); ?></legend>
		<div class="clearfix">
			<div class="input">
				<textarea name="header" rows="15" style="width: 70%"><?php echo htmlspecialchars_decode($this->load->view('email/_header', null, true)) ;?></textarea>
			</div>
		</div>
	</fieldset>

	<fieldset>
		<legend><?php echo lang('em_footer'); ?></legend>

		<div class="clearfix">
			<label for="footer"></label>
			<div class="input">
				<textarea name="footer" rows="15" style="width: 70%"><?php echo htmlspecialchars_decode($this->load->view('email/_footer', null, true)) ;?></textarea>
			</div>
		</div>
	</fieldset>

	<div class="actions">
		<input type="submit" name="submit" id="submit" class="btn primary" value="<?php echo lang('em_save_template') ?>" /> <?php echo lang('bf_or') . ' ' . anchor(SITE_AREA .'/settings/emailer', lang('bf_action_cancel')); ?>
	</div>

<?php echo form_close(); ?>
