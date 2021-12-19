<?php
require_once 'common.php';
$can_view_permissions = check_authorization('can_view_permissions', 'view permissions');
$can_edit_permissions = check_authorization('can_edit_permissions', 'edit permissions');
if ($can_edit_permissions) {
	if (isset($_GET['truncate']) && $_SESSION['is_root']) {
		if (isset($_GET['confirm'])) {
			pgquery('DELETE FROM table_user WHERE is_read_only;');
			echo "Table &quot;table_user&quot; truncated - except for owners.<br/>\n";
		} else {
?>
			Are you sure?
			<a href="?truncate&amp;confirm">Yes</a>
			<a href="">No</a>
<?php
			exit(0);
		}
	} else if (!empty($_GET['tablename']) && !empty($_GET['username'])) {
		$s_tablename = pg_escape_literal($_GET['tablename']);
		$h_tablename = '&apos;' . htmlspecialchars($_GET['tablename']) . '&apos;';
		$s_username = pg_escape_literal($_GET['username']);
		$h_username = '&apos;' . htmlspecialchars($_GET['username']) . '&apos;';
		$tablename_owner = find_owner($s_tablename);
		$tablename_owner_is_administrator = is_administrator($tablename_owner);
		$tablename_owner_is_user_or_public = $tablename_owner == $_SESSION['username']
				|| $tablename_owner == 'public';
		if (isset($_GET['insert'])) {
			if ($tablename_owner_is_user_or_public || $_SESSION['is_administrator']
					&& !$tablename_owner_is_administrator || $_SESSION['is_root']) {
				pgquery("INSERT INTO table_user(tablename, username, is_read_only)
						VALUES($s_tablename, $s_username, FALSE);");
				echo "Row ($h_tablename, $h_username, FALSE) inserted.<br/>\n";
			}
		} else if (!empty($_GET['key1']) && !empty($_GET['key2']) && isset($_GET['update'])) {
			$s_key1 = pg_escape_literal($_GET['key1']);
			$h_key1 = '&apos;' . htmlspecialchars($_GET['key1']) . '&apos;';
			$key1_owner = find_owner($s_key1);
			$key1_owner_is_administrator = is_administrator($key1_owner);
			$key1_owner_is_user_or_public = $key1_owner == $_SESSION['username']
					|| $key1_owner == 'public';
			$s_key2 = pg_escape_literal($_GET['key2']);
			$h_key2 = '&apos;' . htmlspecialchars($_GET['key2']) . '&apos;';
			$key3 = isset($_GET['key3']);
			$s_key3 = pgescapebool($_GET['key3']);
			if (($key3 || $_GET['key1'] == $_GET['tablename'])
					&& $key1_owner_is_user_or_public && $tablename_owner_is_user_or_public
					|| $_SESSION['is_administrator'] && ($key1_owner_is_user_or_public
					|| !$key1_owner_is_administrator) && ($tablename_owner_is_user_or_public
					|| !$tablename_owner_is_administrator) || $_SESSION['is_root']) {
				pgquery("UPDATE table_user SET (tablename, username) = ($s_tablename, $s_username)
						WHERE tablename = $s_key1 AND username = $s_key2
						AND is_read_only = $s_key3;");
				echo "Row ($h_key1, $h_key2, $s_key3)
						updated to ($h_username, $h_tablename, $s_key3).<br/>\n";
			}
		}
	} else if (!empty($_GET['key1']) && !empty($_GET['key2'])) {
		$s_key1 = pg_escape_literal($_GET['key1']);
		$h_key1 = '&apos;' . htmlspecialchars($_GET['key1']) . '&apos;';
		$u_key1 = urlencode($_GET['key1']);
		$key1_owner = find_owner($s_key1);
		$s_key2 = pg_escape_literal($_GET['key2']);
		$h_key2 = '&apos;' . htmlspecialchars($_GET['key2']) . '&apos;';
		$u_key2 = urlencode($_GET['key2']);
		if (($key1_owner == $_SESSION['username'] || $key1_owner == 'public'
				|| $_SESSION['is_administrator'] && !is_administrator($key1_owner)
				|| $_SESSION['is_root']) && isset($_GET['delete']) {
			if (isset($_GET['confirm'])) {
				pgquery("DELETE FROM table_user WHERE tablename = $s_key1 AND username = $s_key2
						AND is_read_only = FALSE;");
				echo "Row ($h_key1, $h_key2, FALSE) deleted.<br/>\n";
			} else {
?>
				Are you sure?
<?php
				echo "<a href=\"?key1=$u_key1&amp;key2=$u_key2&amp;delete&amp;confirm\">Yes</a>\n";
?>
				<a href="">No</a>
<?php
				exit(0);
			}
		}
	}
}
if ($can_edit_permissions) {
	if ($_SESSION['is_root']) {
		$result = pgquery('SELECT * FROM table_user
				ORDER BY is_read_only ASC, tablename ASC, username ASC;');
?>
		You are authorized to view (edit) permissions for all tables.
<?php
	} else if ($_SESSION['is_administrator']) {
		$result = pgquery("SELECT tablename AS t, username, is_read_only FROM table_user
				WHERE EXISTS(SELECT TRUE FROM table_user WHERE tablename = t
				AND (username = {$_SESSION['s_username']} OR NOT is_administrator))
				ORDER BY is_read_only ASC, tablename ASC, username ASC;");
		echo "You are authorized to view (edit) permissions for {$_SESSION['h1username']}-readable
				(-owned) or non-administrator-readable (-owned) tables.\n";
	} else {
		$result = pgquery("SELECT tablename AS t, username, is_read_only FROM table_user
				WHERE EXISTS(SELECT TRUE FROM table_user WHERE tablename = t
				AND (username = {$_SESSION['s_username']} OR username = 'public'))
				ORDER BY is_read_only ASC, tablename ASC, username ASC;");
		echo "You are authorized to view (edit) permissions for {$_SESSION['h1username']}-readable
				(-owned) or public-readable tables.\n";
	}
?>
	Viewing table &quot;table_user&quot;, owners first.
	Tables ordered by tablename ascending and username ascending.
	<table border="1">
		<tbody>
			<tr>
				<th>Tablename</th>
				<th>Username</th>
				<th>Is read only?</th>
<?php
				if ($can_edit_permissions) {
?>
					<th>Actions</th>
<?php
				}
?>
			</tr>
<?php
			if ($can_edit_permissions) {
?>
				<tr>
					<td>
						<input form="insert" type="text" name="tablename"/>
					</td>
					<td>
						<input form="insert" type="text" name="username"/>
					</td>
					<td>
						<input type="checkbox" disabled="disabled"/>
					</td>
					<td>
						<form id="insert" action="" method="GET">
							<input type="submit" name="insert" value="INSERT"/><br/>
							<input type="reset" value="reset"/>
						</form>
<?php
						if ($_SESSION['is_root']) {
?>
							<form action="" method="GET">
								<input type="submit" name="truncate" value="TRUNCATE"/>
							</form>
<?php
						}
?>
					</td>
				</tr>
<?php
			}
			for ($row = pg_fetch_row($result); $row; $row = pg_fetch_row($result)) {
?>
				<tr>
					<td>
<?php
						$tablename = htmlspecialchars($row[0]);
						$username = htmlspecialchars($row[1]);
						$form = "\"update_{$tablename}_$username\"";
						echo "<input form=$form type=\"text\" name=\"tablename\"
								value=\"$tablename\"", $row[2] == 't' ?
								' disabled="disabled"' : '', "/>\n";
?>
					</td>
					<td>
<?php
						echo "<input form=$form type=\"text\" name=\"username\"
								value=\"$username\"/>\n";
?>
					</td>
					<td>
<?php
						echo "<input form=$form type=\"checkbox\" name=\"key3\"",
								$row[2] == 't' ? ' checked="checked"' : '',
								" disabled=\"disabled\"/>\n";
?>
					</td>
<?php
					if ($can_edit_permissions) {
?>
						<td>
<?php
							echo "<form id=$form action=\"\" method=\"GET\">\n";
								echo "<input type=\"hidden\" name=\"key1\"
										value=\"$tablename\"/>\n";
								echo "<input type=\"hidden\" name=\"key2\" value=\"$username\"/>\n";
?>
								<input type="submit" name="update" value="UPDATE"/>
								<input type="reset" value="reset"/>
<?php
							echo "</form>\n";
?>
							<form action="" method="GET">
<?php
								echo "<input type=\"hidden\" name=\"key1\"
										value=\"$tablename\"/>\n";
								echo "<input type=\"hidden\" name=\"key2\" value=\"$username\"/>\n";
?>
								<input type="submit" name="delete" value="DELETE"/>
							</form>
						</td>
<?php
					}
?>
				</tr>
<?php
			}
?>
		</tbody>
	</table>
	Write tablename and username as a string, e.g., tabababababababab and root.<br/>
	<a href="index.php">Done</a>
<?php
}
?>