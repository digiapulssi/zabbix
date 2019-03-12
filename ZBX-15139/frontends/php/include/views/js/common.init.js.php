<script type="text/javascript">
	jQuery(document).ready(function() {
		<?php if (defined('ZBX_PAGE_DO_REFRESH') && CWebUser::getRefresh() != 0): ?>
			PageRefresh.init(<?= CWebUser::getRefresh() * 1000 ?>);
		<?php endif ?>

		<?php if (isset($page['scripts']) && in_array('flickerfreescreen.js', $page['scripts'])): ?>
			window.flickerfreeScreen.responsiveness = <?php echo SCREEN_REFRESH_RESPONSIVENESS * 1000; ?>;
		<?php endif ?>

		cookie.init();
		MMenu.init();
		chkboxRange.init();
	});
</script>
