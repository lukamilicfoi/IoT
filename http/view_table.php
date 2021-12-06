<?php
require_once 'common.php';

function postgresqlOutputToMyInput($data, $oid) {
	if ($data === null) {
		return 'NULL';
	}
	switch ($oid) {
	case 1700://NUMERIC
	case 1186://INTERVAL
	case 1184://TIMESTAMP WITH TIME ZONE
	case 1114://TIMESTAMP WITHOUT TIME ZONE
	case 1266://TIME WITH TIME ZONE
	case 1083://TIME WITHOUT TIME ZONE
	case 1082://DATE
	case 25://TEXT
		return $data;
	case 17://BYTEA
		return substr($data, 2);
	default://16//BOOLEAN
		return $data == 't' ? 'TRUE' : 'FALSE';
	}
}

function myInputToPostgresqlInput($data, $oid) {
	if ($data == 'NULL') {
		return 'NULL';
	}
	switch ($oid) {
	case 1700://NUMERIC
	case 1266://TIME WITH TIME ZONE
		return 'TIME WITH TIME ZONE \'' . $data . '\'';
	case 1184://TIMESTAMP WITH TIME ZONE
		return 'TIMESTAMP WITH TIME ZONE \'' . $data . '\'';
	case 1186://INTERVAL
	case 1114://TIMESTAMP WITHOUT TIME ZONE
	case 1083://TIME WITHOUT TIME ZONE
	case 1082://DATE
	case 25://TEXT
		return '\'' . $data . '\'';
	case 17://BYTEA
		return '\'\\x' . $data . '\'';
	default://16//BOOLEAN
		return $data == 'UNKNOWN' ? 'NULL' : $data;
	}
}

if (!empty($_GET['tablename'])) {
	$s1tablename = pg_escape_literal($_GET['tablename']);
	$s2tablename = pg_escape_identifier($_GET['tablename']);
	$h1tablename = htmlspecialchars($_GET['tablename']);
	$h2tablename = "&quot;$h1tablename&quot;";
	$u_tablename = urlencode($_GET['tablename']);
	$can_view = checkAuthorization(3, 'view tables') && can_view_table($s1tablename);
	$can_edit = checkAuthorization(4, 'edit tables') && can_edit_table($s1tablename);
	if ($can_edit) {
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
					$query .= myInputToPostgresqlInput($_GET[pg_field_name($result, $i)],
							pg_field_type_oid($result, $i)) . ', ';
				}
				pgquery(substr($query, 0, -2) . ');');
				echo "Row $t inserted.<br/>\n";
			} else if (!empty($_GET['key']) && isset($_GET['update'])) {
				$s_key = 'TIMESTAMP \'' . pg_escape_string($_GET['key']) . '\'';
				$h_key = 'TIMESTAMP &apos;' . htmlspecialchars($_GET['key']) . '&apos;';
				$result = pgquery("SELECT * FROM $s2tablename WHERE FALSE;");
				$query = "UPDATE $s2tablename SET (";
				for ($i = 0, $j = pg_num_fields($result); $i < $j; $i++) {
					$query .= pg_field_name($result, $i) . ', ';
				}
				$query = substr($query, 0, -2) . ') = ROW (';
				for ($i = 0; $i < $j; $i++) {
					$query .= myInputToPostgresqlInput($_GET[pg_field_name($result, $i)],
							pg_field_type_oid($result, $i)) . ', ';
				}
				pgquery(substr($query, 0, -2) . ") WHERE t = $s_key;");
				echo "Row $h_key updated.<br/>\n";
			}
		} else if (!empty($_GET['key']) && isset($_GET['delete'])) {
			$s_key = 'TIMESTAMP \'' . pg_escape_string($_GET['key']) . '\'';
			$h_key = 'TIMESTAMP &apos;' . htmlspecialchars($_GET['key']) . '&apos;';
			$u_key = urlencode($_GET['key']);
			if (isset($_GET['confirm'])) {
				$result = pgquery("DELETE FROM $s2tablename WHERE t = $s_key;");
				echo "Row $h_key deleted.<br/>\n";
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
	}
	if ($can_view) {
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
					if ($can_edit) {
?>
						<th>Actions</th>
<?php
					}
?>
				</tr>
<?php
					if ($can_edit) {
?>
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
					}
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
										htmlspecialchars(postgresqlOutputToMyInput($row[$i],
										pg_field_type_oid($result, $i))), "\"/>\n";
?>
							</td>
<?php
						}
						if ($can_edit) {
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
<?php
						}
?>
					</tr>
<?php
				}
?>
			</tbody>
		</table>
		<a href="index.php">Done</a>
<?php
	}
}
?>