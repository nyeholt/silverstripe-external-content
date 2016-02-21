
/**
 * jQuery functionality used on the external content admin page
 */

;(function ($, pt) {
	$().ready(function () {
		$('#Form_EditForm').bind('PageSaved', function (e, b, d) {
			var pageID = $('#Form_EditForm_ID').val();
			pt('sitetree').getTreeNodeByIdx(pageID).ajaxExpansion();
		});

			// bind the migrate form so we can properly handle a migrate
			$('#Form_EditForm_Migrate').livequery(function () {
				$(this).click(function () {
					// we don't want this to be submitted via the edit form, as we want to do an ajax postback for this
					// and not tie up the response.
					var form = $(this).parents('form');
					// wrap it all up and post away!
					var params = form.serializeArray();
					var postParams = {};
					$.each(params, function (index) {
						postParams[this.name] = this.value;
					});

					postParams['action_migrate'] = true;
					statusMessage('Importing ...', 2);

					$.post(form.attr('action'), postParams, function (data) {

						if (data) {
							var response = $.parseJSON(data);
							if (response && response.status) {
								statusMessage(response.message, 'good');
							} else {
								statusMessage("There was a problem with the import");
							}
						}
						// reset the base form
						if (pt) {
							pt(form.attr('id')).resetElements();
						}
					});
					return false;
				});

			});

	});
})(jQuery, $);
