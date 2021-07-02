<?php
require_once 'common.php';
if (checkAuthorization(10, 'view permissions')) {
	if (isset($_GET['truncate']) && $_SESSION['is_root']) {
		if (isset($_GET['confirm'])) {
			pg_free_result(pgquery('TRUNCATE TABLE table_user;'));
			echo "Table &quot;table_user&quot; truncated.<br/>\n";
		} else {
?>
			Are you sure?
			<a href="?truncate&amp;confirm">Yes</a>\n";
			<a href="">No</a>\n";
<?php
			exit(0);
		}
	} else if (!empty($_GET['table']) && !empty($_GET['user'])) {
		if (isset($_GET['insert']) {
			$result = pgquery("SELECT TRUE FROM users WHERE username = '{$_GET['user']}' AND NOT is_administrator;");
			if ($_GET['user'] == $_SESSION['username'] || $_SESSION['is_administrator'] && pg_fetch_row($result) || $_SESSION['is_root']) {
				pg_free_result(pgquery("INSERT INTO table_user(table, user) VALUES('{$_GET['table']}', '{$_GET['user']}');"));
				echo 'Row ', htmlspecialchars($_GET['table']), ', ', htmlspecialchars($_GET['user']), " inserted.<br/>\n";
			}
			pg_free_result($result);
		} else if (!empty($_GET['key1']) && !empty($_GET['key2'])) {
			$result = pgquery("SELECT TRUE FROM users WHERE username = '{$_GET['key2']}' OR username = '{$_GET['user']}' AND is_administrator;");
			if ($_GET['key2'] == $_SESSION['username'] && $_GET['user'] == $_SESSION['username'] || $_SESSION['is_administrator'] && !pg_fetch_row($result) || $_SESSION['is_root']) {
				if (isset($_GET['update'])) {
					pg_free_result(pgquery("UPDATE table_user SET (table, user) = ('{$_GET['table']}', '{$_GET['user']}') WHERE table = '{$_GET['key1']}' AND user = '{$_GET['key2']}';"));
					echo 'Row ', htmlspecialchars($_GET['key1']), ', ', htmlspecialchars($_GET['key2']), " updated.<br/>\n";
				} else if (isset($_GET['delete'])) {
					if (isset($_GET['confirm'])) {
						pg_free_result(pgquery("DELETE FROM table_user WHERE table = '{$_GET['key1']}' AND user = '{$_GET['key2']}';"));
						echo 'Row ', htmlspecialchars($_GET['key1']), ', ', htmlspecialchars($_GET['key2']), " deleted.<br/>\n";
					} else {
?>
						Are you sure?
<?php
						echo '<a href="?key1=', urlencode($_GET['key1']), '&amp;key2=', urlencode($_GET['key2']), "&amp;delete&amp;confirm\">Yes</a>\n";
?>
						<a href="">No</a>
<?php
						exit(0);
					}
				}
			}
			pg_free_result($result);
		}
	}
	if ($_SESSION['is_root']) {
		$result = pgquery('TABLE table_user ORDER BY user ASC, table ASC;');
?>
		Viewing table &quot;table_user&quot;.
<?php
	} else if ($_SESSION['is_administrator']) {
		$result = pgquery("SELECT table_user.* FROM table_user LEFT OUTER JOIN users ON table_user.user = users.username WHERE NOT users.is_administrator OR table_user.user = '{$_SESSION['username']}' ORDER BY users.is_administrator DESC, table_user.user ASC, table_user.table ASC;");
		echo 'Viewing table &quot;table_user&quot; for user ', htmlspecialchars($_SESSION['username']), " and non-administrators.<br/>\n";
	} else {
		$result = pgquery("SELECT * FROM table_user WHERE user = '{$_SESSION['username']}' ORDER BY table ASC;");
		echo 'Viewing table &quot;table_user&quot; for user ', htmlspecialchars($_SESSION['username']), ".<br/>\n";
	}
?>
	<table border="1">
		<tbody>
			<tr>
				<th>Table</th>
				<th>User</th>
				<th>Actions</th>
			</tr>
			<tr>
				<td>
					<input form="insert" type="text" name="table"/>
				</td>
				<td>
<?php
					echo '<input form="insert" type="text" name="user"', $_SESSION['is_administrator'] ? '' : ' value="' . $_SESSION['username'] . '" disabled="disabled"', "/>\n";
?>
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
			for ($row = pg_fetch_row($result); $row; $row = pg_fetch_row($result)) {
?>
				<tr>
					<td>
<?php
						$table = htmlspecialchars($row[0]);
						$user = htmlspecialchars($row[1]);
						echo '<input form="update_', $table, '_', $user, ' type="text" name="table" value="', $table, "\"/>\n";
?>
					</td>
					<td>
<?php
						echo '<input form="update_', $table, '_', $user, ' type="text" name="user" value="', $user, $_SESSION['is_administrator'] ? '' : '" disabled="disabled', "\"/>\n";
?>
					</td>
					<td>
<?php
						echo '<form id="update_', $table, '_', $user, "\" action=\"\" method=\"GET\">\n";
							echo '<input type="hidden" name="key1" value="', $table, "\"/>\n";
							echo '<input type="hidden" name="key2" value="', $user, "\"/>\n";
							echo "<input type=\"submit\" name=\"update\" value=\"UPDATE\"/>\n";
							echo "<input type=\"reset\" value=\"reset\"/>\n";
						echo "</form>\n";
?>
						<form action="" method="GET">
<?php
							echo '<input type="hidden" name="key1" value="', $table, "\"/>\n";
							echo '<input type="hidden" name="key2" value="' $user, "\"/>\n";
							echo "<input type=\"submit\" name=\"delete\" value=\"DELETE\"/>\n";
?>
						</form>
					</td>
				</tr>
<?php
			}
?>
		</tbody>
	</table>
	<a href="index.php">Done</a>
<?php
	pg_free_result($result);
}
?>