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
	} elseif (!vacuous($_GET['add'])) {
		$s1add = pgescapename($_GET['add']);
		$s2add = pgescapebytea($_GET['add']);
		$h_add = 'X&apos;' . htmlspecialchars($_GET['add']) . '&apos;';
		if (can_edit_table($s1add)) {
			pgquery("INSERT INTO eui_oID(eui, out_ID) VALUES($s2eui, " . rand(0, 255) . ');');
			echo "Remote $h_add added.<br/>\n";
		}
	} elseif (!vacuous($_GET['remove'])) {
		$s1remove = pgescapename($_GET['remove']);
		$s2remove = pgescapebytea($_GET['remove']);
		$h_remove = 'X&apos;' . htmlspecialchars($_GET['remove']) . '&apos;';
		$u_remove = urlencode($_GET['remove']);
		if (isset($_GET['confirm'])) {
			if (can_edit_table($s1remove)) {
				pgquery("DELETE FROM eui_oID WHERE eui = $s2remove;");
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
			$result = pgquery('SELECT eui, TRUE FROM eui_oID ORDER BY eui ASC;');
?>
			You are authorized to view (edit) all remotes.
<?php
		} elseif ($_SESSION['is_administrator']) {
			$bin_eui = 'eui_oID.eui';
			$can_edit = "EXISTS(SELECT TRUE FROM table_owner INNER JOIN users
					ON table_owner.username = users.username WHERE table_owner.tablename
					= 't' || encode($bin_eui, 'hex') AND (table_owner.username
					= {$_SESSION['s_username']} OR NOT users.is_administrator
					AND {$_SESSION['s_can_edit_as_others']}))";
			$result = pgquery("SELECT DISTINCT $bin_eui, $can_edit FROM eui_oID
					INNER JOIN table_reader ON 't' || encode($bin_eui, 'hex')
					= table_reader.tablename WHERE $can_edit OR table_reader.username
					= {$_SESSION['s_username']} OR NOT users.is_administrator
					AND {$_SESSION['s_can_view_as_others']} ORDER BY $bin_eui ASC;");
			echo 'You are authorized to view', $can_edit_remotes ? ' (edit)' : '',
					" username-{$_SESSION['h2username']}-readable", $can_edit_remotes ? ' (-owned)'
					: '', $_SESSION['can_view_as_others'] ? ' or non-administrator-readable' : '',
					$_SESSION['can_edit_as_others'] && $can_edit_remotes ? ' (-owned)' : '',
					" remotes.\n";
		} elseif ($_SESSION['is_public']) {
			$bin_eui = 'eui_oID.eui';
			$can_edit = "EXISTS(SELECT TRUE FROM table_owner WHERE tablename = 't'
					|| encode($bin_eui, 'hex') AND username = 'public')";
			$result = pgquery("SELECT $bin_eui, $can_edit FROM eui_oID
					INNER JOIN table_reader ON 't' || encode($bin_eui, 'hex')
					= table_reader.tablename WHERE $can_edit OR table_reader.username = 'public'
					ORDER BY $bin_eui ASC;");
?>
			You are authorized to view (edit) public-user-readable (-owned) tables.
<?php
		} else {
			$bin_eui = 'eui_oID.eui';
			$can_edit = "EXISTS(SELECT TRUE FROM table_owner WHERE tablename
					= 't' || encode($bin_eui, 'hex') AND (username = {$_SESSION['s_username']}
					OR username = 'public') AND {$_SESSION['s_can_edit_as_others']})";
			$result = pgquery("SELECT DISTINCT $bin_eui, $can_edit FROM eui_oID.eui
					INNER JOIN table_reader ON 't' || encode($bin_eui, 'hex')
					= table_reader.tablename WHERE $can_edit OR table_reader.username
					= {$_SESSION['s_username']} OR table_reader.username = 'public'
					AND {$_SESSION['s_can_view_as_others']} ORDER BY $bin_eui ASC;");
			echo 'You are authorized to view', $can_edit_remotes ? ' (edit)' : '',
					" username-{$_SESSION['h2username']}-readable", $can_edit_remotes ? ' (-owned)'
					: '', $_SESSION['can_view_as_others'] ? ' or public-readable' : '',
					$_SESSION['can_edit_as_others'] && $can_edit_remotes ? ' (-owned)' : '',
					" remotes.\n";
		}
?>
		<form action="" method="GET">
			View remotes:
<?php
			for ($row = pg_fetch_row($result); $row; $row = pg_fetch_row($result)) {
				$h_eui = substr($row[0], 2);
				$s_eui = pgescapename($h_eui);
				echo "<a href=\"view_remote_details.php?eui=$h_eui\">$h_eui</a>\n";
				if ($can_edit_remotes && $row[1] == 't') {
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
				<input type="text" name="add" required/>
				<input type="submit" value="(add if permitted)"/>
				Write remote as a binary string, e.g., abababababababab.
<?php
			}
?>
		</form>
		Remotes ordered by address ascending.<br/><br/>
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
?>
	<br/><a href="index.php">Done</a>
<?php
}
?>