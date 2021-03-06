<?php
require_once 'common.php';
if (isset($_GET['truncate']) && $_SESSION['is_root']) {
	if (isset($_GET['confirm'])) {
		pg_free_result(pgquery('DELETE FROM users WHERE username <> \'root\';'));
?>
		Table &quot;users&quot; truncated.<br/>
<?php
	} else {
?>
		Are you sure?
		<a href="?truncate&amp;confirm">Yes</a>
		<a href="?">No</a>
<?php
		exit(0);
	}
} else if ($_SESSION['administrator'] && !isset($_POST['is_administrator']) || $_SESSION['is_root']) {
	if (isset($_POST['insert'])) {
		$query = 'INSERT INTO users(username, password';
		$fields = array('can_view_tables', 'can_send_messages', 'can_inject_messages', 'can_send_queries', 'can_view_rules', 'can_actually_login', 'is_administrator', 'can_view_configuration', 'can_view_permissions', 'can_view_remotes');
		for ($i = 0; $i < 10; $i++) {
			$query .= ", {$fields[$i]}";
		}
		$query .= ") VALUES('{$_POST['username']}', '" . password_hash($_POST['password'], PASSWORD_DEFAULT) . '\'';
		for ($i = 0; $i < 10; $i++) {
			$query .= isset($_POST[$fields[$i]]) ? ', TRUE' : ', FALSE';
		}
		pg_free_result(pgquery($query . ');'));
		echo 'User ', htmlspecialchars($_POST['username']), " inserted.<br/>\n";
	} else if (isset($_POST['update1'])) {
		$query = 'UPDATE users SET (password';
		$fields = array('can_view_tables', 'can_send_messages', 'can_inject_messages', 'can_send_queries', 'can_view_rules', 'can_actually_login', 'is_administrator', 'can_view_configuration', 'can_view_permissions', 'can_view_remotes');
		for ($i = 0; $i < 10; $i++) {
			$query .= ", {$fields[$i]}";
		}
		$query .= ") = (" . (!empty($_POST['password']) ? '\'' . password_hash($_POST['password'], PASSWORD_DEFAULT) . '\'' : 'password');
		for ($i = 0; $i < 10; $i++) {
			$query .= isset($_POST[$fields[$i]]) ? ', TRUE' : ', FALSE';
		}
		pg_free_result(pgquery($query . ") WHERE username = '{$_POST['key']}';"));
		echo 'User ', htmlspecialchars($_POST['key']), " updated.<br/>\n";
	} else if (isset($_POST['delete'])) {
		if (isset($_POST['confirm'])) {
			pg_free_result(pgquery("DELETE FROM users WHERE username = '{$_POST['key']}';"));
			echo 'User ', htmlspecialchars($_POST['key']), " deleted.<br/>\n";
		} else {
?>
			Are you sure?
<?php
			echo '<a href="?username=', urlencode($_POST['username']), "&amp;delete&amp;confirm\">Yes</a>\n";
			echo "<a href=\"?\">No</a>\n";
			exit(0);
		}
	}
} else if (isset($_POST['update2']) && isset($_POST['password'])) {
	pg_free_result(pgquery('UPDATE users SET password = \'' . password_hash($_POST['password'], PASSWORD_DEFAULT) . "' WHERE username = '{$_SESSION['username']}';"));
?>
	Password updated.<br/>
<?php
}
$result1 = pgquery("SELECT * FROM users WHERE username = '{$_SESSION['username']}';");
if ($_SESSION['is_root']) {
	$result2 = pgquery("SELECT * FROM users WHERE username <> 'admin' ORDER BY administrator DESC, username ASC;");
} else if ($_SESSION['is_administrator']) {
	$result2 = pqguery("SELECT * FROM users WHERE NOT is_administrator AND username <> '{$_SESSION['username']}' ORDER BY username ASC;");
}
?>
Viewing table &quot;users&quot;.
<table border="1">
	<tbody>
		<tr>
			<th>Username</th>
			<th>New password?</th>
			<th>Can view tables</th>
			<th>Can send messages</th>
			<th>Can inject messages</th>
			<th>Can send queries</th>
			<th>Can view rules</th>
			<th>Can actually login</th>
			<th>Is administrator?</th>
			<th>Can view configuration?</th>
			<th>Can view permissions?</th>
			<th>Can view remotes?</th>
			<th>Actions</th>
		</tr>
<?php
		if ($_SESSION['is_administrator']) {
?>
			<tr>
				<td>
					<input form="insert" type="text" name="username"/>
				</td>
				<td>
					<input form="insert" type="password" name="password"/>
				</td>
				<td>
					<input form="insert" type="checkbox" name="can_view_tables"/>
				</td>
				<td>
					<input form="insert" type="checkbox" name="can_send_messages"/>
				</td>
				<td>
					<input form="insert" type="checkbox" name="can_inject_messages"/>
				</td>
				<td>
					<input form="insert" type="checkbox" name="can_send_queries"/>
				</td>
				<td>
					<input form="insert" type="checkbox" name="can_view_rules"/>
				</td>
				<td>
					<input form="insert" type="checkbox" name="can_actually_login"/>
				</td>
				<td>
<?php
					echo '<input form="insert" type="checkbox" name="administrator"', $_SESSION['is_root'] ? '' : ' checked="checked" disabled="disabled"', "/>\n";
?>
				</td>
				<td>
					<input form="insert" type="checkbox" name="can_view_configuration"/>
				</td>
				<td>
					<input form="insert" type="checkbox" name="can_view_permissions"/>
				</td>
				<td>
					<input form="insert" type="checkbox" name="can_view_remotes"/>
				</td>
				<td>
					<form id="insert" action="" method="POST">
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
?>
		<tr>
<?php
			$row = pg_fetch_row($result1);
			echo '<td>', htmlspecialchars($row[0]), "</td>\n";
?>
			<td>
				<input form="update2" type="password" name="password"/>
			</td>
<?php
			for ($i = 2; $i < 12; $i++) {
?>
				<td>
<?php
					echo '<input type="checkbox"', $row[$i] == 't' : ' checked="checked"' : '', " disabled=\"disabled\"/>\n";
?>
				</td>
<?php
			}
?>
			<td>
				<form id="update2" action="" method="POST">
					<input type="submit" name="update2" value="UPDATE"/>
				</form>
			</td>
		</tr>
<?php
		if ($_SESSION['is_administrator']) {
			for ($row = pg_fetch_row($result2); $row; $row = pg_fetch_row($result2)) {
?>
				<tr>
<?php
					$user = htmlspecialchars($row[0]);
					echo '<td>', $user, "</td>\n";
?>
					<td>
<?php
						echo '<input form="update1_', $user, '" type="hidden" name="username" value="', $user, "\"/>\n";
						echo '<input form="update1_', $user, "\" type=\"text\" name=\"password\"/>\n";
?>
					</td>
<?php
					for ($i = 2; $i < 12; $i++) {
?>
						<td>
<?php
							echo '<input form="update1_', $user, "\" type=\"checkbox\" name=\"", pg_field_name($result, $i), "\"", $row[$i] == 't' ? ' checked="checked"' : '', "/>\n";
?>
						</td>
<?php
					}
?>
					<td>
<?php
						echo '<form id="update1_', $user, "\" action=\"\" method=\"POST\"><br/>\n";
?>
							<input type="submit" name="update1" value="UPDATE"/><br/>
							<input type="reset" value="reset"/>
<?php
						echo "</form>\n";
?>
						<form action="" method="GET">
<?php
							echo '<input type="hidden" name="username" value="', htmlspecialchars($row[0]), "\"/>\n";
?>
							<input type="submit" name="delete" value="DELETE"/>
						</form>
					</td>
				</tr>
<?php
			}
		}
?>
	</tbody>
</table>
<?php
pg_free_result($result1);
if ($_SESSION['is_administrator']) {
	pg_free_result($result2);
}
?>
<a href="index.php">Done</a>