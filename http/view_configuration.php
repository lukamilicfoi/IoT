<?php
require_once 'common.php';
if (checkAuthorization(8, 'view configuration')) {
	$result = pgquery("SELECT TRUE FROM users WHERE username = '{$_SESSION['username']}' AND NOT is_administrator;");
	if (isset($_GET['update']) && !empty($_GET['username']) && ($_GET['username'] == $_SESSION['username'] || $_SESSION['is_administrator'] && pg_fetch_row($result) || $_SESSION['is_root'])) {
		pg_free_result(pgquery('UPDATE configuration SET (forward_messages, use_internet_switch_algorithm, nsecs_id, nsecs_src, trust_everyone, default_gateway) = (' . (isset($_GET['forward_messages']) ? 'TRU' : 'FALS') . 'E, ' . (isset($_GET['use_internet_switch_algorithm']) ? 'TRU' : 'FALS') . "E, {$_GET['nsecs_id']}, {$_GET['nsecs_src']}, " . (isset($_GET['trust_everyone']) ? 'TRU' : 'FALS') . "E, E'\\\\x{$_GET['default_gateway']}') WHERE username = '{$_GET['username']}';"));
		pg_free_result(pgquery('CALL config();'));
		echo 'Configuration updated for username \'', htmlspecialchars($_GET['username']), "'.<br/>\n";
	}
	pg_free_result($result);
	if ($_SESSION['is_root']) {
		$result = pgquery('TABLE configuration ORDER BY username ASC;');
?>
		Viewing table &quot;configuration&quot;.
<?php
	} else if ($_SESSION['is_administrator']) {
		$result = pgquery("SELECT configuration.* FROM configuration INNER JOIN users ON configuration.username = users.username WHERE NOT users.is_administrator OR configuration.username = '{$_SESSION['username']}' ORDER BY users.is_administrator DESC, configuration.username ASC;");
		echo 'Viewing table &quot;configuration&quot; for username ', htmlspecialchars($_SESSION['username']), " and non-administrators.\n";
	} else {
		$result = pgquery("SELECT * FROM configuration WHERE username = '{$_SESSION['username']}';");
		echo 'Viewing table &quot;configuration&quot; for username ', htmlspecialchars($_SESSION['username']), ".\n";
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
				<th>Actions</th>
			</tr>
<?php
			for ($row = pg_fetch_row($result); $row; $row = pg_fetch_row($result)) {
?>
				<tr>
					<td>
<?php
						echo '<input type="text" value="', htmlspecialchars($row[0]), "\" disabled=\"disabled\"/>\n";
						echo '<input form="update" type="hidden" name="username" value="', htmlspecialchars($row[0]), "\"/>\n";
?>
					</td>
					<td>
<?php
						echo '<input form="update" type="checkbox" name="forward_messages"', $row[1] == 't' ? ' checked="checked"' : '', "/>\n";
?>
					</td>
					<td>
<?php
						echo '<input form="update" type="checkbox" name="use_internet_switch_algorithm"', $row[2] == 't' ? ' checked="checked"' : '', "/>\n";
?>
					</td>
					<td>
<?php
						echo '<input form="update" type="text" name="nsecs_id" value="', $row[3], "\"/>\n";
?>
					</td>
					<td>
<?php
						echo '<input form="update" type="text" name="nsecs_src" value="', $row[4], "\"/>\n";
?>
					</td>
					<td>
<?php
						echo '<input form="update" type="checkbox" name="trust_everyone"', $row[5] == 't' ? ' checked="checked"' : '', "/>\n";
?>
					</td>
					<td>
<?php
						echo '<input form="update" type="text" name="default_gateway" value="', strtoupper(substr($row[6], 2)), "\"/>\n";
?>
					</td>
					<td>
						<form id="update" action="" method="GET">
							<input type="submit" name="update" value="UPDATE"/>
							<input type="reset" value="reset"/>
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