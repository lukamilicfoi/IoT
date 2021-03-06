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
			<title>View source details (as administrator)</title>
			<meta http-equiv="Content-Type" content="text/html; charset=utf-8"/>
		</head>
		<body>
<?php
			if (isset($_GET['SRC'])) {
				$dbconn = pgconnect('host=localhost dbname=postgres user=postgres client_encoding=UTF8');
				if (isset($_GET['truncate'])) {
					if (isset($_GET['confirm'])) {
						pg_free_result(pgquery("DELETE FROM ID_TWR WHERE SRC = E'\\\\x{$_GET['SRC']}';"));//todo redo other like this
						echo 'Table &quot;ID_TWR&quot; truncated for SRC ', htmlspecialchars($_GET['SRC']), ".<br/>\n";
					} else {
?>
						Are you sure?
<?php
						echo '<a href="?SRC=', urlencode($_GET['SRC']), "&amp;truncate&amp;confirm\">Yes</a>\n";
						echo '<a href="?SRC=', urlencode($_GET['SRC']), "\">No</a>\n";
						pg_close($dbconn);
						exit(0);
					}
				} else if (isset($_GET['insert'])) {
					pg_free_result(pgquery("INSERT INTO ID_TWR(SRC, ID, TWR) VALUES(E'\\\\x{$_GET['SRC']}', {$_GET['ID']}, TIMESTAMP '{$_GET['TWR']}');"));
					echo 'Mapping ', htmlspecialchars($_GET['ID']), ' for SRC ', htmlspecialchars($_GET['SRC']), " inserted.<br/>\n";
				} else if (isset($_GET['key'])) {
					if (isset($_GET['update'])) {
						pg_free_result(pgquery("UPDATE ID_TWR SET (ID, TWR) = ({$_GET['ID']}, TIMESTAMP '{$_GET['TWR']}') WHERE SRC = E'\\\\x{$_GET['SRC']}' AND ID = {$_GET['key']};"));
						echo 'Mapping ', htmlspecialchars($_GET['key']), ' for SRC ', htmlspecialchars($_GET['SRC']), " updated.<br/>\n";
					} else if (isset($_GET['delete'])) {
						if (isset($_GET['confirm'])) {
							pg_free_result(pgquery("DELETE FROM ID_TWR WHERE SRC = E'\\\\x{$_GET['SRC']}' AND ID = {$_GET['key']};"));
							echo 'Mapping ', htmlspecialchars($_GET['key']), ' for SRC ', htmlspecialchars($_GET['SRC']), " deleted.<br/>\n";
						} else {
?>
							Are you sure?
<?php
							echo '<a href="?SRC=', urlencode($_GET['SRC']), '&amp;key=', urlencode($_GET['key']), "&amp;delete&amp;confirm\">Yes</a>\n";
							echo '<a href="?SRC=', urlencode($_GET['SRC']), "\">No</a>\n";
							pg_close($dbconn);
							exit(0);
						}
					}
				} else if (isset($_GET['out_ID'])) {
					pg_free_result(pgquery("UPDATE SRC_oID SET out_ID = {$_GET['out_ID']} WHERE SRC = E'\\\\x{$_GET['SRC']}';"));
					echo 'out_ID changed for SRC ', htmlspecialchars($_GET['SRC']), ".<br/>\n";
				} else if (isset($_GET['random'])) {
					pg_free_result(pgquery('UPDATE SRC_oID SET out_ID = ' . rand(0, 255) . " WHERE SRC = E'\\\\x{$_GET['SRC']}';"));
					echo 'out_ID randomized for SRC ', htmlspecialchars($_GET['SRC']), ".<br/>\n";
				} else if (isset($_GET['add'])) {
					pg_free_result(pgquery("INSERT INTO SRC_proto(SRC, proto) VALUES(E'\\\\x{$_GET['SRC']}', (SELECT proto FROM proto_name WHERE name = '{$_GET['add']}'));"));
					echo 'proto ', htmlspecialchars($_GET['add']), ' added for SRC ', htmlspecialchars($_GET['SRC']), ".<br/>\n";
				} else if (isset($_GET['remove'])) {
					if (isset($_GET['confirm'])) {
						pg_free_result(pgquery("DELETE FROM SRC_proto WHERE SRC = E'\\\\x{$_GET['SRC']}' AND proto = (SELECT proto FROM proto_name WHERE name = '{$_GET['remove']}');"));
						echo 'proto ', htmlspecialchars($_GET['remove']), ' removed for SRC ', htmlspecialchars($_GET['SRC']), ".<br/>\n";
					} else {
?>
						Are you sure?
<?php
						echo '<a href="?SRC=', urlencode($_GET['SRC']), '&amp;remove=', urlencode($_GET['remove']), "&amp;confirm\">Yes</a>\n";
						echo '<a href="?SRC=', urlencode($_GET['SRC']), "\">No</a>\n";
						pg_close($dbconn);
						exit(0);
					}
				}
				$result = pgquery("SELECT ID, TWR FROM ID_TWR WHERE SRC = E'\\\\x{$_GET['SRC']}' ORDER BY ID ASC;");
				echo 'Viewing table &quot;ID_TWR&quot; for SRC ', htmlspecialchars($_GET['SRC']), ".\n";
?>
				<table border="1">
					<tbody>
						<tr>
							<th>message identifier</th>
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
									echo '<input type="hidden" name="SRC" value="', htmlspecialchars($_GET['SRC']), "\"/>\n";
?>
									<input type="submit" name="insert" value="Insert new mapping for this SRC"/><br/>
									<input type="reset" value="reset"/>
								</form>
								<form action="" method="GET">
<?php
									echo '<input type="hidden" name="SRC" value="', htmlspecialchars($_GET['SRC']), "\"/>\n";
?>
									<input type="submit" name="truncate" value="Truncate mappings for this SRC"/>
								</form>
							</td>
						</tr>
<?php
						for ($row = pg_fetch_row($result); $row; $row = pg_fetch_row($result)) {
?>
							<tr>
								<td>
<?php
									echo "<input form=\"update{$row[0]}\" type=\"text\" name=\"ID\" value=\"{$row[0]}\"/>\n";
?>
								</td>
								<td>
<?php
									echo "<input form=\"update{$row[0]}\" type=\"text\" name=\"TWR\" value=\"{$row[1]}\"/>\n";
?>
								</td>
								<td>
<?php
									echo "<form id=\"update{$row[0]}\" action=\"\" method=\"GET\">\n";//todo redo other like this
										echo '<input type="hidden" name="SRC" value="', htmlspecialchars($_GET['SRC']), "\"/>\n";
										echo "<input type=\"hidden\" name=\"key\" value=\"{$row[0]}\"/>\n";
										echo "<input type=\"submit\" name=\"update\" value=\"Update this mapping for this SRC\"/><br/>\n";
										echo "<input type=\"reset\" value=\"reset\"/>\n";
									echo "</form>\n";
?>
									<form action="" method="GET">
<?php
										echo '<input type="hidden" name="SRC" value="', htmlspecialchars($_GET['SRC']), "\"/>\n";
										echo "<input type=\"hidden\" name=\"key\" value=\"{$row[0]}\"/>\n";
?>
										<input type="submit" name="delete" value="Delete this mapping for this SRC"/>
									</form>
								</td>
							</tr>
<?php
						}
?>
					</tbody>
				</table>
				outgoing ID
				<form action="" method="GET">
<?php
					pg_free_result($result);
					$result = pgquery("SELECT out_ID FROM SRC_oID WHERE SRC = E'\\\\x{$_GET['SRC']}';");
					echo '<input type="hidden" name="SRC" value="', htmlspecialchars($_GET['SRC']), "\"/>\n";
					echo '<input type="text" name="out_ID" value="', pg_fetch_row($result)[0], "\"/><br/>\n";
?>
					<input type="submit" value="change"/><br/>
					<input type="reset" value="reset"/>
				</form>
				<form action="" method="GET">
<?php
					echo '<input type="hidden" name="SRC" value="', htmlspecialchars($_GET['SRC']), "\"/>\n";
?>
					<input type="submit" name="random" value="randomize"/>
				</form>
				<form action="" method="GET">
					View protocol:
<?php
					pg_free_result($result);
					$result = pgquery("SELECT SRC_proto.proto, proto_name.name FROM SRC_proto INNER JOIN proto_name ON SRC_proto.proto = proto_name.proto WHERE SRC = E'\\\\x{$_GET['SRC']}' ORDER BY SRC_proto.proto ASC;");
					for ($row = pg_fetch_row($result); $row; $row = pg_fetch_row($result)) {
						echo '<a href="view_source_protocol_details.php?SRC=', urlencode($_GET['SRC']), "&amp;proto={$row[0]}\">{$row[1]}</a>\n";
						echo '<a href="?SRC=', urlencode($_GET['SRC']), "&amp;remove={$row[1]}\">(remove)</a>\n";
					}
					echo '<input type="hidden" name="SRC" value="', htmlspecialchars($_GET['SRC']), "\"/>\n";
?>
					<input type="text" name="add"/>
					<input type="submit" value="(add)"/>
				</form>
				<a href="view_sources.php">Done</a>
<?php
				pg_free_result($result);
				pg_close($dbconn);
			}
?>
		</body>
	</html>
<?php
}
?>