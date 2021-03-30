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
			<title>View remote details (as administrator)</title>
			<meta http-equiv="Content-Type" content="text/html; charset=utf-8"/>
		</head>
		<body>
<?php
			if (isset($_GET['addr'])) {
				$dbconn = pgconnect('host=localhost dbname=postgres user=postgres client_encoding=UTF8');
				if (isset($_GET['out_ID'])) {
					pg_free_result(pgquery("UPDATE addr_oID SET out_ID = {$_GET['out_ID']} WHERE addr = E'\\\\x{$_GET['addr']}';"));
					echo 'out_ID changed for DST ', htmlspecialchars($_GET['addr']), ".<br/>\n";
				} else if (isset($_GET['random'])) {
					pg_free_result(pgquery('UPDATE addr_oID SET out_ID = ' . rand(0, 255) . " WHERE addr = E'\\\\x{$_GET['addr']}';"));
					echo 'out_ID randomized for DST ', htmlspecialchars($_GET['addr']), ".<br/>\n";
				} else if (isset($_GET['add'])) {
					pg_free_result(pgquery("INSERT INTO SRC_proto(SRC, proto) VALUES(E'\\\\x{$_GET['addr']}', (SELECT proto FROM proto_name WHERE name = '{$_GET['add']}'));"));
					echo 'proto ', htmlspecialchars($_GET['add']), ' added for SRC ', htmlspecialchars($_GET['addr']), ".<br/>\n";
				} else if (isset($_GET['add2'])) {
					pg_free_result(pgquery("INSERT INTO SRC_DST(SRC, DST) VALUES(E'\\\\x{$_GET['addr']}', E'\\\\x{$_GET['add2']}';"));
					echo 'DST ', htmlspecialchars($_GET['add2']), ' added for SRC ', htmlspecialchars($_GET['addr'), ".<br/>\n";
				} else if (isset($_GET['remove'])) {
					if (isset($_GET['confirm'])) {
						pg_free_result(pgquery("DELETE FROM SRC_proto WHERE SRC = E'\\\\x{$_GET['addr']}' AND proto = (SELECT proto FROM proto_name WHERE name = '{$_GET['remove']}');"));
						echo 'proto ', htmlspecialchars($_GET['remove']), ' removed for SRC ', htmlspecialchars($_GET['addr']), ".<br/>\n";
					} else {
?>
						Are you sure?
<?php
						echo '<a href="?addr=', urlencode($_GET['addr']), '&amp;remove=', urlencode($_GET['remove']), "&amp;confirm\">Yes</a>\n";
						echo '<a href="?addr=', urlencode($_GET['addr']), "\">No</a>\n";
						pg_close($dbconn);
						exit(0);
					}
				} else if (isset($_GET['remove2'])) {
					if (isset($_GET['confirm'])) {
						pg_free_result(pgquery("DELETE FROM SRC_DST WHERE SRC = E'\\\\x{$_GET['addr']}' AND DST = E'\\\\x{$_GET['DST']}';"));
						echo 'DST ', htmlspecialchars($_GET['remove2']), ' removed for SRC ', htmlspecialchars($_GET['addr']), ".<br/>\n";
					} else {
?>
						Are you sure?
<?php
						echo '<a href="?addr=', urlencode($_GET['addr']), '&amp;remove2=', urlencode($_GET['remove2']), "&amp;confirm\">Yes</a>\n";
						echo '<a href="?addr=', urlencode($_GET['addr']), "\">No</a>\n";
						pg_close($dbconn);
						exit(0);
					}
				}
?>
				<form action="" method="GET">
					View destination:
<?php
					$result = pgquery("SELECT DST FROM SRC_DST WHERE SRC = E'\\\\x{$_GET['addr']}' ORDER BY DST ASC;");
					for ($row = pg_fetch_row($result); $row; $row = pg_fetch_row($result)) {
						$str = substr($row[0], 2);
						echo '<a href="view_source_destination_details.php?SRC=', urlencode($_GET['addr']}, "&amp;DST={$str}\">{$str}</a>\n";
						echo '<a href="?addr=', urlencode($_GET['addr']), "&amp;remove2={$str}\">(remove)</a>\n";
					}
					echo '<input type="hidden" name="addr" value="', htmlspecialchars($_GET['addr']), "\"/>\n";
?>
					<input type="text" name="add2"/>
					<input type="submit" value="(add)"/>
				</form>
				outgoing ID
				<form action="" method="GET">
<?php
					pg_free_result($result);
					$result = pgquery("SELECT out_ID FROM addr_oID WHERE addr = E'\\\\x{$_GET['addr']}';");
					echo '<input type="hidden" name="addr" value="', htmlspecialchars($_GET['addr']), "\"/>\n";
					echo '<input type="text" name="out_ID" value="', pg_fetch_row($result)[0], "\"/><br/>\n";
?>
					<input type="submit" value="change"/><br/>
					<input type="reset" value="reset"/>
				</form>
				<form action="" method="GET">
<?php
					echo '<input type="hidden" name="addr" value="', htmlspecialchars($_GET['addr']), "\"/>\n";
?>
					<input type="submit" name="random" value="randomize"/>
				</form>
				<form action="" method="GET">
					View protocol:
<?php
					pg_free_result($result);
					$result = pgquery("SELECT SRC_proto.proto, proto_name.name FROM SRC_proto INNER JOIN proto_name ON SRC_proto.proto = proto_name.proto WHERE SRC = E'\\\\x{$_GET['addr']}' ORDER BY SRC_proto.proto ASC;");
					for ($row = pg_fetch_row($result); $row; $row = pg_fetch_row($result)) {
						echo '<a href="view_source_protocol_details.php?SRC=', urlencode($_GET['addr']), "&amp;proto={$row[0]}\">{$row[1]}</a>\n";
						echo '<a href="?addr=', urlencode($_GET['addr']), "&amp;remove={$row[1]}\">(remove)</a>\n";
					}
					echo '<input type="hidden" name="addr" value="', htmlspecialchars($_GET['addr']), "\"/>\n";
?>
					<input type="text" name="add"/>
					<input type="submit" value="(add)"/>
				</form>
				<a href="view_remotes.php">Done</a>
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