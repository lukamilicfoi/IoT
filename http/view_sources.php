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
			<title>View sources (as administrator)</title>
			<meta http-equiv="Content-Type" content="text/html; charset=utf-8"/>
		</head>
		<body>
<?php
			$dbconn = pgconnect('host=localhost dbname=postgres user=postgres client_encoding=UTF8');
			if (isset($_GET['load'])) {
				pg_free_result(pgquery('SELECT load_store(TRUE);'));
				$_SESSION['loaded'] = true;
?>
				Loaded sources from running program.
<?php
			} else if (isset($_GET['store'])) {
				pg_free_result(pgquery('SELECT load_store(FALSE);'));
				unset($_SESSION['loaded']);
?>
				Stored sources to running program.
<?php
			} else if (isset($_GET['add'])) {
				pg_free_result(pgquery("INSERT INTO addr_oID(addr, out_ID) VALUES(E'\\\\x{$_GET['add']}', " . rand(0, 255) . ');'));
				echo 'Source ', htmlspecialchars($_GET['add']), " added.\n";
			} else if (isset($_GET['remove'])) {
				if (isset($_GET['confirm'])) {
					pg_free_result(pgquery("DELETE FROM addr_oID WHERE addr = E'\\\\x{$_GET['remove']}';"));
					echo 'Source ', htmlspecialchars($_GET['remove']), " removed.\n";
				} else {
?>
					Are you sure?
<?php
					echo '<a href="?remove=', urlencode($_GET['remove']), "&amp;confirm\">Yes</a>\n";
?>
					<a href="?">No</a>
<?php
					pg_close($dbconn);
					exit(0);
				}
			}
			if (isset($_SESSION['loaded'])) {
				$result = pgquery('SELECT addr FROM addr_oID ORDER BY addr ASC;');
?>
				<form action="" method="GET">
					View source:
<?php
					for ($row = pg_fetch_row($result); $row; $row = pg_fetch_row($result)) {
						$str = substr($row[0], 2);
						echo "<a href=\"view_source_details.php?addr={$str}\">{$str}</a>\n";
						echo "<a href=\"?remove={$str}\">(remove)</a>\n";
					}
?>
					<input type="text" name="add"/>
					<input type="submit" value="(add)"/>
				</form>
				<a href="?load">Reload sources from running program</a><br/>
				<a href="?store">Store sources to running program</a><br/>
<?php
				pg_free_result($result);
			} else {
?>
				<br/><a href="?load">Load sources from running program</a><br/>
				<a href="?store">Delete sources from running program</a><br/>
<?php
			}
			pg_close($dbconn);
?>
			<a href="index.php">Done</a>
		</body>
	</html>
<?php
}
?>