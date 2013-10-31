<% if $HasDownloads %>
	<% if $DownloadsAvailable %>
		<form class="downloadable-files-form" action="$DownloadZipLink" method="post">
			<input type="hidden" name="OrderID" value="$ID">
  			<div class="downloadable-controls">
				<div class="download-instructions">
					<p>Here are your digital purchases for download.</p>
				</div>
				<div class="download-all-wrapper">
					<input type="submit" class="download-zip button" value="Download Selected Items">
				</div>
			</div>
			<% include DownloadTable %>
		</form>
	<% else %>
		<p class="downloads-unavailable">This order contains files which will be available for download as soon as payment is received.</p>
	<% end_if %>
<% end_if %>