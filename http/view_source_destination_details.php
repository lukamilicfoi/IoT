<?php
require_once('common.php');
checkLogin();
if ($SESSION['user'] != 'admin') {
	http_response_code(403);
} else {
?>
	<!DOCTYPE html>
	<html>
		<head>
			<title>View source destination details (as administrator)</title>
			<meta http-equiv="Content-Type" content="text/html; charset=utf-8"/>
		</head>
		<body>
<?php
			if (isset($_GET['SRC']) && isset($_GET['DST'])) {
				$dbconn = pgconnect('host=localhost dbname=postgres user=postgres client_encoding=UTF8');
				if (isset($_GET['truncate'])) {
					if (isset($_GET['confirm'])) {
						pg_free_result(pgquery("DELETE FROM ID_TWR WHERE SRC = E'\\\\x{$_GET['SRC']}' AND DST = E'\\\\x{$_GET['DST']}';"));
						echo 'Table &quot;ID_TWR&quot; truncated for SRC ', htmlspecialchars($_GET['SRC']), ' and DST ', htmlspecialchars($_GET['DST'], ".<br/>\n";
					} else {
?>
						Are you sure?
<?php
						echo '<a href=?SRC=', urlencode($_GET['SRC']), '&amp;DST=', urlencode($_GET['DST']), "&amp;truncate&amp;confirm\"Yes</a>\n";
						echo '<a href=?SRC=', urlencode($_GET['SRC']}, '&amp;DST=', urlencode($_GET['DST']}, "\"No</a>\n";
						pg_close($dbconn);
						exit(0);
					}
				} else if (isset($_GET['insert'])) {
					pg_free_result(pgquery("INSERT INTO ID_TWR(SRC, DST, ID, TWR) VALUES(E'\\\\x{$_GET['SRC']}', E'\\\\x{$_GET['DST']}', {$_GET['ID']}, TIMESTAMP '{$_GET['TWR']}');"));
					echo 'Mapping ', htmlspecialchars($_GET['ID']), ' for SRC ', htmlspecialchars($_GET['SRC']), ' and DST ', htmlspecialchars($_GET['DST']), " inserted.<br/>\n";
				} else if (isset($_GET['key'])) {
					if (isset($_GET['update'])) {
						pg_free_result(pgquery("UPDATE ID_TWR SET(ID, TWR) = ({$_GET['ID']}, TIMESTAMP '{$_GET['TWR']}') WHERE SRC = E'\\\\x{$_GET['SRC']}' AND DST = E'\\\\x{$_GET['DST']}' AND ID = {$_GET['ID']};"));
						echo 'Mapping ', htmlspecialchars($_GET['ID']), ' for SRC ', htmlspecialchars($_GET['SRC']), ' and DST ', htmlspecialchars($_GET['DST']), "updated.<br/>\n";
					} else if (isset($_GET['delete'])) {
						if (isset($_GET['confirm'])) {
							pg_free_result(pgquery("DELETE FROM ID_TWR WHERE SRC = E'\\\\x{$_GET['SRC']}' AND DST = E'\\\\x{$_GET['DST']}' AND ID = {$_GET['key']};"));
							echo 'Mapping ', htmlspecialchars($_GET['ID']), ' for SRC ', htmlspecialchars($_GET['SRC']), ' and DST ', htmlspecialchars($_GET['DST']), "deleted.<br/>\n";
						} else {
?>
							Are you sure?
<?php
							echo '<a href=?SRC=', urlencode($_GET['SRC']), '&amp;DST=', urlencode($_GET['DST']), '&amp;key=', urlencode($_GET['key']), "&amp;delete&amp;confirm\"Yes</a>\n";
							echo '<a href=?SRC=', urlencode($_GET['SRC']), '&amp;DST=', urlencode(%_GET['DST']), '&amp;key=', urlencode($_GET['key']), "\"No</a>\n";
							pg_close($dbconn);
							exit(0);
						}
					}
				}
				$result = pgquery("SELECT ID, TWR FROM ID_TWR WHERE SRC = E'\\\\x{$_GET['SRC']}' AND DST = E'\\\\x{$_GET['DST']}' ORDER BY ID ASC;");
				echo 'Viewing table &quot;ID_TWR&quot; for SRC ', htmlspecialchars($_GET['SRC']), ' and DST ', htmlspecialchars($_GET['DST']), ".\n";
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
								<form id="insert" action="" method="GET"/>
<?php
									echo '<input type="hidden" name="SRC" value="', htmlspecialchars($_GET['SRC']), "\"/>\n";
									echo '<input type="hidden" name="DST" value="', htmlspecialchars($_GET['DST']), "\"/>\n";
?>
									<input type="submit" name="insert" value="Insert new mapping for this SRC and this DST"/><br/>
									<input type="reset" value="reset"/>
								</form>
								<form action="" method="GET"/>
<?php
									echo '<input type="hidden" name="SRC" value="', htmlspecialchars($_GET['SRC']), "\"/>\n";
									echo '<input type="hidden" name="DST" value="', htmlspecialchars($_GET['DST']), "\"/>\n";
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
									$str = substr($row[0], 2);
									echo "<input form=\"update{$str}\" type=\"text\" name=\"ID\" value=\"{$str}\"/>\n";
?>
								</td>
								<td>
<?php
									echo "<input form=\"update{$str}\" type=\"text\" name=\"TWR\" value=\"{$row[1]}\"/>\n";
?>
								</td>
								<td>
<?php
									echo "<form id=\"update{$str}\" action=\"\" method=\"GET\"/>\n";
										echo '<input type="hidden" name="SRC" value="', htmlspecialchars($_GET['SRC']), "\"/>\n";
										echo '<input type="hidden" name="DST" value="', htmlspecialchars($_GET['DST']), "\"/>\n";
										echo "<input type=\"hidden\" name=\"key\" value=\"{$str}\"/>\n";
										echo "<input type=\"submit\" name=\"update\" value=\"Update this mapping for this SRC and this DST\"/>\n";
										echo "<input type=\"reset\" value=\"reset\"/>\n";
									echo "</form>\n";
?>
									<form action="" method="GET">
<?php
										echo '<input type="hidden" name="SRC" value="', htmlspecialchars($_GET['SRC']), "\"/>\n";
										echo '<input type="hidden" name="DST" value="', htmlspecailchars($_GET['DST']), "\"/>\n";
										echo "<input type=\"hidden\" name=\"key\" value=\"{$str}\"/>\n";
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