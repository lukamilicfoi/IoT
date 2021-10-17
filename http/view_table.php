<?php
require_once 'common.php';

function postgresqlOutputToStandard($data, $oid) {
	if ($data === null) {
		return 'NULL';
	}
	switch ($oid) {
	case 1700://NUMERIC
		return $data;
	case 1186://INTERVAL
		return 'INTERVAL \'' . $data . '\'';
	case 1184://TIMESTAMP WITH TIME ZONE
	case 1114://TIMESTAMP WITHOUT TIME ZONE
		return 'TIMESTAMP \'' . $data . '\'';
	case 1266://TIME WITH TIME ZONE
	case 1083://TIME WITHOUT TIME ZONE
		return 'TIME \'' . $data . '\'';
	case 1082://DATE
		return 'DATE \'' . $data . '\'';
	case 25://TEXT
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
	case 1266://TIME WITH TIME ZONE
		return 'TIME WITH TIME ZONE' . substr($data, 5);
	case 1184://TIMESTAMP WITH TIME ZONE
		return 'TIMESTAMP WITH TIME ZONE' . substr($data, 10);
	case 1186://INTERVAL
	case 1114://TIMESTAMP WITHOUT TIME ZONE
	case 1083://TIME WITHOUT TIME ZONE
	case 1082://DATE
	case 25://TEXT
		return $data;
	case 17://BYTEA
		return '\'\\x' . substr($data, 2);
	default://16//BOOLEAN
		return $data == 'UNKNOWN' ? 'NULL' : $data;
	}
}

if (checkAuthorization(3, 'view tables') && !empty($_GET['tablename'])) {
	$s1tablename = pg_escape_literal($_GET['tablename']);
	$s2tablename = pg_escape_identifier($_GET['tablename']);
	$h1tablename = htmlspecialchars($_GET['tablename']);
	$h2tablename = "&quot;$h1tablename&quot;";
	$u_tablename = urlencode($_GET['tablename']);
	$result1 = pgquery("SELECT username FROM table_user WHERE tablename = $s1tablename;");
	$result2 = pgquery("SELECT TRUE FROM table_user WHERE tablename = $s1tablename
			AND username = {$_SESSION['s_username']};");
	$result3 = pgquery("SELECT TRUE FROM table_user INNER JOIN users
			ON table_user.username = users.username
			WHERE table_user.tablename = $s1tablename AND NOT users.is_administrator;");
	$row = pg_fetch_row($result1);
	if (!$row || $row[0] === null || pg_fetch_row($result2) || pg_fetch_row($result3)
			&& $_SESSION['is_administrator'] || $_SESSION['is_root']) {
		if (isset($_GET['truncate'])) {
			if (isset($_GET['confirm'])) {
				$result = pgquery("TRUNCATE TABLE $s2tablename;");
				echo "Table $h2tablename truncated.<br/>\n";
				pg_free_result($result);
			} else {
?>
				Are you sure?
<?php
				echo "<a href=\"?tablename=$u_tablename&amp;truncate&amp;confirm\">Yes</a>\n";
				echo "<a href=\"?tablename=$u_tablename\">No</a>";
				exit(0);
			}
		} else if (!empty($_GET['t'])) {
			if (isset($_GET['insert'])) {
				$t = htmlspecialchars($_GET['t']);
				$result = pgquery("SELECT * FROM $s2tablename WHERE FALSE;");
				$query = "INSERT INTO $s2tablename(";
				for ($i = 0, $j = pg_num_fields($result); $i < $j; $i++) {
					$query .= pg_field_name($result, $i) . ', ';
				}
				$query = substr($query, 0, -2) . ') VALUES(';
				for ($i = 0; $i < $j; $i++) {
					$query .= standardToPostgresqlInput($_GET[pg_field_name($result, $i)],
							pg_field_type_oid($result, $i)) . ', ';
				}
				$result = pgquery(substr($query, 0, -2) . ');');
				echo "Row $t inserted.<br/>\n";
				pg_free_result($result);
			} else if (!empty($_GET['key']) && isset($_GET['update'])) {
				$s_key = 'TIMESTAMP \'' . pg_escape_string(substr($_GET['key'], 11, -1));
				$h_key = htmlspecialchars($_GET['key']);
				$result = pgquery("SELECT * FROM $s2tablename WHERE FALSE;");
				$query = "UPDATE $s2tablename SET (";
				for ($i = 0, $j = pg_num_fields($result); $i < $j; $i++) {
					$query .= pg_field_name($result, $i) . ', ';
				}
				$query = substr($query, 0, -2) . ') = ROW (';
				for ($i = 0; $i < $j; $i++) {
					$query .= standardToPostgresqlInput($_GET[pg_field_name($result, $i)],
							pg_field_type_oid($result, $i)) . ', ';
				}
				$result = pgquery(substr($query, 0, -2) . ") WHERE t = $s_key;");
				echo "Row $h_key updated.<br/>\n";
				pg_free_result($result);
			}
		} else if (!empty($_GET['key']) && isset($_GET['delete'])) {
			$s_key = 'TIMESTAMP \'' . pg_escape_string(substr($_GET['key'], 11, -1);
			$h_key = htmlspecialchars($_GET['key']);
			$u_key = urlencode($_GET['key']);
			if (isset($_GET['confirm'])) {
				$result = pgquery("DELETE FROM $s2tablename WHERE t = $s_key;");
				echo "Row $h_key deleted.<br/>\n";
				pg_free_result($result);
			} else {
?>
				Are you sure?
<?php
				echo '<a href="?tablename=',
						"$u_tablename&amp;key=$u_key&amp;delete&amp;confirm\">Yes</a>\n";
				echo "<a href=\"?tablename=$u_tablename\">No</a>";
				exit(0);
			}
		}
		$result = pgquery("TABLE $s2tablename ORDER BY t DESC;");
		echo "Viewing table $h2tablename, newest first.\n";
?>
		<table border="1">
			<tbody>
				<tr>
<?php
					for ($i = 0, $j = pg_num_fields($result); $i < $j; $i++) {
						echo '<th>', pg_field_name($result, $i), ' (',
								pg_field_type_oid($result, $i), ")</th>\n";
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
							echo '<input form="insert" type="text" name="',
									pg_field_name($result, $i), "\"/>\n";
?>
						</td>
<?php
					}
?>
					<td>
						<form id="insert" action="" method="GET">
<?php
							echo "<input type=\"hidden\" name=\"tablename\"
									value=\"$h1tablename\"/>\n";
?>
							<input type="submit" name="insert" value="INSERT"/><br/>
							<input type="reset" value="reset"/>
						</form>
						<form action="" method="GET">
<?php
							echo "<input type=\"hidden\" name=\"tablename\"
									value=\"$h1tablename\"/>\n";
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
								echo "<input form=\"update{$row[$t]}\" type=\"text\" name=\"",
										pg_field_name($result, $i), '" value="',
										htmlspecialchars(postgresqlOutputToStandard($row[$i],
										pg_field_type_oid($result, $i))), "\"/>\n";
?>
							</td>
<?php
						}
?>
						<td>
<?php
							echo "<form id=\"update{$row[$t]}\" action=\"\" method=\"GET\">\n";
								echo '<input type="hidden" name="key" value="TIMESTAMP ',
										pg_field_type_oid($result, $t) == 1184
										? 'WITH TIME ZONE ' : '', "&apos;{$row[$t]}&apos;\"/>\n";
								echo "<input type=\"hidden\" name=\"tablename\"
										value=\"$h1tablename\"/>\n";
?>
								<input type="submit" name="update" value="UPDATE"/><br/>
								<input type="reset" value="reset"/>
<?php
							echo "</form>\n";
?>
							<form action="" method="GET">
<?php
								echo '<input type="hidden" name="key" value="TIMESTAMP ',
										pg_field_type_oid($result, $t) == 1184
										? 'WITH TIME ZONE ' : '', "&apos;{$row[$t]}&apos;\"/>\n";
								echo "<input type=\"hidden\" name=\"tablename\"
										value=\"$h1tablename\"/>\n";
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
		<a href="index.php">Done</a>
<?php
		pg_free_result($result);
	}
	pg_free_result($result1);
	pg_free_result($result2);
	pg_free_result($result3);
}
?>