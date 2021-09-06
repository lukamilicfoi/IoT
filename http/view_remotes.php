<?php
require_once 'common.php';
if (checkAuthorization(10, 'view remotes')) {
	if (isset($_GET['load'])) {
		pg_free_result(pgquery('CALL load_store(TRUE);'));
		$_SESSION['loaded'] = true;
?>
		Loaded remotes from running program.<br/>
<?php
	} else if (isset($_GET['store'])) {
		pg_free_result(pgquery('CALL load_store(FALSE);'));
		unset($_SESSION['loaded']);
?>
		Stored remotes to running program.<br/>
<?php
	} else if (!empty($_GET['add'])) {
		$sql_add1 = pg_escape_string($_GET['add']);
		$sql_add2 = pgescapebytea($_GET['add']);
		$html_add = htmlspecialchars($_GET['add']);
		$result1 = pgquery("SELECT TRUE FROM table_user WHERE tablename = 't$sql_add1';");
		$result2 = pgquery("SELECT TRUE FROM table_user WHERE tablename = 't$sql_add1'
				AND username = {$_SESSION['sql_username']};");
		$result3 = pgquery("SELECT TRUE FROM table_user INNER JOIN users ON table_user.username
				= users.username WHERE NOT users.is_administrator
				AND table_user.tablename = 't$sql_add1';");
		if (!pg_fetch_row($result1) || pg_fetch_row($result2) || $_SESSION['is_administrator']
				&& pg_fetch_row($result3) || $_SESSION['is_root']) {
			pg_free_result(pgquery("INSERT INTO addr_oID(addr, out_ID) VALUES($sql_add2, "
					. rand(0, 255) . ');'));
			echo "Remote X&apos;$html_add&apos; added.\n";
		}
		pg_free_result($result1);
		pg_free_result($result2);
		pg_free_result($result3);
	} else if (!empty($_GET['remove'])) {
		$sql_remove1 = pg_escape_string($_GET['remove']);
		$sql_remove2 = pgescapebytea($_GET['remove']);
		$html_remove = htmlspecialchars($_GET['remove']);
		if (isset($_GET['confirm'])) {
			$result1 = pgquery("SELECT TRUE FROM table_user WHERE tablename = 't$sql_remove1';");
			$result2 = pgquery("SELECT TRUE FROM table_user WHERE tablename = 't$sql_remove1'
					AND username = {$_SESSION['sql_username']};");
			$result3 = pgquery("SELECT TRUE FROM table_user INNER JOIN users ON table_user.username
					= users.username WHERE NOT users.is_administrator
					AND table_user.tablename = 't$sql_remove2';");
			if (!pg_fetch_row($result1) || pg_fetch_row($result2) || $_SESSION['is_administrator']
					&& pg_fetch_row($result3) || $_SESSION['is_root']) {
				pg_free_result(pgquery("DELETE FROM addr_oID WHERE addr = $sql_remove2;"));
				echo "Remote X&apos;$html_remove&apos; removed.\n";
			}
			pg_free_result($result1);
			pg_free_result($result2);
			pg_free_result($result3);
		} else {
			$url_remove = urlencode($_GET['remove']);
?>
			Are you sure?
<?php
			echo "<a href=\"?remove=$urlremove&amp;confirm\">Yes</a>\n";
?>
			<a href="?">No</a>
<?php
			exit(0);
		}
	}
	if (isset($_SESSION['loaded'])) {
		if ($_SESSION['is_root']) {
			$result = pgquery('SELECT addr_oID.addr FROM addr_oID LEFT OUTER JOIN table_user
					ON \'t\' || encode(addr_oID.addr, \'hex\') = table_user.tablename
					LEFT OUTER JOIN users ON table_user.username = users.username
					ORDER BY users.is_administrator DESC, addr_oID.addr ASC;');
		} else if ($_SESSION['is_administrator']) {
			$result = pgquery("SELECT addr_oID.addr FROM addr_oID LEFT OUTER JOIN table_user
					ON 't' || encode(addr_oID.addr, 'hex') = table_user.tablename
					LEFT OUTER JOIN users ON table_user.username = users.username
					WHERE users.username IS NULL OR users.username = {$_SESSION['sql_username']}
					OR NOT users.is_administrator ORDER BY addr_oID.addr ASC;");
		} else {
			$result = pgquery("SELECT addr_oID.addr FROM addr_oID LEFT OUTER JOIN table_user
					ON 't' || encode(addr_oID.addr, 'hex') = table_user.tablename
					LEFT OUTER JOIN users ON table_user.username = users.username
					WHERE users.username IS NULL OR users.username = {$_SESSION['sql_username']}
					ORDER BY addr_oID.addr ASC;");
		}
?>
		<form action="" method="GET">
<?php
			if ($_SESSION['is_root']) {
?>
				View remote, administrators&apos; first:
<?php
			} else if ($_SESSION['is_administrator']) {
				echo "View remote (public, &apos;{$_SESSION['html_username']}&apos;&apos;s,
						non-administrators' shown):\n";
			} else {
				echo "View remote (public, &apos;{$_SESSION['html_username']}&apos;&apos;s
						shown):\n";
			}
			for ($row = pg_fetch_row($result); $row;      $row = pg_fetch_row($result)) {
				$str = substr($row[0], 2);
				echo '<a href="view_remote_details.php?addr=', $str, '">', $str, "</a>\n";
				echo '<a href="?remove=', $str, "\">(remove)</a>\n";
			}
			if (pg_num_rows($result) == 0) {
?>
				&lt;no remotes&gt;
<?php
			}
?>
			<input type="text" name="add"/>
			<input type="submit" value="(add as public)"/>
		</form>
		Write remote as a binary string, e.g., abababababababab.<br/>
		<a href="?load">Reload all remotes from running program</a><br/>
		<a href="?store">Store all remotes to running program</a><br/>
<?php
		pg_free_result($result);
	} else {
?>
		<a href="?load">Load all remotes from running program</a><br/>
		<a href="?store">Delete all remotes from running program</a><br/>
<?php
	}
?>
	<a href="index.php">Done</a>
<?php
}
?>