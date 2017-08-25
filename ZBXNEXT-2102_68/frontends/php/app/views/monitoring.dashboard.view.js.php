<script type="text/x-jquery-tmpl" id="edit_dashboard_control">
<?= (new CSpan([
	new CList([
		(new CButton('dashbrd-config'))->addClass(ZBX_STYLE_BTN_DASHBRD_CONF),
		(new CButton('dashbrd-add-widget', [(new CSpan())->addClass(ZBX_STYLE_PLUS_ICON), _('Add widget')]))
			->addClass(ZBX_STYLE_BTN_ALT),
		(new CButton('dashbrd-save', _('Save changes'))),
		(new CLink(_('Cancel'), '#'))->setId('dashbrd-cancel'),
		''
	])
]))
	->addClass(ZBX_STYLE_DASHBRD_EDIT)
	->toString()
?>
</script>

<script type="text/javascript">
	// Server-side constances used in dashboard client-side methods.
	var PRIVATE_SHARING = <?= PRIVATE_SHARING; ?>,
		PERM_READ_WRITE = <?= PERM_READ_WRITE; ?>,
		PERM_READ = <?= PERM_READ; ?>;

	// Save changes and cancel editing dashboard.
	function dashbrd_save_changes() {
		// Update buttons on existing widgets to view mode.
		jQuery('.dashbrd-grid-widget-container').dashboardGrid('saveDashboardChanges');
	};

	// Cancel editing dashboard.
	function dashbrd_cancel(e) {
		// To prevent going by href link.
		e.preventDefault();

		// Update buttons on existing widgets to view mode.
		jQuery('.dashbrd-grid-widget-container').dashboardGrid('cancelEditDashboard');
	};

	// Add new widget.
	function dashbrd_add_widget() {
		jQuery('.dashbrd-grid-widget-container').dashboardGrid('addNewWidget');
	};

	var showEditMode = function showEditMode() {
		jQuery('#dashbrd-edit').closest('ul')
			.hide()
			.before(jQuery('#edit_dashboard_control').html())

		var ul = jQuery('#dashbrd-config').closest('ul');
		jQuery('#dashbrd-config', ul).click(function() {
			jQuery('.dashbrd-grid-widget-container').dashboardGrid('openDashboardPropertiesDialog');
		});
		jQuery('#dashbrd-add-widget', ul).click(dashbrd_add_widget);
		jQuery('#dashbrd-save', ul).click(dashbrd_save_changes);
		jQuery('#dashbrd-cancel', ul).click(dashbrd_cancel);

		// Update buttons on existing widgets to edit mode.
		jQuery('.dashbrd-grid-widget-container').dashboardGrid('setModeEditDashboard');

		// Hide filter with timeline.
		jQuery('.filter-btn-container, #filter-space').hide();
		timeControl.removeAllSBox();
	};

	jQuery(document).ready(function() {
		// Turn on edit dashboard.
		jQuery('#dashbrd-edit').click(showEditMode);

		// Enter edit mode when creating or cloning dashboard.
		if (!jQuery('.dashbrd-grid-widget-container').dashboardGrid('isDashboardSaved')) {
			showEditMode();
			jQuery('.dashbrd-grid-widget-container').dashboardGrid('openDashboardPropertiesDialog');
		}
	});

	function dashbaordAddMessages(messages) {
		var $message_div = jQuery('<div>').attr('id','dashbrd-messages');
		$message_div.append(messages);
		jQuery('.article').prepend($message_div);
	}

	function dashboardRemoveMessages() {
		jQuery('#dashbrd-messages').remove();
		jQuery('.msg-good').remove();
	}

	// Function is in global scope, because it should be accessable by html onchange() attribute.
	function updateWidgetConfigDialogue() {
		jQuery('.dashbrd-grid-widget-container').dashboardGrid('updateWidgetConfigDialogue');
	}
</script>
