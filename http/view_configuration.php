<?php
require_once 'common.php';
if (checkAuthorization(9, 'view configuration') {
	$result = pgquery("SELECT TRUE FROM users WHERE username = '{$_GET['username']}' AND is_administrator IS FALSE;");
	if (isset($_GET['update']) && !empty($_GET['user']) && ($_GET['user'] == $_SESSION['username'] || $_SESSION['is_administrator'] && pg_fetch_row($result) || $_SESSION['is_root'])) {
		pg_free_result(pgquery('UPDATE configuration SET (forward_messages, use_internet_switch_algorithm, nsecs_id, nsecs_src, trust_everyone_for_sending, trust_everyone_for_receiving, default_gateway_proto_id, default_gateway_imm_DST) = (' . (isset($_GET['forward_messages']) ? 'TRU' : 'FALS') . 'E, ' . (isset($_GET['use_internet_switch_algorithm']) ? 'TRU' : 'FALS') . "E, {$_GET['nsecs_id']}, {$_GET['nsecs_src']}, " . (isset($_GET['trust_everyone_for_sending']) ? 'TRU' : 'FALS') . 'E, ' . (isset($_GET['trust_everyone_for_receiving']) ? 'TRU' : 'FALS') . "E, {$_GET['default_gateway_proto_id'}, E\'\\\\x" . substr($_GET['default_gateway_imm_DST'], 2) . "') WHERE user = '{$_GET['user']}';"));
		pg_free_result(pgquery('SELECT config();'));
		echo 'Configuration updated for user ', htmlspecialchars($_GET['user']), ".\n";
	}
	pg_free_result($result);
	if ($_SESSION['is_root']) {
		$result = pgquery('TABLE configuration ORDER BY user ASC;');
?>
		Viewing table &quot;configuration&quot;.
<?php
	} else if ($_SESSION['is_administrator']) {
		$result = pgquery("SELECT configuration.* FROM configuration INNER JOIN users ON configuration.user = users.username WHERE NOT users.is_administrator OR configuration.user = '{$_SESSION['username']}' ORDER BY users.administrator DESC, configuration.user ASC;");
		echo 'Viewing table &quot;configuration&quot; for user ', htmlspecialchars($_SESSION['username']), " and non-administrators.\n";
	} else {
		$result = pgquery("SELECT * FROM configuration WHERE user = '{$_SESSION['username']}';");
		echo 'Viewing table &quot;configuration&quot; for user ', htmlspecialchars($_SESSION['username']), ".\n";
	}
?>
	<table border="1">
		<tbody>
			<tr>
				<th>forward messages</th>
				<th>use internet switch algorithm</th>
				<th>duplicate expiration in seconds</th>
				<th>address expiration in seconds</th>
				<th>trust everyone for sending</th>
				<th>trust everyone for receiving</th>
				<th>default gateway proto_id</th>
				<th>default gateway imm_DST</th>
				<th>user</th>
				<th>Actions</th>
			</tr>
<?php
			for ($row = pg_fetch_row($result); $row; $row = pg_fetch_row($result)) {
?>
				<tr>
					<td>
<?php
						echo '<input form="update" type="checkbox" name="forward_messages"', $row[0] == 't' ? ' checked="checked"' : '', "/>\n";
?>
					</td>
					<td>
<?php
						echo '<input form="update" type="checkbox" name="use_internet_switch_algorithm"', $row[1] == 't' ? ' checked="checked"' : '', "/>\n";
?>
					</td>
					<td>
<?php
						echo '<input form="update" type="text" name="nsecs_id" value="', $row[2], "\"/>\n";
?>
					</td>
					<td>
<?php
						echo '<input form="update" type="text" name="nsecs_src" value="', $row[3], "\"/>\n";
?>
					</td>
					<td>
<?php
						echo '<input form="update" type="checkbox" name="trust_everyone_for_sending"', $row[4] == 't' ? ' checked="checked"' : '', "/>\n";
?>
					</td>
					<td>
<?php
						echo '<input form="update" type="checkbox" name="trust_everyone_for_receiving"', $row[5] == 't' ? ' checked="checked"' : '', "/>\n";
?>
					</td>
					<td>
<?php
						echo '<input form="update" type="text" name="default_gateway_proto_id" value="&apos;', htmlspecialchars($row[6]), "&apos;\"/>\n";
?>
					</td>
					<td>
<?php
						echo '<input form="update" type="text" name="default_gateway_imm_DST" value="X&apos;', substr($row[7], 2), "&apos;\"/>\n";
?>
					</td>
					<td>
<?php
						echo '<input form="update" type="text" name="user" value="&apos;', $row[8], "&apos;\" disabled=\"disabled\"/>\n";
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
<?php
	pg_free_result($result);
?>
	<a href="index.php">Done</a>
<?php
}
?>