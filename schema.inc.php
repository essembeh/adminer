<?php
page_header(lang('Database schema'), array(), $_GET["db"]);

$table_pos = array();
$table_pos_js = array();
preg_match_all('~([^:]+):([-0-9.]+)x([-0-9.]+)(_|$)~', $_COOKIE["schema"], $matches, PREG_SET_ORDER); //! ':' in table name
foreach ($matches as $i => $match) {
	$table_pos[$match[1]] = array($match[2], $match[3]);
	$table_pos_js[] = "\n\t'" . addcslashes($match[1], "\r\n'\\") . "': [ $match[2], $match[3] ]";
}

$top = 0;
$base_left = -1;
$schema = array();
$referenced = array();
$result = $mysql->query("SHOW TABLE STATUS");
while ($row = $result->fetch_assoc()) {
	if (!isset($row["Engine"])) { // view
		continue;
	}
	$pos = 0;
	$schema[$row["Name"]]["fields"] = array();
	foreach (fields($row["Name"]) as $name => $field) {
		$pos += 1.25;
		$field["pos"] = $pos;
		$schema[$row["Name"]]["fields"][$name] = $field;
	}
	$schema[$row["Name"]]["pos"] = ($table_pos[$row["Name"]] ? $table_pos[$row["Name"]] : array($top, 0));
	if ($row["Engine"] == "InnoDB") {
		foreach (foreign_keys($row["Name"]) as $val) {
			if (!$val["db"]) {
				if ($table_pos[$row["Name"]][1] || $table_pos[$row["Name"]][1]) {
					$left = min($table_pos[$row["Name"]][1], $table_pos[$val["table"]][1]) - 1;
				} else {
					$left = $base_left;
					$base_left -= .1;
				}
				while ($schema[$row["Name"]]["references"][$val["table"]][10000 * $left] || $referenced[$val["table"]][$row["Name"]][10000 * $left]) {
					$left -= .0001;
				}
				$schema[$row["Name"]]["references"][$val["table"]][10000 * $left] = array_combine($val["source"], $val["target"]);
				$referenced[$val["table"]][$row["Name"]][10000 * $left] = $val["target"];
			}
		}
	}
	$top = max($top, $schema[$row["Name"]]["pos"][0] + 2.5 + $pos);
}
$result->free();

?>
<script type="text/javascript">
var that, x, y, em;
var table_pos = {<?php echo implode(",", $table_pos_js) . "\n"; ?>};

function mousedown(el, event) {
	that = el;
	em = document.getElementById('schema').offsetHeight / <?php echo $top; ?>;
	x = event.clientX - el.offsetLeft;
	y = event.clientY - el.offsetTop;
}
document.onmousemove = function (ev) {
	if (that !== undefined) {
		ev = ev || event;
		var left = (ev.clientX - x) / em;
		var top = (ev.clientY - y) / em;
		that.style.left = left + 'em';
		that.style.top = top + 'em';
		var divs = document.getElementsByTagName('div');
		for (var i=0; i < divs.length; i++) {
			if (divs[i].className == 'references') {
				var left1 = Math.min(left, (table_pos[divs[i].title] ? table_pos[divs[i].title][1] : 0)) - 1;
				divs[i].style.left = left1;
				divs[i].style.width = left - left1;
			}
		}
	}
}
document.onmouseup = function (ev) {
	if (that !== undefined) {
		ev = ev || event;
		table_pos[that.firstChild.firstChild.firstChild.data] = [ (ev.clientY - y) / em, (ev.clientX - x) / em ];
		that = undefined;
		var date = new Date();
		date.setMonth(date.getMonth() + 1);
		var s = '';
		for (var key in table_pos) {
			s += '_' + key + ':' + Math.round(table_pos[key][0] * 10000) / 10000 + 'x' + Math.round(table_pos[key][1] * 10000) / 10000;
		}
		document.cookie = 'schema=' + encodeURIComponent(s.substr(1)) + '; expires=' + date + '; path=' + location.pathname + location.search;
	}
}
</script>

<div id="schema" style="height: <?php echo $top; ?>em;">
<?php
foreach ($schema as $name => $table) {
	echo "<div class='table' style='top: " . $table["pos"][0] . "em; left: " . $table["pos"][1] . "em;' onmousedown='mousedown(this, event);'>";
	echo '<a href="' . htmlspecialchars($SELF) . 'table=' . urlencode($name) . '"><strong>' . htmlspecialchars($name) . "</strong></a><br />\n";
	foreach ($table["fields"] as $field) {
		$val = htmlspecialchars($field["field"]);
		if (preg_match('~char|text~', $field["type"])) {
			$val = "<span class='char'>$val</span>";
		} elseif (preg_match('~date|time|year~', $field["type"])) {
			$val = "<span class='date'>$val</span>";
		} elseif (preg_match('~binary|blob~', $field["type"])) {
			$val = "<span class='binary'>$val</span>";
		} elseif (preg_match('~enum|set~', $field["type"])) {
			$val = "<span class='enum'>$val</span>";
		}
		echo ($field["primary"] ? "<em>$val</em>" : $val) . "<br />\n";
	}
	foreach ((array) $table["references"] as $target_name => $refs) {
		foreach ($refs as $left => $columns) {
			$left = $left / 10000 - $table_pos[$name][1];
			foreach ($columns as $source => $target) {
				echo '<div class="references" title="' . htmlspecialchars($target_name) . "\" style='left: $left" . "em; top: " . $table["fields"][$source]["pos"] . "em; padding-top: .5em;'><div style='border-top: 1px solid Gray; width: " . (-$left) . "em;'></div></div>\n";
			}
		}
	}
	foreach ((array) $referenced[$name] as $target_name => $refs) {
		foreach ($refs as $left => $columns) {
			$left = $left / 10000 - $table_pos[$name][1];
			foreach ($columns as $target) {
				echo '<div class="references" title="' . htmlspecialchars($target_name) . "\" style='left: $left" . "em; top: " . $table["fields"][$target]["pos"] . "em; width: " . (-$left) . "em; height: 1.25em; background: url(arrow.gif) no-repeat right center;'><div style='height: .5em; border-bottom: 1px solid Gray; width: " . (-$left) . "em;'></div></div>\n";
			}
		}
	}
	echo "</div>\n";
}
foreach ($schema as $name => $table) {
	foreach ((array) $table["references"] as $target_name => $refs) {
		foreach ($refs as $left => $ref) {
			$left /= 10000;
			$min_pos = $top;
			$max_pos = -10;
			foreach ($ref as $source => $target) {
				$pos1 = $table["pos"][0] + $table["fields"][$source]["pos"];
				$pos2 = $schema[$target_name]["pos"][0] + $schema[$target_name]["fields"][$target]["pos"];
				$min_pos = min($min_pos, $pos1, $pos2);
				$max_pos = max($max_pos, $pos1, $pos2);
			}
			echo "<div class='references' style='left: $left" . "em; top: $min_pos" . "em; padding: .5em 0;' /><div style='border-right: 1px solid Gray; height: " . ($max_pos - $min_pos) . "em;'></div></div>\n";
		}
	}
}
?>
</div>
