<?php
require_once 'common.php';
$can_view_remotes = checkAuthorization(14, 'view remotes');
if ($can_view_remotes && isset($_GET['load'])) {
	pg_free_result(pgquery('CALL load_store(TRUE);'));
	$_SESSION['loaded'] = true;
?>
	Loaded remotes from running program.<br/>
<?php
}
$can_edit_remotes = checkAuthorization(15, 'edit remotes');
if ($can_edit_remotes) {
	if (isset($_GET['store'])) {
		pg_free_result(pgquery('CALL load_store(FALSE);'));
		unset($_SESSION['loaded']);
?>
		Stored remotes to running program.<br/>
<?php
	} else if (!empty($_GET['add'])) {
		$s1add = pgescapename($_GET['add']);
		$s2add = pgescapebytea($_GET['add']);
		$h_add = 'X&apos;' . htmlspecialchars($_GET['add']) . '&apos;';
		if (can_edit_table($s1add)) {
			pg_free_result(pgquery("INSERT INTO addr_oID(addr, out_ID) VALUES($s2add, "
					. rand(0, 255) . ');'));
			echo "Remote $h_add added.\n";
		}
	} else if (!empty($_GET['remove'])) {
		$s1remove = pgescapename($_GET['remove']);
		$s2remove = pgescapebytea($_GET['remove']);
		$h_remove = 'X&apos;' . htmlspecialchars($_GET['remove']) . '&apos;';
		$u_remove = urlencode($_GET['remove']);
		if (isset($_GET['confirm'])) {
			if (can_edit_table($s1remove)) {
				pg_free_result(pgquery("DELETE FROM addr_oID WHERE addr = $s2remove;"));
				echo "Remote $h_remove removed.\n";
			}
		} else {
?>
			Are you sure?
<?php
			echo "<a href=\"?remove=$u_remove&amp;confirm\">Yes</a>\n";
?>
			<a href="?">No</a>
<?php
			exit(0);
		}
if ($can_view_tables) {
	if (isset($_SESSION['loaded'])) {
		if ($_SESSION['is_root']) {
			$result = pgquery('SELECT addr_oID.addr FROM addr_oID LEFT OUTER JOIN table_user
					ON \'t\' || encode(addr_oID.addr, \'hex\') = table_user.tablename
					LEFT OUTER JOIN users ON table_user.username = users.username
					ORDER BY addr_oID.addr ASC;');
		} else if ($_SESSION['is_administrator']) {
			$result = pgquery("SELECT addr_oID.addr FROM addr_oID LEFT OUTER JOIN table_user
					ON 't' || encode(addr_oID.addr, 'hex') = table_user.tablename
					LEFT OUTER JOIN users ON table_user.username = users.username
					WHERE users.username IS NULL OR users.username = {$_SESSION['s_username']}
					OR NOT users.is_administrator ORDER BY addr_oID.addr ASC;");
		} else {
			$result = pgquery("SELECT addr_oID.addr FROM addr_oID LEFT OUTER JOIN table_user
					ON 't' || encode(addr_oID.addr, 'hex') = table_user.tablename
					LEFT OUTER JOIN users ON table_user.username = users.username
					WHERE users.username IS NULL OR users.username = {$_SESSION['s_username']}
					ORDER BY addr_oID.addr ASC;");
		}
?>
		<form action="" method="GET">
<?php
			if ($_SESSION['is_root']) {
?>
				View remote:
<?php
			} else if ($_SESSION['is_administrator']) {
				echo "View remote (public, {$_SESSION['h2username']}&apos;s, non-administrators'
						shown):\n";
			} else {
				echo "View remote (public, {$_SESSION['h2username']}&apos;s shown):\n";
			}
			for ($row = pg_fetch_row($result); $row; $row = pg_fetch_row($result)) {
				$str = substr($row[0], 2);
				echo "<a href=\"view_remote_details.php?addr=$str\">$str</a>\n";
				echo "<a href=\"?remove=$str\">(remove)</a>\n";
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