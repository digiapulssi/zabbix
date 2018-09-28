<script type="text/javascript">
	jQuery(function($) {
		var mediatypes_ids = [];

		<?php foreach ($mediatypes_ids as $mediatypeid => $mediatype): ?>
				mediatypes_ids[<?= $mediatypeid ?>] = <?= $mediatype ?>;
		<?php endforeach ?>

		// Type of media.
		$('#mediatypeid').change(function() {
			var mediatypeid = $(this).val(),
				mediatype = mediatypes_ids[mediatypeid];

			if (mediatype == <?= MEDIA_TYPE_SERVICENOW ?>) {
				$('#sendto')
					.closest('li')
					.hide();
			}
			else {
				$('#sendto')
					.closest('li')
					.show();
			}

			$('#type').val(mediatype);
		});

		$('#mediatypeid').trigger('change');
	});
</script>
