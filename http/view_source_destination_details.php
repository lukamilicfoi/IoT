<?php
require_once 'common.php';
if (checkAuthorization(10, 'view remotes') && !empty($_GET['SRC']) && !empty($_GET['DST'])) {
	$result1 = pgquery("SELECT TRUE FROM table_user WHERE tablename = 't{$_GET['SRC']}';");
	$result2 = pgquery("SELECT TRUE FROM table_user WHERE tablename = 't{$_GET['SRC']}' AND username = '{$_SESSION['username']}';");
	$result3 = pgquery("SELECT TRUE FROM table_user INNER JOIN users ON table_user.username = users.username WHERE table_user.tablename = 't{$_GET['SRC']}' AND NOT users.is_administrator;");
	$h_SRC = htmlspecialchars($_GET['SRC']);
	$h_DST = htmlspecialchars($_GET['DST']);
	$u_SRC = urlencode($_GET['SRC']);
	$u_DST = urlencode($_GET['DST']);
	if (!pg_fetch_row($result1) || pg_fetch_row($result2) || pg_fetch_row($result3) && $_SESSION['is_administrator'] || $_SESSION['is_root']) {
		if (isset($_GET['truncate'])) {
			if (isset($_GET['confirm'])) {
				pg_free_result(pgquery("DELETE FROM ID_TWR WHERE SRC = E'\\\\x{$_GET['SRC']}' AND DST = E'\\\\x{$_GET['DST']}';"));
				echo 'Table &quot;ID_TWR&quot; truncated for SRC X&apos;', $h_SRC, '&apos; and DST X&apos;', $h_DST, "&apos;.<br/>\n";
			} else {
?>
				Are you sure?
<?php
				echo '<a href="?SRC=', $u_SRC, '&amp;DST=', $u_DST, "&amp;truncate&amp;confirm\">Yes</a>\n";
				echo '<a href="?SRC=', $u_SRC, '&amp;DST=', $u_DST, "\">No</a>\n";
				exit(0);
			}
		} else if (!empty($_GET['ID']) && !empty($_GET['TWR'])) {
			if (isset($_GET['insert'])) {
				pg_free_result(pgquery("INSERT INTO ID_TWR(SRC, DST, ID, TWR) VALUES(E'\\\\x{$_GET['SRC']}', E'\\\\x{$_GET['DST']}', {$_GET['ID']}, TIMESTAMP '{$_GET['TWR']}');"));
				echo 'Mapping ', htmlspecialchars($_GET['ID']), ' for SRC X&apos;', $h_SRC, '&apos; and DST X&apos;', $h_DST, "&apos; inserted.<br/>\n";
			} else if (!empty($_GET['key']) && isset($_GET['update'])) {
				pg_free_result(pgquery("UPDATE ID_TWR SET(ID, TWR) = ({$_GET['ID']}, TIMESTAMP '{$_GET['TWR']}') WHERE SRC = E'\\\\x{$_GET['SRC']}' AND DST = E'\\\\x{$_GET['DST']}' AND ID = {$_GET['key']};"));
				echo 'Mapping ', htmlspecialchars($_GET['key']), ' for SRC X&apos;', $h_SRC, '&apos; and DST X&apos;', $h_DST, "&apos; updated.<br/>\n";
			}
		} else if (!empty($_GET['key']) && isset($_GET['delete'])) {
			if (isset($_GET['confirm'])) {
				pg_free_result(pgquery("DELETE FROM ID_TWR WHERE SRC = E'\\\\x{$_GET['SRC']}' AND DST = E'\\\\x{$_GET['DST']}' AND ID = {$_GET['key']};"));
				echo 'Mapping ', htmlspecialchars($_GET['key']), ' for SRC X&apos;', $h_SRC, '&apos; and DST X&apos;', $h_DST, "&apos; deleted.<br/>\n";
			} else {
?>
				Are you sure?
<?php
				echo '<a href="?SRC=', $u_SRC, '&amp;DST=', $u_DST, '&amp;key=', urlencode($_GET['key']), "&amp;delete&amp;confirm\">Yes</a>\n";
				echo '<a href="?SRC=', $u_SRC, '&amp;DST=', $u_DST, "\">No</a>\n";
				exit(0);
			}
		}
		$result = pgquery("SELECT ID, TWR FROM ID_TWR WHERE SRC = E'\\\\x{$_GET['SRC']}' AND DST = E'\\\\x{$_GET['DST']}' ORDER BY ID ASC;");
		echo 'Viewing table &quot;ID_TWR&quot; for SRC X&apos;', $h_SRC, '&apos; and DST X&apos;', $h_DST, "&apos;.\n";
?>
		<table border="1">
			<tbody>
				<tr>
					<th>identifier</th>
					<th>time when received</th>
					<th>Actions</th>
				</tr>
				<tr>
					<td>
						<input form="insert" type="text" name="ID"/>
					</td>
					<td>
						<input form="insert" type="text" name="TWR"/>
					</td>
					<td>
						<form id="insert" action="" method="GET">
<?php
							echo '<input type="hidden" name="SRC" value="', $h_SRC, "\"/>\n";
							echo '<input type="hidden" name="DST" value="', $h_DST, "\"/>\n";
?>
							<input type="submit" name="insert" value="Insert new mapping for this SRC and this DST"/><br/>
							<input type="reset" value="reset"/>
						</form>
						<form action="" method="GET">
<?php
							echo '<input type="hidden" name="SRC" value="', $h_SRC, "\"/>\n";
							echo '<input type="hidden" name="DST" value="', $h_DST, "\"/>\n";
?>
							<input type="submit" name="truncate" value="Truncate mappings for this SRC and this DST"/>
						</form>
					</td>
				</tr>
<?php
				for ($row = pg_fetch_row($result); $row; $row = pg_fetch_row($result)) {
?>
					<tr>
						<td>
<?php
							echo '<input form="update', $row[0], '" type="text" name="ID" value="', $row[0], "\"/>\n";
?>
						</td>
						<td>
<?php
							echo '<input form="update', $row[0], '" type="text" name="TWR" value="', $row[1], "\"/>\n";
?>
						</td>
						<td>
<?php
							echo '<form id="update', $row[0], "\" action=\"\" method=\"GET\"/>\n";
								echo '<input type="hidden" name="SRC" value="', $h_SRC, "\"/>\n";
								echo '<input type="hidden" name="DST" value="', $h_DST, "\"/>\n";
								echo '<input type="hidden" name="key" value="', $row[0], "\"/>\n";
?>
								<input type="submit" name="update" value="Update this mapping for this SRC and this DST\"/><br/>
								<input type="reset" value="reset"/>
<?php
							echo "</form>\n";
?>
							<form action="" method="GET">
<?php
								echo '<input type="hidden" name="SRC" value="', $h_SRC, "\"/>\n";
								echo '<input type="hidden" name="DST" value="', $h_DST, "\"/>\n";
								echo '<input type="hidden" name="key" value="', $row[0], "/>\n";
?>
								<input type="submit" name="delete" value="Delete this mapping for this SRC and this DST"/>
							</form>
						</td>
					</tr>
<?php
				}
?>
			</tbody>
		</table>
		Write the first column as an integer, e.g., 11.<br/>
		Write the second column as a timestamp, e.g., 1111-11-11 11:11:11.<br/>
<?php
		echo '<a href="view_remote_details.php?addr=', $u_SRC, "\">Done</a>\n";
		pg_free_result($result);
	}
	pg_free_result($result1);
	pg_free_result($result2);
	pg_free_result($result3);
}
?>