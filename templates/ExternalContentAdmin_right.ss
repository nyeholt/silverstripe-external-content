<div id="form_actions_right" class="ajaxActions">
</div>

<% if EditForm %>
	$EditForm
<% else %>
	<form id="Form_EditForm" action="admin/assets/?executeForm=EditForm" method="post" enctype="multipart/form-data">
		<h1>$ApplicationName</h1>
		<p><% _t('WELCOME','Welcome to') %> $ApplicationName! <% _t('CHOOSEPAGE','Please choose a page from the left.') %></p>
	</form>
<% end_if %>

<p id="statusMessage" style="visibility:hidden"></p>
