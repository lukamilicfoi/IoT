<?php
require_once 'common.php';
checkLogin();
$admin = $_SESSION['user'] == 'admin';
?>
<!DOCTYPE html>
<html>
	<head>
<?php
		echo '<title>Index', $admin ? ' as administrator' : '', "</title>\n";
?>
		<meta http-equiv="Content-Type" content="text/html; charset=utf-8"/>
	</head>
	<body>
<?php
		$dbconn = pgconnect('host=localhost dbname=postgres user=' . ($admin ? 'postgres' : 'luka') . ' client_encoding=UTF8');
		if (checkAuthorization(2, 'view tables')) {
			$result = pgquery('SELECT relname FROM pg_catalog.pg_class WHERE relname LIKE \'t________________\' AND relname <> \'table_constraints\' ORDER BY relname ASC;');
?>
			View table:
<?php
			for ($row = pg_fetch_row($result); $row; $row = pg_fetch_row($result)) {
				echo "<a href=\"view_table.php?table={$row[0]}\">{$row[0]}</a>\n";
			}
			pg_free_result($result);
		}
?>
		<br/>
<?php
		if (checkAuthorization(6, 'view rules')) {
?>
			<a href="view_rules.php">View rules</a><br/>
<?php
		}
?>
		<a href="view_users.php">View users</a><br/>
<?php
		if (checkAuthorization(3, 'send messages')) {
			if (isset($_GET['msgtosend'])) {
				$result = pgquery('SELECT send_receive(E\'\\\\x' . substr($_GET['msgtosend'], 2) . ", '{$_GET['proto_id']}', E'\\\\x" . substr($_GET['imm_DST'], 2) . ', ' . (isset($_GET['CCF']) ? 'TRU' : 'FALS') . 'E, ' . (isset($_GET['ACF']) ? 'TRU' : 'FALS') . 'E, TRUE);');
				pg_free_result($result);
				echo 'Message ', htmlspecialchars($_GET['msgtosend']), " sent.\n";
			}
?>
			<form action="" method="GET">
				Send message
				<input type="text" name="msgtosend"/>
				using protocol id
				<input type="text" name="proto_id"/>
				and imm_DST
				<input type="text" name="imm_DST"/>
				using CCF
				<input type="checkbox" name="CCF"/>
				and ACF
				<input type="checkbox" name="ACF"/>
				<input type="submit" value="submit"/>
				<input type="reset" value="reset"/>
			</form>
<?php
		}
		if (checkAuthorization(4, '&quot;receive&quot; messages')) {
			if (isset($_GET['msgtoreceive'])) {
				$result = pgquery('SELECT send_receive(E\'\\\\x' . substr($_GET['msgtoreceive'], 2) . ", '{$_GET['proto_id']}', E'\\\\x" . substr($_GET['imm_SRC'], 2) . ', ' . (isset($_GET['CCF']) ? 'TRU' : 'FALS') . 'E, ' . (isset($_GET['ACF']) ? 'TRU' : ' FALS') . 'E, FALSE);');
				pg_free_result($result);
				echo 'Message ', htmlspecialchars($_GET['msgtoreceive']), " &quot;received&quot;.\n";
			}
?>
			<form action="" method="GET">
				&quot;Receive&quot; message
				<input type="text" name="msgtoreceive"/>
				using protocol id
				<input type="text" name="proto_id"/>
				and imm_SRC
				<input type="text" name="imm_SRC"/>
				using CCF
				<input type="checkbox" name="CCF"/>
				and ACF
				<input type="checkbox" name="ACF"/>
				<input type="submit" value="submit"/>
				<input type="reset" value="reset"/>
			</form>
<?php
		}
		if (checkAuthorization(5, 'send queries to database')) {
			if (isset($_GET['query'])) {
				if (!$admin) {
					$flock = fopen('/tmp/flock', 'c');
					if (!$flock) {
						exit('cannot fopen');
					}
					if (!flock($flock, LOCK_EX)) {
						exit('cannot flock');
					}
					$dbconn2 = pgconnect('host=localhost dbname=postgres user=postgres client_encoding=UTF8');
					pg_free_result(pgquery("UPDATE currentuser SET currentuser = {$_SESSION['user']};"));
					pg_close($dbconn2);
					pgconnect('host=localhost dbname=postgres user=luka client_encoding=UTF8');
				}
				$result = pgquery($_GET['query']);
				if (!$admin) {
					fclose($flock);
				}
				echo 'Query ', htmlspecialchars($_GET['query']), ' sent to database (PostgreSQL ', pg_version()['client'], ").\n";
?>
				<table border="1">
					<tbody>
						<tr>
<?php
							for ($i = 0, $j = pg_num_fields($result); $i < $j; $i++) {
								echo '<th>', pg_field_name($result, $i), ' (', pg_field_type_oid($result, $i), ")</th>\n";
							}
?>
						</tr>
<?php
						for ($row = pg_fetch_row($result); $row; $row = pg_fetch_row($result)) {
?>
							<tr>
<?php
								for ($i = 0; $i < $j; $i++) {
									echo '<td>', htmlspecialchars($row[$i]), "</td>\n";
								}
?>
							</tr>
<?php
						}
?>
					</tbody>
				</table>
<?php
				pg_free_result($result);
			}
?>
			<form action="" method="GET">
<?php
				echo 'Send query to database (PostgreSQL ', pg_version()['client'], ")\n";
?>
				<input type="text" name="query"/>
				<input type="submit" value="submit"/>
				<input type="reset" value="reset"/>
			</form>
<?php
		}
		if ($admin) {
?>
			<a href="view_sources.php">View sources</a><br/>
			<a href="view_configuration.php">View configuration</a><br/>
<?php
		}
?>
		<a href="login.php?logout">Logout</a>
<?php
		pg_close($dbconn);
?>
	</body>
</html>