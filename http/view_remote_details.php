<?php
require_once 'common.php';
if (checkAuthorization(10, 'view remotes') && !empty($_GET['addr'])) {
	$s1addr = pgescapename($_GET['addr']);
	$s2addr = pgescapebytea($_GET['addr']);
	$h1addr = htmlspecialchars($_GET['addr']);
	$h2addr = "X&apos;$h1addr&apos;";
	$u_addr = urlencode($_GET['addr']);
	$result1 = pgquery("SELECT TRUE FROM table_user WHERE tablename = $s1addr;");
	$result2 = pgquery("SELECT TRUE FROM table_user WHERE tablename = $s1addr
			AND username = {$_SESSION['s_username']};");
	$result3 = pgquery("SELECT TRUE FROM table_user INNER JOIN users ON table_user.username
			= users.username WHERE table_user.tablename = $s1addr AND NOT users.is_administrator;");
	if ($_SESSION['is_root'] || !pg_fetch_row($result1) || pg_fetch_row($result2)
				|| $_SESSION['is_administrator'] && pg_fetch_row($result3)) {
		if (!empty($_GET['out_ID'])) {
			$out_ID = intval($_GET['out_ID']);
			pg_free_result(pgquery("UPDATE addr_oID SET out_ID = $out_ID WHERE addr = $s2addr;"));
			echo "out_ID changed for DST $h2addr.<br/>\n";
		} else if (isset($_GET['randomize'])) {
			pg_free_result(pgquery('UPDATE addr_oID SET out_ID = ' . rand(0, 255)
					. " WHERE addr = $s2addr;"));
			echo "out_ID randomized for DST $h2addr.<br/>\n";
		} else if (!empty($_GET['add_proto'])) {
			$s_add_proto = pg_escape_literal($_GET['add_proto']);
			$h_add_proto = '&apos;' . htmlspecialchars($_GET['add_proto']) . '&apos;';
			pg_free_result(pgquery("INSERT INTO SRC_proto(SRC, proto) VALUES($s2addr,
					(SELECT proto FROM proto_name WHERE name = $s_add_proto));"));
			echo "proto $h_add_proto added for SRC $h2addr.<br/>\n";
		} else if (!empty($_GET['add_DST'])) {
			$s_add_DST = pgescapebytea($_GET['add_DST']);
			$h_add_DST = 'X&apos;' . htmlspecialchars($_GET['add_DST']) . '&apos;';
			pg_free_result(pgquery("INSERT INTO SRC_DST(SRC, DST) VALUES($s2addr, $s_add_DST);"));
			echo "DST $h_add_DST added for SRC $h2addr.<br/>\n";
		} else if (!empty($_GET['remove_proto'])) {
			$s_remove_proto = pg_escape_literal($_GET['remove_proto']);
			$h_remove_proto = '&apos;' . htmlspecialchars($_GET['remove_proto']) . '&apos;';
			$u_remove_proto = urlencode($_GET['remove_proto']);
			if (isset($_GET['confirm'])) {
				pg_free_result(pgquery("DELETE FROM SRC_proto WHERE SRC = $s2addr
						AND proto = (SELECT proto FROM proto_name WHERE name = $s_remove_proto);"));
				echo "proto $h_remove_proto removed for SRC $h2addr.<br/>\n";
			} else {
?>
				Are you sure?
<?php
				echo "<a href=\"?addr=$u_addr&amp;remove_proto=$u_remove_proto&amp;confirm\">",
						"Yes</a>\n";
				echo "<a href=\"?addr=$u_addr\">No</a>\n";
				exit(0);
			}
		} else if (!empty($_GET['remove_DST'])) {
			$s_remove_DST = pgescapebytea($_GET['remove_DST']);
			$h_remove_DST = 'X&apos;' . htmlspecialchars($_GET['remove_DST']) . '&apos;';
			$u_remove_DST = urlencode($_GET['remove_DST']);
			if (isset($_GET['confirm'])) {
				pg_free_result(pgquery("DELETE FROM SRC_DST WHERE SRC = $s2addr
						AND DST = $s_remove_DST;"));
				echo "DST $h_remove_DST removed for SRC $h2addr.<br/>\n";
			} else {
?>
				Are you sure?
<?php
				echo "<a href=\"?addr=$u_addr&amp;remove_DST=$u_remove_DST&amp;confirm\">Yes</a>\n";
				echo "<a href=\"?addr=$u_addr\">No</a>\n";
				exit(0);
			}
		}
?>
		<form action="" method="GET">
			View destination:
<?php
			$result = pgquery("SELECT DST FROM SRC_DST WHERE SRC = $s2addr ORDER BY DST ASC;");
			for ($row = pg_fetch_row($result); $row; $row = pg_fetch_row($result)) {
				$str = substr($row[0], 2);
				echo '<a href="view_source_destination_details.php',
						"?SRC=$u_addr&amp;DST=$str\">$str</a>\n";
				echo "<a href=\"?addr=$u_addr&amp;remove_DST=$str\">(remove)</a>\n";
			}
			if (pg_num_rows($result) == 0) {
?>
				&lt;no destinations&gt;
<?php
			}
			echo "<input type=\"hidden\" name=\"addr\" value=\"$h1addr\"/>\n";
?>
			<input type="text" name="add_DST"/>
			<input type="submit" value="(add)"/>
		</form>
		outgoing ID
<?php
		pg_free_result($result);
		$result = pgquery("SELECT out_ID FROM addr_oID WHERE addr = $s2addr;");
		echo "<input form=\"change\" type=\"hidden\" name=\"addr\" value=\"$h1addr\"/>\n";
		echo '<input form="change" type="text" name="out_ID" value="', pg_fetch_row($result)[0],
				"\"/>\n";
?>
		<input form="change" type="submit" value="change"/>
		<input form="change" type="reset" value="reset"/>
<?php
		echo "<input form=\"random\" type=\"hidden\" name=\"addr\" value=\"$h1addr\"/>\n";
?>
		<input form="random" type="submit" name="randomize" value="randomize"/>
		<form id="change" action="" method="GET"></form>
		<form id="random" action="" method="GET"></form>
		<form action="" method="GET">
			View protocol:
<?php
			pg_free_result($result);
			$result = pgquery("SELECT proto_name.name FROM SRC_proto INNER JOIN proto_name
					ON SRC_proto.proto = proto_name.proto WHERE SRC = $s2addr
					ORDER BY SRC_proto.proto ASC;");
			for ($row = pg_fetch_row($result); $row; $row = pg_fetch_row($result)) {
				echo '<a href="view_source_protocol_details.php',
						"?SRC=$u_addr&amp;proto={$row[0]}\">{$row[0]}</a>\n";
				echo "<a href=\"?addr=$u_addr&amp;remove_proto={$row[0]}\">(remove)</a>\n";
			}
			if (pg_num_rows($result) == 0) {
?>
				&lt;no protocols&gt;
<?php
			}
			echo "<input type=\"hidden\" name=\"addr\" value=\"$h1addr\"/>\n";
?>
			<input type="text" name="add_proto"/>
			<input type="submit" value="(add)"/>
		</form>
		Write destination as a binary string, e.g., abababababababab.<br/>
		Write id as an integer, e.g., 11.<br/>
		Write protocol as a string, e.g., tcp.<br/>
		<a href="view_remotes.php">Done</a>
<?php
		pg_free_result($result);
	}
	pg_free_result($result1);
	pg_free_result($result2);
	pg_free_result($result3);
}
?>