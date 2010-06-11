<?
function find_text($text, $delimit_start = '`', $delimit_end = '`') {
	$start = strpos($text, $delimit_start);
	if ($start === false) return false;

	$end = strpos(substr($text, $start + 1), $delimit_end);
	if ($end === false) return false;

	return substr($text, $start + 1, $end);
}


class Scaffold {
	public $table = array();

	function Scaffold($table) {
		$columns = array();
		foreach($table as $key => $value)
			if (is_array($value))
				$columns[] = array('tipo' => $value, 'nombre' => $key);
		$this->table = $table;
		$this->columns = $columns;
	}

	function listtable() {
		$column_array = array();
		$return_string = "<?php\n";

		if ($this->table['include'] != '')
			$return_string .= "include('../{$this->table['include']}');\n";

		$return_string .= "\nprint_header('" . ucwords($this->table['name']) . "');

if (isset(\$_GET['msg'])) echo '<p id=\"msg\">'.\$_GET['msg'].'</p>';

/* Default search criteria (may be overriden by search form) */
\$conds = 'TRUE';
include('{$this->table['search_page']}');

/* Default paging criteria (may be overriden by paging functions) */
\$start = 0;
\$lim   = 100;
include('{$this->table['paging_page']}');

/* Get selected entries! */
\$sql = \"SELECT * FROM `{$this->table['name']}` WHERE \$conds \" . get_order('{$this->table['name']}') . \" LIMIT \$start,\$lim\";

echo '<table>\n";
		$return_string .= "  <tr>\n";
		foreach($this->columns as $v) {
			$return_string .= '    <th>'. $this->title($v['nombre']) . ' \' . put_order(\''.$v['nombre']."') . '</th>\n";
		}
		$return_string .= "  </tr>';

\$r = mysql_query(\$sql) or trigger_error(mysql_error());
while(\$row = mysql_fetch_array(\$r)) {\n";
		$return_string .= "	echo '  <tr>\n";

		foreach($this->columns as $v) {
			if($v['tipo']['blob'])
				$val = "nl2br(\$row['".$v['nombre']."'])";
			elseif($v['tipo']['date'] or $v['tipo']['datetime'])
				$val = "humanize(\$row['".$v['nombre']."'])";
			else
				$val = "\$row['".$v['nombre']."']";

			$return_string .= "    <td>' . $val . '</td>\n";
		}
		$return_string .= "    <td><a href=\"{$this->table['crud_page']}?{$this->table['id_key']}=' . \$row['{$this->table['id_key']}'] . '\">Edit</a></td>
    <td><a href=\"{$this->table['crud_page']}?delete=1&amp;{$this->table['id_key']}=' . \$row['{$this->table['id_key']}'] . '\" onclick=\"return confirm(\'Are you sure?\')\">Delete</a></td>
  </tr>';\n";
		$return_string .= "}\n\n";
		$return_string .= "echo '</table>

<p><a href=\"{$this->table['crud_page']}\">New entry</a></p>';

print_footer();
?>";

		return $return_string;
	}

	function crud_page() {
		$return_string = "<?php\n";
		if ($this->table['include'] != '')
			$return_string .= "include('../{$this->table['include']}');\n\n";

		$return_string .= "if (isset(\$_GET['delete'])) {
	mysql_query(\"DELETE FROM `{$this->table['name']}` WHERE `{$this->table['id_key']}` = '\$_GET[{$this->table['id_key']}]}'\");
	\$msg = (mysql_affected_rows() ? 'Row deleted.' : 'Nothing deleted.');
	header('Location: {$this->table['list_page']}?msg='.\$msg);
}

\${$this->table['id_key']} = (isset(\$_GET['{$this->table['id_key']}']) ? \$_GET['{$this->table['id_key']}'] : 0);
\$action = (\${$this->table['id_key']} ? 'Edit' : 'Add new');\n\n";

		$column_array = array();

		$return_string .= "if (isset(\$_POST['submitted'])) {
	foreach(\$_POST AS \$key => \$value) { \$_POST[\$key] = mysql_real_escape_string(\$value); }\n";
		$insert = "REPLACE INTO `{$this->table['name']}` (";
		$counter = 0;
		foreach($this->columns as $v) {
			$insert .= "`$v[nombre]`" ;
			if ($counter < count($this->columns) - 1)
				$insert .= ", ";
			$counter++;
		}
		$insert .= ") VALUES (\${$this->table['id_key']}, ";

		$counter = 0;
		foreach ($this->columns as $v) {
			if ($v['nombre'] != $this->table['id_key']) {
				$field = $v['nombre'];
				$val = parse($field, $v['tipo']);
				$insert .= "'$val'";
				if ($counter < count($this->columns) - 2)
					$insert .= ", ";
				$counter++;
			}
		}
		$insert .= ');';

		$return_string .= "	\$sql = \"$insert\";
	mysql_query(\$sql) or die(mysql_error());
	\$msg = (mysql_affected_rows()) ? 'Edited row.' : 'Nothing changed.';
	header('Location: {$this->table['list_page']}?msg='.\$msg);
}

print_header(\"\$action {$this->table['name']}\");

\$row = mysql_fetch_array ( mysql_query(\"SELECT * FROM `{$this->table['name']}` WHERE `{$this->table['id_key']}` = '\${$this->table['id_key']}' \"));
?>\n";

$return_string .= $this->build_form($this->columns, 'Add / Edit') . '
<?
print_footer();
?>';

		return $return_string;
	}

	function session_auth() {
		$return_string = "<?php
include('".$this->table['include']."');
\$msg = \$_GET['msg'];

if (isset(\$_POST['user']) && isset(\$_POST['pass'])) {
	if ((strlen(\$_POST['user']) > 0) and (strlen(\$_POST['pass']) > 0)
	  and (\$login[\$_POST['user']] == \$_POST['pass'])) {
		\$_SESSION['user_logged_in'] = true;
		header('Location: {$this->table['list_page']}?msg=Logged in.');
		exit;
	} else {
		unset(\$_SESSION['user_logged_in']);
		\$msg = 'Sorry, wrong user id or password.';
	}
}

print_header('Login - ".ucwords($this->table['name'])."');

if (\$_GET['action'] == 'logout') {
	unset(\$_SESSION['user_logged_in']);
	session_destroy();
}

if (strlen(\$msg) > 0) echo '<p id=\"msg\">'.\$msg.'</p>';
if (\$_SESSION['user_logged_in'] != true) {
?>\n";
$return_string .= '<form action="" method="post">
<p>You need to log in to edit this database.</p>
<ul>
  <li><label>User: <input type="text" name="user" value="<?= stripslashes($_POST[user]) ?>" /></label></li>
  <li><label>Pass: <input type="password" name="pass" /></label></li>
</ul>
<p><input type="submit" value="Login" /></p>
</form>
<?
} else {
	echo \'<p><a href="'.$this->table['list_page'].'">Go to Listing</a></p>\';
}

print_footer();
?>';
		return $return_string;
	}

	function search_page() {
		$return_string = $this->build_form($this->columns, 'Search', 'get', '_GET');
		$return_string .= "\n\n<?php\n";

		$return_string .= '$opts = array(';
		$cols = '';
		foreach($this->columns as $col) {
			$cols .= "'{$col['nombre']}_opts', ";
		}
		$return_string .= substr($cols, 0, -2) . ");\n"
. '/* Sorround "contains" search term between %% */
foreach ($opts as $o) {
	if ($_GET[$o] == \'like\') {
		$v = substr($o, 0, -5);
		$_GET[$v] = \'%\' . $_GET[$v] . \'%\';
	}
}'."\n\n";
		foreach($this->columns as $col) {
			$return_string .= "if (search_by('{$col['nombre']}'))
	\$conds .= \" AND {$col['nombre']} {\$_GET['{$col['nombre']}_opts']} '{\$_GET['{$col['nombre']}']}'\";\n";
		}

		return $return_string . "?>";
	}

	function paging_page() {
		$return_string = '<?php
$page = ($_GET[\'page\'] ? $_GET[\'page\'] : 1);
$start = ($page-1) * $lim;

$num_results = mysql_result(mysql_query(\'SELECT COUNT('.$this->table['id_key'].') AS tot FROM `'.$this->table['name'].'` WHERE \' .  $conds), 0);
$num_pages = ceil($num_results / $lim);

/* Mantain search and sorting parameters */
$pars = explode("[&]", $_SERVER[\'argv\'][0]);
$res = array();
foreach($pars as $n => $par) {
	$p = explode("[=]", $par);
	if ($p[0] != \'page\')
		array_push($res, join(\'=\', $p));
}
$pars = join("&amp;", $res);

echo \'<p>Pages: \';
echo ($page-1 > 0 ? \'<a href="?\'.$pars.\'&amp;page=\'.($page-1).\'">Previous</a>\' : \'Previous\') . \' | \';
if ($num_pages <= 25) {
	options_range(1, $num_pages);
} else {
	if ($page <= 5 or ($page >= $num_pages-4 and !($page > $num_pages))) {
		options_range(1,5);
		echo "... |\n";
		options_range($num_pages-4, $num_pages);
	} elseif (5 < $page and $page <= $num_pages-4) {
		options_range(1,5);
		echo "... |\n";
		options_range(max(6,$page-3), min($page+3, $num_pages-5));
		echo "... |\n";
		options_range($num_pages-4, $num_pages);
	}
}
echo ($page+1 <= $num_pages ? \'<a href="?\'.$pars.\'&amp;page=\'.($page+1).\'">Next</a>\' : \'Next\');
echo "</p>

<p style=\"text-align:center;font-size:.9em\">(Showing entries $start to "
  . min($start+$lim, $num_results) . " out of $num_results.)</p>\n\n";

function options_range($start, $end) {
	global $pars;
	for ($i=$start; $i <= $end; $i++)
		echo ($i == $_GET[\'page\'] ? "<strong>$i</strong>" : "<a href=\"?$pars&amp;page=$i\">$i</a>") . " |\n";
}
?>';

		return $return_string;
	}

	function get_functions() {
		$return_string = '<?php
/* General configuration */
/* MySQL */
$mysql_host = \'localhost\';
$mysql_user = \'root\';
$mysql_pass = \'\';
$dbname = \'phpsc_db\';

/* Allowed users  */
$login = array(
	\'admin\' => \'pass\'
);


/* phpscaffold code - you may leave this untouched */

/* Session based or basic HTTP authentication. */
$sess_auth = true;

if ($sess_auth == true) {
	session_start();
	if ((!preg_match(\'/inc.auth.php/\', $_SERVER[\'PHP_SELF\']))
	  and (!isset($_SESSION[\'user_logged_in\'])
	  or $_SESSION[\'user_logged_in\'] !== true)) {
		header(\'Location: ../inc.auth.php\');
		exit;
	}
} else {
	function doAuth() {
		header(\'WWW-Authenticate: Basic realm="Protected Area"\');
		header(\'HTTP/1.0 401 Unauthorized\');
		echo \'Valid username / password required.\';
		exit;
	}

	function checkUser() {
		global $login;
		if($_SERVER[\'PHP_AUTH_USER\']!=\'\' && $_SERVER[\'PHP_AUTH_PW\']!=\'\') {
			return ($login[$_SERVER[\'PHP_AUTH_USER\']] == $_SERVER[\'PHP_AUTH_PW\']);
		}
		return false;
	}

	if (!isset($_SERVER[\'PHP_AUTH_USER\']) or !checkUser()) {
		doAuth();
	}
}


// DB connect
$link = @mysql_connect($mysql_host, $mysql_user, $mysql_pass);
if (!$link)
	die(\'Not connected: \' . mysql_error());
if (!mysql_select_db($dbname))
	die ("Can\'t use $dbname: " . mysql_error());

function print_header($title) {
?>
<!DOCTYPE html PUBLIC "-//W3C//DTD XHTML 1.0 Strict//EN"
 "http://www.w3.org/TR/xhtml1/DTD/xhtml1-strict.dtd">
<html xmlns="http://www.w3.org/1999/xhtml" xml:lang="es">
<head>
<meta http-equiv="Content-Type" content="text/html; charset=UTF-8" />
<title><?= $title ?></title>
<style type="text/css" media="screen">
body {
  font: .8em "Trebuchet MS", Trebuchet, Verdana, Sans-Serif;
}
#msg {
  padding: 5px 10px;
  border: 1px solid #3a3;
  background: #dfd;
  font-weight: bold;
}
label span {
  display: block;
  float: left;
  width: 9em;
}
form {
  width: 50em;
  margin: auto;
}
</style>
<script type="text/javascript" src="http://ajax.googleapis.com/ajax/libs/jquery/1.4.2/jquery.min.js"></script>
</head>

<body>
<h1><?= $title ?>
  <? if ($_SESSION[\'user_logged_in\'] == true) echo \'<span style="font-size:12px"><a href="../inc.auth.php?action=logout&amp;msg=You have been logged out.">[Logout]</a></p>\'; ?></h1>
<?
}

function print_footer() {
	$index = preg_match(\'/'.$this->table['list_page'].'/\', $_SERVER[\'PHP_SELF\']);
	$login = preg_match(\'/inc.auth.php/\', $_SERVER[\'PHP_SELF\']);
	if (!$index and !$login)
		echo \'<p><a href="'.$this->table['list_page'].'">Back to Listing</a></p>\';
	echo "</body>\n</html>";
}'."

function input_date(\$field, \$value) {
	\$day  = \$field . '_day';
	\$mth  = \$field . '_mth';
	\$year = \$field . '_year';

	\$sel_day  = (substr(\$value,8,2) ? substr(\$value,8,2) : date(d));
	\$sel_mth  = (substr(\$value,5,2) ? substr(\$value,5,2) : date(m));
	\$sel_year = (substr(\$value,0,4) > 0 ? substr(\$value,0,4) : date(Y));

	\$ret = select_range(\$day, \$sel_day, 1, 31) . '/';
	\$ret .= select_range(\$mth, \$sel_mth, 1, 12) . '/';
	\$ret .= select_range(\$year, \$sel_year, 2009, 2020);

	return \$ret;
}

function input_datetime(\$field, \$value) {
	\$seg  = \$field . '_seg';
	\$min  = \$field . '_min';
	\$hour = \$field . '_hour';

	\$sel_seg  = (substr(\$value,17,2) ? substr(\$value,17,2) : date(s));
	\$sel_min  = (substr(\$value,14,2) ? substr(\$value,14,2) : date(i));
	\$sel_hour = (substr(\$value,11,2) ? substr(\$value,11,2) : date(h));

	\$ret = input_date(\$field, \$value) . ' @ ';
	\$ret .= select_range(\$hour, \$sel_hour, 0, 23) . ':';
	\$ret .= select_range(\$min, \$sel_min, 0, 59, 5) . ':';
	\$ret .= select_range(\$seg, \$sel_seg, 0, 59, 5);

	return \$ret;
}

function search_options(\$field, \$selected = 'is', \$type = 'int') {
	\$ret = \"<select name=".'\"{$field}_opts\">\n"'.";
	if (\$type == 'int') {
		\$selected = (\$selected == 'is') ? '=' : \$selected;
		\$options = array('=' => '=',
			'<' => '&lt;',
			'<=' => '≤',
			'>' => '&gt;',
			'>=' => '≥');
	} else { /* is string */
		\$options = array('=' => 'is', 'like' => 'contains');
	}
	foreach (\$options as \$k => \$v) {
		\$sel = (\$selected == \$k ? ' selected=\"selected\"' : '');
		\$ret .= \"  <option value=\\\"\$k\\\"\$sel>\$v</option>\\n\";
	}
	return \$ret .= \"</select>\\n\";
}

function select_range(\$name, \$selected, \$start, \$finish, \$range = 1) {
	\$ret = '<select name=\"'.\$name.'\">';
	for(\$i=\$start; \$i <= \$finish; \$i += \$range) {
		\$sel = (\$selected == \$i ? ' selected=\"selected\"' : '');
		\$ret .= \"<option\$sel>\$i</option>\\n\";
	}
	\$ret .= '</select>';
	return \$ret;
}

/*
*	Build select menu with data from a model.
*/
function build_options(\$table_name, \$name_col, \$selected = null, \$id_col = 'id') {
	\$sql = \"SELECT \$id_col, \$name_col FROM \$table_name\";
	\$r = mysql_query(\$sql) or trigger_error(mysql_error());
	\$ret = '<select name=\"'.\$table_name.'\">';
	while(\$row = mysql_fetch_array(\$r)) {
		\$sel = (\$selected == \$row[\$id_col] ? ' selected=\"selected\"' : '');
		\$ret .= \"<option value=\\\"\$row[\$id_col]\\\"\$sel>\$row[\$name_col]</option>\\n\";
	}
	return \$ret . '</select>';
}

/*
*	From separate _GET variables (YYYY, (M)M, (D)D)
*	 to MySQL string (YYYY-MM-DD)
*/
function parse_date_vars(\$date_field) {
	\$year = \$_GET[\$date_field.'_year'];
	\$mth  = \$_GET[\$date_field.'_mth'];
	\$day  = \$_GET[\$date_field.'_day'];

	if(strlen(\$mth) == 1) \$mth = '0'.\$mth;
	if(strlen(\$day) == 1) \$day = '0'.\$day;

	return \$year.'-'.\$mth.'-'.\$day;
}

/*
*	_GET variable set with non-trivial value?
*/
function search_by(\$var) {
	return (isset(\$_GET[\$var]) and strlen(\$_GET[\$var]) > 0);
}

function put_order(\$col) {
	\$pars = explode(\"[&]\", \$_SERVER['argv'][0]);
	\$res = array();
	foreach(\$pars as \$n => \$par) {
		\$p = explode(\"[=]\", \$par);
		if (\$p[0] != 'order' and \$p[0] != 'col')
			array_push(\$res, join('=', \$p));
	}
	array_push(\$res, \"col=\$col\");
	\$pars = join(\"&amp;\", \$res);
	return \"<a href=\\\"\$_SERVER[PHP_SELF]?\$pars&amp;order=ASC\\\">↑</a>
	<a href=\\\"\$_SERVER[PHP_SELF]?\$pars&amp;order=DESC\\\">↓</a>\";
}

function get_order(\$table, \$default = '{$this->table['id_key']} ASC') {
	if (isset(\$_GET['order']) and isset(\$_GET['col']))
		return \"ORDER BY \$table.{\$_GET['col']} {\$_GET['order']}\";
	else
		return \"ORDER BY \$default\";
}

function humanize(\$date) {
	\$pattern = (strlen(\$date) == 10 ? 'd/m/Y' : 'd/m/Y @ h:i:s');
	return date(\$pattern, strtotime(\$date));
}

function pr(\$arr) {
	echo '<pre>';
	print_r(\$arr);
	echo '</pre>';
}
?>";
		return $return_string;
	}


	function build_form($cols, $submit, $method = 'post', $value = 'row') {
		$is_search = ($submit == 'Search');

		$legend = $submit;
		if ($is_search)
			$legend = "<a href=\"#\" onclick=\"$('#search-form').slideToggle()\">$legend</a>";

		$res = '<form action="" method="'.$method.'">
<fieldset>
<legend>' . $legend . '</legend>
<div' . ($is_search ? ' id="search-form" style="display:none"' : '') . '>
<ul>
';
		foreach ($cols as $col)
			$res .= $this->form_input($col, $value, $is_search);

		$res .= '</ul>
<p><input type="hidden" value="1" name="submitted" />
  <input type="submit" value="'.$submit.'" /></p>
</div>
</fieldset>
</form>';
		return $res;
	}

	function form_input($col, $value, $is_search = false) {
	if ($col['nombre'] != $this->table['id_key']) {

		$text = '  <li><label><span>' . $this->title($col['nombre']) . ":</span>\n";
		if ($is_search)
			$text .= "    <?= search_options('".$col['nombre']."', \$_GET['".$col['nombre']."_opts']) ?></label>\n";
		$text .= '    ';

		/* Takes value either from $_GET['id'] or from $row['id'] */
		$val = '$'.$value.'[\''.$col['nombre'].'\']';

		if ($col['tipo']['bool'])
			$text .= '<input type="checkbox" name="'.$col['nombre'].'" value="1" <?= ('.$val.' == 1 ? \'checked="checked"\' : \'\') ?> />';
		elseif ($col['tipo']['date'])
			$text .= '<?= input_date(\''.$col['nombre'].'\', '.$val.') ?>';
		elseif ($col['tipo']['datetime'])
			$text .= '<?= input_datetime(\''.$col['nombre'].'\', '.$val.') ?>';
		elseif ($col['tipo']['blob'])
			$text .= '<textarea name="'.$col['nombre'].'" cols="40" rows="10"><?= stripslashes('.$val.') ?></textarea>';
		else
			$text .= '<input type="text" name="'.$col['nombre'].'" value="<?= stripslashes('.$val.') ?>" />';

		if (!$is_search) $text .= '</label>'; /* Could be closed after search_options */
		return $text . "</li>\n";
	} /* If not id column */
	} /* form_input function */

	function title($name) {
		return ucwords(str_replace('_', ' ', trim($name)));
	}
}

function parse($field, $type) {
	if ($type['date']) {
		$day  = $field . '_day';
		$mth  = $field . '_mth';
		$year = $field . '_year';
		$val = "\$_POST[$year]-\$_POST[$mth]-\$_POST[$day]";
	} elseif ($type['datetime']) {
		$seg  = $field . '_seg';
		$min  = $field . '_min';
		$hour = $field . '_hour';
		$day  = $field . '_day';
		$mth  = $field . '_mth';
		$year = $field . '_year';
		$val = "\$_POST[$year]-\$_POST[$mth]-\$_POST[$day] \$_POST[$hour]:\$_POST[$min]:\$_POST[$seg]";
	} else {
		$val = "\$_POST[$field]";
	}
	return $val;
}

/* Given a table, compute it's Primary Key */
function get_primary_key($text) {
	$text = explode("\n", $text);
	foreach ($text as $line) {
		if (preg_match('/PRIMARY KEY/', $line)) {
			/* Remove parenthesis */
			$key = explode('(', $line);
			$key = substr($key[1], 0, -1);
			/* Remove surrounding comillas? */
			if ($key[0] == '`') $key = substr($key,1,-2);
		}
	}
	return $key;
}

function pr($arr) {
	echo '<pre>';
	print_r($arr);
	echo '</pre>';
}
?>
