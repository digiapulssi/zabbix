<script type="text/x-jquery-tmpl" id="tag-row">
	<?= renderTagTableRow('#{rowNum}')->toString() ?>
</script>

<script type="text/javascript">
	jQuery(function($) {
		$('#tbl-tags').dynamicRows({
			template: '#tag-row'
		});
	});
</script>
