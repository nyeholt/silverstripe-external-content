<h2>$ContentItem.Title</h2>
<% if ContentItem.Children %>
	<% loop ContentItem.Children %>
	<h2><a href="$CurrentPage.Link?item=$ID">$Title</a></h2>
	<p>
	<a href="$DownloadLink">Download URL to the object</a>
	</p>
	<% end_loop %>
<% end_if %>