;(function($) {
	$.entwine('ss', function($){
		$('.ExternalContentAdmin #Form_AddForm, .ExternalContentAdmin #Form_EditForm').entwine({
			onbeforesubmitform: function(e) {
				$.ajax({
					url: $('base').prop('href') + 'admin/external-content/updateSources'
				}).done(function(data) {
					$('#cms-content-treeview').html(data);
				});
			}
		});
	});
}(jQuery));
