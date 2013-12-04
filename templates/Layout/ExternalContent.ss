<h2>$Title</h2>

<% if Children %>
	<% loop Children %>
	<h2><a href="$Link">$Title</a></h2>
	<p>
	<a href="$DownloadLink">Download URL to the object</a>
	</p>
	<% end_loop %>
<% end_if %>