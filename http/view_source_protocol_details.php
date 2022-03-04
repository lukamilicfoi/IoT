<?php
require_once 'common.php';
if (!vacuous($_GET['SRC']) && !vacuous($_GET['proto'])) {
	$s1SRC = pgescapename($_GET['SRC']);
	$s2SRC = pgescapebytea($_GET['SRC']);
	$h1SRC = htmlspecialchars($_GET['SRC']);
	$h2SRC = "X&apos;$h1SRC&apos;";
	$u_SRC = urlencode($_GET['SRC']);
	$s_proto = pg_escape_literal($_GET['proto']);
	$h1proto = htmlspecialchars($_GET['proto']);
	$h2proto = "&apos;$h1proto&apos;";
	$u_proto = urlencode($_GET['proto']);
	$can_view = check_authorization('can_view_remotes', 'view remotes') && can_view_table($s1SRC);
	$can_edit = check_authorization('can_edit_remotes', 'edit remotes') && can_edit_table($s1SRC);
	if ($can_edit) {
		if (isset($_GET['truncate'])) {
			if (isset($_GET['confirm'])) {
				pgquery("DELETE FROM iSRC_TWR WHERE SRC = $s1SRC AND proto
						= (SELECT proto FROM proto_name WHERE name = $s_proto);");
				echo "Table &quot;iSRC_TWR&quot; truncated for SRC $h2SRC
						and proto $h2proto.<br/>\n";
			} else {
?>
				Are you sure?
<?php
				echo '<a href="?SRC=',
						"$u_SRC&amp;proto=$u_proto&amp;truncate&amp;confirm\">Yes</a>\n";
				echo "<a href=\"?SRC=$u_SRC&amp;proto=$u_proto\">No</a>\n";
				exit(0);
			}
		} elseif (!vacuous($_GET['imm_SRC']) && !vacuous($_GET['TWR'])) {
			$s_imm_SRC = pgescapebytea($_GET['imm_SRC']);
			$h_imm_SRC = 'X&apos;' . htmlspecialchars($_GET['imm_SRC']) . '&apos;';
			$TWR = pgescapetimestamp($_GET['TWR']);
			if (isset($_GET['insert'])) {
				pgquery("INSERT INTO iSRC_TWR(SRC, proto, imm_SRC, TWR) VALUES($s2SRC,
						(SELECT proto FROM proto_name WHERE name = $s_proto), $s_imm_SRC, $TWR);");
				echo "Mapping $h_imm_SRC for SRC $h2SRC and proto $h2proto inserted.<br/>\n";
			} else if (!vacuous($_GET['key']) && isset($_GET['update'])) {
				$s_key = pgescapebytea($_GET['key']);
				$h_key = 'X&apos;' . htmlspecialchars($_GET['key']) . '&apos;';
				pgquery("UPDATE iSRC_TWR SET (imm_SRC, TWR) = ($s_imm_SRC, $TWR)
						WHERE SRC = $s2SRC AND proto = (SELECT proto FROM proto_name
						WHERE name = $s_proto) AND imm_SRC = $s_imm_SRC;");
				echo "Mapping $h_key for SRC $h2SRC and proto $h2proto updated.<br/>\n";
			}
		} elseif (!vacuous($_GET['key']) && isset($_GET['delete'])) {
			$s_key = pgescapebytea($_GET['key']);
			$h_key = 'X&apos;' . htmlspecialchars($_GET['key']) . '&apos;';
			$u_key = urlencode($_GET['key']);
			if (isset($_GET['confirm'])) {
				pgquery("DELETE FROM iSRC_TWR WHERE SRC = $s2SRC AND proto = (SELECT proto
						FROM proto_name WHERE name = $s_proto) AND imm_SRC = $s_key;"));
				echo "Mapping $h_key for SRC $h2SRC and proto $h2proto deleted.<br/>\n";
			} else {
?>
				Are you sure?
<?php
				echo "<a href=\"?SRC=$u_SRC&amp;proto=$u_proto&amp;key=$u_key&amp;delete",
						"&amp;confirm\">Yes</a>\n";
				echo "<a href=\"?SRC=$u_SRC&amp;proto=$u_proto\">No</a>\n";
				exit(0);
			}
		}
	}
	if ($can_view) {
		$result = pgquery("SELECT imm_SRC, TWR FROM iSRC_TWR WHERE SRC = $s2SRC AND proto
				= (SELECT proto FROM proto_name WHERE name = $s_proto) ORDER BY imm_SRC ASC;");
		echo "Viewing table &quot;iSRC_TWR&quot; for SRC $h2SRC and proto $h2proto.<br/>\n";
?>
		Table ordered by immediate source address ascending.<br/><br/>
		<table border="1">
			<tbody>
				<tr>
					<th>immediate source address (imm_SRC)</th>
					<th>time when received (TWR)</th>
<?php
					if ($can_edit) {
?>
						<th>(actions)</th>
<?php
					}
?>
				</tr>
<?php
				if ($can_edit) {
?>
					<tr>
						<td>
							<input form="insert" type="text" name="imm_SRC" required autofocus/>
						</td>
						<td>
							<input form="insert" type="text" name="TWR" required/>
						</td>
						<td>
							<form id="insert" action="" method="GET">
<?php
								echo "<input type=\"hidden\" name=\"SRC\" value=\"$h1SRC\"/>\n";
								echo "<input type=\"hidden\" name=\"proto\" value=\"$h1proto\"/>\n";
?>
								<input type="submit" name="insert"
										value="Insert new mapping for this SRC and this proto"/>
										<br/>
								<input type="reset" value="reset"/>
							</form>
							<form action="" method="GET">
<?php
								echo "<input type=\"hidden\" name=\"SRC\" value=\"$h1SRC\"/>\n";
								echo "<input type=\"hidden\" name=\"proto\" value=\"$h1proto\"/>\n";
?>
								<input type="submit" name="truncate"
										value="Truncate mappings for this SRC and this proto"/>
							</form>
						</td>
					</tr>
<?php
				}
				for ($row = pg_fetch_row($result); $row; $row = pg_fetch_row($result)) {
?>
					<tr>
						<td>
<?php
							$str = substr($row[0], 2);
							echo "<input form=\"update$str\" type=\"text\" name=\"imm_SRC\"
									value=\"$str\" required autofocus/>\n";
?>
						</td>
						<td>
<?php
							echo "<input form=\"update$str\" type=\"text\" name=\"TWR\"
									value=\"{$row[1]}\" required/>\n";
?>
						</td>
<?php
						if ($can_edit) {
?>
							<td>
<?php
								echo "<form id=\"update$str\" action=\"\" method=\"GET\">\n";
									echo "<input type=\"hidden\" name=\"SRC\" value=\"$h1SRC\"/>\n";
									echo "<input type=\"hidden\" name=\"proto\"
											value=\"$h1proto\"/>\n";
									echo "<input type=\"hidden\" name=\"key\" value=\"$str\"/>\n";
?>
									<input type="submit" name="update"
											value="Update this mapping for this SRC and this proto"
											/><br/>
									<input type="reset" value="reset"/>
<?php
								echo "</form>\n";
?>
								<form action="" method="GET">
<?php
									echo "<input type=\"hidden\" name=\"SRC\" value=\"$h1SRC\"/>\n";
									echo "<input type=\"hidden\" name=\"proto\"
											value=\"$h1proto\"/>\n";
									echo "<input type=\"hidden\" name=\"key\" value=\"$str\"/>\n";
?>
									<input type="submit" name="delete"
											value="Delete this mapping for this SRC and this proto"
											/>
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
		<br/>Write imm_SRC as a binary string, e.g., abababababababab.<br/>
		Write TWR as a timestamp, e.g., 1111-11-11 11:11:11.<br/>
		You can edit something only if you have edit permissions on this remote.<br/><br/>
<?php
		echo "<a href=\"view_remote_details.php?addr=$u_SRC\">Done</a>\n";
	}
}
?>