<?php

include dirname(__FILE__).'/common.item.edit.js.php';

$this->data['valueTypeVisibility'] = [];
zbx_subarray_push($this->data['valueTypeVisibility'], ITEM_VALUE_TYPE_UINT64, 'units');
zbx_subarray_push($this->data['valueTypeVisibility'], ITEM_VALUE_TYPE_UINT64, 'row_units');
zbx_subarray_push($this->data['valueTypeVisibility'], ITEM_VALUE_TYPE_FLOAT, 'units');
zbx_subarray_push($this->data['valueTypeVisibility'], ITEM_VALUE_TYPE_FLOAT, 'row_units');
zbx_subarray_push($this->data['valueTypeVisibility'], ITEM_VALUE_TYPE_FLOAT, 'trends');
zbx_subarray_push($this->data['valueTypeVisibility'], ITEM_VALUE_TYPE_FLOAT, 'row_trends');
zbx_subarray_push($this->data['valueTypeVisibility'], ITEM_VALUE_TYPE_UINT64, 'trends');
zbx_subarray_push($this->data['valueTypeVisibility'], ITEM_VALUE_TYPE_UINT64, 'row_trends');
zbx_subarray_push($this->data['valueTypeVisibility'], ITEM_VALUE_TYPE_LOG, 'logtimefmt');
zbx_subarray_push($this->data['valueTypeVisibility'], ITEM_VALUE_TYPE_LOG, 'row_logtimefmt');
zbx_subarray_push($this->data['valueTypeVisibility'], ITEM_VALUE_TYPE_FLOAT, 'valuemapid');
zbx_subarray_push($this->data['valueTypeVisibility'], ITEM_VALUE_TYPE_STR, 'valuemapid');
zbx_subarray_push($this->data['valueTypeVisibility'], ITEM_VALUE_TYPE_STR, 'row_valuemap');
zbx_subarray_push($this->data['valueTypeVisibility'], ITEM_VALUE_TYPE_STR, 'valuemap_name');
zbx_subarray_push($this->data['valueTypeVisibility'], ITEM_VALUE_TYPE_FLOAT, 'row_valuemap');
zbx_subarray_push($this->data['valueTypeVisibility'], ITEM_VALUE_TYPE_FLOAT, 'valuemap_name');
zbx_subarray_push($this->data['valueTypeVisibility'], ITEM_VALUE_TYPE_UINT64, 'valuemapid');
zbx_subarray_push($this->data['valueTypeVisibility'], ITEM_VALUE_TYPE_UINT64, 'row_valuemap');
zbx_subarray_push($this->data['valueTypeVisibility'], ITEM_VALUE_TYPE_UINT64, 'valuemap_name');
zbx_subarray_push($this->data['valueTypeVisibility'], ITEM_VALUE_TYPE_STR, 'inventory_link');
zbx_subarray_push($this->data['valueTypeVisibility'], ITEM_VALUE_TYPE_STR, 'row_inventory_link');
zbx_subarray_push($this->data['valueTypeVisibility'], ITEM_VALUE_TYPE_TEXT, 'inventory_link');
zbx_subarray_push($this->data['valueTypeVisibility'], ITEM_VALUE_TYPE_TEXT, 'row_inventory_link');
zbx_subarray_push($this->data['valueTypeVisibility'], ITEM_VALUE_TYPE_FLOAT, 'inventory_link');
zbx_subarray_push($this->data['valueTypeVisibility'], ITEM_VALUE_TYPE_FLOAT, 'row_inventory_link');
zbx_subarray_push($this->data['valueTypeVisibility'], ITEM_VALUE_TYPE_UINT64, 'inventory_link');
zbx_subarray_push($this->data['valueTypeVisibility'], ITEM_VALUE_TYPE_UINT64, 'row_inventory_link');
?>
<script type="text/javascript">
	function displayKeyButton() {
		// selected item type
		var type = parseInt(jQuery('#type').val());

		jQuery('#keyButton').prop('disabled',
			type != <?php echo ITEM_TYPE_ZABBIX; ?>
				&& type != <?php echo ITEM_TYPE_ZABBIX_ACTIVE; ?>
				&& type != <?php echo ITEM_TYPE_SIMPLE; ?>
				&& type != <?php echo ITEM_TYPE_INTERNAL; ?>
				&& type != <?php echo ITEM_TYPE_AGGREGATE; ?>
				&& type != <?php echo ITEM_TYPE_DB_MONITOR; ?>
				&& type != <?php echo ITEM_TYPE_SNMPTRAP; ?>
				&& type != <?php echo ITEM_TYPE_JMX; ?>
		)
	}

	jQuery(document).ready(function() {
		// field switchers
		<?php
		if (!empty($this->data['valueTypeVisibility'])) { ?>
			var valueTypeSwitcher = new CViewSwitcher('value_type', 'change',
				<?php echo zbx_jsvalue($this->data['valueTypeVisibility'], true); ?>);
		<?php } ?>

		var old_value;

		jQuery('#type').change(function() {
				displayKeyButton();

				var type = jQuery(this).val(),
					value_type = jQuery('#value_type'),
					data_type = jQuery('#data_type');
				old_value = value_type.val();

				if (type == <?= ITEM_TYPE_AGGREGATE ?> || type == <?= ITEM_TYPE_CALCULATED ?>) {
					if (!(old_value == <?= ITEM_VALUE_TYPE_UINT64 ?> || old_value == <?= ITEM_VALUE_TYPE_FLOAT ?>)) {
						value_type.val(<?= ITEM_VALUE_TYPE_UINT64 ?>);
					}

					data_type.val(<?= ITEM_DATA_TYPE_DECIMAL ?>);
					value_type.trigger('change');
				}
			})
			.trigger('change');

		// Whenever non-numeric type is changed back to numeric type, set the default value in "trends" field.
		jQuery('#value_type').on('focus', function () {
			old_value = jQuery(this).val();
		}).change(function() {
			var new_value = jQuery(this).val(),
				trends = jQuery('#trends');

			if ((old_value == <?= ITEM_VALUE_TYPE_STR ?> || old_value == <?= ITEM_VALUE_TYPE_LOG ?>
					|| old_value == <?= ITEM_VALUE_TYPE_TEXT ?>)
					&& ((new_value == <?= ITEM_VALUE_TYPE_FLOAT ?>
					|| new_value == <?= ITEM_VALUE_TYPE_UINT64 ?>)
					&& trends.val() == 0)) {
				trends.val('<?= $this->data['trends_default'] ?>');
			}
		});
	});
</script>
