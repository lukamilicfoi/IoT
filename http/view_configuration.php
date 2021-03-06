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
				pg_free_result(pgquery('UPDATE configuration SET (forward_messages, use_internet_switch_algorithm, nsecs_id, nsecs_src) = (' . (isset($_GET['forward_messages']) ? 'TRU' : 'FALS') . 'E, ' . (isset($_GET['use_internet_switch_algorithm']) ? 'TRU' : 'FALS') . "E, {$_GET['nsecs_id']}, {$_GET['nsecs_src']});"));
				pg_free_result(pgquery('SELECT config();'));
?>
				Configuration updated.<br/>
<?php
			}
			if (isset($_GET['update2'])) {
				pg_free_result(pgquery('UPDATE proto_name SET (C, A) = (' . (isset($_GET['C']) ? 'TRU' : 'FALS') . 'E, ' . (isset($_GET['A']) ? 'TRU' : 'FALS') . "E) WHERE name = '{$_GET['name']}';"));
			}
?>
			Changing protocols takes effect on next executable run.
<?php
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
							<form id="update" action="" method="GET">
								<input type="submit" name="update" value="UPDATE"/>
								<input type="reset" value="reset"/>
							</form>
						</td>
					</tr>
				</tbody>
			</table>
			Viewing table &quot;proto_name&quot;.
			<table>
				<tbody>
					<tr>
						<th>protocol</th>
						<th>C</th>
						<th>A</th>
						<th>Actions</th>
					</tr>
<?php
					$result = pgquery('SELECT name FROM proto_name;');
					for ($row = pg_fetch_row($result); $row; $row = pg_fetch_row($result)) {
?>
						<tr>
							<td>
<?php
								echo "<input form=\"update{$row[0]}\" type=\"text\" name=\"protocol\" value=\"{$row[0]}\" disabled=\"disabled\"/>\n";
?>
							</td>
							<td>
<?php
								echo "<input form=\"update{$row[0]}\" type=\"checkbox\" name=\"C\"", $row[2] == 't' ? ' checked="checked"' : '', "/>\n";
?>
							</td>
							<td>
<?php
								echo "<input form=\"update{$row[0]}\" type=\"checkbox\" name=\"A\"", $row[3] == 't' ? ' checked="checked"' : '', "/>\n";
?>
							</td>
							<td>
<?php
								echo "<form id=\"update{$row[0]}\" action=\"\" method=\"GET\">\n";
?>
									<input type="submit" name="update" value="UPDATE"/>
									<input type="reset" value="reset"/>
<?php
								echo "</form>\n";
?>
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
<?php
}
?>