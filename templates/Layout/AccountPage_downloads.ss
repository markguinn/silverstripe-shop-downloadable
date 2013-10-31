<% require themedCSS(account) %>
<% include AccountNavigation %>
<div class="typography">
	$Content
	<h2 class="pagetitle">$Title</h2>

	<% if $HasDownloads %>
		<form class="downloadable-files-form" action="$DownloadZipLink" method="post">
			<div class="downloadable-controls">
				<div class="download-sort">
					$SortControl
				</div>
				<div class="download-all-wrapper">
					<input type="submit" class="download-zip button" value="Download Selected Items">
				</div>
			</div>
			<% include DownloadTable %>
		</form>
	<% else %>
		<p class="downloads-unavailable">You have not yet made any purchases with downloadable files.</p>
	<% end_if %>
</div>
<div class="clear"></div>
