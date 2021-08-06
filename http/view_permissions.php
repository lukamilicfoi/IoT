<?php
require_once 'common.php';
if (checkAuthorization(9, 'view permissions')) {
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
	} else if (!empty($_GET['tablename']) && !empty($_GET['username'])) {
		if (isset($_GET['insert'])) {
			$result = pgquery("SELECT TRUE FROM users WHERE username = '{$_GET['username']}' AND NOT is_administrator;");
			if ($_GET['username'] == $_SESSION['username'] || $_SESSION['is_administrator'] && pg_fetch_row($result) || $_SESSION['is_root']) {
				pg_free_result(pgquery("INSERT INTO table_user(tablename, username) VALUES('{$_GET['tablename']}', '{$_GET['username']}');"));
				echo 'Row ', htmlspecialchars($_GET['tablename']), ', ', htmlspecialchars($_GET['username']), " inserted.<br/>\n";
			}
			pg_free_result($result);
		} else if (!empty($_GET['key1']) && !empty($_GET['key2'])) {
			$result = pgquery("SELECT TRUE FROM users WHERE username = '{$_GET['key2']}' OR username = '{$_GET['username']}' AND is_administrator;");
			if ($_GET['key2'] == $_SESSION['username'] && $_GET['username'] == $_SESSION['username'] || $_SESSION['is_administrator'] && !pg_fetch_row($result) || $_SESSION['is_root']) {
				if (isset($_GET['update'])) {
					pg_free_result(pgquery("UPDATE table_user SET (tablename, username) = ('{$_GET['tablename']}', '{$_GET['username']}') WHERE tablename = '{$_GET['key1']}' AND username = '{$_GET['key2']}';"));
					echo 'Row ', htmlspecialchars($_GET['key1']), ', ', htmlspecialchars($_GET['key2']), " updated.<br/>\n";
				} else if (isset($_GET['delete'])) {
					if (isset($_GET['confirm'])) {
						pg_free_result(pgquery("DELETE FROM table_user WHERE tablename = '{$_GET['key1']}' AND username = '{$_GET['key2']}';"));
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
		$result = pgquery('TABLE table_user ORDER BY username ASC, tablename ASC;');
?>
		Viewing table &quot;table_user&quot;.
<?php
	} else if ($_SESSION['is_administrator']) {
		$result = pgquery("SELECT table_user.* FROM table_user LEFT OUTER JOIN users ON table_user.username = users.username WHERE NOT users.is_administrator OR table_user.username = '{$_SESSION['username']}' ORDER BY users.is_administrator DESC, table_user.username ASC, table_user.tablename ASC;");
		echo 'Viewing table &quot;table_user&quot; for username ', htmlspecialchars($_SESSION['username']), " and non-administrators.<br/>\n";
	} else {
		$result = pgquery("SELECT * FROM table_user WHERE user = '{$_SESSION['username']}' ORDER BY table ASC;");
		echo 'Viewing table &quot;table_user&quot; for username ', htmlspecialchars($_SESSION['username']), ".<br/>\n";
	}
?>
	<table border="1">
		<tbody>
			<tr>
				<th>Tablename</th>
				<th>Username</th>
				<th>Actions</th>
			</tr>
			<tr>
				<td>
					<input form="insert" type="text" name="tablename"/>
				</td>
				<td>
<?php
					echo '<input form="insert" type="text" name="username"', $_SESSION['is_administrator'] ? '' : ' value="' . $_SESSION['username'] . '" disabled="disabled"', "/>\n";
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
						$tablename = htmlspecialchars($row[0]);
						$user = htmlspecialchars($row[1]);
						echo '<input form="update_', $tablename, '_', $username, ' type="text" name="tablename" value="', $tablename, "\"/>\n";
?>
					</td>
					<td>
<?php
						echo '<input form="update_', $tablename, '_', $username, ' type="text" name="username" value="', $username, $_SESSION['is_administrator'] ? '' : '" disabled="disabled', "\"/>\n";
?>
					</td>
					<td>
<?php
						echo '<form id="update_', $tablename, '_', $username, "\" action=\"\" method=\"GET\">\n";
							echo '<input type="hidden" name="key1" value="', $tablename, "\"/>\n";
							echo '<input type="hidden" name="key2" value="', $username, "\"/>\n";
?>
							<input type="submit" name="update" value="UPDATE"/>
							<input type="reset" value="reset"/>
<?php
						echo "</form>\n";
?>
						<form action="" method="GET">
<?php
							echo '<input type="hidden" name="key1" value="', $tablename, "\"/>\n";
							echo '<input type="hidden" name="key2" value="', $username, "\"/>\n";
?>
							<input type="submit" name="delete" value="DELETE"/>
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