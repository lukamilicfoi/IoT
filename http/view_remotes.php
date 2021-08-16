<?php
require_once 'common.php';
if (checkAuthorization(10, 'view remotes')) {
	if (isset($_GET['load'])) {
		pg_free_result(pgquery('CALL load_store(TRUE);'));
		$_SESSION['loaded'] = true;
?>
		Loaded remotes from running program.
<?php
	} else if (isset($_GET['store'])) {
		pg_free_result(pgquery('CALL load_store(FALSE);'));
		unset($_SESSION['loaded']);
?>
		Stored remotes to running program.
<?php
	} else if (!empty($_GET['add'])) {
		$result1 = pgquery("SELECT TRUE FROM table_user WHERE tablename = 't{$_GET['add']}';");
		$result2 = pgquery("SELECT TRUE FROM table_user WHERE tablename = 't{$_GET['add']}' AND username = '{$_SESSION['username']}';");
		$result3 = pgquery("SELECT TRUE FROM table_user INNER JOIN users ON table_user.username = users.username WHERE NOT users.is_administrator AND table_user.tablename = 't{$_GET['add']}';");
		if (!pg_fetch_row($result1) || pg_fetch_row($result2) || $_SESSION['is_administrator'] && pg_fetch_row($result3) || $_SESSION['is_root']) {
			pg_free_result(pgquery("INSERT INTO addr_oID(addr, out_ID) VALUES(E'\\\\x{$_GET['add']}', " . rand(0, 255) . ');'));
			echo 'Remote ', htmlspecialchars($_GET['add']), " added.\n";
		}
		pg_free_result($result1);
		pg_free_result($result2);
		pg_free_result($result3);
	} else if (!empty($_GET['remove'])) {
		if (isset($_GET['confirm'])) {
			$result1 = pgquery("SELECT TRUE FROM table_user WHERE tablename = 't{$_GET['remove']}';");
			$result2 = pgquery("SELECT TRUE FROM table_user WHERE tablename = 't{$_GET['remove']}' AND username = '{$_SESSION['username']}';");
			$result3 = pgquery("SELECT TRUE FROM table_user INNER JOIN users ON table_user.username = users.username WHERE NOT users.is_administrator AND table_user.tablename = 't{$_GET['remove']}';");
			if (!pg_fetch_row($result1) || pg_fetch_row($result2) || $_SESSION['is_administrator'] && pg_fetch_row($result3) || $_SESSION['is_root']) {
				pg_free_result(pgquery("DELETE FROM addr_oID WHERE addr = E'\\\\x{$_GET['remove']}';"));
				echo 'Remote ', htmlspecialchars($_GET['remove']), " removed.\n";
			}
			pg_free_result($result1);
			pg_free_result($result2);
			pg_free_result($result3);
		} else {
?>
			Are you sure?
<?php
			echo '<a href="?remove=', urlencode($_GET['remove']), "&amp;confirm\">Yes</a>\n";
?>
			<a href="?">No</a>
<?php
			exit(0);
		}
	}
	if (isset($_SESSION['loaded'])) {
		if ($_SESSION['is_root']) {
			$result = pgquery('SELECT addr FROM addr_oID ORDER BY addr ASC;');
		} else if ($_SESSION['is_administrator']) {
			$result = pgquery("SELECT addr_oID.addr FROM addr_oID LEFT OUTER JOIN table_user ON 't' || encode(addr_oID.addr, 'hex') == table_user.tablename LEFT OUTER JOIN users ON table_user.username = users.username WHERE users.username IS NULL OR users.username = '{$_SESSION['username']}' OR NOT users.is_administrator ORDER BY addr_oID.addr ASC;");
		} else {
			$result = pgquery("SELECT addr_oID.addr FROM addr_oID LEFT OUTER JOIN table_user ON 't' || encode(addr_oID.addr, 'hex') == table_user.tablename WHERE table_user.username = '{$_SESSION['username']}' ORDER BY addr_oID.addr ASC;");
		}
?>
		<form action="" method="GET">
			View remote:
<?php
			for ($row = pg_fetch_row($result); $row; $row = pg_fetch_row($result)) {
				$str = substr($row[0], 2);
				echo '<a href="view_remote_details.php?addr=', $str, '">', $str, "</a>\n";
				echo '<a href="?remove=', $str, "\">(remove)</a>\n";
			}
?>
			<input type="text" name="add"/>
			<input type="submit" value="(add)"/>
		</form>
		<a href="?load">Reload all remotes from running program</a><br/>
		<a href="?store">Store all remotes to running program</a><br/>
<?php
		pg_free_result($result);
	} else {
?>
		<br/><a href="?load">Load all remotes from running program</a><br/>
		<a href="?store">Delete all remotes from running program</a><br/>
<?php
	}
?>
	<a href="index.php">Done</a>
<?php
}
?>