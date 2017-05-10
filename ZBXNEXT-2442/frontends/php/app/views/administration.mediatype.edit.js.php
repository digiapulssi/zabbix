<script type="text/x-jquery-tmpl" id="exec_params_row">
	<tr class="form_row">
		<td>
			<input type="text" id="exec_params_#{rowNum}_exec_param" name="exec_params[#{rowNum}][exec_param]" maxlength="255" style="width: <?= ZBX_TEXTAREA_STANDARD_WIDTH ?>px;">
		</td>
		<td>
			<button type="button" id="exec_params_#{rowNum}_remove" name="exec_params[#{rowNum}][remove]" class="<?= ZBX_STYLE_BTN_LINK ?> element-table-remove"><?= _('Remove') ?></button>
		</td>
	</tr>
</script>
<script type="text/javascript">
	jQuery(document).ready(function($) {
		var initialized = false;
		// type of media
		$('#type').change(function() {
			switch ($(this).val()) {
				case '<?= MEDIA_TYPE_EMAIL ?>':
					$('#smtp_server, #smtp_port, #smtp_helo, #smtp_email, #smtp_security, #smtp_authentication').closest('li').show();
					$('#exec_path, #gsm_modem, #jabber_username, #eztext_username, #eztext_limit, #exec_params_table')
						.closest('li')
						.hide();
					$('#eztext_link').hide();

					// radio button actions
					toggleSecurityOptions();
					toggleAuthenticationOptions();
					setMaxSessionsTypeOther();
					break;

				case '<?= MEDIA_TYPE_EXEC ?>':
					$('#exec_path, #exec_params_table').closest('li').show();
					$('#smtp_server, #smtp_port, #smtp_helo, #smtp_email, #gsm_modem, #jabber_username, #eztext_username, #eztext_limit, #passwd, #smtp_verify_peer, #smtp_verify_host, #smtp_username, #smtp_security, #smtp_authentication')
						.closest('li')
						.hide();
					$('#eztext_link').hide();
					setMaxSessionsTypeOther();
					break;

				case '<?= MEDIA_TYPE_SMS ?>':
					$('#gsm_modem').closest('li').show();
					$('#smtp_server, #smtp_port, #smtp_helo, #smtp_email, #exec_path, #jabber_username, #eztext_username, #eztext_limit, #passwd, #smtp_verify_peer, #smtp_verify_host, #smtp_username, #smtp_security, #smtp_authentication, #exec_params_table')
						.closest('li')
						.hide();
					$('#eztext_link').hide();
					setMaxSessionsTypeSMS();
					break;

				case '<?= MEDIA_TYPE_JABBER ?>':
					$('#jabber_username, #passwd').closest('li').show();
					$('#smtp_server, #smtp_port, #smtp_helo, #smtp_email, #exec_path, #gsm_modem, #eztext_username, #eztext_limit, #smtp_verify_peer, #smtp_verify_host, #smtp_username, #smtp_security, #smtp_authentication, #exec_params_table')
						.closest('li')
						.hide();
					$('#eztext_link').hide();
					setMaxSessionsTypeOther();
					break;

				case '<?= MEDIA_TYPE_EZ_TEXTING ?>':
					$('#eztext_username, #eztext_limit, #passwd').closest('li').show();
					$('#eztext_link').show();
					$('#smtp_server, #smtp_port, #smtp_helo, #smtp_email, #exec_path, #gsm_modem, #jabber_username, #smtp_verify_peer, #smtp_verify_host, #smtp_username, #smtp_security, #smtp_authentication, #exec_params_table')
						.closest('li')
						.hide();
					setMaxSessionsTypeOther();
					break;
			}
		});

		// clone button
		$('#clone').click(function() {
			$('#mediatypeid, #delete, #clone').remove();
			$('#update').text(<?= CJs::encodeJson(_('Add')) ?>);
			$('#update').val('mediatype.create').attr({id: 'add'});
			$('#description').focus();
		});

		// Trim spaces on sumbit. Spaces for script parameters should not be trimmed.
		$('#media_type_form').submit(function() {
			var attempts = $('#maxattempts');
			if ($.trim(attempts.val()) === '') {
				attempts.val(0);
			}
			var mstype = $('#maxsessionsType :radio:checked').val(),
				inputBox = $('#maxsessions');
			if (mstype !== 'custom') {
				inputBox.val(mstype === 'one' ? 1 : 0);
			}
			else if (mstype === 'custom' && $.trim(inputBox.val()) === '') {
				inputBox.val(0);
			}

			$(this).trimValues([
				'#description', '#smtp_server', '#smtp_port', '#smtp_helo', '#smtp_email', '#exec_path', '#gsm_modem',
				'#jabber_username', '#eztext_username', '#smtp_username', '#maxsessions'
			]);
		});

		$('#maxsessionsType :radio').change(function() {
			toggleMaxSessionsType(this);
		});

		// Refresh field visibility on document load.
		$('#type').trigger('change');
		$('#maxsessionsType :radio:checked').trigger('change');

		$('input[name=smtp_security]').change(function() {
			toggleSecurityOptions();
		});

		$('input[name=smtp_authentication]').change(function() {
			toggleAuthenticationOptions();
		});

		/**
		 * Show or hide "SSL verify peer" and "SSL verify host" fields.
		 */
		function toggleSecurityOptions() {
			if ($('input[name=smtp_security]:checked').val() == <?= SMTP_CONNECTION_SECURITY_NONE ?>) {
				$('#smtp_verify_peer, #smtp_verify_host').prop('checked', false).closest('li').hide();
			}
			else {
				$('#smtp_verify_peer, #smtp_verify_host').closest('li').show();
			}
		}

		/**
		 * Show or hide "Username" and "Password" fields.
		 */
		function toggleAuthenticationOptions() {
			if ($('input[name=smtp_authentication]:checked').val() == <?= SMTP_AUTHENTICATION_NORMAL ?>) {
				$('#smtp_username, #passwd').closest('li').show();
			}
			else {
				$('#smtp_username, #passwd').val('').closest('li').hide();
			}
		}

		/**
		 * Set maxsessions value according selected radio button.
		 * Set readonly status according #maxsessionType value
		 */
		function toggleMaxSessionsType(radio) {
			var mstype = $(radio).val();
			var inputBox = $('#maxsessions');
			switch(mstype) {
				case 'one' :
					inputBox.hide();
					break;
				case 'unlimited' :
					inputBox.hide();
					break;
				default :
					inputBox.show();
					if (initialized == true) {
						inputBox.select().focus();
					}
					break;
			}
		}

		/**
		 * Set maxsessionsType for MEDIA_TYPE_SMS
		 */
		function setMaxSessionsTypeSMS() {
			$('#maxsessionsType :radio')
				.attr('disabled', true)
				.filter('[value=one]')
					.attr('disabled', false)
					.click();
		}

		/**
		 * Set maxsessionsType for other media types
		 */
		function setMaxSessionsTypeOther() {
			if (initialized == false) {
				return;
			}
			$('#maxsessionsType :radio')
				.attr('disabled', false)
				.filter('[value=one]')
					.click();
		}

		$('#exec_params_table').dynamicRows({ template: '#exec_params_row' });
		initialized = true;
	});
</script>
