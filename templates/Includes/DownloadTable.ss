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
