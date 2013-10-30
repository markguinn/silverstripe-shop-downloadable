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

			<table class="downloadable-files order-downloads">
				<thead>
					<tr>
						<th></th>
						<th>File</th>
						<th>Product</th>
						<th>Purchase Date</th>
						<th>File Size</th>
						<th>Download</th>
					</tr>
				</thead>
				<tbody>
					<% loop $Downloads %>
						<tr>
							<td><input type="checkbox" name="Files[]" value="$Link.Hash" checked></td>
							<td><img src="$File.Icon" class="file-icon" width="16"> $File.Name</td>
							<td>$Product.Title</td>
							<td>$Order.Placed.Nice</td>
							<td>$File.Size</td>
							<td><a href="$Link.AbsoluteURL" class="button file-download">Download</a></td>
						</tr>
					<% end_loop %>
				</tbody>
			</table>
		</form>
	<% else %>
		<p class="downloads-unavailable">This order contains files which will be available for download as soon as payment is received.</p>
	<% end_if %>
<% end_if %>