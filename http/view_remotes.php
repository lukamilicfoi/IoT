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
			pgquery("INSERT INTO addr_oID(addr, out_ID) VALUES($s2add, " . rand(0, 255) . ');');
			echo "Remote $h_add added.<br/>\n";
		}
	} elseif (!vacuous($_GET['remove'])) {
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
			$result = pgquery('SELECT addr, TRUE FROM addr_oID ORDER BY addr ASC;');
?>
			You are authorized to view (edit) all remotes.
<?php
		} elseif ($_SESSION['is_administrator']) {
			$b_address = 'addr_oID.addr';
			$can_edit = "EXISTS(SELECT TRUE FROM table_owner INNER JOIN users
					ON table_owner.username = users.username WHERE table_owner.tablename
					= 't' || encode($b_address, 'hex') AND (table_owner.username
					= {$_SESSION['s_username']} OR NOT users.is_administrator
					AND {$_SESSION['s_can_edit_as_others']}))";
			$result = pgquery("SELECT DISTINCT $b_address, $can_edit FROM addr_oID
					INNER JOIN table_reader ON 't' || encode($b_address, 'hex')
					= table_reader.tablename WHERE $can_edit OR table_reader.username
					= {$_SESSION['s_username']} OR NOT users.is_administrator
					AND {$_SESSION['s_can_view_as_others']} ORDER BY $b_address ASC;");
			echo 'You are authorized to view', $can_edit_remotes ? ' (edit)' : '',
					" username-{$_SESSION['h2username']}-readable", $can_edit_remotes ? ' (-owned)'
					: '', $_SESSION['can_view_as_others'] ? ' or non-administrator-readable' : '',
					$_SESSION['can_edit_as_others'] && $can_edit_remotes ? ' (-owned)' : '',
					" remotes.\n";
		} elseif ($_SESSION['is_public']) {
			$b_address = 'addr_oID.addr';
			$can_edit = "EXISTS(SELECT TRUE FROM table_owner WHERE tablename = 't'
					|| encode($b_address, 'hex') AND username = 'public')";
			$result = pgquery("SELECT $b_address, $can_edit FROM addr_oID
					INNER JOIN table_reader ON 't' || encode($b_address, 'hex')
					= table_reader.tablename WHERE $can_edit OR table_reader.username = 'public'
					ORDER BY $b_address ASC;");
?>
			You are authorized to view (edit) public-user-readable (-owned) tables.
<?php
		} else {
			$b_address = 'addr_oID.addr';
			$can_edit = "EXISTS(SELECT TRUE FROM table_owner WHERE tablename
					= 't' || encode($b_address, 'hex') AND (username = {$_SESSION['s_username']}
					OR username = 'public') AND {$_SESSION['s_can_edit_as_others']})";
			$result = pgquery("SELECT DISTINCT $b_address, $can_edit FROM addr_oID.addr
					INNER JOIN table_reader ON 't' || encode($b_address, 'hex')
					= table_reader.tablename WHERE $can_edit OR table_reader.username
					= {$_SESSION['s_username']} OR table_reader.username = 'public'
					AND {$_SESSION['s_can_view_as_others']} ORDER BY $b_address ASC;");
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
				$h_addr = substr($row[0], 2);
				$s_addr = pgescapename($h_addr);
				echo "<a href=\"view_remote_details.php?addr=$h_addr\">$h_addr</a>\n";
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