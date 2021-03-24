<?php
require_once 'common.php';
checkLogin();
if ($_SESSION['user'] != 'admin') {
	http_response_code(403);
} else {
?>
	<!DOCTYPE html>
	<html>
		<head>
			<title>View configuration (as administrator)</title>
			<meta http-equiv="Content-Type" content="text/html; charset=utf-8"/>
		</head>
		<body>
<?php
			$dbconn = pgconnect('host=localhost dbname=postgres user=postgres client_encoding=UTF8');
			if (isset($_GET['update'])) {
				pg_free_result(pgquery('UPDATE configuration SET (forward_messages, use_internet_switch_algorithm, nsecs_id, nsecs_src, trust_everyone, default_gateway) = (' . (isset($_GET['forward_messages']) ? 'TRU' : 'FALS') . 'E, ' . (isset($_GET['use_internet_switch_algorithm']) ? 'TRU' : 'FALS') . "E, {$_GET['nsecs_id']}, {$_GET['nsecs_src']}, " . (isset($_GET['trust_everyone']) ? 'TRU' : 'FALS') . 'E, E\'\\\\x' . substr($_GET['default_gateway'], 2) . ');'));
				pg_free_result(pgquery('SELECT config();'));
?>
				Configuration updated.<br/>
<?php
			}
			$result = pgquery('TABLE configuration;');
			$row = pg_fetch_row($result);
?>
			Viewing table &quot;configuration&quot;.
			<table border="1">
				<tbody>
					<tr>
						<th>forward messages</th>
						<th>use internet switch algorithm</th>
						<th>duplicate expiration in seconds</th>
						<th>address expiration in seconds</th>
						<th>trust everyone</th>
						<th>default gateway</th>
						<th>Actions</th>
					</tr>
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
							echo "<input form=\"update\" type=\"text\" name=\"nsecs_id\" value=\"{$row[2]}\"/>\n";
?>
						</td>
						<td>
<?php
							echo "<input form=\"update\" type=\"text\" name=\"nsecs_src\" value=\"{$row[3]}\"/>\n";
?>
						</td>
						<td>
<?php
							echo '<input form="update" type="checkbox" name="trust_everyone"', $row[4] == 't' ? ' checked="checked"' : '', "/>\n";
?>
						</td>
						<td>
<?php
							echo '<input form="update" type="text" name="default_gateway" value="X\'', substr($row[5], 2), "'\"/>\n";
?>
						</td>
						<td>
							<form id="update" action="" method="GET">
								<input type="submit" name="update" value="UPDATE"/>
								<input type="reset" value="reset"/>
							</form>
						</td>
					</tr>
				</tbody>
			</table>
<?php
			pg_free_result($result);
			pg_close($dbconn);
?>
			<a href="index.php">Done</a>
		</body>
	</html>
<?php
}
?>