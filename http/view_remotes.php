<?php
require_once 'common.php';
$can_view_remotes = check_authorization('can_view_remotes', 'view remotes');
$can_edit_remotes = check_authorization('can_edit_remotes', 'edit remotes');
if ($can_view_remotes && isset($_GET['load'])) {
	pgquery('CALL load_store(TRUE);');
	$_SESSION['loaded'] = true;
?>
	Loaded remotes from running program.<br/>
<?php
}
if ($can_edit_remotes) {
	if (isset($_GET['store'])) {
		pgquery('CALL load_store(FALSE);');
		unset($_SESSION['loaded']);
?>
		Stored remotes to running program.<br/>
<?php
	} else if (!empty($_GET['add'])) {
		$s1add = pgescapename($_GET['add']);
		$s2add = pgescapebytea($_GET['add']);
		$h_add = 'X&apos;' . htmlspecialchars($_GET['add']) . '&apos;';
		if (can_edit_table($s1add)) {
			pgquery("INSERT INTO addr_oID(addr, out_ID) VALUES($s2add, " . rand(0, 255) . ');');
			echo "Remote $h_add added.<br/>\n";
		}
	} else if (!empty($_GET['remove'])) {
		$s1remove = pgescapename($_GET['remove']);
		$s2remove = pgescapebytea($_GET['remove']);
		$h_remove = 'X&apos;' . htmlspecialchars($_GET['remove']) . '&apos;';
		$u_remove = urlencode($_GET['remove']);
		if (isset($_GET['confirm'])) {
			if (can_edit_table($s1remove)) {
				pgquery("DELETE FROM addr_oID WHERE addr = $s2remove;");
				echo "Remote $h_remove removed.<br/>\n";
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
	}
}
if ($can_view_remotes) {
	if (isset($_SESSION['loaded'])) {
		if ($_SESSION['is_root']) {
			$result = pgquery('SELECT addr FROM addr_oID ORDER BY addr ASC;');
?>
			You are authorized to view (edit) all remotes.
<?php
		} else if ($_SESSION['is_administrator']) {
			$result = pgquery("SELECT DISTINCT addr_oID.addr FROM addr_oID INNER JOIN table_user
					ON 't' || encode(addr_oID.addr, 'hex') = table_user.tablename INNER JOIN users
					ON table_user.username = users.username
					WHERE users.username = {$_SESSION['s_username']} OR NOT users.is_administrator
					ORDER BY addr_oID.addr ASC;");
			echo "You are authorized to view (edit) {$_SESSION['h2username']}-readable (-owned)
					or non-administrator-readable (-owned) remotes.\n";
		} else {
			$result = pgquery("SELECT DISTINCT addr_oID.addr FROM addr_oID INNER JOIN table_user
					ON 't' || encode(addr_oID.addr, 'hex') = table_user.tablename INNER JOIN users
					ON table_user.username = users.username
					WHERE users.username = {$_SESSION['s_username']} OR users.username = 'public'
					ORDER BY addr_oID.addr ASC;");
			echo "You are authorized to view (edit) {$_SESSION['h2username']}-readable (-owned)
					or public-readable (-owned) remotes.\n";
		}
?>
		<form action="" method="GET">
			View remotes:
<?php
			for ($row = pg_fetch_row($result); $row; $row = pg_fetch_row($result)) {
				$str = substr($row[0], 2);
				echo "<a href=\"view_remote_details.php?addr=$str\">$str</a>\n";
				if ($can_edit_remotes) {
					echo "<a href=\"?remove=$str\">(remove)</a>\n";
				}
			}
			if (pg_num_rows($result) == 0) {
?>
				&lt;no remotes&gt;
<?php
			}
			if ($can_edit_remotes) {
?>
				<input type="text" name="add"/>
				<input type="submit" value="(add if permitted)"/>
				Write remote as a binary string, e.g., abababababababab.
<?php
			}
?>
		</form>
		Remotes ordered by address ascending.<br/>
		<a href="?load">Reload all remotes from running program</a><br/>
<?php
		if ($can_edit_remotes) {
?>
			<a href="?store">Store all remotes to running program</a><br/>
<?php
		}
	} else {
?>
		<a href="?load">Load all remotes from running program</a><br/>
<?php
		if ($can_edit_remotes) {
?>
			<a href="?store">Delete all remotes from running program</a><br/>
<?php
		}
	}
}
?>
<a href="index.php">Done</a>