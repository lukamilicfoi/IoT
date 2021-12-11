<?php
require_once 'common.php';
$can_view_configuration = checkAuthorization(10, 'view configuration');
$can_edit_configuration = checkAuthorization(11, 'edit configuration');
if ($can_edit_configuration && isset($_GET['update']) && !empty($_GET['username'])) {
	$s_username = pg_escape_literal($_GET['username']);
	$h_username = '&apos;' . htmlspecialchars($_GET['username']) . '&apos;';
	if ($_GET['username'] == $_SESSION['username'] || $_SESSION['is_administrator']
			&& !is_administrator($s_username) || $_SESSION['is_root']) {
		pgquery('UPDATE configuration SET (forward_messages, use_internet_switch_algorithm,
				nsecs_id, nsecs_src, trust_everyone, default_gateway) = ('
				. pgescapebool($_GET['forward_messages']) . ', '
				. pgescapebool($_GET['use_internet_switch_algorithm']) . ', '
		   		. intval($_GET['nsecs_id']) . ', '. intval($_GET['nsecs_src']) . ', '
		   		. pgescapebool($_GET['trust_everyone']) . ', '
		   		. pgescapebytea($_GET['default_gateway']) . ") WHERE username = $s_username;");
		pgquery('CALL config();');
		echo "Configuration updated for username $h_username.<br/>\n";
	}
}
if ($can_view_configuration) {
	if ($_SESSION['is_root']) {
		$result = pgquery('SELECT configuration.* FROM configuration
				INNER JOIN users ON configuration.username = users.username
				ORDER BY users.is_administrator DESC, configuration.username ASC;');
?>
		Viewing table &quot;configuration&quot;, administrators first.
<?php
	} else if ($_SESSION['is_administrator']) {
		$result = pgquery("SELECT configuration.* FROM configuration INNER JOIN users
				ON configuration.username = users.username WHERE NOT users.is_administrator
				OR configuration.username = {$_SESSION['s_username']}
				ORDER BY users.is_administrator DESC, configuration.username ASC;");
		echo "Viewing table &quot;configuration&quot; for username {$_SESSION['h2username']}
				and non-administrators.\n";
	} else {
		$result = pgquery("SELECT * FROM configuration
				WHERE username = {$_SESSION['s_username']};");
		echo "Viewing table &quot;configuration&quot; for username {$_SESSION['h2username']}.\n";
	}
?>
	<table border="1">
		<tbody>
			<tr>
				<th>Username</th>
				<th>Forward messages?</th>
				<th>Use internet switch algorithm?</th>
				<th>Duplicate expiration in seconds</th>
				<th>Address expiration in seconds</th>
				<th>Trust everyone?</th>
				<th>Default gateway</th>
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
						echo "<input type=\"text\" value=\"$username\" disabled=\"disabled\"/>\n";
						echo "<input form=\"update_$username\" type=\"hidden\" name=\"username\"
								value=\"$username\"/>\n";
?>
					</td>
					<td>
<?php
						echo "<input form=\"update_$username\" type=\"checkbox\"
								name=\"forward_messages\"",
								$row[1] == 't' ? ' checked="checked"' : '', "/>\n";
?>
					</td>
					<td>
<?php
						echo "<input form=\"update_$username\" type=\"checkbox\"
								name=\"use_internet_switch_algorithm\"",
								$row[2] == 't' ? ' checked="checked"' : '', "/>\n";
?>
					</td>
					<td>
<?php
						echo "<input form=\"update_$username\" type=\"text\" name=\"nsecs_id\"
								value=\"{$row[3]}\"/>\n";
?>
					</td>
					<td>
<?php
						echo "<input form=\"update_$username\" type=\"text\" name=\"nsecs_src\"
								value=\"{$row[4]}\"/>\n";
?>
					</td>
					<td>
<?php
						echo "<input form=\"update_$username\" type=\"checkbox\"
								name=\"trust_everyone\"",
								$row[5] == 't' ? ' checked="checked"' : '', "/>\n";
?>
					</td>
					<td>
<?php
						echo "<input form=\"update_$username\" type=\"text\"
								name=\"default_gateway\" value=\"",
								substr($row[6], 2), "\"/>\n";
?>
					</td>
<?php
					if ($can_edit_configuration) {
?>
						<td>
<?php
							echo "<form id=\"update_$username\" action=\"\" method=\"GET\">\n";
?>
								<input type="submit" name="update" value="UPDATE"/>
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
	Write default gateway as a binary string, e.g., abababababababab.<br/>
	<a href="index.php">Done</a>
<?php
}
?>