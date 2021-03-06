<?php
require_once 'common.php';
if (!empty($_GET['SRC']) && !empty($_GET['proto']) && checkAuthorization(11, 'view remotes')) {
	$result1 = pgquery("SELECT TRUE FROM table_user WHERE table = 't{$_GET['SRC']}';");
	$result2 = pgquery("SELECT TRUE FROM table_user WHERE table = 't{$_GET['SRC']}' AND user = '{$_SESSION['username']}';");
	$result3 = pgquery("SELECT TRUE FROM table_user INNER JOIN users ON table_user.user = users.username WHERE table = 't{$_GET['SRC']}' AND NOT users.is_administrator;");
	$h_SRC = htmlspecialchars($_GET['SRC']);
	$h_proto = htmlspecialchars($_GET['proto']);
	$u_SRC = urlencode($_GET['SRC']);
	$u_proto = urlencode($_GET['proto']);
	if ($_SESSION['is_root'] || !pg_fetch_row($result1) || pg_fetch_row($result2) || $_SESSION['is_administrator'] && pg_fetch_row($result3)) {
		if (isset($_GET['truncate'])) {
			if (isset($_GET['confirm'])) {
				pg_free_result(pgquery("DELETE FROM iSRC_TWR WHERE SRC = E'\\\\x{$_GET['SRC']}' AND proto = '{$_GET['proto']}';"));
				echo 'Table &quot;iSRC_TWR&quot; truncated for SRC ', $h_SRC, ' and proto ', $h_proto, ".<br/>\n";
			} else {
?>
				Are you sure?
<?php
				echo '<a href="?SRC=', $u_SRC, '&amp;proto=', $u_proto, "&amp;truncate&amp;confirm\">Yes</a>\n";
				echo '<a href="?SRC=', $u_SRC, '&amp;proto=', $u_proto, "\">No</a>\n";
				exit(0);
			}
		} else if (!empty($_GET['imm_SRC']) && !empty($_GET['TWR'])) {
			if (isset($_GET['insert'])) {
				pg_free_result(pgquery("INSERT INTO iSRC_TWR(SRC, proto, imm_SRC, TWR) VALUES(E'\\\\x{$_GET['SRC']}', '{$_GET['proto']}', E'\\\\x{$_GET['imm_SRC']}', TIMESTAMP '{$_GET['TWR']}');"));
				echo 'Mapping ', htmlspecialchars($_GET['imm_SRC']), ' for SRC ', $h_SRC, ' and proto ', $h_proto, " inserted.<br/>\n";
			} else if (!empty($_GET['key'])) {
				if (isset($_GET['update'])) {
					pg_free_result(pgquery("UPDATE iSRC_TWR SET (imm_SRC, TWR) = (E'\\\\x{$_GET['SRC']}', TIMESTAMP '{$_GET['TWR']}') WHERE SRC = E'\\\\x{$_GET['SRC']}' AND proto = '{$_GET['proto']}' AND imm_SRC = E'\\\\x{$_GET['imm_SRC']}';"));
					echo 'Mapping ', htmlspecialchars($_GET['key']), ' for SRC ', $h_SRC, ' and proto ', $h_proto, " updated.<br/>\n";
				} else if (isset($_GET['delete'])) {
					if (isset($_GET['confirm'])) {
						pg_free_result(pgquery("DELETE FROM iSRC_TWR WHERE SRC = E'\\\\x{$_GET['SRC']}' AND proto = '{$_GET['proto']}' AND imm_SRC = E'\\\\x{$_GET['key']}';"));
						echo 'Mapping ', htmlspecialchars($_GET['key']), ' for SRC ', $h_SRC, ' and proto ', $h_proto, " deleted.<br/>\n";
					} else {
?>
						Are you sure?
<?php
						echo '<a href="?SRC=', $u_SRC, '&amp;proto=', $u_proto, '&amp;key=', urlencode($_GET['key']), "&amp;delete&amp;confirm\">Yes</a>\n";
						echo '<a href="?SRC=', $u_SRC, '&amp;proto=', $u_proto, "\">No</a>\n";
						exit(0);
					}
				}
			}
		}
		$result = pgquery("SELECT imm_SRC, TWR FROM iSRC_TWR WHERE SRC = E'\\\\x{$_GET['SRC']}' AND proto = '{$_GET['proto']}' ORDER BY imm_SRC ASC;");
		echo 'Viewing table &quot;iSRC_TWR&quot; for SRC ', $h_SRC, ' and proto ', $h_proto, ".\n";
?>
		<table border="1">
			<tbody>
				<tr>
					<th>immediate source address</th>
					<th>time when received</th>
					<th>Actions</th>
				</tr>
				<tr>
					<td>
						<input form="insert" type="text" name="imm_SRC"/>
					</td>
					<td>
						<input form="insert" type="text" name="TWR"/>
					</td>
					<td>
						<form id="insert" action="" method="GET">
<?php
							echo '<input type="hidden" name="SRC" value="', $h_SRC, "\"/>\n";
							echo '<input type="hidden" name="proto" value="', $h_proto, "\"/>\n";
?>
							<input type="submit" name="insert" value="Insert new mapping for this SRC and this proto"/><br/>
							<input type="reset" value="reset"/>
						</form>
						<form action="" method="GET">
<?php
							echo '<input type="hidden" name="SRC" value="', $h_SRC, "\"/>\n";
							echo '<input type="hidden" name="proto" value="', $h_proto, "\"/>\n";
?>
							<input type="submit" name="truncate" value="Truncate mappings for this SRC and this proto"/>
						</form>
					</td>
				</tr>
<?php
				for ($row = pg_fetch_row($result); $row; $row = pg_fetch_row($result)) {
?>
					<tr>
						<td>
<?php
							$str = strtoupper(substr($row[0], 2));
							echo '<input form="update', $str, '" type="text" name="imm_SRC" value="', $str, "\"/>\n";
?>
						</td>
						<td>
<?php
							echo '<input form="update', $str, '" type="text" name="TWR" value="', $row[1], "\"/>\n";
?>
						</td>
						<td>
<?php
							echo '<form id="update', $str, "\" action=\"\" method=\"GET\">\n";
								echo '<input type="hidden" name="SRC" value="', $h_SRC, "\"/>\n";
								echo '<input type="hidden" name="proto" value="', $h_proto, "\"/>\n";
								echo '<input type="hidden" name="key" value="', $str, "\"/>\n";
								echo "<input type=\"submit\" name=\"update\" value=\"Update this mapping for this SRC and this proto\"/><br/>\n";
								echo "<input type=\"reset\" value=\"reset\"/>\n";
							echo "</form>\n";
?>
							<form action="" method="GET">
<?php
								echo '<input type="hidden" name="SRC" value="', $h_SRC, "\"/>\n";
								echo '<input type="hidden" name="proto" value="', $h_proto, "\"/>\n";
								echo '<input type="hidden" name="key" value="', $str, "\"/>\n";
?>
								<input type="submit" name="delete" value="Delete this mapping for this SRC and this proto"/>
							</form>
						</td>
					</tr>
<?php
				}
?>
			</tbody>
		</table>
<?php
		echo '<a href="view_remote_details.php?addr=', $u_SRC, "\">Done</a>\n";
		pg_free_result($result);
	}
	pg_free_result($result1);
	pg_free_result($result2);
	pg_free_result($result3);
}
?>