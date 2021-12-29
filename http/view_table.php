<?php
require_once 'common.php';
if (!empty($_GET['tablename'])) {
	$s1tablename = pg_escape_literal($_GET['tablename']);
	$s2tablename = pg_escape_identifier($_GET['tablename']);
	$h1tablename = htmlspecialchars($_GET['tablename']);
	$h2tablename = "&quot;$h1tablename&quot;";
	$u_tablename = urlencode($_GET['tablename']);
	$can_view = check_authorization('can_view_tables', 'view tables')
			&& can_view_table($s1tablename);
	$can_edit = check_authorization('can_edit_tables', 'edit tables')
			&& can_edit_table($s1tablename);
	if ($can_edit) {
		if (isset($_GET['truncate'])) {
			if (isset($_GET['confirm'])) {
				pgquery("TRUNCATE TABLE $s2tablename;");
				echo "Table $h2tablename truncated.<br/>\n";
			} else {
?>
				Are you sure?
<?php
				echo "<a href=\"?tablename=$u_tablename&amp;truncate&amp;confirm\">Yes</a>\n";
				echo "<a href=\"?tablename=$u_tablename\">No</a>";
				exit(0);
			}
		} elseif (!empty($_GET['t'])) {
			if (isset($_GET['insert'])) {
				$t = htmlspecialchars(pgescapetimestamp($_GET['t']));
				$result = pgquery("SELECT * FROM $s2tablename WHERE FALSE;");
				$query = "INSERT INTO $s2tablename(";
				for ($i = 0, $j = pg_num_fields($result); $i < $j; $i++) {
					$query .= pg_escape_identifier(pg_field_name($result, $i)) . ', ';
				}
				$query = substr($query, 0, -2) . ') VALUES(';
				for ($i = 0; $i < $j; $i++) {
					$query .= my_input_to_postgresql_input($_GET[pg_field_name($result, $i)],
							pg_field_type_oid($result, $i)) . ', ';
				}
				pgquery(substr($query, 0, -2) . ');');
				echo "Row $t inserted.<br/>\n";
			} elseif (!empty($_GET['key']) && isset($_GET['update'])) {
				$s_key = pgescapetimestamp($_GET['key']);
				$h_key = htmlspecialchars($s_key);
				$result = pgquery("SELECT * FROM $s2tablename WHERE FALSE;");
				$query = "UPDATE $s2tablename SET (";
				for ($i = 0, $j = pg_num_fields($result); $i < $j; $i++) {
					$query .= pg_escape_identifier(pg_field_name($result, $i)) . ', ';
				}
				$query = substr($query, 0, -2) . ') = ROW (';
				for ($i = 0; $i < $j; $i++) {
					$query .= my_input_to_postgresql_input($_GET[pg_field_name($result, $i)],
							pg_field_type_oid($result, $i)) . ', ';
				}
				pgquery(substr($query, 0, -2) . ") WHERE t = $s_key;");
				echo "Row $h_key updated.<br/>\n";
			}
		} elseif (!empty($_GET['key']) && isset($_GET['delete'])) {
			$s_key = pgescapetimestamp($_GET['key']);
			$h_key = htmlspecialchars($s_key);
			$u_key = urlencode($_GET['key']);
			if (isset($_GET['confirm'])) {
				pgquery("DELETE FROM $s2tablename WHERE t = $s_key;");
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
		echo "Viewing table $h2tablename.<br/>\n";
?>
		Table ordered by &quot;t&quot; descending.
		<table border="1">
			<tbody>
				<tr>
<?php
					for ($i = 0, $j = pg_num_fields($result); $i < $j; $i++) {
						echo '<th>', htmlspecialchars(pg_field_name($result, $i)), ' (',
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
										htmlspecialchars(pg_field_name($result, $i)), "\"/>\n";
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
										htmlspecialchars(pg_field_name($result, $i)), '" value="',
										htmlspecialchars(postgresql_output_to_my_input($row[$i],
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
									echo "<input type=\"hidden\" name=\"key\"
											value="{$row[$t]}\"/>\n";
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
									echo "<input type=\"hidden\" name=\"key\"
											value="{$row[$t]}\"/>\n";
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
		</table><br/>
		<a href="index.php">Done</a>
<?php
	}
}
?>