<script type="text/javascript">
	function add_var_to_opener_obj(obj, name, value) {
		new_variable = window.opener.document.createElement('input');
		new_variable.type = 'hidden';
		new_variable.name = name;
		new_variable.value = value;
		obj.appendChild(new_variable);
	}

	function insertText(obj, value) {
		<?php if ($this->data['dstfld1'] == 'expression') { ?>
			jQuery(obj).val(jQuery(obj).val() + value);
		<?php } else { ?>
			jQuery(obj).val(value);
		<?php } ?>
	}

	jQuery(function($) {
		function setReadOnly() {
			var selected_fn = $('#function option:selected');

			if (selected_fn.val() === 'last' || selected_fn.val() === 'strlen' || selected_fn.val() === 'band') {
				if ($('#paramtype option:selected').val() == <?= PARAM_TYPE_COUNTS ?>) {
					$('#params_last').removeAttr('readonly');
				}
				else {
					$('#params_last').attr('readonly', 'readonly');
				}
			}
		}

		setReadOnly();

		$('#paramtype').change(function() {
			setReadOnly();
		});
	});
</script>

<?php
if (!empty($this->data['insert'])) { ?>
	<script type="text/javascript">
		insertText(jQuery('#<?php echo $this->data['dstfld1']; ?>', window.opener.document), <?php echo zbx_jsvalue($this->data['expression']); ?>);
		close_window();
	</script>
<?php
}
if (!empty($this->data['cancel'])) {?>
	<script type="text/javascript">
		close_window();
	</script>
<?php
}
