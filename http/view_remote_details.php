<?php
require_once 'common.php';
if (!vacuous($_GET['eui'])) {
	$s1eui = pgescapename($_GET['eui']);
	$s2eui = pgescapebytea($_GET['eui']);
	$h1eui = htmlspecialchars($_GET['eui']);
	$h2eui = "X&apos;$h1eui&apos;";
	$u_eui = urlencode($_GET['eui']);
	$can_view = check_authorization('can_view_remotes', 'view remotes at all')
			&& can_view_table($s1eui);
	$can_edit = check_authorization('can_edit_remotes', 'edit remotes at all')
			&& can_edit_table($s1eui);
	if ($can_edit) {
		if (!vacuous($_GET['out_ID'])) {
			$out_ID = intval($_GET['out_ID']);
			pgquery("UPDATE eui_oID SET out_ID = $out_ID WHERE eui = $s2eui;");
			echo "out_ID changed for DST $h2eui.<br/>\n";
		} elseif (isset($_GET['randomize'])) {
			pg_free_result(pgquery('UPDATE eui_oID SET out_ID = ' . rand(0, 255)
					. " WHERE eui = $s2eui;"));
			echo "out_ID randomized for DST $h2eui.<br/>\n";
		} elseif (!vacuous($_GET['add_proto'])) {
			$s_add_proto = pg_escape_literal($_GET['add_proto']);
			$h_add_proto = '&apos;' . htmlspecialchars($_GET['add_proto']) . '&apos;';
			pgquery("INSERT INTO SRC_proto(SRC, proto) VALUES($s2eui, $s_add_proto));");
			echo "proto $h_add_proto added for SRC $h2eui.<br/>\n";
		} elseif (!vacuous($_GET['add_DST'])) {
			$s_add_DST = pgescapebytea($_GET['add_DST']);
			$h_add_DST = 'X&apos;' . htmlspecialchars($_GET['add_DST']) . '&apos;';
			pgquery("INSERT INTO SRC_DST(SRC, DST) VALUES($s2eui, $s_add_DST);");
			echo "DST $h_add_DST added for SRC $h2eui.<br/>\n";
		} elseif (!vacuous($_GET['remove_proto'])) {
			$s_remove_proto = pg_escape_literal($_GET['remove_proto']);
			$h_remove_proto = '&apos;' . htmlspecialchars($_GET['remove_proto']) . '&apos;';
			$u_remove_proto = urlencode($_GET['remove_proto']);
			if (isset($_GET['confirm'])) {
				pgquery("DELETE FROM SRC_proto WHERE SRC = $s2eui AND proto = $s_remove_proto;");
				echo "proto $h_remove_proto removed for SRC $h2eui.<br/>\n";
			} else {
?>
				Are you sure?
<?php
				echo '<a href="',
						"?eui=$u_eui&amp;remove_proto=$u_remove_proto&amp;confirm\">Yes</a>\n";
				echo "<a href=\"?eui=$u_eui\">No</a>\n";
				exit(0);
			}
		} elseif (!vacuous($_GET['remove_DST'])) {
			$s_remove_DST = pgescapebytea($_GET['remove_DST']);
			$h_remove_DST = 'X&apos;' . htmlspecialchars($_GET['remove_DST']) . '&apos;';
			$u_remove_DST = urlencode($_GET['remove_DST']);
			if (isset($_GET['confirm'])) {
				pgquery("DELETE FROM SRC_DST WHERE SRC = $s2eui AND DST = $s_remove_DST;");
				echo "DST $h_remove_DST removed for SRC $h2eui.<br/>\n";
			} else {
?>
				Are you sure?
<?php
				echo '<a href="',
						"?eui=$u_eui&amp;remove_DST=$u_remove_DST&amp;confirm\">Yes</a>\n";
				echo "<a href=\"?eui=$u_eui\">No</a>\n";
				exit(0);
			}
		}
	}
	if ($can_view) {
		echo 'You are authorized to view ', $can_edit ? ' (edit) ' : '', "everything here.<br/><br/>\n";
?>
		<form action="" method="GET">
			View destination for this SRC:
<?php
			$result = pgquery("SELECT DST FROM SRC_DST WHERE SRC = $s2eui ORDER BY DST ASC;");
			for ($row = pg_fetch_row($result); $row; $row = pg_fetch_row($result)) {
				$DST = substr($row[0], 2);
				echo '<a href="view_source_destination_details.php',
						"?SRC=$u_eui&amp;DST=$DST\">$DST</a>\n";
				if ($can_edit) {
					echo "<a href=\"?eui=$u_eui&amp;remove_DST=$DST\">(remove)</a>\n";
				}
			}
			if (pg_num_rows($result) == 0) {
?>
				&lt;no destinations&gt;
<?php
			}
			if ($can_edit) {
				echo "<input type=\"hidden\" name=\"addr\" value=\"$h1eui\"/>\n";
?>
				<input type="text" name="add_DST" required/>
				<input type="submit" value="(add)"/>
				Write destination as a binary string, e.g., abababababababab.
<?php
			}
?>
		</form>
		Destinations ordered by address ascending.<br/><br/>
		Outgoing ID for this SRC:
<?php
		$result = pgquery("SELECT out_ID FROM eui_oID WHERE eui = $s2eui;");
		echo "<input form=\"change\" type=\"hidden\" name=\"eui\" value=\"$h1eui\"/>\n";
		echo '<input form="change" type="text" name="out_ID" value="', pg_fetch_row($result)[0],
				"\"/>\n";
		if ($can_edit) {
?>
			<input form="change" type="submit" value="change" required/>
			<input form="change" type="reset" value="reset"/>
<?php
			echo "<input form=\"random\" type=\"hidden\" name=\"eui\" value=\"$h1eui\"/>\n";
?>
			<input form="random" type="submit" name="randomize" value="randomize"/>
			Write id as an integer, e.g., 11.
			<form id="change" action="" method="GET"></form>
			<form id="random" action="" method="GET"></form>
<?php
		}
?>
		<br/><br/><form action="" method="GET">
			View protocol for this SRC:
<?php
			$result = pgquery("SELECT proto FROM SRC_proto
					WHERE SRC = $s2eui ORDER BY proto ASC;");
			for ($row = pg_fetch_row($result); $row; $row = pg_fetch_row($result)) {
				echo '<a href="view_source_protocol_details.php',
						"?SRC=$u_eui&amp;proto={$row[0]}\">{$row[0]}</a>\n";
				if ($can_edit) {
					echo "<a href=\"?eui=$u_eui&amp;remove_proto={$row[0]}\">(remove)</a>\n";
				}
			}
			if (pg_num_rows($result) == 0) {
?>
				&lt;no protocols&gt;
<?php
			}
			if ($can_edit) {
				echo "<input type=\"hidden\" name=\"eui\" value=\"$h1eui\"/>\n";
?>
				<input type="text" name="add_proto" required/>
				<input type="submit" value="(add)"/>
				Write protocol as a string, e.g., tcp.
<?php
			}
?>
		</form>
		Protocols ordered by name ascending.
		<br/><br/><a href="view_remotes.php">Done</a>
<?php
	}
}
?>