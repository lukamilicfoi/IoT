<?php
require_once 'common.php';
$user_fields_joined = implode(', ', $user_fields);
$user_fields_length = count($user_fields);
$can_view_yourself = check_authorization('can_view_yourself', 'view yourself');
$s_can_view_yourself = $can_view_yourself ? 'TRUE' : 'FALSE';
$can_edit_yourself = check_authorization('can_edit_yourself', 'edit yourself');
$s_can_edit_yourself = $can_edit_yourself ? 'TRUE' : 'FALSE';
$can_view_others = check_authorization('can_view_others', 'view others');
$s_can_view_others = $can_view_others ? 'TRUE' : 'FALSE';
$can_edit_others = check_authorization('can_edit_others', 'edit others');
$s_can_edit_others = $can_edit_others ? 'TRUE' : 'FALSE';
if ($can_edit_others) {
	if (isset($_GET['truncate']) && $_SESSION['is_root']) {
		if (isset($_GET['confirm'])) {
			$result = pgquery('SELECT username FROM users
					WHERE username <> \'root\' AND username <> \'public\';');
			for ($row = pg_fetch_row($result); $row; $row = pg_fetch_row($result)) {
				pgquery('DROP OWNED BY ' . pgescapeusername2($row[0]) . ' CASCADE;');
			}
			pgquery('DELETE FROM users WHERE username <> \'root\' AND username <> \'public\';');
?>
			Table &quot;users&quot; truncated except for users &apos;root&apos;
					and &apos;public&apos;.<br/>
<?php
		} else {
?>
			Are you sure?
			<a href="?truncate&amp;confirm">Yes</a>
			<a href="?">No</a>
<?php
			exit(0);
		}
	} elseif (!vacuous($_POST['username'])) {
		$s1username = pg_escape_literal($_POST['username']);
		$s2username = pgescapeusername2($_POST['username']);
		$h_username = '&apos;' . htmlspecialchars($_POST['username']) . '&apos;';
		if (($_SESSION['is_administrator'] && !isset($_POST['is_administrator'])
				|| $_SESSION['is_root']) && isset($_POST['insert'])) {
			$query = "INSERT INTO users(username, password, is_administrator, $user_fields_joined,
					can_actually_login) VALUES($s1username, '" . password_hash($_POST['password'],
					PASSWORD_DEFAULT) . '\', ' . pgescapebool($_POST['is_administrator']) . ', ';
			foreach ($user_fields as $field) {
				$query .= pgescapebool($_POST[$field]) . ', ';
			}
			pgquery($query . pgescapebool($_POST['can_actually_login']) . ');');
			pgquery("INSERT INTO configuration(username, forward_messages,
					use_internet_switch_algorithm, nsecs_id, nsecs_src, trust_everyone,
					default_gateway, insecure_port, secure_port) SELECT $s1username,
					forward_messages, use_internet_switch_algorithm, nsecs_id, nsecs_src,
					trust_everyone, default_gateway, insecure_port, secure_port FROM configuration
					WHERE username = {$_SESSION['s_username']};");
			pgquery("CREATE ROLE $s2username;");
			pgquery("GRANT CREATE ON SCHEMA public TO $s2username;");
			echo "User $h_username inserted.<br/>\n";
		} elseif (!vacuous($_POST['key'])) {
			$s1key = pg_escape_literal($_POST['key']);
			$s2key = pgescapeusername2($_POST['username']);
			if (($_SESSION['is_administrator'] && !isset($_POST['is_administrator'])
					&& !is_administrator($s1username) || $_SESSION['is_root'])
					&& isset($_POST['update1']) && ($_POST['key'] != 'root'
					&& $_POST['key'] != 'public' || $_POST['key'] == $_POST['username'])) {
				$h_key = htmlspecialchars($_POST['key']);
				$query = 'UPDATE users SET (username' . (!vacuous($_POST['password']) ? ', password'
						: '') . ", is_administrator, $user_fields_joined, can_actually_login)
						= ($s1username" . (!vacuous($_POST['password']) ? ', \''
						. password_hash($_POST['password'], PASSWORD_DEFAULT) . '\'' : '') . ', ' .
						pgescapebool($_POST['is_administrator']) . ', ';
				foreach ($user_fields as $field) {
					$query .= pgescapebool($_POST[$field]) . ', ';
				}
				pgquery($query . pgescapebool($_POST['can_actually_login'])
						. ") WHERE username = $s1key;");
				if ($s2key != $s2username) {
					pgquery("ALTER ROLE $s2key RENAME TO $s2username;");
				}
				echo "User $h_key updated.<br/>\n";
			}
		}
	} elseif (!vacuous($_GET['key'])) {
		$s1key = pg_escape_literal($_GET['key']);
		$s2key = pgescapeusername2($_GET['key']);
		$h_key = '&apos;' . htmlspecialchars($_GET['key']) . '&apos;';
		$u_key = urlencode($_GET['key']);
		if (($_SESSION['is_administrator'] && !is_administrator($s1key) || $_SESSION['is_root'])
				&& isset($_GET['delete']) && $_GET['key'] != 'root' && $_GET['key'] != 'public') {
			if (isset($_GET['confirm'])) {
				pgquery("DELETE FROM users WHERE username = $s1key;");
				pgquery("DROP OWNED BY $s2key CASCADE;");
				pgquery("DELETE FROM table_owner WHERE username = $s1key;");
				pgquery("DROP ROLE $s2key;");
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
	}
}
if ($can_edit_yourself && isset($_POST['update2']) && !vacuous($_POST['username'])) {
	$s1username = pg_escape_literal($_POST['username']);
	$s2username = pgescapeusername2($_POST['username']);
	$h_username = '&apos;' . htmlspecialchars($_POST['username']) . '&apos;';
	pgquery('UPDATE users SET (username' . (!vacuous($_POST['password']) ? ', password' : '')
			. ') = ROW(' . pg_escape_literal($_POST['username']) . (!vacuous($_POST['password'])
			? ', \'' . password_hash($_POST['password'], PASSWORD_DEFAULT) . '\'' : '')
			. ") WHERE username = {$_SESSION['s_username']};");
	if ($_SESSION['s2username'] != $s2username) {
		pgquery("ALTER ROLE {$_SESSION['s2username']} RENAME TO $s2username;");
	}
	echo "User $h_username updated.<br/>\n";
}
if ($can_view_yourself || $can_view_others) {
	if ($_SESSION['is_root']) {
		$result = pgquery("SELECT *, TRUE FROM users ORDER BY username ASC;");
?>
		You are authorized to view (edit) all users.<br/>
<?php
	} elseif ($_SESSION['is_administrator']) {
		$result = pgquery("SELECT username, TRUE, is_administrator, $user_fields_joined,
				can_actually_login, username = {$_SESSION['s_username']} AND $s_can_edit_yourself
				OR NOT is_administrator AND $s_can_edit_others FROM users
				WHERE username = {$_SESSION['s_username']} AND $s_can_view_yourself
				OR NOT is_administrator AND $s_can_view_others ORDER BY username ASC;");
		echo 'You are authorized to view', $can_edit_yourself ? ' (edit)' : '',
				" username {$_SESSION['h2username']}", $can_view_others ? ' or view' : '',
				$can_edit_others ? ' (edit)' : '', $can_view_others ? ' non-administrators'
				: '', ".<br/>\n";
	} elseif ($_SESSION['is_public']) {
		$result = pgquery("SELECT *, $s_can_edit_yourself FROM users WHERE username = 'public';");
?>
		You are authorized to view (edit) public user.
<?php
	} else {
		$result = pgquery("SELECT username, TRUE, is_administrator, $user_fields_joined,
				can_actually_login, username = {$_SESSION['s_username']} AND $s_can_edit_yourself
				OR username = 'public' AND $s_can_edit_others FROM users
				WHERE username = {$_SESSION['s_username']} AND $s_can_view_yourself
				OR username = 'public' AND $s_can_view_others ORDER BY username ASC;");
		echo 'You are authorized to view', $can_edit_yourself ? ' (edit)' : '',
				" username {$_SESSION['h2username']}", $can_view_others ? ' or view' : '',
				$can_edit_others ? ' (edit)' : '', $can_view_others ? ' public user'
				: '', ".<br/>\n";
	}
?>
	Viewing table &quot;users&quot;.<br/>
	Table ordered by username ascending.<br/><br/>
	<table border="1">
		<tbody>
			<tr>
				<th>Username</th>
				<th>New password?</th>
				<th>Is administrator?</th>
<?php
				foreach ($user_fields as $field) {
					echo '<th>', ucfirst(strtr($field, '_', ' ')), "?</th>\n";
				}
?>
				<th>Can actually login?</th>
				<th>Actions</th>
			</tr>
<?php
			if ($_SESSION['is_administrator'] && $can_edit_others) {
?>
				<tr>
					<td>
						<input form="insert" type="text" name="username" required autofocus/>
					</td>
					<td>
						<input form="insert" type="password" name="password" required
								autocomplete="new-password"/>
					</td>
					<td>
<?php
						if ($_SESSION['is_root']) {
?>
							<input form="insert" type="checkbox" name="is_administrator"/>
<?php
						} else {
?>
							<input type="checkbox" disabled/>
<?php
						}
?>
					</td>
<?php
					foreach ($user_fields as $field) {
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
						<input form="insert" type="checkbox" name="can_actually_login"/>
					</td>
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
						if ($username == $_SESSION['h1username'] && $can_edit_yourself) {
							echo "<input form=\"update2\" type=\"text\" name=\"username\"
									value=\"$username\" ", $username == 'root' ? 'readonly' : 'required', " autofocus/>\n";
						} elseif ($username != $_SESSION['h1username'] && $can_edit_others) {
							echo "<input form=\"update1_$username\" type=\"text\"
									name=\"username\" value=\"$username\" ", $username == 'public'
									? 'readonly' : 'required', "/>\n";
						} else {
							echo "<input type=\"text\" value=\"$username\" disabled/>\n";
						}
?>
					</td>
					<td>
<?php
						if ($username == $_SESSION['h1username'] && $can_edit_yourself) {
							echo "<input form=\"update2\" type=\"password\" name=\"password\"
									autocomplete=\"new-password\"/>\n";
						} elseif ($username != $_SESSION['h1username'] && $can_edit_others) {
							echo "<input form=\"update1_$username\" type=\"password\"
									name=\"password\" autocomplete=\"new-password\"/>\n";
						} else {
?>
							<input type="password" disabled/>
<?php
						}
?>
					</td>
					<td>
<?php
						if ($_SESSION['is_root'] && $username != 'root' && $username != 'public') {
							echo "<input form=\"update1_$username\" type=\"checkbox\"
									name=\"is_administrator\"",
									$row[2] == 't' ? ' checked' : '', "/>\n";
						} else {
							echo "<input type=\"checkbox\"", $row[2] == 't' ? ' checked' : '',
									" disabled/>\n";
						}
?>
					</td>
<?php
					for ($i = 0; $i < $user_fields_length; $i++) {
?>
						<td>
<?php
							echo "<input form=\"update1_$username\" type=\"checkbox\"
									name=\"{$user_fields[$i]}\"", $row[$i + 3] == 't'
									? ' checked' : '', $can_edit_others && $username
									!= $_SESSION['h1username'] ? '' : ' disabled', "/>\n";
?>
						</td>
<?php
					}
?>
					<td>
<?php
						echo "<input form=\"update1_$username\" type=\"checkbox\"
								name=\"can_actually_login\"", $row[$user_fields_length + 3] == 't'
								? ' checked' : '', $can_edit_others && $username
								!= $_SESSION['h1username'] ? '' : ' disabled', "/>\n";
?>
					</td>
					<td>
<?php
						if ($username == $_SESSION['h1username'] && $can_edit_yourself) {
?>
							<form id="update2" action="?" method="POST">
								<input type="submit" name="update2" value="UPDATE"/><br/>
								<input type="reset" value="reset"/>
							</form>
<?php
						} elseif ($username != $_SESSION['h1username'] && $can_edit_others) {
							echo "<form id=\"update1_$username\" action=\"?\" method=\"POST\">\n";
								echo "<input type=\"hidden\" name=\"key\" value=\"$username\"/>\n";
?>
								<input type="submit" name="update1" value="UPDATE"/><br/>
								<input type="reset" value="reset"/>
<?php
							echo "</form>\n";
							if ($username != 'public') {
?>
								<form action="?" method="GET">
<?php
									echo "<input type=\"hidden\" name=\"key\" value=\"$username\"/>\n";
?>
									<input type="submit" name="delete" value="DELETE"/>
								</form>
<?php
							}
						}
?>
					</td>
				</tr>
<?php
			}
?>
		</tbody>
	</table>
	<br/>Users &apos;public&apos; and &apos;root&apos; cannot be renamed or deleted.<br/>
	Deleting a user also deletes his tables.<br/>
	Only root can delete all users.<br/>
	<a href="index.php">Done</a>
<?php
}
?>