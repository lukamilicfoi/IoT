<?php
require_once 'common.php';
checkLogin();
if ($_SESSION['user'] != 'admin') {
	http_response_code(403);
} else {
?>
	<!DOCTYPE html>
	<html>
		<head>
			<title>View source protocol details (as administrator)</title>
			<meta http-equiv="Content-Type" content="text/html; charset=utf-8"/>
		</head>
		<body>
<?php
			if (isset($_GET['SRC']) && isset($_GET['proto'])) {
				$dbconn = pgconnect('host=localhost dbname=postgres user=postgres client_encoding=UTF8');
				if (isset($_GET['truncate'])) {
					if (isset($_GET['confirm'])) {
						pg_free_result(pgquery("DELETE FROM iSRC_TWR WHERE SRC = E'\\\\x{$_GET['SRC']}' AND proto = '{$_GET['proto']}';"));
						echo 'Table &quot;iSRC_TWR&quot; truncated for SRC ', htmlspecialchars($_GET['SRC']), ' and proto ', htmlspecialchars($_GET['proto']), ".<br/>\n";
					} else {
?>
						Are you sure?
<?php
						echo '<a href="?SRC=', urlencode($_GET['SRC']), '&amp;proto=', urlencode($_GET['proto']), "&amp;truncate&amp;confirm\">Yes</a>\n";
						echo '<a href="?SRC=', urlencode($_GET['SRC']), '&amp;proto=', urlencode($_GET['proto']), "\">No</a>\n";
						pg_close($dbconn);
						exit(0);
					}
				} else if (isset($_GET['insert'])) {
					pg_free_result(pgquery("INSERT INTO iSRC_TWR(SRC, proto, imm_SRC, TWR) VALUES(E'\\\\x{$_GET['SRC']}', '{$_GET['proto']}', E'\\\\x{$_GET['imm_SRC']}', TIMESTAMP '{$_GET['TWR']}');"));
					echo 'Mapping ', htmlspecialchars($_GET['imm_SRC']), ' for SRC ', htmlspecialchars($_GET['SRC']), ' and proto ', htmlspecialchars($_GET['proto']), " inserted.<br/>\n";
				} else if (isset($_GET['key'])) {
					if (isset($_GET['update'])) {
						pg_free_result(pgquery("UPDATE iSRC_TWR SET (imm_SRC, TWR) = (E'\\\\x{$_GET['SRC']}', TIMESTAMP '{$_GET['TWR']}') WHERE SRC = E'\\\\x{$_GET['SRC']}' AND proto = '{$_GET['proto']}' AND imm_SRC = E'\\\\x{$_GET['imm_SRC']}';"));
						echo 'Mapping ', htmlspecialchars($_GET['key']), ' for SRC ', htmlspecialchars($_GET['SRC']), ' and proto ', htmlspecialchars($_GET['proto']), " updated.<br/>\n";
					} else if (isset($_GET['delete'])) {
						if (isset($_GET['confirm'])) {
							pg_free_result(pgquery("DELETE FROM iSRC_TWR WHERE SRC = E'\\\\x{$_GET['SRC']}' AND proto = '{$_GET['proto']}' AND imm_SRC = E'\\\\x{$_GET['key']}';"));
							echo 'Mapping ', htmlspecialchars($_GET['key']), ' for SRC ', htmlspecialchars($_GET['SRC']), ' and proto ', htmlspecialchars($_GET['proto']), " deleted.<br/>\n";
						} else {
?>
							Are you sure?
<?php
							echo '<a href="?SRC=', urlencode($_GET['SRC']), '&amp;proto=', urlencode($_GET['proto']), '&amp;key=', urlencode($_GET['key']), "&amp;delete&amp;confirm\">Yes</a>\n";
							echo '<a href="?SRC=', urlencode($_GET['SRC']), '&amp;proto=', urlencode($_GET['proto']), "\">No</a>\n";
							pg_close($dbconn);
							exit(0);
						}
					}
				}
				$result = pgquery("SELECT imm_SRC, TWR FROM iSRC_TWR WHERE SRC = E'\\\\x{$_GET['SRC']}' AND proto = '{$_GET['proto']}' ORDER BY imm_SRC ASC;");
				echo 'Viewing table &quot;iSRC_TWR&quot; for SRC ', htmlspecialchars($_GET['SRC']), ' and proto ', htmlspecialchars($_GET['proto']), ".\n";
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
									echo '<input type="hidden" name="SRC" value="', htmlspecialchars($_GET['SRC']), "\"/>\n";
									echo '<input type="hidden" name="proto" value="', htmlspecialchars($_GET['proto']), "\"/>\n";
?>
									<input type="submit" name="insert" value="Insert new mapping for this SRC and this proto"/><br/>
									<input type="reset" value="reset"/>
								</form>
								<form action="" method="GET">
<?php
									echo '<input type="hidden" name="SRC" value="', htmlspecialchars($_GET['SRC']), "\"/>\n";
									echo '<input type="hidden" name="proto" value="', htmlspecialchars($_GET['proto']), "\"/>\n";
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
									$str = substr($row[0], 2);
									echo "<input form=\"update{$str}\" type=\"text\" name=\"imm_SRC\" value=\"{$str}\"/>\n";
?>
								</td>
								<td>
<?php
									echo "<input form=\"update{$str}\" type=\"text\" name=\"TWR\" value=\"{$row[1]}\"/>\n";
?>
								</td>
								<td>
<?php
									echo "<form id=\"update{$str}\" action=\"\" method=\"GET\">\n";
										echo '<input type="hidden" name="SRC" value="', htmlspecialchars($_GET['SRC']), "\"/>\n";
										echo '<input type="hidden" name="proto" value="', htmlspecialchars($_GET['proto']), "\"/>\n";
										echo "<input type=\"hidden\" name=\"key\" value=\"{$str}\"/>\n";
										echo "<input type=\"submit\" name=\"update\" value=\"Update this mapping for this SRC and this proto\"/><br/>\n";
										echo "<input type=\"reset\" value=\"reset\"/>\n";
									echo "</form>\n";
?>
									<form action="" method="GET">
<?php
										echo '<input type="hidden" name="SRC" value="', htmlspecialchars($_GET['SRC']), "\"/>\n";
										echo '<input type="hidden" name="proto" value="', htmlspecialchars($_GET['proto']), "\"/>\n";
										echo "<input type=\"hidden\" name=\"key\" value=\"{$str}\"/>\n";
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
				echo '<a href="view_source_details.php?SRC=', urlencode($_GET['SRC']), "\">Done</a>\n";
				pg_free_result($result);
				pg_close($dbconn);
			}
?>
		</body>
	</html>
<?php
}
?>