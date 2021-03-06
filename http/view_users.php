<?php
require_once 'common.php';
checkLogin();
$admin = $_SESSION['user'] == 'admin';
?>
<!DOCTYPE html>
<html>
	<head>
<?php
		echo '<title>View users', $admin ? ' as administrator' : '', "</title>\n";
?>
		<meta http-equiv="Content-Type" content="text/html; charset=utf-8"/>
	</head>
	<body>
<?php
		$dbconn = pgconnect('host=localhost dbname=postgres user=' . ($admin ? 'postgres' : 'luka') . ' client_encoding=UTF8');
		if (isset($_GET['truncate'])) {
			if (isset($_GET['confirm'])) {
				$result = pgquery('DELETE FROM users WHERE username <> \'admin\';');
?>
				Table &quot;users&quot; truncated.<br/>
<?php
				pg_free_result($result);
			} else {
?>
				Are you sure?
				<a href="?truncate&amp;confirm">Yes</a>
				<a href="?">No</a>
<?php
				pg_close($dbconn);
				exit(0);
			}
		} else if (isset($_POST['insert'])) {
			$query = "INSERT INTO users(username, password, canViewTables, canSendMessages, can_Receive_Messages, canSendQueries, canViewRules, canActuallyLogin) VALUES('{$_POST['username']}', '" . password_hash($_POST['password'], PASSWORD_DEFAULT) . '\'';
			$fields = array('canviewtables', 'cansendmessages', 'can_receive_messages', 'cansendqueries', 'canviewrules', 'canactuallylogin');
			for ($i = 0; $i < 6; $i++) {
				$query .= isset($_POST[$fields[$i]]) ? ', TRUE' : ', FALSE';
			}
			$result = pgquery($query . ');');
			echo 'User ', htmlspecialchars($_POST['username']), " inserted.<br/>\n";
			pg_free_result($result);
		} else if (isset($_POST['update'])) {
			$query = "UPDATE users SET (password, canViewTables, canSendMessages, can_Receive_Messages, canSendQueries, canViewRules, canActuallyLogin) = (" . (isset($_POST['password']) && !empty($_POST['password']) ? '\'' . password_hash($_POST['password'], PASSWORD_DEFAULT) . '\'' : 'password');
			$fields = array('canviewtables', 'cansendmessages', 'can_receive_messages', 'cansendqueries', 'canviewrules', 'canactuallylogin');
			for ($i = 0; $i < 6; $i++) {
				$query .= isset($_POST[$fields[$i]]) ? ', TRUE' : ', FALSE';
			}
			$result = pgquery($query . ") WHERE username = '{$_POST['username']}';");
			echo 'User ', htmlspecialchars($_POST['username']), " updated.<br/>\n";
			pg_free_result($result);
		} else if (isset($_GET['delete'])) {
			if (isset($_GET['confirm'])) {
				$result = pgquery("DELETE FROM users WHERE username = '{$_GET['username']}';");
				echo 'User ', htmlspecialchars($_GET['username']), " deleted.<br/>\n";
				pg_free_result($result);
			} else {
?>
				Are you sure?
<?php
				echo '<a href="?username=', urlencode($_GET['username']), "&amp;delete&amp;confirm\">Yes</a>\n";
				echo "<a href=\"?\">No</a>\n";
				pg_close($dbconn);
				exit(0);
			}
		} else if (isset($_POST['update2']) && isset($_POST['password'])) {
			$result = pgquery('UPDATE users SET password = \'' . password_hash($_POST['password'], PASSWORD_DEFAULT) . "' WHERE username = '{$_SESSION['user']}';");
?>
			Password updated.<br/>
<?php
			pg_free_result($result);
		}
		$result = pgquery('SELECT * FROM users WHERE username ' . ($admin ? '<> \'admin' : "= '{$_SESSION['user']}") . '\' ORDER BY username ASC;');
?>
		Viewing table &quot;users&quot;.
		<table border="1">
			<tbody>
				<tr>
					<th>Username</th>
					<th>New password?</th>
					<th>Can view tables</th>
					<th>Can send messages</th>
					<th>Can &quot;receive&quot; messages</th>
					<th>Can send queries</th>
					<th>Can view rules</th>
					<th>Can actually login</th>
					<th>Actions</th>
				</tr>
<?php
				if ($admin) {
?>
					<tr>
						<td>
							<form id="insert" action="" method="POST">
								<input type="text" name="username"/>
							</form>
						</td>
						<td>
							<input form="insert" type="password" name="password"/>
						</td>
						<td>
							<input form="insert" type="checkbox" name="canviewtables"/>
						</td>
						<td>
							<input form="insert" type="checkbox" name="cansendmessages"/>
						</td>
						<td>
							<input form="insert" type="checkbox" name="can_receive_messages"/>
						</td>
						<td>
							<input form="insert" type="checkbox" name="cansendqueries"/>
						</td>
						<td>
							<input form="insert" type="checkbox" name="canviewrules"/>
						</td>
						<td>
							<input form="insert" type="checkbox" name="canactuallylogin"/>
						</td>
						<td>
							<input form="insert" type="submit" name="insert" value="INSERT"/><br/>
							<input form="insert" type="reset" value="reset"/>
							<form action="" method="GET">
								<input type="submit" name="truncate" value="TRUNCATE"/>
							</form>
						</td>
					</tr>
<?php
					for ($row = pg_fetch_row($result); $row; $row = pg_fetch_row($result)) {
?>
						<tr>
<?php
							echo '<td>', htmlspecialchars($row[0]), "</td>\n";
?>
							<td>
<?php
								echo "<form id=\"update", htmlspecialchars($row[0]), "\" action=\"\" method=\"POST\">\n";
									echo '<input type="hidden" name="username" value="', htmlspecialchars($row[0]), "\"/>\n";
?>
									<input type="text" name="password"/>
<?php
								echo "</form>\n";
?>
							</td>
<?php
							for ($i = 2; $i < 8; $i++) {
?>
								<td>
<?php
									echo '<input form="update', htmlspecialchars($row[0]), "\" type=\"checkbox\" name=\"", pg_field_name($result, $i), "\"", $row[$i] == 't' ? ' checked="checked"' : '', "/>\n";
?>
								</td>
<?php
							}
?>
							<td>
<?php
								echo '<input form="update', htmlspecialchars($row[0]), "\" type=\"submit\" name=\"update\" value=\"UPDATE\"/><br/>\n";
								echo '<input form="update', htmlspecialchars($row[0]), "\" type=\"reset\" value=\"reset\"/>\n";
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
				} else {
?>
					<tr>
<?php
						$row = pg_fetch_row($result);
						echo '<td>', htmlspecialchars($row[0]), "</td>\n";
?>
						<td>
							<form id="update2" action="" method="POST">
								<input type="password" name="password"/>
							</form>
						</td>
<?php
						for ($i = 2; $i < 8; $i++) {
?>
							<td>
<?php
								echo "<input type=\"checkbox\"", $row[$i] == 't' ? ' checked="checked"' : '', " disabled=\"disabled\"/>\n";
?>
							</td>
<?php
						}
?>
						<td>
							<input form="update2" type="submit" name="update2" value="UPDATE"/>
						</td>
					</tr>
<?php
				}
?>
			</tbody>
		</table>
<?php
		pg_free_result($result);
		pg_close($dbconn);
?>
		<a href="index.php">Done</a>
	</body>
</html>