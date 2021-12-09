<?php
require_once 'common.php';
$can_view_permissions = checkAuthorization(10, 'view permissions');
$can_edit_permissions = checkAuthorization(11, 'edit permissions');
if ($can_edit_permissions) {
	if (isset($_GET['truncate']) && $_SESSION['is_root']) {
		if (isset($_GET['confirm'])) {
			pg_free_result(pgquery('TRUNCATE TABLE table_user;'));
			echo "Table &quot;table_user&quot; truncated.<br/>\n";
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
		$username_is_administrator = is_administrator($s_username);
		$username_is_user = $_GET['username'] == $_SESSION['username'];
		$is_read_only = isset($_GET['is_read_only']);
		$s_is_readonly = pgescapebool($_GET['is_read_only']);
		$tablename_owner = find_owner($s_tablename);
		$tablename_owner_is_administrator = is_administrator($tablename_owner);
		$tablename_owner_is_user = $tablename_owner == $_SESSION['username'];
		if (isset($_GET['insert'])) {
			if ($is_read_only && ($username_is_user && $tablename_owner == 'public'
					|| $_SESSION['is_administrator'] && ($username_is_user
					|| !$username_is_administrator) && ($tablename_owner_is_user
					|| !$tablename_owner_is_administrator) || $_SESSION['is_root'])) {
				pgquery("INSERT INTO table_user(tablename, username, is_read_only)
						VALUES($s_tablename, $s_username, FALSE);");
				echo "Row ($h_tablename, $h_username, FALSE) inserted.<br/>\n";
			}
		} else if (!empty($_GET['key1']) && !empty($_GET['key2']) && isset($_GET['update'])) {
			$s_key1 = pg_escape_literal($_GET['key1']);
			$h_key1 = '&apos;' . htmlspecialchars($_GET['key1']) . '&apos;';
			$key1_owner = find_owner($s_key1);
			$s_key2 = pg_escape_literal($_GET['key2']);
			$h_key2 = '&apos;' . htmlspecialchars($_GET['key2']) . '&apos;';
			$key2_is_administrator = is_administrator($s_key2);
			$key2_is_user = $_GET['key2'] == $_SESSION['username'];
			$key3 = isset($_GET['key3']);
			if ($is_read_only == $key3 && ($key2_is_user && $username_is_user
					&& $tablename_owner == 'public' && $key1_owner == 'public'
					|| $_SESSION['is_administrator'] && ($key2_is_user
					&& !$username_is_administrator || !$key2_is_administrator && $username_is_user
					|| !$key2_is_administrator && !$username_is_administrator)
					&& ($tablename_owner_is_user || !$tablename_owner_is_administrator)
					&& ($key1_owner == $_SESSION['username'] || !is_administrator($key1_owner))
					|| $_SESSION['is_root'])) {
				pgquery("UPDATE table_user SET (tablename, username) = ($s_tablename, $s_username)
						WHERE tablename = $s_key1 AND username = $s_key2;");
				echo "Row ($h_key1, $h_key2, $s_is_read_only)
						updated to ($h_username, $h_tablename, $s_is_read_only).<br/>\n";
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
		$key2_is_user = $_GET['key2'] == $_SESSION['username'];
		$key3 = isset($_GET['key3']);
		if (!$key3 && (($key2_is_user && $key1_owner == 'public' || $_SESSION['is_administrator']
				&& ($key2_is_user || !is_administrator($s_key2))
				&& ($key1_owner == $_SESSION['username'] || !is_administrator($key1_owner))
				|| $_SESSION['is_root']) && isset($_GET['delete']))) {
			if (isset($_GET['confirm'])) {
				pg_free_result(pgquery("DELETE FROM table_user WHERE tablename = $s_key1
						AND username = $s_key2;"));
				echo "Row ($h_key1, $h_key2, FALSE) deleted.<br/>\n";
			} else {
?>
				Are you sure?
<?php
				echo "<a href=\"?key1=$u_key1&amp;key2=$u_key2&amp;key3=$s_key3",
						"&amp;delete&amp;confirm\">Yes</a>\n";
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
		$result = pgquery('SELECT tablename AS t, username, is_read_only,
				(SELECT username FROM table_user WHERE tablename = t AND NOT is_read_only) AS u,
				(SELECT is_administrator FROM users WHERE username = u) FROM table_user
				ORDER BY is_administrator DESC, u ASC, t ASC, is_read_only ASC, username ASC;');
?>
		Viewing table &quot;table_user&quot;, administrators first.
<?php
	} else if ($_SESSION['is_administrator']) {
		$result = pgquery("SELECT tablename AS t, username, is_read_only,
				(SELECT username FROM table_user WHERE tablename = t AND NOT is_read_only) AS u,
				(SELECT is_administrator FROM users WHERE username = u), EXISTS(SELECT TRUE
				FROM table_user WHERE tablename = t AND username = 'public'
				OR username = {$_SESSION['username']}) AS e FROM table_user
				WHERE u = 'public' OR u = {$_SESSION['s_username']} OR NOT is_administrator OR e
				ORDER BY is_administrator DESC, u ASC, t ASC, is_read_only ASC, username ASC;");
		echo "Viewing table &quot;table_user&quot; for public, username {$_SESSION['h2username']}
				and non-administrators.<br/>\n";
	} else {
		$result = pgquery("SELECT tablename AS t, username, is_read_only, EXISTS(SELECT TRUE
				FROM table_user WHERE tablename = t AND username = 'public'
				OR username = {$_SESSION['username']}) AS e FROM table_user
				WHERE e ORDER BY t ASC, is_read_only ASC, username ASC;");
		echo "Viewing table &quot;table_user&quot; for public
				and username {$_SESSION['h2username']}.<br/>\n";
	}
?>
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
<?php
						if ($_SESSION['is_administrator']) {
?>
							<input form="insert" type="text" name="username"/>
<?php
						} else {
							echo "<input type=\"text\" value=\"{$_SESSION['h1username']}\"
									disabled=\"disabled\"/>\n";
							echo "<input form=\"insert\" type=\"hidden\" name=\"username\"
									value=\"{$_SESSION['h1username']}\"/>\n";
						}
?>
						<input form="insert" type="checkbox" disabled="disabled"/>
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
								value=\"$tablename\"/>\n";
?>
					</td>
					<td>
<?php
						if ($_SESSION['is_administrator']) {
							echo "<input form=$form type=\"text\" name=\"username\"
									value=\"$username\"/>\n";
						} else {
							echo "<input type=\"text\" value=\"$username\"
									disabled=\"disabled\"/>\n";
							echo "<input form=$form type=\"hidden\" name=\"username\"
									value=\"$username\" disabled=\"/>\n";
						}
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