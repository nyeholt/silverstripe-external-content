;(function($) {
	$.entwine('ss', function($) {
		$('.cms-container').entwine({
			submitForm: function(form, button, callback, ajaxOptions) {
				$('.ExternalContentAdmin #cms-content-tools-CMSMain').append("<div class='cms-content-loading-overlay ui-widget-overlay-light'></div><div class='cms-content-loading-spinner'></div>");

				// Trigger the default form submission.

				this._super(form, button, callback, ajaxOptions);

				// Wait a moment before the next AJAX call, otherwise some undefined behaviour may occur.

				setTimeout(function() {

					// Update the external sources view.

					$.ajax({
						url: $('base').prop('href') + 'admin/external-content/updateSources'
					}).done(function(data) {
						$('#cms-content-treeview').html(data);
						$('.ExternalContentAdmin #cms-content-tools-CMSMain .cms-content-loading-overlay, .ExternalContentAdmin #cms-content-tools-CMSMain .cms-content-loading-spinner').remove();
					});
				}, 1000);
			}
		});
	});
}(jQuery));
