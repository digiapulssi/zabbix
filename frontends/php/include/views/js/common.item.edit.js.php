<script type="text/x-jquery-tmpl" id="delayFlexRow">
	<tr class="form_row">
		<td>
			<ul class="<?= CRadioButtonList::ZBX_STYLE_CLASS ?>" id="delay_flex_#{rowNum}_type">
				<li>
					<input type="radio" id="delay_flex_#{rowNum}_type_0" name="delay_flex[#{rowNum}][type]" value="0" checked="checked">
					<label for="delay_flex_#{rowNum}_type_0"><?= _('Flexible') ?></label>
				</li><li>
					<input type="radio" id="delay_flex_#{rowNum}_type_1" name="delay_flex[#{rowNum}][type]" value="1">
					<label for="delay_flex_#{rowNum}_type_1"><?= _('Scheduling') ?></label>
				</li>
			</ul>
		</td>
		<td>
			<input type="text" id="delay_flex_#{rowNum}_delay" name="delay_flex[#{rowNum}][delay]" maxlength="255" placeholder="<?= ZBX_ITEM_FLEXIBLE_DELAY_DEFAULT ?>">
			<input type="text" id="delay_flex_#{rowNum}_schedule" name="delay_flex[#{rowNum}][schedule]" maxlength="255" placeholder="<?= ZBX_ITEM_SCHEDULING_DEFAULT ?>" style="display: none;">
		</td>
		<td>
			<input type="text" id="delay_flex_#{rowNum}_period" name="delay_flex[#{rowNum}][period]" maxlength="255" placeholder="<?= ZBX_DEFAULT_INTERVAL ?>">
		</td>
		<td>
			<button type="button" id="delay_flex_#{rowNum}_remove" name="delay_flex[#{rowNum}][remove]" class="<?= ZBX_STYLE_BTN_LINK ?> element-table-remove"><?= _('Remove') ?></button>
		</td>
	</tr>
</script>
<script type="text/javascript">
	jQuery(function($) {
		$('#delayFlexTable').on('click', 'input[type="radio"]', function() {
			var rowNum = $(this).attr('id').split('_')[2];

			if ($(this).val() == <?= ITEM_DELAY_FLEXIBLE; ?>) {
				$('#delay_flex_' + rowNum + '_schedule').hide();
				$('#delay_flex_' + rowNum + '_delay').show();
				$('#delay_flex_' + rowNum + '_period').show();
			}
			else {
				$('#delay_flex_' + rowNum + '_delay').hide();
				$('#delay_flex_' + rowNum + '_period').hide();
				$('#delay_flex_' + rowNum + '_schedule').show();
			}
		});

		$('#delayFlexTable').dynamicRows({
			template: '#delayFlexRow'
		});
	});
</script>
<?php

/*
 * Visibility
 */
$this->data['typeVisibility'] = [];
$i = 0;

foreach ($this->data['delay_flex'] as $delayFlex) {
	if (!isset($delayFlex['delay']) && !isset($delayFlex['period'])) {
		continue;
	}

	foreach ($this->data['types'] as $type => $label) {
		if ($type == ITEM_TYPE_TRAPPER || $type == ITEM_TYPE_ZABBIX_ACTIVE || $type == ITEM_TYPE_SNMPTRAP
				|| $type == ITEM_TYPE_DEPENDENT) {
			continue;
		}
		zbx_subarray_push($this->data['typeVisibility'], $type, 'delay_flex['.$i.'][delay]');
		zbx_subarray_push($this->data['typeVisibility'], $type, 'delay_flex['.$i.'][period]');
	}

	$i++;
}

if (!empty($this->data['interfaces'])) {
	zbx_subarray_push($this->data['typeVisibility'], ITEM_TYPE_ZABBIX, 'interface_row');
	zbx_subarray_push($this->data['typeVisibility'], ITEM_TYPE_ZABBIX, 'interfaceid');
	zbx_subarray_push($this->data['typeVisibility'], ITEM_TYPE_SIMPLE, 'interface_row');
	zbx_subarray_push($this->data['typeVisibility'], ITEM_TYPE_SIMPLE, 'interfaceid');
	zbx_subarray_push($this->data['typeVisibility'], ITEM_TYPE_SNMPV1, 'interface_row');
	zbx_subarray_push($this->data['typeVisibility'], ITEM_TYPE_SNMPV1, 'interfaceid');
	zbx_subarray_push($this->data['typeVisibility'], ITEM_TYPE_SNMPV2C, 'interface_row');
	zbx_subarray_push($this->data['typeVisibility'], ITEM_TYPE_SNMPV2C, 'interfaceid');
	zbx_subarray_push($this->data['typeVisibility'], ITEM_TYPE_SNMPV3, 'interface_row');
	zbx_subarray_push($this->data['typeVisibility'], ITEM_TYPE_SNMPV3, 'interfaceid');
	zbx_subarray_push($this->data['typeVisibility'], ITEM_TYPE_EXTERNAL, 'interface_row');
	zbx_subarray_push($this->data['typeVisibility'], ITEM_TYPE_EXTERNAL, 'interfaceid');
	zbx_subarray_push($this->data['typeVisibility'], ITEM_TYPE_IPMI, 'interface_row');
	zbx_subarray_push($this->data['typeVisibility'], ITEM_TYPE_IPMI, 'interfaceid');
	zbx_subarray_push($this->data['typeVisibility'], ITEM_TYPE_SSH, 'interface_row');
	zbx_subarray_push($this->data['typeVisibility'], ITEM_TYPE_SSH, 'interfaceid');
	zbx_subarray_push($this->data['typeVisibility'], ITEM_TYPE_TELNET, 'interface_row');
	zbx_subarray_push($this->data['typeVisibility'], ITEM_TYPE_TELNET, 'interfaceid');
	zbx_subarray_push($this->data['typeVisibility'], ITEM_TYPE_JMX, 'interface_row');
	zbx_subarray_push($this->data['typeVisibility'], ITEM_TYPE_JMX, 'interfaceid');
	zbx_subarray_push($this->data['typeVisibility'], ITEM_TYPE_SNMPTRAP, 'interface_row');
	zbx_subarray_push($this->data['typeVisibility'], ITEM_TYPE_SNMPTRAP, 'interfaceid');
	zbx_subarray_push($this->data['typeVisibility'], ITEM_TYPE_HTTPAGENT, 'interface_row');
	zbx_subarray_push($this->data['typeVisibility'], ITEM_TYPE_HTTPAGENT, 'interfaceid');
}
zbx_subarray_push($this->data['typeVisibility'], ITEM_TYPE_SIMPLE, 'row_username');
zbx_subarray_push($this->data['typeVisibility'], ITEM_TYPE_SIMPLE, 'username');
zbx_subarray_push($this->data['typeVisibility'], ITEM_TYPE_SIMPLE, 'row_password');
zbx_subarray_push($this->data['typeVisibility'], ITEM_TYPE_SIMPLE, 'password');
zbx_subarray_push($this->data['typeVisibility'], ITEM_TYPE_SNMPV1, 'snmp_oid');
zbx_subarray_push($this->data['typeVisibility'], ITEM_TYPE_SNMPV2C, 'snmp_oid');
zbx_subarray_push($this->data['typeVisibility'], ITEM_TYPE_SNMPV3, 'snmp_oid');
zbx_subarray_push($this->data['typeVisibility'], ITEM_TYPE_SNMPV1, 'row_snmp_oid');
zbx_subarray_push($this->data['typeVisibility'], ITEM_TYPE_SNMPV2C, 'row_snmp_oid');
zbx_subarray_push($this->data['typeVisibility'], ITEM_TYPE_SNMPV3, 'row_snmp_oid');
zbx_subarray_push($this->data['typeVisibility'], ITEM_TYPE_SNMPV1, 'snmp_community');
zbx_subarray_push($this->data['typeVisibility'], ITEM_TYPE_SNMPV2C, 'snmp_community');
zbx_subarray_push($this->data['typeVisibility'], ITEM_TYPE_SNMPV1, 'row_snmp_community');
zbx_subarray_push($this->data['typeVisibility'], ITEM_TYPE_SNMPV2C, 'row_snmp_community');
zbx_subarray_push($this->data['typeVisibility'], ITEM_TYPE_SNMPV3, 'snmpv3_contextname');
zbx_subarray_push($this->data['typeVisibility'], ITEM_TYPE_SNMPV3, 'row_snmpv3_contextname');
zbx_subarray_push($this->data['typeVisibility'], ITEM_TYPE_SNMPV3, 'snmpv3_securityname');
zbx_subarray_push($this->data['typeVisibility'], ITEM_TYPE_SNMPV3, 'row_snmpv3_securityname');
zbx_subarray_push($this->data['typeVisibility'], ITEM_TYPE_SNMPV3, 'snmpv3_securitylevel');
zbx_subarray_push($this->data['typeVisibility'], ITEM_TYPE_SNMPV3, 'row_snmpv3_securitylevel');
zbx_subarray_push($this->data['typeVisibility'], ITEM_TYPE_SNMPV1, 'port');
zbx_subarray_push($this->data['typeVisibility'], ITEM_TYPE_SNMPV2C, 'port');
zbx_subarray_push($this->data['typeVisibility'], ITEM_TYPE_SNMPV3, 'port');
zbx_subarray_push($this->data['typeVisibility'], ITEM_TYPE_SNMPV1, 'row_port');
zbx_subarray_push($this->data['typeVisibility'], ITEM_TYPE_SNMPV2C, 'row_port');
zbx_subarray_push($this->data['typeVisibility'], ITEM_TYPE_SNMPV3, 'row_port');
zbx_subarray_push($this->data['typeVisibility'], ITEM_TYPE_IPMI, 'ipmi_sensor');
zbx_subarray_push($this->data['typeVisibility'], ITEM_TYPE_IPMI, 'row_ipmi_sensor');
zbx_subarray_push($this->data['typeVisibility'], ITEM_TYPE_SSH, 'authtype');
zbx_subarray_push($this->data['typeVisibility'], ITEM_TYPE_SSH, 'row_authtype');
zbx_subarray_push($this->data['typeVisibility'], ITEM_TYPE_SSH, 'username');
zbx_subarray_push($this->data['typeVisibility'], ITEM_TYPE_SSH, 'row_username');
zbx_subarray_push($this->data['typeVisibility'], ITEM_TYPE_TELNET, 'username');
zbx_subarray_push($this->data['typeVisibility'], ITEM_TYPE_TELNET, 'row_username');
zbx_subarray_push($this->data['typeVisibility'], ITEM_TYPE_DB_MONITOR, 'username');
zbx_subarray_push($this->data['typeVisibility'], ITEM_TYPE_DB_MONITOR, 'row_username');
zbx_subarray_push($this->data['typeVisibility'], ITEM_TYPE_JMX, 'username');
zbx_subarray_push($this->data['typeVisibility'], ITEM_TYPE_JMX, 'row_username');
zbx_subarray_push($this->data['typeVisibility'], ITEM_TYPE_JMX, 'jmx_endpoint');
zbx_subarray_push($this->data['typeVisibility'], ITEM_TYPE_JMX, 'row_jmx_endpoint');
zbx_subarray_push($this->data['typeVisibility'], ITEM_TYPE_SSH, 'password');
zbx_subarray_push($this->data['typeVisibility'], ITEM_TYPE_SSH, 'row_password');
zbx_subarray_push($this->data['typeVisibility'], ITEM_TYPE_TELNET, 'password');
zbx_subarray_push($this->data['typeVisibility'], ITEM_TYPE_TELNET, 'row_password');
zbx_subarray_push($this->data['typeVisibility'], ITEM_TYPE_DB_MONITOR, 'password');
zbx_subarray_push($this->data['typeVisibility'], ITEM_TYPE_DB_MONITOR, 'row_password');
zbx_subarray_push($this->data['typeVisibility'], ITEM_TYPE_JMX, 'password');
zbx_subarray_push($this->data['typeVisibility'], ITEM_TYPE_JMX, 'row_password');
zbx_subarray_push($this->data['typeVisibility'], ITEM_TYPE_SSH, 'label_executed_script');
zbx_subarray_push($this->data['typeVisibility'], ITEM_TYPE_TELNET, 'label_executed_script');
zbx_subarray_push($this->data['typeVisibility'], ITEM_TYPE_DB_MONITOR, 'label_params');
zbx_subarray_push($this->data['typeVisibility'], ITEM_TYPE_CALCULATED, 'label_formula');
zbx_subarray_push($this->data['typeVisibility'], ITEM_TYPE_SSH, 'params_script');
zbx_subarray_push($this->data['typeVisibility'], ITEM_TYPE_SSH, 'row_params');
zbx_subarray_push($this->data['typeVisibility'], ITEM_TYPE_TELNET, 'params_script');
zbx_subarray_push($this->data['typeVisibility'], ITEM_TYPE_TELNET, 'row_params');
zbx_subarray_push($this->data['typeVisibility'], ITEM_TYPE_DB_MONITOR, 'params_dbmonitor');
zbx_subarray_push($this->data['typeVisibility'], ITEM_TYPE_DB_MONITOR, 'row_params');
zbx_subarray_push($this->data['typeVisibility'], ITEM_TYPE_CALCULATED, 'params_calculted');
zbx_subarray_push($this->data['typeVisibility'], ITEM_TYPE_CALCULATED, 'row_params');
zbx_subarray_push($this->data['typeVisibility'], ITEM_TYPE_TRAPPER, 'trapper_hosts');
zbx_subarray_push($this->data['typeVisibility'], ITEM_TYPE_TRAPPER, 'row_trapper_hosts');
zbx_subarray_push($this->data['typeVisibility'], ITEM_TYPE_DEPENDENT, 'row_master_item');
$ui_rows = [
	ITEM_TYPE_HTTPAGENT => [
		'url_row', 'query_fields_row', 'request_method_row', 'timeout_row', 'post_type_row', 'posts_row', 'headers_row',
		'status_codes_row', 'follow_redirects_row', 'retrieve_mode_row', 'output_format_row', 'allow_traps_row',
		'request_method', 'http_proxy_row', 'http_authtype_row', 'http_authtype', 'verify_peer_row', 'verify_host_row',
		'ssl_key_file_row', 'ssl_cert_file_row', 'ssl_key_password_row', 'trapper_hosts', 'allow_traps'
	]
];
foreach ($ui_rows[ITEM_TYPE_HTTPAGENT] as $row) {
	zbx_subarray_push($this->data['typeVisibility'], ITEM_TYPE_HTTPAGENT, $row);
}

foreach ($this->data['types'] as $type => $label) {
	switch ($type) {
		case ITEM_TYPE_DB_MONITOR:
			$defaultKey = $this->data['is_discovery_rule']
				? ZBX_DEFAULT_KEY_DB_MONITOR_DISCOVERY
				: ZBX_DEFAULT_KEY_DB_MONITOR;
			zbx_subarray_push($this->data['typeVisibility'], $type,
				['id' => 'key', 'defaultValue' => $defaultKey]
			);
			break;
		case ITEM_TYPE_SSH:
			zbx_subarray_push($this->data['typeVisibility'], $type,
				['id' => 'key', 'defaultValue' => ZBX_DEFAULT_KEY_SSH]
			);
			break;
		case ITEM_TYPE_TELNET:
			zbx_subarray_push($this->data['typeVisibility'], $type,
				['id' => 'key', 'defaultValue' => ZBX_DEFAULT_KEY_TELNET]
			);
			break;
		default:
			zbx_subarray_push($this->data['typeVisibility'], $type, ['id' => 'key', 'defaultValue' => '']);
	}
}
foreach ($this->data['types'] as $type => $label) {
	if ($type == ITEM_TYPE_TRAPPER || $type == ITEM_TYPE_ZABBIX_ACTIVE || $type == ITEM_TYPE_SNMPTRAP
			|| $type == ITEM_TYPE_DEPENDENT) {
		continue;
	}
	zbx_subarray_push($this->data['typeVisibility'], $type, 'row_flex_intervals');
	zbx_subarray_push($this->data['typeVisibility'], $type, 'row_new_delay_flex');
	zbx_subarray_push($this->data['typeVisibility'], $type, 'new_delay_flex[delay]');
	zbx_subarray_push($this->data['typeVisibility'], $type, 'new_delay_flex[period]');
	zbx_subarray_push($this->data['typeVisibility'], $type, 'add_delay_flex');
}
foreach ($this->data['types'] as $type => $label) {
	if ($type == ITEM_TYPE_TRAPPER || $type == ITEM_TYPE_SNMPTRAP || $type == ITEM_TYPE_DEPENDENT) {
		continue;
	}
	zbx_subarray_push($this->data['typeVisibility'], $type, 'delay');
	zbx_subarray_push($this->data['typeVisibility'], $type, 'row_delay');
}

// disable dropdown items for calculated and aggregate items
foreach ([ITEM_TYPE_CALCULATED, ITEM_TYPE_AGGREGATE] as $type) {
	// set to disable character, log and text items in value type
	zbx_subarray_push($this->data['typeDisable'], $type, [ITEM_VALUE_TYPE_STR, ITEM_VALUE_TYPE_LOG, ITEM_VALUE_TYPE_TEXT], 'value_type');
}

$this->data['securityLevelVisibility'] = [];
zbx_subarray_push($this->data['securityLevelVisibility'], ITEM_SNMPV3_SECURITYLEVEL_AUTHNOPRIV, 'snmpv3_authprotocol');
zbx_subarray_push($this->data['securityLevelVisibility'], ITEM_SNMPV3_SECURITYLEVEL_AUTHNOPRIV, 'row_snmpv3_authprotocol');
zbx_subarray_push($this->data['securityLevelVisibility'], ITEM_SNMPV3_SECURITYLEVEL_AUTHNOPRIV, 'snmpv3_authpassphrase');
zbx_subarray_push($this->data['securityLevelVisibility'], ITEM_SNMPV3_SECURITYLEVEL_AUTHNOPRIV, 'row_snmpv3_authpassphrase');
zbx_subarray_push($this->data['securityLevelVisibility'], ITEM_SNMPV3_SECURITYLEVEL_AUTHPRIV, 'snmpv3_authprotocol');
zbx_subarray_push($this->data['securityLevelVisibility'], ITEM_SNMPV3_SECURITYLEVEL_AUTHPRIV, 'row_snmpv3_authprotocol');
zbx_subarray_push($this->data['securityLevelVisibility'], ITEM_SNMPV3_SECURITYLEVEL_AUTHPRIV, 'snmpv3_authpassphrase');
zbx_subarray_push($this->data['securityLevelVisibility'], ITEM_SNMPV3_SECURITYLEVEL_AUTHPRIV, 'row_snmpv3_authpassphrase');
zbx_subarray_push($this->data['securityLevelVisibility'], ITEM_SNMPV3_SECURITYLEVEL_AUTHPRIV, 'snmpv3_privprotocol');
zbx_subarray_push($this->data['securityLevelVisibility'], ITEM_SNMPV3_SECURITYLEVEL_AUTHPRIV, 'row_snmpv3_privprotocol');
zbx_subarray_push($this->data['securityLevelVisibility'], ITEM_SNMPV3_SECURITYLEVEL_AUTHPRIV, 'snmpv3_privpassphrase');
zbx_subarray_push($this->data['securityLevelVisibility'], ITEM_SNMPV3_SECURITYLEVEL_AUTHPRIV, 'row_snmpv3_privpassphrase');

$this->data['authTypeVisibility'] = [];
zbx_subarray_push($this->data['authTypeVisibility'], ITEM_AUTHTYPE_PUBLICKEY, 'publickey');
zbx_subarray_push($this->data['authTypeVisibility'], ITEM_AUTHTYPE_PUBLICKEY, 'row_publickey');
zbx_subarray_push($this->data['authTypeVisibility'], ITEM_AUTHTYPE_PUBLICKEY, 'privatekey');
zbx_subarray_push($this->data['authTypeVisibility'], ITEM_AUTHTYPE_PUBLICKEY, 'row_privatekey');

?>
<script type="text/javascript">
	function setAuthTypeLabel() {
		if (jQuery('#authtype').val() == <?php echo CJs::encodeJson(ITEM_AUTHTYPE_PUBLICKEY); ?>
				&& jQuery('#type').val() == <?php echo CJs::encodeJson(ITEM_TYPE_SSH); ?>) {
			jQuery('#row_password label').html(<?php echo CJs::encodeJson(_('Key passphrase')); ?>);
		}
		else {
			jQuery('#row_password label').html(<?php echo CJs::encodeJson(_('Password')); ?>);
		}
	}

	jQuery(document).ready(function($) {
		<?php
		if (!empty($this->data['authTypeVisibility'])) { ?>
			var authTypeSwitcher = new CViewSwitcher('authtype', 'change',
				<?php echo zbx_jsvalue($this->data['authTypeVisibility'], true); ?>);
		<?php }
		if (!empty($this->data['typeVisibility'])) { ?>
			var typeSwitcher = new CViewSwitcher('type', 'change',
				<?php echo zbx_jsvalue($this->data['typeVisibility'], true); ?>,
				<?php echo zbx_jsvalue($this->data['typeDisable'], true); ?>);
		<?php } ?>
		if ($('#http_authtype').length) {
			new CViewSwitcher('http_authtype', 'change', <?= zbx_jsvalue([
				HTTPTEST_AUTH_BASIC => ['http_username_row', 'http_password_row'],
				HTTPTEST_AUTH_NTLM => ['http_username_row', 'http_password_row']
			], true) ?>);
		}
		<?php
		if (!empty($this->data['securityLevelVisibility'])) { ?>
			var securityLevelSwitcher = new CViewSwitcher('snmpv3_securitylevel', 'change',
				<?php echo zbx_jsvalue($this->data['securityLevelVisibility'], true); ?>);
		<?php } ?>

		if ($('#allow_traps').length) {
			new CViewSwitcher('allow_traps', 'change', <?= zbx_jsvalue([
				HTTPCHECK_ALLOW_TRAPS_ON => ['row_trapper_hosts']
			], true) ?>);
		}

		$('#type')
			.change(function() {
				// update the interface select with each item type change
				var itemInterfaceTypes = <?php echo CJs::encodeJson(itemTypeInterface()); ?>;
				organizeInterfaces(itemInterfaceTypes[parseInt($(this).val())]);

				setAuthTypeLabel();
			})
			.trigger('change');

		$('#authtype').bind('change', function() {
			setAuthTypeLabel();
		});

		$('[data-action="parse_url"]').click(function() {
			var url_node = $(this).siblings('[name="url"]'),
				table = $('#query_fields_pairs').data('editableTable'),
				url = parseUrlString(url_node.val())

			if (typeof url === 'object') {
				if (url.pairs.length > 0) {
					table.addRows(url.pairs);
					table.getTableRows().map(function() {
						var empty = $(this).find('input[type="text"]').map(function() {
							return $(this).val() == '' ? this : null;
						});

						return empty.length == 2 ? this : null;
					}).map(function() {
						table.removeRow(this);
					});
				}

				url_node.val(url.url);
			}
			else {
				overlayDialogue({
					'title': <?= CJs::encodeJson(_('Error')); ?>,
					'content': $('<span>').html(<?=
						CJs::encodeJson(_('Failed to parse URL.').'<br><br>'._('URL is not properly encoded.'));
					?>),
					'buttons': [
						{
							title: <?= CJs::encodeJson(_('Ok')); ?>,
							class: 'btn-alt',
							focused: true,
							action: function() {}
						}
					]
				});
			}
		});

		$('#request_method').change(function() {
			if ($(this).val() == <?= HTTPCHECK_REQUEST_HEAD ?>) {
				$(':radio', '#retrieve_mode')
					.filter('[value=<?= HTTPTEST_STEP_RETRIEVE_MODE_HEADERS ?>]').click()
					.end()
					.attr('disabled', 'disabled');
			}
			else {
				$(':radio', '#retrieve_mode').removeAttr('disabled');
			}
		});
	});
</script>
