<?php
require_once 'common.php';

function postgresqlOutputToStandard($data, $oid) {
	if ($data === null) {
		return 'NULL';
	}
	switch ($oid) {
	case 1700://NUMERIC
		return $data;
	case 1184://TIMESTAMP WITH TIME ZONE
	case 1114://TIMESTAMP WITHOUT TIME ZONE
		return 'TIMESTAMP \'' . $data . '\'';
	case 1043://CHARACTER VARYING
		return '\'' . $data . '\'';
	case 17://BYTEA
		return 'X\'' . strtoupper(substr($data, 2)) . '\'';
	default://16//BOOLEAN
		return $data == 't' ? 'TRUE' : 'FALSE';
	}
}

function standardToPostgresqlInput($data, $oid) {
	if ($data == 'NULL') {
		return 'NULL';
	}
	switch ($oid) {
	case 1700://NUMERIC
	case 1114://TIMESTAMP WITHOUT TIME ZONE
	case 1043://CHARACTER VARYING
		return $data;
	case 1184://TIMESTAMP WITH TIME ZONE
		return 'TIMESTAMP WITH TIME ZONE' . substr($data, 10);
	case 17://BYTEA
		return 'E\'\\\\x' . substr($data, 2);
	default://16//BOOLEAN
		return $data == 'UNKNOWN' ? 'NULL' : $data;
	}
}

checkLogin();
?>
<!DOCTYPE html>
<html>
	<head>
		<title>View table</title>
		<meta http-equiv="Content-Type" content="text/html; charset=utf-8"/>
	</head>
	<body>
<?php
		$dbconn = pgconnect('host=localhost dbname=postgres user=' . ($_SESSION['user'] == 'admin' ? 'postgres' : 'luka') . ' client_encoding=UTF8');
		if (checkAuthorization(2, 'view tables') && isset($_GET['table'])) {
			if (isset($_GET['truncate'])) {
				if (isset($_GET['confirm'])) {
					$result = pgquery("TRUNCATE TABLE {$_GET['table']};");
					echo 'Table ', htmlspecialchars($_GET['table']), " truncated.<br/>\n";
					pg_free_result($result);
				} else {
?>
					Are you sure?
<?php
					echo '<a href="?table=', urlencode($_GET['table']), "&amp;truncate&amp;confirm\">Yes</a>\n";
					echo '<a href="?table=', urlencode($_GET['table']), '">No</a>';
					pg_close($dbconn);
					exit(0);
				}
			} else if (isset($_GET['insert'])) {
				$result = pgquery("SELECT * FROM {$_GET['table']} WHERE FALSE;");
				$query = "INSERT INTO {$_GET['table']}(";
				for ($i = 0, $j = pg_num_fields($result); $i < $j; $i++) {
					$query .= pg_field_name($result, $i) . ', ';
				}
				$query = substr($query, 0, -2) . ') VALUES(';
				for ($i = 0; $i < $j; $i++) {
					$query .= standardToPostgresqlInput($_GET[pg_field_name($result, $i)], pg_field_type_oid($result, $i)) . ', ';
				}
				$result = pgquery(substr($query, 0, -2) . ');');
				echo 'Row ', htmlspecialchars($_GET['t']), " inserted.<br/>\n";
				pg_free_result($result);
			} else if (isset($_GET['key'])) {
				if (isset($_GET['update'])) {
					$result = pgquery("SELECT * FROM {$_GET['table']} WHERE FALSE;");
					$query = "UPDATE {$_GET['table']} SET (";
					for ($i = 0, $j = pg_num_fields($result); $i < $j; $i++) {
						$query .= pg_field_name($result, $i) . ', ';
					}
					$query = substr($query, 0, -2) . ') = (';
					for ($i = 0; $i < $j; $i++) {
						$query .= standardToPostgresqlInput($_GET[pg_field_name($result, $i)], pg_field_type_oid($result, $i)) . ', ';
					}
					$result = pgquery(substr($query, 0, -2) . ") WHERE t = {$_GET['key']};");
					echo 'Row ', htmlspecialchars($_GET['key']), " updated.<br/>\n";
					pg_free_result($result);
				} else if (isset($_GET['delete'])) {
					if (isset($_GET['confirm'])) {
						$result = pgquery("DELETE FROM {$_GET['table']} WHERE t = {$_GET['key']};");
						echo 'Row ', htmlspecialchars($_GET['key']), " deleted.<br/>\n";
						pg_free_result($result);
					} else {
?>
						Are you sure?
<?php
						echo '<a href="?table=', urlencode($_GET['table']), '&amp;key=', urlencode($_GET['key']), "&amp;delete&amp;confirm\">Yes</a>\n";
						echo '<a href="?table=', urlencode($_GET['table']), '">No</a>';
						pg_close($dbconn);
						exit(0);
					}
				}
			}
			$result = pgquery("TABLE {$_GET['table']} ORDER BY t ASC;");
			echo 'Viewing table ', htmlspecialchars($_GET['table']), ".\n";
?>
			<table border="1">
				<tbody>
					<tr>
<?php
						for ($i = 0, $j = pg_num_fields($result); $i < $j; $i++) {
							echo '<th>', pg_field_name($result, $i), ' (', pg_field_type_oid($result, $i), ")</th>\n";
						}
?>
						<th>Actions</th>
					</tr>
					<tr>
<?php
						for ($i = 0; $i < $j; $i++) {
?>
							<td>
<?php
								echo '<input form="insert" type="text" name="', pg_field_name($result, $i), "\"/>\n";
?>
							</td>
<?php
						}
?>
						<td>
							<form id="insert" action="" method="GET">
<?php
								echo '<input type="hidden" name="table" value="', htmlspecialchars($_GET['table']), "\"/>\n";
?>
								<input type="submit" name="insert" value="INSERT"/><br/>
								<input type="reset" value="reset"/>
							</form>
							<form action="" method="GET">
<?php
								echo '<input type="hidden" name="table" value="', htmlspecialchars($_GET['table']), "\"/>\n";
?>
								<input type="submit" name="truncate" value="TRUNCATE"/>
							</form>
						</td>
					</tr>
<?php
					$t = pg_field_num($result, 't');
					for ($row = pg_fetch_row($result); $row; $row = pg_fetch_row($result)) {
?>
						<tr>
<?php
							for ($i = 0; $i < $j; $i++) {
?>
								<td>
<?php
									echo "<input form=\"update{$row[$t]}\" type=\"text\" name=\"", pg_field_name($result, $i), "\" value=\"", htmlspecialchars(postgresqlOutputToStandard($row[$i], pg_field_type_oid($result, $i))), "\"/>\n";
?>
								</td>
<?php
							}
?>
							<td>
<?php
								echo "<form id=\"update{$row[$t]}\" action=\"\" method=\"GET\">\n";
									echo '<input type="hidden" name="key" value="TIMESTAMP ', pg_field_type_oid($result, $t) == 1184 ? 'WITH TIME ZONE ' : '', "&apos;{$row[$t]}&apos;\"/>\n";
									echo '<input type="hidden" name="table" value="', htmlspecialchars($_GET['table']), "\"/>\n";
									echo "<input type=\"submit\" name=\"update\" value=\"UPDATE\"/><br/>\n";
									echo "<input type=\"reset\" value=\"reset\"/>\n";
								echo "</form>\n";
?>
								<form action="" method="GET">
<?php
									echo '<input type="hidden" name="key" value="TIMESTAMP ', pg_field_type_oid($result, $t) == 1184 ? 'WITH TIME ZONE ' : '', "&apos;{$row[$t]}&apos;\"/>\n";
									echo '<input type="hidden" name="table" value="', htmlspecialchars($_GET['table']), "\"/>\n";
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
<?php
			pg_free_result($result);
		}
		pg_close($dbconn);
?>
		<a href="index.php">Done</a>
	</body>
</html>