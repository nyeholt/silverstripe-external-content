/**
 * File: external-content-admin.js
 */
(function($) {

	$.entwine('ss', function($){

		/**
		 * Name
		 */
		$('.cms-edit-form input[name=Name]').entwine({
			onchange: function() {
				this.updateTreeLabel(this.val());
			},

			/**
			 * Function: updateTreeLabel
			 *
			 * Update the tree
			 * (String) title
			 */
			updateTreeLabel: function(title) {
				var id = $('.cms-edit-form input[name=ID]').val();

				// only update immediate text element, we don't want to update all the nested ones
				var treeItem = $('.item:first', $('.cms-tree').find("[data-id='" + id + "']"));
				if (title && title != "") {
					treeItem.text(title);
				}
			}

		});
	});
}(jQuery));
