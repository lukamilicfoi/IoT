<?php
require_once 'common.php';
$can_view_configuration = check_authorization('can_view_configuration', 'view configuration');
$can_edit_configuration = check_authorization('can_edit_configuration', 'edit configuration');
if ($can_edit_configuration && !empty($_GET['username']) && isset($_GET['update'])) {
	$s_username = pg_escape_literal($_GET['username']);
	$h_username = '&apos;' . htmlspecialchars($_GET['username']) . '&apos;';
	if ($_GET['username'] == $_SESSION['username'] || ($_GET['username'] == 'public'
			|| $_SESSION['is_administrator'] && !is_administrator($s_username))
			&& $_SESSION['can_edit_as_others'] || $_SESSION['is_root']) {
		pgquery('UPDATE configuration SET (forward_messages, use_lan_switch_algorithm, nsecs_id,
				nsecs_src, trust_sending, default_gateway, my_eui, insecure_port, secure_port) = ('
				. formescapebool($_GET['forward_messages']) . ', '
				. formescapebool($_GET['use_lan_switch_algorithm']) . ', '
				. formescapeinteger($_GET['nsecs_id']) . ', '
				. formescapeinteger($_GET['nsecs_src']) . ', '
				. formescapeinteger($_GET['insecure_port']) . ', '
				. formescapeinteger($_GET['secure_port']) . ', '
				. formescapebool($_GET['trust_sending']) . ', '
				. formescapebool($_GET['trust_receiving']) . ', '
		   		. formescapebytea($_GET['default_gateway']) . ', '
				. formescapebytea($_GET['my_eui']) . ") WHERE username = $s_username;");
		pgquery('CALL config();');
		echo "Configuration updated for username $h_username.<br/>\n";
	}
}
if ($can_view_configuration) {
	if ($_SESSION['is_root']) {
		$result = pgquery('SELECT *, TRUE FROM configuration ORDER BY username ASC;');
?>
		You are authorized to view (edit) configuration for all users.<br/>
<?php
	} elseif ($_SESSION['is_administrator']) {
		$result = pgquery("SELECT configuration.*, configuration.username
				= {$_SESSION['s_username']} OR NOT users.is_administrator
				AND {$_SESSION['s_can_edit_as_others']} FROM configuration INNER JOIN users
				ON configuration.username = users.username WHERE configuration.username
				= {$_SESSION['s_username']} OR NOT users.is_administrator
				AND {$_SESSION['s_can_view_as_others']} ORDER BY configuration.username ASC;");
		echo 'You are authorized to view', $can_edit_configuration ? ' (edit)' : '',
				" configuration for username {$_SESSION['h2username']}", $can_view_as_others
				? ' or non-administrators' : '', $_SESSION['can_edit_as_others']
				&& $can_edit_configuration ? '' : ' (noedit)', ".<br/>\n";
	} elseif ($_SESSION['is_public']) {
		$result = pgquery('SELECT *, TRUE FROM configuration WHERE username = \'public\';');
		echo 'You are authorized to view', $can_edit_configuration ? ' (edit)' : '',
				" configuration for public user.<br/>\n";
	} else {
		$result = pgquery("SELECT *, username = {$_SESSION['s_username']} OR username = 'public'
				AND {$_SESSION['s_can_edit_as_others']} FROM configuration WHERE username
				= {$_SESSION['s_username']} OR username = 'public'
				AND {$_SESSION['s_can_view_as_others']} ORDER BY username ASC;");
		echo 'You are authorized to view', $can_edit_configuration ? ' (edit)' : '',
				" configuration for username {$_SESSION['h2username']}",
				$_SESSION['can_view_as_others'] ? ' or public user' : '',
				$_SESSION['can_edit_as_others'] && $can_edit_configuration ? '' : ' (noedit)',
				".<br/>\n";
	}
?>
	Viewing table &quot;configuration&quot;.<br/>
	Table ordered by username ascending.<br/><br/>
	<table border="1">
		<tbody>
			<tr>
				<th>Username</th>
				<th>Forward messages?</th>
				<th>Use LAN switch algorithm?</th>
				<th>Duplicate expiration in seconds</th>
				<th>Address expiration in seconds</th>
				<th>Trust sending?</th>
				<th>Trust receiving?</th>
				<th>Default gateway</th>
				<th>My EUI</th>
				<th>Insecure port</th>
				<th>Secure port</th>
<?php
				if ($can_edit_configuration) {
?>
					<th>Actions</th>
<?php
				}
?>
			</tr>
<?php
			for ($row = pg_fetch_row($result); $row; $row = pg_fetch_row($result)) {
?>
				<tr>
					<td>
<?php
						$username = htmlspecialchars($row[0]);
						echo "<input form=\"update_$username\" type=\"text\" name=\"username\"
								value=\"$username\" readonly autofocus/>\n";
?>
					</td>
					<td>
<?php
						echo "<input form=\"update_$username\" type=\"checkbox\"
								name=\"forward_messages\"",
								$row[1] == 't' ? ' checked' : '', "/>\n";
?>
					</td>
					<td>
<?php
						echo "<input form=\"update_$username\" type=\"checkbox\"
								name=\"use_lan_switch_algorithm\"",
								$row[2] == 't' ? ' checked' : '', "/>\n";
?>
					</td>
					<td>
<?php
						echo "<input form=\"update_$username\" type=\"text\" name=\"nsecs_id\"
								value=\"", is_null($row[3]) ? '' : $row[3], "\" size=\"8\"/>\n";
?>
					</td>
					<td>
<?php
						echo "<input form=\"update_$username\" type=\"text\" name=\"nsecs_src\"
								value=\"", is_null($row[4]) ? '' : $row[4], "\" size=\"8\"/>\n";
?>
					</td>
					<td>
<?php
						echo "<input form=\"update_$username\" type=\"checkbox\"
								name=\"trust_sending\"", $row[5] == 't' ? ' checked' : '', "/>\n";
?>
					</td>
					<td>
<?php
						echo "<input form=\"update_$username\" type=\"checkbox\"
								name=\"trust_receiving\"", $row[6] == 't' ? ' checked' : '', "/>\n";
?>
					</td>
					<td>
<?php
						echo "<input form=\"update_$username\" type=\"text\"
								name=\"default_gateway\" value=\"",
								is_null($row[7]) ? '' : substr($row[7], 2), "\" size=\"16\"/>\n";
?>
					</td>
					<td>
<?php
						echo "<input form=\"update_$username\" type=\"text\"
								name=\"my_eui\" value=\"",
								is_null($row[8]) ? '' : substr($row[8], 2), "\" size=\"16\"/>\n";
?>
					</td>
					<td>
<?php
						echo "<input form=\"update_$username\" type=\"text\" name=\"insecure_port\"
								value=\"", is_null($row[9]) ? '' : $row[9], "\" size=\"8\"/>\n";
?>
					</td>
					<td>
<?php
						echo "<input form=\"update_$username\" type=\"text\" name=\"secure_port\"
								value=\"", is_null($row[10]) ? '' : $row[10], "\" size=\"8\"/>\n";
?>
					</td>
<?php
					if ($can_edit_configuration && $row[11] == 't') {
?>
						<td>
<?php
							echo "<form id=\"update_$username\" action=\"\" method=\"GET\">\n";
?>
								<input type="submit" name="update" value="UPDATE"/><br/>
								<input type="reset" value="reset"/>
<?php
							echo "</form>\n";
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
	<br/>Write gateway (empty if unused) and EUI (empty if default) as a binary string, e.g., abababababababab.<br/>
	Write numbers as an integer, e.g., 11.<br/>
	Empty field indicates NULL value.<br/><br/>
	<a href="index.php">Done</a>
<?php
}
?>