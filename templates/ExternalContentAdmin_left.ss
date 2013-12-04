<h2><% _t('EXTERNAL_CONTENT.Connectors','Connectors') %></h2>
<div id="treepanes" style="overflow-y: auto;">
	<ul id="TreeActions">
		<li class="action" id="addpage"><button><% _t('CREATE','Create',PR_HIGH) %></button></li>
		<li class="action" id="deletepage"><button><% _t('DELETE', 'Delete') %></button></li>
	</ul>
	<div style="clear:both;"></div>
	<!--
	Sneaky form definition to hide the ability to select variations on the provider to create. Will
	need to update this later to support other providers... possibly.

	<form class="actionparams" id="addpage_options" style="display: none" action="admin/external-content/addprovider">
		<div>
		<input type="hidden" name="ParentID" />
		<input class="action" type="submit" value="<% _t('GO','Go') %>" />
		</div>
	</form>-->
	<% loop CreateProviderForm %>
		<form class="actionparams" id="$FormName" action="$FormAction">
			<% loop Fields %>
			$FieldHolder
			<% end_loop %>
		</form>
	<% end_loop %>

	$DeleteItemsForm
	<form class="actionparams" id="sortitems_options" style="display: none">
		<p id="sortitems_message" style="margin: 0"><% _t('TOREORG','To reorganise your folders, drag them around as desired.') %></p>
	</form>
	$SiteTreeAsUL
</div>