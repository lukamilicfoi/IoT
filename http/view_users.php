<?php
require_once 'common.php';
if (isset($_GET['truncate']) && $_SESSION['is_root']) {
	if (isset($_GET['confirm'])) {
		pg_free_result(pgquery('DELETE FROM users
				WHERE username <> \'root\' AND username <> \'public\';'));
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
		$query = 'INSERT INTO users(username, password';
		for ($i = 0; $i < 16; $i++) {
			$query .= ", {$user_fields[$i]}";
		}
		$query .= ") VALUES($s_username, '" . password_hash($_POST['password'], PASSWORD_DEFAULT)
				. '\'';
		for ($i = 0; $i < 16; $i++) {
			$query .= ', ' . pgescapebool($_POST[$user_fields[$i]]);
		}
		pgquery($query . ');');
		pgquery("INSERT INTO configuration(username, forward_messages,
				use_internet_switch_algorithm, nsecs_id, nsecs_src, trust_everyone, default_gateway)
				SELECT $s_username, forward_messages, use_internet_switch_algorithm, nsecs_id,
				nsecs_src, trust_everyone, default_gateway FROM configuration
				WHERE username = 'root';");
		echo "User $h_username inserted.<br/>\n";
	} else if (isset($_POST['update1'])) {
		$s_username = pg_escape_literal($_POST['username']);
		$h_username = '&apos;' . htmlspecialchars($_POST['username']) . '&apos;';
		if ($_SESSION['is_administrator'] && !isset($_POST['is_administrator'])
				&& !is_administrator($s_username) || $_SESSION['is_root']) {
			$query = 'UPDATE users SET (' . (!empty($_POST['password']) ? 'password, ' : '');
			for ($i = $_SESSION['is_root'] ? 0 : 1; $i < 16; $i++) {
				$query .= "{$user_fields[$i]}, ";
				$query = substr($query, 0, -2) . ") = (" . (!empty($_POST['password'])
						? '\'' . password_hash($_POST['password'], PASSWORD_DEFAULT) . '\', ' : '');
				for ($i = $_SESSION['is_root'] ? 0 : 1; $i < 16; $i++) {
					$query .= pgescapebool($_POST[$user_fields[$i]]) . ', ';
				}
				pgquery(substr($query, 0, -2) . ") WHERE username = $s_username;");
				echo "User $h_username updated.<br/>\n";
			}
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
$result1 = pgquery("SELECT username, TRUE, is_administrator, $joined_fields FROM users
		WHERE username = {$_SESSION['s_username']};");
if ($_SESSION['is_root']) {
	$result2 = pgquery("SELECT * FROM users WHERE username <> 'root'
			ORDER BY is_administrator DESC, username ASC;");
?>
	Viewing table &quot;users&quot;, administrators first.
<?php
} else if ($_SESSION['is_administrator']) {
	$result2 = pgquery("SELECT username, TRUE, is_administrator, $joined_fields FROM users
			WHERE NOT is_administrator AND username <> {$_SESSION['s_username']}
			ORDER BY username ASC;");
	echo "Viewing table &quot;users&quot; for username {$_SESSION['h2username']}
			and non-administrators.\n";
} else {
	echo "Viewing table &quot;users&quot; for username {$_SESSION['h2username']}.\n";
}
?>
<table border="1">
	<tbody>
		<tr>
			<th>Username</th>
			<th>New password?</th>
<?php
			for ($field : $user_fields) {
				echo '<th>', ucfirst(str_replace($field, '_', ' ')), "</th>\n";
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
				for ($field : $user_fields) {
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
?>
		<tr>
			<td>
<?php
				$row = pg_fetch_row($result1);
				echo '<input type="text" value="', htmlspecialchars($row[0]),
						"\" disabled=\"disabled\"/>\n";
?>
			</td>
			<td>
				<input form="update2" type="password" name="password"/>
			</td>
<?php
			for ($i = 2; $i < 18; $i++) {
?>
				<td>
<?php
					echo '<input type="checkbox"', $row[$i] == 't' ? ' checked="checked"' : '',
							" disabled=\"disabled\"/>\n";
?>
				</td>
<?php
			}
?>
			<td>
				<form id="update2" action="?" method="POST">
					<input type="submit" name="update2" value="UPDATE"/>
				</form>
			</td>
		</tr>
<?php
		if ($_SESSION['is_administrator']) {
			for ($row = pg_fetch_row($result2); $row; $row = pg_fetch_row($result2)) {
?>
				<tr>
					<td>
<?php
						$username = htmlspecialchars($row[0]);
						echo "<input type=\"text\" value=\"$username\" disabled=\"disabled\"/>\n";
						echo "<input form=\"update1_$username\" type=\"hidden\" name=\"username\"
								value=\"$username\"/>\n";
?>
					</td>
					<td>
<?php
						echo "<input form=\"update1_$username\" type=\"text\"
								name=\"password\"/>\n";
?>
					</td>
<?php
					for ($i = 2; $i < 18; $i++) {
?>
						<td>
<?php
							echo "<input form=\"update1_$username\" type=\"checkbox\" name=\"",
									pg_field_name($result2, $i), '"', $row[$i] == 't'
									? ' checked="checked"' : '', !$_SESSION['is_root'] && $i == 2
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
		}
?>
	</tbody>
</table>
Write username as a string, e.g., root.<br/>
<a href="index.php">Done</a>