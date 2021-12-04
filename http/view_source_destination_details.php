<?php
require_once 'common.php';
if (!empty($_GET['SRC']) && !empty($_GET['DST'])) {
	$can_view_remotes = checkAuthorization(10, 'view remotes');
	$can_edit_remotes = checkAuthorization(11; 'edit remotes');
	$s1SRC = pgescapename($_GET['SRC']);
	$s2SRC = pgescapebytea($_GET['SRC']);
	$h1SRC = htmlspecialchars($_GET['SRC']);
	$h2SRC = "X&apos;$h1SRC&apos;";
	$u_SRC = urlencode($_GET['SRC']);
	$s_DST = pgescapebytea($_GET['DST']);
	$h1DST = htmlspecialchars($_GET['DST']);
	$h2DST = "X&apos;$h1DST&apos;";
	$u_DST = urlencode($_GET['DST']);
	if ($can_edit_remotes && can_edit_table($s1SRC) {
		if (isset($_GET['truncate'])) {
			if (isset($_GET['confirm'])) {
				pg_free_result(pgquery("DELETE FROM ID_TWR WHERE SRC = $s2SRC AND DST = $s_DST;"));
				echo "Table &quot;ID_TWR&quot; truncated for SRC $h2SRC and DST $h2DST.<br/>\n";
			} else {
?>
				Are you sure?
<?php
				echo "<a href=\"?SRC=$u_SRC&amp;DST=$u_DST&amp;truncate&amp;confirm\">Yes</a>\n";
				echo "<a href=\"?SRC=$u_SRC&amp;DST=$u_DST\">No</a>\n";
				exit(0);
			}
		} else if (!empty($_GET['ID']) && !empty($_GET['TWR'])) {
			$id = intval($_GET['ID']);
			$TWR = 'TIMESTAMP \'' . pg_escape_string($_GET['TWR']) . '\'';
			if (isset($_GET['insert'])) {
				pg_free_result(pgquery("INSERT INTO ID_TWR(SRC, DST, ID, TWR)
							VALUES($s2SRC, $s_DST, $id, $TWR);"));
				echo "Mapping $id for SRC $h2SRC and DST $h2DST inserted.<br/>\n";
			} else if (!empty($_GET['key']) && isset($_GET['update'])) {
				$key = intval($_GET['key']);
				pg_free_result(pgquery("UPDATE ID_TWR SET(ID, TWR) = ($id, $TWR)
						WHERE SRC = $s2SRC AND DST = $s_DST AND ID = $key;"));
				echo "Mapping $key for SRC $h2SRC and DST $h2DST updated.<br/>\n";
			}
		} else if (!empty($_GET['key']) && isset($_GET['delete'])) {
			$key = intval($_GET['key']);
			if (isset($_GET['confirm'])) {
				pg_free_result(pgquery("DELETE FROM ID_TWR WHERE SRC = $s2SRC AND DST = $s_DST
						AND ID = $key;"));
				echo "Mapping $key for SRC $h2SRC and DST $h2DST deleted.<br/>\n";
			} else {
?>
				Are you sure?
<?php
				echo '<a href="?SRC=',
							"$u_SRC&amp;DST=$u_DST&amp;key=$key&amp;delete&amp;confirm\">Yes</a>\n";
				echo "<a href=\"?SRC=$u_SRC&amp;DST=$u_DST\">No</a>\n";
				exit(0);
			}
		}
	}
	if ($can_view_remotes && can_view_table($s1SRC) {
		$result = pgquery("SELECT ID, TWR FROM ID_TWR WHERE SRC = $s2SRC AND DST = $s_DST
				ORDER BY ID ASC;");
		echo "Viewing table &quot;ID_TWR&quot; for SRC $h2SRC and DST $h2DST.\n";
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
							echo "<input type=\"hidden\" name=\"SRC\" value=\"$h1SRC\"/>\n";
							echo "<input type=\"hidden\" name=\"DST\" value=\"$h1DST\"/>\n";
?>
							<input type="submit" name="insert"
									value="Insert new mapping for this SRC and this DST"/><br/>
							<input type="reset" value="reset"/>
						</form>
						<form action="" method="GET">
<?php
							echo "<input type=\"hidden\" name=\"SRC\" value=\"$h1SRC\"/>\n";
							echo "<input type=\"hidden\" name=\"DST\" value=\"$h1DST\"/>\n";
?>
							<input type="submit" name="truncate"
									value="Truncate mappings for this SRC and this DST"/>
						</form>
					</td>
				</tr>
<?php
				for ($row = pg_fetch_row($result); $row; $row = pg_fetch_row($result)) {
?>
					<tr>
						<td>
<?php
							echo "<input form=\"update{$row[0]}\" type=\"text\" name=\"ID\"
									value=\"{$row[0]}\"/>\n";
?>
						</td>
						<td>
<?php
							echo "<input form=\"update{$row[0]}\" type=\"text\" name=\"TWR\"
									value=\"{$row[1]}\"/>\n";
?>
						</td>
						<td>
<?php
							echo "<form id=\"update{$row[0]}\" action=\"\" method=\"GET\"/>\n";
								echo "<input type=\"hidden\" name=\"SRC\" value=\"$h1SRC\"/>\n";
								echo "<input type=\"hidden\" name=\"DST\" value=\"$h1DST\"/>\n";
								echo "<input type=\"hidden\" name=\"key\" value=\"{$row[0]}\"/>\n";
?>
								<input type="submit" name="update"
										value="Update this mapping for this SRC and this DST\"/><br/>
								<input type="reset" value="reset"/>
<?php
							echo "</form>\n";
?>
							<form action="" method="GET">
<?php
								echo "<input type=\"hidden\" name=\"SRC\" value=\"{$h1SRC}\"/>\n";
								echo "<input type=\"hidden\" name=\"DST\" value=\"{$h1DST}\"/>\n";
								echo "<input type=\"hidden\" name=\"key\" value=\"{$row[0]}\"/>\n";
?>
								<input type="submit" name="delete"
										value="Delete this mapping for this SRC and this DST"/>
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
		echo "<a href=\"view_remote_details.php?addr=$u_SRC\">Done</a>\n";
		pg_free_result($result);
	}
	pg_free_result($result1);
	pg_free_result($result2);
	pg_free_result($result3);
}
?>