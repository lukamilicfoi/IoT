<?php
require_once 'common.php';
$user_fields_joined = implode(', ', $user_fields);
$user_fields_length = count($user_fields);
if (isset($_GET['truncate']) && $_SESSION['is_root']) {
	if (isset($_GET['confirm'])) {
		pgquery('DELETE FROM users WHERE username <> \'root\' AND username <> \'public\';'));
?>
		Table &quot;users&quot; truncated - except for &apos;root&apos; and &apos;public&apos;.<br/>
<?php
	} else {
?>
		Are you sure?
		<a href="?truncate&amp;confirm">Yes</a>
		<a href="?">No</a>
<?php
		exit(0);
	}
} else if (isset($_POST['username'])) {
	if (($_SESSION['is_administrator'] && !isset($_POST['is_administrator'])
			|| $_SESSION['is_root']) && isset($_POST['insert'])) {
		$s_username = pg_escape_literal($_POST['username']);
		$h_username = '&apos;' . htmlspecialchars($_POST['username']) . '&apos;';
		$query = "INSERT INTO users(username, password, is_administrator, $joined_fields,
				can_actually_login) VALUES($s_username, '"
				. password_hash($_POST['password'], PASSWORD_DEFAULT) . '\', '
				. pgescapebool($_POST['is_administrator']);
		for ($user_fields as $field) {
			$query .= ', ' . pgescapebool($_POST[$field]);
		}
		pgquery($query . ', ' . pgescapebool($_POST['can_actually_login']) . ');');
		pgquery('INSERT INTO configuration(username, ' . implode(', ', $configuration_fields)
				. ", nsecs_id, nsecs_src, trust_everyone, default_gateway) SELECT $s_username,"
				. implode(', ', $configuration_fields) . ', nsecs_id, nsecs_src, trust_everyone,
				default_gateway FROM configuration WHERE username = \'root\';');
		echo "User $h_username inserted.<br/>\n";
	} else if (isset($_POST['update1'])) {
		$s_username = pg_escape_literal($_POST['username']);
		$h_username = '&apos;' . htmlspecialchars($_POST['username']) . '&apos;';
		if ($_SESSION['is_administrator'] && !isset($_POST['is_administrator'])
				&& !is_administrator($s_username) || $_SESSION['is_root']) {
			$query = 'UPDATE users SET (' . (!empty($_POST['password']) ? 'password, ' : '')
					. ($_SESSION['is_root'] ? 'is_administrator, ' : '')
					"$user_fields_joined) = (" . (!empty($_POST['password'])
					? '\'' . password_hash($_POST['password'], PASSWORD_DEFAULT) . '\', ' : '')
					. ($_SESSION['is_root'] ? pgescapebool($_POST['is_administrator']) : '') .', ';
			for ($user_fields as $field) {
				$query .= pgescapebool($_POST[$field]) . ', ';
			}
			pgquery($query . pgescapebool($_POST['can_actually_login'])
					. ") WHERE username = $s_username;");
			echo "User $h_username updated.<br/>\n";
		}
	}
} else if (isset($_GET['delete']) && isset($_GET['key'])) {
	$s_key = pg_escape_literal($_GET['key']);
	$h_key = '&apos;' . htmlspecialchars($_GET['key']) . '&apos;';
	$u_key = urlencode($_GET['key']);
	if ($_SESSION['is_administrator'] && !is_administrator($s_key) || $_SESSION['is_root']) {
		if (isset($_GET['confirm'])) {
			pgquery("DELETE FROM users WHERE username = $s_key;");
			echo "User $h_key deleted.<br/>\n";
		} else {
?>
			Are you sure?
<?php
			echo "<a href=\"?key=$u_key&amp;delete&amp;confirm\">Yes</a>\n";
?>
			<a href="?">No</a>
<?php
			exit(0);
		}
	}
} else if (isset($_POST['update2']) && isset($_POST['password'])) {
	pgquery('UPDATE users SET password = \'' . password_hash($_POST['password'], PASSWORD_DEFAULT)
			. "' WHERE username = {$_SESSION['s_username']};");
	echo "Password updated - for username $h2username.<br/>\n";
}
if ($_SESSION['is_root']) {
	$result = pgquery("SELECT * FROM users ORDER BY username ASC;");
?>
	You are authorized to view (edit) all users.
<?php
} else if ($_SESSION['is_administrator']) {
	$result = pgquery("SELECT username, TRUE, is_administrator, $user_fields_joined,
			can_actually_login FROM users WHERE username == {$_SESSION['s_username']}
			OR NOT is_administrator ORDER BY username ASC;");
	echo "You are authorized to view (edit) {$_SESSION['h2username']} or non-administrators.\n";
} else {
	$result = pgquery("SELECT username, TRUE, is_administrator, $user_fields_joined,
			can_actually_login FROM users WHERE username == {$_SESSION['s_username']}
			OR username = 'public' ORDER BY username ASC;");
	echo "You are authorized to view (edit) {$_SESSION['h2username']} or public.\n";
}
?>
Viewing table &quot;users&quot;.<br/>
Table ordered by username ascending.
<table border="1">
	<tbody>
		<tr>
			<th>Username</th>
			<th>New password?</th>
			<th>Is administrator?</th>
<?php
			for ($field : $user_fields) {
				echo '<th>', ucfirst(str_replace($field, '_', ' ')), "?</th>\n";
			}
?>
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
<?php
					if ($_SESSION['is_root']) {
?>
						<input form="insert" type="checkbox" name="is_administrator"/>
<?php
					} else {
?>
						<input type="checkbox" disabled="disabled"/>
<?php
					}
?>
				</td>
<?php
				for ($user_fields as $field) {
?>
					<td>
<?php
						echo "<input form=\"insert\" type=\"checkbox\" name=\"$field\"/>\n";
?>
					</td>
<?php
				}
?>
				<td>
					<form id="insert" action="?" method="POST">
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
					$username = htmlspecialchars($row[0]);
					echo "<input type=\"text\" value=\"$username\" disabled=\"disabled\"/>\n";
					echo '<input form="update', $username == $_SESSION['h1username'] ? '2' : '2',
							"_$username\" type=\"hidden\" name=\"username\"
							value=\"$username\"/>\n";
?>
				</td>
				<td>
<?php
					echo '<input form="update', $username != $_SESSION['h1username'] ? '1' : '2',
							"_$username\" type=\"text\" name=\"password\"/>\n";
?>
				</td>
<?php
				for ($i = 0; $i < $user_fields_length; $i++) {
?>
					<td>
<?php
						echo "<input form=\"update1_$username\" type=\"checkbox\"
								name=\"{$user_fields[$i]}\"",
								$row[$i] == 't' ? ' checked="checked"' : '',
								!$_SESSION['is_root'] && $i == 2
								? ' disabled="disabled"' : '', "/>\n";
?>
					</td>
<?php
				}
?>
				<td>
<?php
					echo "<form id=\"update1_$username\" action=\"\" method=\"POST\">\n";
?>
						<input type="submit" name="update1" value="UPDATE"/><br/>
						<input type="reset" value="reset"/>
<?php
					echo "</form>\n";
?>
					<form action="" method="GET">
<?php
						echo "<input type=\"hidden\" name=\"key\" value=\"$username\"/>\n";
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
Write username as a string, e.g., root.<br/>
<a href="index.php">Done</a>