<?php
require_once 'common.php';
$can_view = check_authorization('can_view_adapters_and_underlying_protocols',
		'view adapters and underlying protocols');
$can_edit = check_authorization('can_edit_adapters_and_underlying_protocols',
		'edit adapters and underlying protocols');
if ($can_edit) {
	if (!vacuous($_GET['adapter'])) {
		$s_adapter = pg_escape_literal($_GET['adapter']);
		$h_adapter = '&apos;' . htmlspecialchars($_GET['adapter']) . '&apos;';
		if (isset($_GET['enable'])) {
			pgquery("UPDATE adapters SET enabled = TRUE WHERE adapter = $s_adapter;");
			echo "Adapter $h_adapter enabled.<br/>\n";
		} elseif (isset($_GET['disable'])) {
			pgquery("UPDATE adapters SET enabled = FALSE WHERE adapter = $s_adapter;");
			echo "Adapter $h_adapter disabled.<br/>\n";
		}
		pgquery("CALL refresh_adapters();");
	} elseif (!vacuous($_GET['proto'])) {
		$s_proto = pg_escape_literal($_GET['proto']);
		$h_proto = '&apos;' . htmlspecialchars($_GET['proto']) . '&apos;';
		if (isset($_GET['enable'])) {
			pgquery("UPDATE protocols SET enabled = TRUE WHERE proto = $s_proto;");
			echo "Protocol $h_proto enabled.<br/>\n";
		} elseif (isset($_GET['disable'])) {
			pgquery("UPDATE protocols SET enabled = FALSE WHERE proto = $s_proto;");
			echo "Protocol $s_proto disabled.<br/>\n";
		}
		pgquery("CALL refresh_protocols();");
	}
}
if ($can_view) {
	$result = pgquery('SELECT *, TRUE AS a_p FROM adapters
			UNION ALL SELECT *, FALSE AS a_p FROM protocols ORDER BY a_p DESC;');
	echo 'You are authorized to view', $can_edit ? ' (edit)' : '',
			" all adapters and underlying protocols.<br/>\n";
?>
	<br/>Viewing adapters ordered by name.
	<table border="1">
		<tbody>
			<tr>
				<th>Adapter</th>
				<th>Enabled?</th>
<?php
				if ($can_edit) {
?>
					<th>Actions</th>
<?php
				}
?>
			</tr>
<?php
			for ($row = pg_fetch_row($result); $row && $row[2] == 't';
					$row = pg_fetch_row($result)) {
?>
				<tr>
					<td>
<?php
						$adapter = htmlspecialchars($row[0]);
						$form = "\"$adapter\"";
						echo "<input form=$form type=\"text\" name=\"adapter\" value=$form
								readonly/>\n";
?>
					</td>
					<td>
<?php
						echo '<input type="checkbox"', $row[1] == 't' ? ' checked' : '',
								" disabled/>\n";
?>
					</td>
<?php
					if ($can_edit) {
?>
						<td>
<?php
							echo "<form id=$form action=\"\" method=\"GET\">\n";
								echo "<input form=$form type=\"submit\" name=\"", $row[1] == 't'
										? 'disable' : 'enable', "\" value=\"toggle\"/>\n";
							echo "</form>\n";
?>
						</td>
<?php
					}
?>
				</tr>
<?php
			}
?>
		</tbody>
	</table>
	<br/>Viewing underlying protocols ordered by name.
	<table border="1">
		<tbody>
			<tr>
				<th>Underlying protocol</th>
				<th>Enabled?</th>
<?php
				if ($can_edit) {
?>
					<th>Actions</th>
<?php
				}
?>
			</tr>
<?php
			while ($row) {
?>
				<tr>
					<td>
<?php
						$proto = htmlspecialchars($row[0]);
						$form = "\"$proto\"";
						echo "<input form=$form type=\"text\" name=\"proto\" value=$form
								readonly/>\n";
?>
					</td>
					<td>
<?php
						echo '<input type="checkbox"', $row[1] == 't' ? 'checked' : '',
								" disabled/>\n";
?>
					</td>
<?php
					if ($can_edit) {
?>
						<td>
<?php
							echo "<form id=$form action=\"\" method=\"GET\">\n";
								echo "<input form=$form type=\"submit\" name=\"", $row[1] == 't'
										? 'disable' : 'enable', "\" value=\"toggle\"/>\n";
							echo "</form>\n";
?>
						</td>
<?php
					}
?>
				</tr>
<?php
				$row = pg_fetch_row($result);
			}
?>
		</tbody>
	</table>
	<br/><a href="index.php">Done</a>
<?php
}
?>
				