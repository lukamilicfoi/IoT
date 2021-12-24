<?php
require_once 'common.php';
$can_view_permissions = check_authorization('view permissions');
$can_edit_permissions = check_authorization('edit permissions');
if ($can_edit_permissions) {
	if (isset($_GET['truncate']) && $_SESSION['is_root']) {
		if (isset($_GET['confirm'])) {
			pgquery('TRUNCATE TABLE table_reader;');
			echo "Table &quot;table_reader&quot; truncated.<br/>\n";
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
				pgquery("INSERT INTO table_reader(tablename, username)
						VALUES($s_tablename, $s_username);");
				echo "Owner ($h_tablename, $h_username) inserted.<br/>\n";
			}
		} else if (!empty($_GET['key1']) && !empty($_GET['key2'])) {
			$s_key1 = pg_escape_literal($_GET['key1']);
			$h_key1 = '&apos;' . htmlspecialchars($_GET['key1']) . '&apos;';
			$key1_owner = find_owner($s_key1);
			$key1_owner_is_administrator = is_administrator($key1_owner);
			$key1_owner_is_user_or_public = $key1_owner == $_SESSION['username']
					|| $key1_owner == 'public';
			$s_key2 = pg_escape_literal($_GET['key2']);
			$h_key2 = '&apos;' . htmlspecialchars($_GET['key2']) . '&apos;';
			if (($key1_owner_is_user_or_public || $_SESSION['is_administrator']
					&& !$key1_owner_is_administrator || $_SESSION['is_root'])
					&& isset($_GET['update1'])) {
				pgquery("UPDATE table_owner SET username = $s_username
						WHERE tablename = $s_tablename;");
			} else if (($key1_owner_is_user_or_public && $tablename_owner_is_user_or_public
					|| $_SESSION['is_administrator'] && ($key1_owner_is_user_or_public
					|| !$key1_owner_is_administrator) && ($tablename_owner_is_user_or_public
					|| !$tablename_owner_is_administrator) || $_SESSION['is_root'])
					&& isset($_GET['update2'])) {
				pgquery("UPDATE table_reader SET (tablename, username) = ($s_tablename, $s_username)
						WHERE tablename = $s_key1 AND username = $s_key2;");
				echo "Reader ($h_key1, $h_key2) updated to ($h_username, $h_tablename).<br/>\n";
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
				pgquery("DELETE FROM table_reader WHERE tablename = $s_key1
						AND username = $s_key2;");
				echo "Reader ($h_key1, $h_key2) deleted.<br/>\n";
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
	$result = pgquery('SELECT tablename FROM table_owner ORDER BY tablename ASC;');
	if ($_SESSION['is_root']) {
?>
		You are authorized to view (edit) permissions for all tables.
<?php
	} else if ($_SESSION['is_administrator']) {
		echo "You are authorized to view (edit) permissions for {$_SESSION['h2username']}-readable
				(-owned) or non-administrator-readable (-owned) tables.\n";
	} else {
		echo "You are authorized to view (edit) permissions for {$_SESSION['h2username']}-readable
				(-owned) or public-readable tables.\n";
	}
?>
	Viewing table &quot;table_user&quot;, table owners shown first.
	Tables ordered by tablename ascending and username ascending.
	<table border="1">
		<tbody>
			<tr>
				<th>Tablename</th>
				<th>Username</th>
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
						$h_tablename = htmlspecialchars($row[0]);
						$s_tablename = pg_escape_literal($row[0]);
						$username = htmlspecialchars($row[1]);
						$form = "\"update_{$tablename}_$username\"";
						echo "<input form=$form type=\"text\" name=\"tablename\"
								value=\"$h_tablename\"",
								$row[2] == 't' ? ' disabled="disabled"' : '', "/>\n";
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
					if ($can_edit_permissions && can_edit_table($s_tablename)) {
?>
						<td>
<?php
							echo "<form id=$form action=\"\" method=\"GET\">\n";
								echo "<input type=\"hidden\" name=\"key1\"
										value=\"$h_tablename\"/>\n";
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
										value=\"$h_tablename\"/>\n";
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