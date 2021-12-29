<?php
require_once 'common.php';
$can_view_permissions = check_authorization('can_view_permissions', 'view permissions');
$can_edit_permissions = check_authorization('can_edit_permissions', 'edit permissions');
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
	} elseif (!empty($_GET['tablename']) && !empty($_GET['username'])) {
		$s_tablename = pg_escape_literal($_GET['tablename']);
		$h_tablename = '&apos;' . htmlspecialchars($_GET['tablename']) . '&apos;';
		$s_username = pg_escape_literal($_GET['username']);
		$h_username = '&apos;' . htmlspecialchars($_GET['username']) . '&apos;';
		$tablename_owner = find_owner($s_tablename);
		$tablename_owner_is_administrator = is_administrator($tablename_owner);
		$tablename_owner_is_user = $tablename_owner == $_SESSION['username'];
		$tablename_owner_is_user_or_public = $tablename_owner_is_user
				|| $tablename_owner == 'public';
		if (isset($_GET['insert'])) {
			if ($tablename_owner_is_user_or_public || $_SESSION['is_administrator']
					&& !$tablename_owner_is_administrator || $_SESSION['is_root']) {
				pgquery("INSERT INTO table_reader(tablename, username)
						VALUES($s_tablename, $s_username);");
				echo "Reader ($h_tablename, $h_username) inserted.<br/>\n";
			}
		} elseif (!empty($_GET['key1']) && !empty($_GET['key2'])) {
			$s_key1 = pg_escape_literal($_GET['key1']);
			$h_key1 = '&apos;' . htmlspecialchars($_GET['key1']) . '&apos;';
			$key1_owner = find_owner($s_key1);
			$key1_owner_is_administrator = is_administrator($key1_owner);
			$key1_owner_is_user = $key1_owner == $_SESSION['username'];
			$key1_owner_is_user_or_public = $key1_owner_is_user
					|| $key1_owner == 'public';
			$s_key2 = pg_escape_literal($_GET['key2']);
			$h_key2 = '&apos;' . htmlspecialchars($_GET['key2']) . '&apos;';
			if (($key1_owner_is_user_or_public && $tablename_owner_is_user_or_public
					|| $_SESSION['is_administrator'] && ($key1_owner_is_user
					|| !$key1_owner_is_administrator) && ($tablename_owner_is_user
					|| !$tablename_owner_is_administrator) || $_SESSION['is_root'])
					&& isset($_GET['update1'])) {
				pgquery("UPDATE table_reader SET (tablename, username) = ($s_tablename, $s_username)
						WHERE tablename = $s_key1 AND username = $s_key2;");
				echo "Reader ($h_key1, $h_key2) updated to ($h_username, $h_tablename).<br/>\n";
			} elseif (($key1_owner_is_user_or_public || $_SESSION['is_administrator']
					&& !$key1_owner_is_administrator || $_SESSION['is_root'])
					&& isset($_GET['update2'])) {
				pgquery("UPDATE table_owner SET username = $s_username WHERE tablename = $s_key1;");
				echo "Owner ($h_key1, $h_key2) updated to ($h_key1, $h_username).<br/>\n";
			}
		}
	} elseif (!empty($_GET['key1']) && !empty($_GET['key2'])) {
		$s_key1 = pg_escape_literal($_GET['key1']);
		$h_key1 = '&apos;' . htmlspecialchars($_GET['key1']) . '&apos;';
		$u_key1 = urlencode($_GET['key1']);
		$key1_owner = find_owner($s_key1);
		$s_key2 = pg_escape_literal($_GET['key2']);
		$h_key2 = '&apos;' . htmlspecialchars($_GET['key2']) . '&apos;';
		$u_key2 = urlencode($_GET['key2']);
		if (($key1_owner == $_SESSION['username'] || $key1_owner == 'public'
				|| $_SESSION['is_administrator'] && !is_administrator($key1_owner)
				|| $_SESSION['is_root']) && isset($_GET['delete'])) {
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
	if ($_SESSION['is_root']) {
		$result = pgquery('SELECT *, TRUE, FALSE AS is_owner FROM table_reader
				UNION ALL SELECT *, TRUE, TRUE AS is_owner FROM table_owner
				ORDER BY is_owner ASC, tablename ASC, username ASC;');
?>
		You are authorized to view (edit) permissions for all tables.<br/>
<?php
	} elseif ($_SESSION['is_administrator']) {
		$can_view = "EXISTS(SELECT TRUE FROM table_reader INNER JOIN users
				ON table_reader.username = users.username WHERE table_reader.tablename = table_name
				AND (username = {$_SESSION['s_username']} OR NOT users.is_administrator))";
		$result = pgquery("SELECT table_reader.tablename AS table_name, table_reader.username,
				EXISTS(SELECT TRUE FROM table_owner INNER JOIN users ON table_owner.username
				= users.username WHERE table_owner.tablename = table_name AND (table_owner.username
				= {$_SESSION['s_username']} OR NOT users.is_administrator)) AS can_edit, FALSE
				AS is_owner FROM table_reader WHERE can_edit OR $can_view UNION ALL
				SELECT table_owner.tablename AS table_name, table_reader.username
				= {$_SESSION['s_username']} OR NOT users.is_administrator AS can_edit, TRUE
				AS is_owner FROM table_owner INNER JOIN users ON table_owner.username
				= users.username WHERE can_edit OR $can_view ORDER BY is_owner ASC, table_name ASC,
				table_owner.username ASC;");
		echo "You are authorized to view (edit) permissions for
				username-{$_SESSION['h2username']}-readable (-owned)
				or non-administrator-readable (-owned) tables.<br/>\n";
	} else {
		$can_view = "EXISTS(SELECT TRUE FROM table_reader WHERE tablename = table_name
				AND (username = {$_SESSION['s_username']} OR username = 'public'))";
		$result = pgquery("SELECT tablename AS table_name, username, EXISTS(SELECT TRUE
				FROM table_owner WHERE tablename = table_name AND (username
				= {$_SESSION['s_username']} OR username = 'public')) AS can_edit, FALSE AS is_owner
				FROM table_reader WHERE can_edit OR $can_view UNION ALL SELECT tablename
				AS table_name, username = {$_SESSION['s_username']} OR NOT users.is_administrator
				AS can_edit, TRUE AS is_owner FROM table_owner WHERE can_edit OR $can_view
				ORDER BY is_owner ASC, table_name ASC, username ASC;");
		echo "You are authorized to view (edit) permissions for
				username-{$_SESSION['h2username']}-readable (-owned)
				or public-readable tables.<br/>\n";
	}
?>
	Viewing tables &quot;table_reader&quot; and &quot;table_owner&quot;,
			table readers shown first.<br/>
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
								$row[2] == 't' ? ' readonly' : '', "/>\n";
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
					if ($can_edit_permissions) {
?>
						<td>
<?php
							echo "<form id=$form action=\"\" method=\"GET\">\n";
								echo "<input type=\"hidden\" name=\"key1\"
										value=\"$h_tablename\"/>\n";
								echo "<input type=\"hidden\" name=\"key2\" value=\"$username\"/>\n";
								echo '<input type="submit" name="update', $row[2] != 't'
										? '1" value="UPDATE reader' : '2" value="UPDATE owner',
										"\"/>\n";
?>
								<input type="reset" value="reset"/>
<?php
							echo "</form>\n";
							if ($row[2] != 't') {
?>
								<form action="" method="GET">
<?php
									echo "<input type=\"hidden\" name=\"key1\"
											value=\"$h_tablename\"/>\n";
									echo "<input type=\"hidden\" name=\"key2\"
											value=\"$username\"/>\n";
?>
									<input type="submit" name="delete" value="DELETE"/>
								</form>
<?php
							}
?>
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