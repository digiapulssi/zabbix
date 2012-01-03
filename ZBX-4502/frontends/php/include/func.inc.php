<?php
/*
** Zabbix
** Copyright (C) 2000-2011 Zabbix SIA
**
** This program is free software; you can redistribute it and/or modify
** it under the terms of the GNU General Public License as published by
** the Free Software Foundation; either version 2 of the License, or
** (at your option) any later version.
**
** This program is distributed in the hope that it will be useful,
** but WITHOUT ANY WARRANTY; without even the implied warranty of
** MERCHANTABILITY or FITNESS FOR A PARTICULAR PURPOSE.  See the
** GNU General Public License for more details.
**
** You should have received a copy of the GNU General Public License
** along with this program; if not, write to the Free Software
** Foundation, Inc., 51 Franklin Street, Fifth Floor, Boston, MA  02110-1301, USA.
**/
?>
<?php
/************ REQUEST ************/
function redirect($url) {
	zbx_flush_post_cookies();
	$curl = new Curl($url);
	$curl->setArgument('sid', null);
	header('Location: '.$curl->getUrl());
	exit();
}

function jsRedirect($url, $timeout = null) {
	zbx_flush_post_cookies();

	$script = '';
	if (is_numeric($timeout)) {
		$script .= 'setTimeout(\'window.location="'.$url.'"\','.($timeout * 1000).')';
	}
	else {
		$script .= 'window.location.replace("'.$url.'");';
	}
	insert_js($script);
}

function get_request($name, $def = null) {
	return isset($_REQUEST[$name]) ? $_REQUEST[$name] : $def;
}

/************ COOKIES ************/
function get_cookie($name, $default_value = null) {
	if (isset($_COOKIE[$name])) {
		return $_COOKIE[$name];
	}
	return $default_value;
}

function zbx_setcookie($name, $value, $time = null) {
	setcookie($name, $value, isset($time) ? $time : 0);
	$_COOKIE[$name] = $value;
}

function zbx_unsetcookie($name) {
	zbx_setcookie($name, null, -99999);
	unset($_COOKIE[$name]);
}

function zbx_flush_post_cookies($unset = false) {
	global $ZBX_PAGE_COOKIES;

	if (isset($ZBX_PAGE_COOKIES)) {
		foreach ($ZBX_PAGE_COOKIES as $cookie) {
			if ($unset) {
				zbx_unsetcookie($cookie[0]);
			}
			else {
				zbx_setcookie($cookie[0], $cookie[1], $cookie[2]);
			}
		}
		unset($ZBX_PAGE_COOKIES);
	}
}

function zbx_set_post_cookie($name, $value, $time = null) {
	global $ZBX_PAGE_COOKIES;

	$ZBX_PAGE_COOKIES[] = array($name, $value, isset($time) ? $time : 0);
}

/************* DATE *************/
function getMonthCaption($num) {
	switch ($num) {
		case 1: return _('January');
		case 2: return _('February');
		case 3: return _('March');
		case 4: return _('April');
		case 5: return _('May');
		case 6: return _('June');
		case 7: return _('July');
		case 8: return _('August');
		case 9: return _('September');
		case 10: return _('October');
		case 11: return _('November');
		case 12: return _('December');
	}
	return _s('[Wrong value for month: "%s" ]', $num);
}

function getDayOfWeekCaption($num) {
	switch ($num) {
		case 1: return _('Monday');
		case 2: return _('Tuesday');
		case 3: return _('Wednesday');
		case 4: return _('Thursday');
		case 5: return _('Friday');
		case 6: return _('Saturday');
		case 0:
		case 7: return _('Sunday');
	}
	return _s('[Wrong value for day: "%s" ]', $num);
}

// Convert seconds (0..SEC_PER_WEEK) to string representation. For example, 212400 -> 'Tuesday 11:00'
function dowHrMinToStr($value, $display24Hours = false) {
	$dow = $value - $value % SEC_PER_DAY;
	$hr = $value - $dow;
	$hr -= $hr % SEC_PER_HOUR;
	$min = $value - $dow - $hr;
	$min -= $min % SEC_PER_MIN;

	$dow /= SEC_PER_DAY;
	$hr /= SEC_PER_HOUR;
	$min /= SEC_PER_MIN;

	if ($display24Hours && $hr == 0 && $min == 0) {
		$dow--;
		$hr = 24;
	}

	return sprintf('%s %02d:%02d', getDayOfWeekCaption($dow), $hr, $min);
}

// Convert Day Of Week, Hours and Minutes to seconds representation. For example, 2 11:00 -> 212400. false if error occured
function dowHrMinToSec($dow, $hr, $min) {
	if (zbx_empty($dow) || zbx_empty($hr) || zbx_empty($min) || !zbx_ctype_digit($dow) || !zbx_ctype_digit($hr) || !zbx_ctype_digit($min)) {
		return false;
	}

	if ($dow == 7) {
		$dow = 0;
	}

	if ($dow < 0 || $dow > 6) {
		return false;
	}

	if ($hr < 0 || $hr > 24) {
		return false;
	}

	if ($min < 0 || $min > 59) {
		return false;
	}

	return $dow * SEC_PER_DAY + $hr * SEC_PER_HOUR + $min * SEC_PER_MIN;
}

// Convert timestamp to string representation. Retun 'Never' if 0.
function zbx_date2str($format, $value = null) {
	static $weekdaynames, $weekdaynameslong, $months, $monthslong;

	if (is_null($value)) {
		$value = time();
	}
	elseif (!$value) {
		return _('Never');
	}

	if (!is_array($weekdaynames)) {
		$weekdaynames = array(
			0 => _('Sun'),
			1 => _('Mon'),
			2 => _('Tue'),
			3 => _('Wed'),
			4 => _('Thu'),
			5 => _('Fri'),
			6 => _('Sat')
		);
	}

	if (!is_array($weekdaynameslong)) {
		$weekdaynameslong = array(
			0 => _('Sunday'),
			1 => _('Monday'),
			2 => _('Tuesday'),
			3 => _('Wednesday'),
			4 => _('Thursday'),
			5 => _('Friday'),
			6 => _('Saturday')
		);
	}

	if (!is_array($months)) {
		$months = array(
			1 => _('Jan'),
			2 => _('Feb'),
			3 => _('Mar'),
			4 => _('Apr'),
			5 => _('May'),
			6 => _('Jun'),
			7 => _('Jul'),
			8 => _('Aug'),
			9 => _('Sep'),
			10 => _('Oct'),
			11 => _('Nov'),
			12 => _('Dec')
		);
	}

	if (!is_array($monthslong)) {
		$monthslong = array(
			1 => _('January'),
			2 => _('February'),
			3 => _('March'),
			4 => _('April'),
			5 => _('May'),
			6 => _('June'),
			7 => _('July'),
			8 => _('August'),
			9 => _('September'),
			10 => _('October'),
			11 => _('November'),
			12 => _('December')
		);
	}

	$rplcs = array(
		'l' => $weekdaynameslong[date('w', $value)],
		'F' => $monthslong[date('n', $value)],
		'D' => $weekdaynames[date('w', $value)],
		'M' => $months[date('n', $value)]
	);

	$output = '';
	$part = '';
	$length = zbx_strlen($format);
	for ($i = 0; $i < $length; $i++) {
		$pchar = $i > 0 ? zbx_substr($format, $i - 1, 1) : '';
		$char = zbx_substr($format, $i, 1);

		if ($pchar != '\\' && isset($rplcs[$char])) {
			$output .= (zbx_strlen($part) ? date($part, $value) : '').$rplcs[$char];
			$part = '';
		}
		else {
			$part .= $char;
		}
	}
	$output .= zbx_strlen($part) > 0 ? date($part, $value) : '';
	return $output;
}

// calculate and convert timestamp to string representation
function zbx_date2age($start_date, $end_date = 0, $utime = false) {
	if (!$utime) {
		$start_date = date('U', $start_date);
		if ($end_date) {
			$end_date = date('U', $end_date);
		}
		else {
			$end_date = time();
		}
	}
	return convertUnitsS(abs($end_date - $start_date));
}

function zbxDateToTime($strdate) {
	if (6 == sscanf($strdate, '%04d%02d%02d%02d%02d%02d', $year, $month, $date, $hours, $minutes, $seconds)) {
		return mktime($hours, $minutes, $seconds, $month, $date, $year);
	}
	elseif (5 == sscanf($strdate, '%04d%02d%02d%02d%02d', $year, $month, $date, $hours, $minutes)) {
		return mktime($hours, $minutes, 0, $month, $date, $year);
	}
	else {
		return time();
	}
}

/*************** CONVERTING ******************/
function rgb2hex($color) {
	$HEX = array(
		dechex($color[0]),
		dechex($color[1]),
		dechex($color[2])
	);
	foreach ($HEX as $id => $value) {
		if (zbx_strlen($value) != 2) {
			$HEX[$id] = '0'.$value;
		}
	}
	return $HEX[0].$HEX[1].$HEX[2];
}

function hex2rgb($color) {
	if ($color[0] == '#') {
		$color = substr($color, 1);
	}

	if (zbx_strlen($color) == 6) {
		list($r, $g, $b) = array($color[0].$color[1], $color[2].$color[3], $color[4].$color[5]);
	}
	elseif (zbx_strlen($color) == 3) {
		list($r, $g, $b) = array($color[0].$color[0], $color[1].$color[1], $color[2].$color[2]);
	}
	else {
		return false;
	}

	return array(hexdec($r), hexdec($g), hexdec($b));
}

function zbx_num2bitstr($num, $rev = false) {
	if (!is_numeric($num)) {
		return 0;
	}

	$sbin = 0;
	$strbin = '';

	$len = 32;
	if ($num > 2147483647) {
		$len = 64;
	}

	for ($i = 0; $i < $len; $i++) {
		$sbin = 1 << $i;
		$bit = ($sbin & $num) ? '1' : '0';
		if ($rev) {
			$strbin .= $bit;
		}
		else {
			$strbin = $bit.$strbin;
		}
	}
	return $strbin;
}

function str2mem($val) {
	$val = trim($val);
	$last = zbx_strtolower(zbx_substr($val, -1, 1));

	switch ($last) {
		// The 'G' modifier is available since PHP 5.1.0
		case 'g':
			$val *= 1024;
		case 'm':
			$val *= 1024;
		case 'k':
			$val *= 1024;
	}
	return $val;
}

function mem2str($size) {
	$prefix = _('B');
	if ($size > 1048576) {
		$size = $size / 1048576;
		$prefix = _('M');
	}
	elseif ($size > 1024) {
		$size = $size / 1024;
		$prefix = _('K');
	}
	return round($size, 6).$prefix;
}

function convertUnitsUptime($value) {
	if (($secs = round($value)) < 0) {
		$value = '-';
		$secs = -$secs;
	}
	else {
		$value = '';
	}

	$days = floor($secs / SEC_PER_DAY);
	$secs -= $days * SEC_PER_DAY;

	$hours = floor($secs / SEC_PER_HOUR);
	$secs -= $hours * SEC_PER_HOUR;

	$mins = floor($secs / SEC_PER_MIN);
	$secs -= $mins * SEC_PER_MIN;

	if ($days != 0) {
		$value .= _n('%1$d day, ', '%1$d days, ', $days);
	}
	$value .= sprintf('%02d:%02d:%02d', $hours, $mins, $secs);
	return $value;
}

function convertUnitsS($value) {
	if (floor(abs($value) * 1000) == 0) {
		$value = ($value == 0) ? '0'._('s') : '< 1'._('ms');
		return $value;
	}

	if (($secs = round($value * 1000) / 1000) < 0) {
		$value = '-';
		$secs = -$secs;
	}
	else {
		$value = '';
	}
	$n_unit = 0;

	if (($n = floor($secs / SEC_PER_YEAR)) != 0) {
		$value .= $n._('y').' ';
		$secs -= $n * SEC_PER_YEAR;
		if (0 == $n_unit) {
			$n_unit = 4;
		}
	}

	if (($n = floor($secs / SEC_PER_MONTH)) != 0) {
		$value .= $n._('m').' ';
		$secs -= $n * SEC_PER_MONTH;
		if (0 == $n_unit) {
			$n_unit = 3;
		}
	}

	if (($n = floor($secs / SEC_PER_DAY)) != 0) {
		$value .= $n._('d').' ';
		$secs -= $n * SEC_PER_DAY;
		if (0 == $n_unit) {
			$n_unit = 2;
		}
	}

	if ($n_unit < 4 && ($n = floor($secs / SEC_PER_HOUR)) != 0) {
		$value .= $n._('h').' ';
		$secs -= $n * SEC_PER_HOUR;
		if (0 == $n_unit) {
			$n_unit = 1;
		}
	}

	if ($n_unit < 3 && ($n = floor($secs / SEC_PER_MIN)) != 0) {
		$value .= $n._('m').' ';
		$secs -= $n * SEC_PER_MIN;
	}

	if ($n_unit < 2 && ($n = floor($secs)) != 0) {
		$value .= $n._('s').' ';
		$secs -= $n;
	}

	if ($n_unit < 1 && ($n = round($secs * 1000)) != 0) {
		$value .= $n._('ms');
	}

	return rtrim($value);
}

function convert_units($value, $units, $convert = ITEM_CONVERT_WITH_UNITS) {
	// special processing for unix timestamps
	if ($units == 'unixtime') {
		return zbx_date2str(_('Y.m.d H:i:s'), $value);
	}

	// special processing of uptime
	if ($units == 'uptime') {
		return convertUnitsUptime($value);
	}

	// special processing for seconds
	if ($units == 's') {
		return convertUnitsS($value);
	}

	// any other unit
	// black list wich do not require units metrics..
	$blackList = array('%', 'ms', 'rpm', 'RPM');

	if (in_array($units, $blackList) || (zbx_empty($units) && ($convert == ITEM_CONVERT_WITH_UNITS || $value < 1))) {
		if (abs($value) >= ZBX_UNITS_ROUNDOFF_THRESHOLD) {
			$value = round($value, ZBX_UNITS_ROUNDOFF_UPPER_LIMIT);
		}
		$value = sprintf('%.'.ZBX_UNITS_ROUNDOFF_LOWER_LIMIT.'f', $value);
		$value = preg_replace('/^([\-0-9]+)(\.)([0-9]*)[0]+$/U', '$1$2$3', $value);
		$value = rtrim($value, '.');

		if (zbx_empty($units)) {
			return $value;
		}
		else {
			return $value.' '.$units;
		}
	}

	switch ($units) {
		case 'Bps':
		case 'B':
			$step = 1024;
			$convert = $convert ? $convert : ITEM_CONVERT_NO_UNITS;
			break;
		case 'b':
		case 'bps':
			$convert = $convert ? $convert : ITEM_CONVERT_NO_UNITS;
		default:
			$step = 1000;
	}

	// init intervals
	static $digitUnits;
	if (is_null($digitUnits)) {
		$digitUnits = array();
	}

	if (!isset($digitUnits[$step])) {
		$digitUnits[$step] = array(
			array('pow' => -2, 'short' => _('µ'), 'long' => _('Micro')),
			array('pow' => -1, 'short' => _('m'), 'long' => _('Milli')),
			array('pow' => 0, 'short' => '', 'long' => ''),
			array('pow' => 1, 'short' => _('K'), 'long' => _('Kilo')),
			array('pow' => 2, 'short' => _('M'), 'long' => _('Mega')),
			array('pow' => 3, 'short' => _('G'), 'long' => _('Giga')),
			array('pow' => 4, 'short' => _('T'), 'long' => _('Tera')),
			array('pow' => 5, 'short' => _('P'), 'long' => _('Peta')),
			array('pow' => 6, 'short' => _('E'), 'long' => _('Exa')),
			array('pow' => 7, 'short' => _('Z'), 'long' => _('Zetta')),
			array('pow' => 8, 'short' => _('Y'), 'long' => _('Yotta'))
		);

		foreach ($digitUnits[$step] as $dunit => $data) {
			// skip mili & micro for values without units
			$digitUnits[$step][$dunit]['value'] = bcpow($step, $data['pow'], 9);
		}
	}

	if ($value < 0) {
		$abs = bcmul($value, '-1');
	}
	else {
		$abs = $value;
	}

	$valUnit = array('pow' => 0, 'short' => '', 'long' => '', 'value' => $value);
	if ($abs > 999 || $abs < 0.001) {
		foreach ($digitUnits[$step] as $dnum => $data) {
			if (bccomp($abs, $data['value']) > -1) {
				$valUnit = $data;
			}
			else {
				break;
			}
		}
		if (round($valUnit['value'], 6) > 0) {
			$valUnit['value'] = bcdiv(sprintf('%.6f',$value), sprintf('%.6f', $valUnit['value']), 6);
		}
		else {
			$valUnit['value'] = 0;
		}
	}

	switch ($convert) {
		case 0: $units = trim($units);
		case 1: $desc = $valUnit['short']; break;
		case 2: $desc = $valUnit['long']; break;
	}

	$value = preg_replace('/^([\-0-9]+)(\.)([0-9]*)[0]+$/U','$1$2$3', round($valUnit['value'], ZBX_UNITS_ROUNDOFF_UPPER_LIMIT));
	$value = rtrim($value, '.');

	return sprintf('%s %s%s', $value, $desc, $units);
}

/************* ZBX MISC *************/
function zbx_avg($values) {
	zbx_value2array($values);
	$sum = 0;
	foreach ($values as $value) {
		$sum = bcadd($sum, $value);
	}
	return bcdiv($sum, count($values));
}

// accepts parametr as integer either
function zbx_ctype_digit($x) {
	return ctype_digit(strval($x));
}

function zbx_empty($value) {
	if (is_null($value)) {
		return true;
	}
	if (is_array($value) && empty($value)) {
		return true;
	}
	if (is_string($value) && $value === '') {
		return true;
	}
	return false;
}

function zbx_is_int($var) {
	if (is_int($var)) {
		return true;
	}

	if (is_string($var)) {
		if (ctype_digit($var) || strcmp(intval($var), $var) == 0) {
			return true;
		}
	}
	else {
		if ($var > 0 && zbx_ctype_digit($var)) {
			return true;
		}
	}

	return preg_match("/^\-?\d{1,20}+$/", $var);
}

function zbx_array_diff($primary, $secondary, $field) {
	$fields1 = zbx_objectValues($primary, $field);
	$fields2 = zbx_objectValues($secondary, $field);

	$first = array_diff($fields1, $fields2);
	$first = zbx_toHash($first);

	$second = array_diff($fields2, $fields1);
	$second = zbx_toHash($second);

	$result = array(
		'first' => array(),
		'second' => array(),
		'both' => array()
	);

	foreach ($primary as $array) {
		if (!isset($array[$field])) {
			$result['first'][] = $array;
		}
		elseif (isset($first[$array[$field]])) {
			$result['first'][] = $array;
		}
		else {
			$result['both'][$array[$field]] = $array;
		}
	}

	foreach ($secondary as $array) {
		if (!isset($array[$field])) {
			$result['second'][] = $array;
		}
		elseif (isset($second[$array[$field]])) {
			$result['second'][] = $array;
		}
	}
	return $result;
}

function zbx_array_push(&$array, $add) {
	foreach ($array as $key => $value) {
		foreach ($add as $newKey => $newValue) {
			$array[$key][$newKey] = $newValue;
		}
	}
}

/**
 * Find if array has any duplicate values and return an array with info about them.
 * In case of no duplicates, empty array is returned.
 * Example of usage:
 *     $result = zbx_arrayFindDuplicates(
 *         array('a', 'b', 'c', 'c', 'd', 'd', 'd', 'e')
 *     );
 *     array(
 *         'd' => 3,
 *         'c' => 2,
 *     )
 * @param array $array
 * @return array
 */
function zbx_arrayFindDuplicates(array $array) {
	$countValues = array_count_values($array); // counting occurrences of every value in array
	foreach ($countValues as $value => $count) {
		if ($count <= 1) {
			unset($countValues[$value]);
		}
	}
	arsort($countValues); // sorting, so that the most duplicates would be at the top
	return $countValues;
}

/************* STRING *************/
if (!function_exists('zbx_stripslashes')) {
	function zbx_stripslashes($value) {
		if (is_array($value)) {
			foreach ($value as $id => $data) {
				$value[$id] = zbx_stripslashes($data);
			}
		}
		elseif (is_string($value)) {
			$value = stripslashes($value);
		}
		return $value;
	}
}

function zbx_nl2br($str) {
	$str_res = array();
	$str_arr = explode("\n", $str);
	foreach ($str_arr as $id => $str_line) {
		array_push($str_res, $str_line, BR());
	}
	return $str_res;
}

function zbx_htmlstr($str) {
	return str_replace(array('<', '>', '"'), array('&lt;', '&gt;', '&quot;'), $str);
}

function zbx_formatDomId($value) {
	return str_replace(array('[',']'), array('_', ''), $value);
}

function zbx_strlen($str) {
	if (defined('ZBX_MBSTRINGS_ENABLED')) {
		return mb_strlen($str);
	}
	else {
		return strlen($str);
	}
}

function zbx_strstr($haystack, $needle) {
	if (defined('ZBX_MBSTRINGS_ENABLED')) {
		$pos = mb_strpos($haystack, $needle);
		if ($pos !== false) {
			return mb_substr($haystack, $pos);
		}
		else {
			return false;
		}
	}
	else {
		return strstr($haystack, $needle);
	}
}

function zbx_stristr($haystack, $needle) {
	if (defined('ZBX_MBSTRINGS_ENABLED')) {
		$haystack_B = mb_strtoupper($haystack);
		$needle = mb_strtoupper($needle);

		$pos = mb_strpos($haystack_B, $needle);
		if ($pos !== false) {
			$pos = mb_substr($haystack, $pos);
		}
		return $pos;
	}
	else {
		return stristr($haystack, $needle);
	}
}

function zbx_substring($haystack, $start, $end = null) {
	if (!is_null($end) && $end < $start) {
		return '';
	}

	if (defined('ZBX_MBSTRINGS_ENABLED')) {
		if (is_null($end)) {
			$result = mb_substr($haystack, $start);
		}
		else {
			$result = mb_substr($haystack, $start, ($end - $start));
		}
	}
	else {
		if (is_null($end)) {
			$result = substr($haystack, $start);
		}
		else {
			$result = substr($haystack, $start, ($end - $start));
		}
	}
	return $result;
}

function zbx_substr($string, $start, $length = null) {
	if (defined('ZBX_MBSTRINGS_ENABLED')) {
		if (is_null($length)) {
			$result = mb_substr($string, $start);
		}
		else {
			$result = mb_substr($string, $start, $length);
		}
	}
	else {
		if (is_null($length)) {
			$result = substr($string, $start);
		}
		else {
			$result = substr($string, $start, $length);
		}
	}
	return $result;
}

function zbx_str_revert($str) {
	if (defined('ZBX_MBSTRINGS_ENABLED')) {
		$result = '';
		$stop = mb_strlen($str);
		for ($idx = 0; $idx < $stop; $idx++) {
			$result = mb_substr($str, $idx, 1).$result;
		}
	}
	else {
		$result = strrev($str);
	}
	return $result;
}

function zbx_strtoupper($str) {
	if (defined('ZBX_MBSTRINGS_ENABLED')) {
		return mb_strtoupper($str);
	}
	else {
		return strtoupper($str);
	}
}

function zbx_strtolower($str) {
	if (defined('ZBX_MBSTRINGS_ENABLED')) {
		return mb_strtolower($str);
	}
	else {
		return strtolower($str);
	}
}

function zbx_strpos($haystack, $needle, $offset = 0) {
	if (defined('ZBX_MBSTRINGS_ENABLED')) {
		return mb_strpos($haystack, $needle, $offset);
	}
	else {
		return strpos($haystack, $needle, $offset);
	}
}

function zbx_stripos($haystack, $needle, $offset = 0) {
	if (defined('ZBX_MBSTRINGS_ENABLED')) {
		$haystack = mb_convert_case($haystack, MB_CASE_LOWER);
		$needle = mb_convert_case($needle, MB_CASE_LOWER);
		return mb_strpos($haystack, $needle, $offset);
	}
	else {
		return stripos($haystack, $needle, $offset);
	}
}

function zbx_strrpos($haystack, $needle) {
	if (defined('ZBX_MBSTRINGS_ENABLED')) {
		return mb_strrpos($haystack, $needle);
	}
	else {
		return strrpos($haystack, $needle);
	}
}

function zbx_substr_replace($string, $replacement, $start, $length = null) {
	if (defined('ZBX_MBSTRINGS_ENABLED')) {
		$string_length = mb_strlen($string);

		if ($start < 0) {
			$start = max(0, $string_length + $start);
		}
		elseif ($start > $string_length) {
			$start = $string_length;
		}

		if ($length < 0) {
			$length = max(0, $string_length - $start + $length);
		}
		elseif ($length === null || $length > $string_length) {
			$length = $string_length;
		}

		if (($start + $length) > $string_length) {
			$length = $string_length - $start;
		}

		return mb_substr($string, 0, $start) . $replacement . mb_substr($string, $start + $length, $string_length - $start - $length);
	}
	else {
		return substr_replace($string, $replacement, $start, $length);
	}
}

/************* SELECT *************/
function selectByPattern(&$table, $column, $pattern, $limit) {
	$chunk_size = $limit;

	$rsTable = array();
	foreach ($table as $num => $row) {
		if (zbx_strtoupper($row[$column]) == zbx_strtoupper($pattern)) {
			$rsTable = array($num => $row) + $rsTable;
		}
		elseif ($limit > 0) {
			$rsTable[$num] = $row;
		}
		else {
			continue;
		}
		$limit--;
	}

	if (!empty($rsTable)) {
		$rsTable = array_chunk($rsTable, $chunk_size, true);
		$rsTable = $rsTable[0];
	}

	return $rsTable;
}

/************* SORT *************/
function natksort(&$array) {
	$keys = array_keys($array);
	natcasesort($keys);

	$new_array = array();

	foreach ($keys as $k) {
		$new_array[$k] = $array[$k];
	}

	$array = $new_array;
	return true;
}

function asort_by_key(&$array, $key) {
	if (!is_array($array)) {
		error(_('Incorrect type of asort_by_key.'));
		return array();
	}
	$key = htmlspecialchars($key);
	uasort($array, create_function('$a,$b', 'return $a[\''.$key.'\'] - $b[\''.$key.'\'];'));
	return $array;
}

// recursively sort an array by key
function zbx_rksort(&$array, $flags = null) {
	if (is_array($array)) {
		foreach ($array as $id => $data) {
			zbx_rksort($array[$id]);
		}
		ksort($array, $flags);
	}
	return $array;
}

/**
 * Class for sorting array by multiple fields.
 * When PHP 5.3+ arraives to Zabbix should be changed to function with closure.
 */
class ArraySorter {
	protected static $fields;

	private function __construct() {}

	/**
	 * Sort array by multiple fields
	 * @static
	 * @param array $array array to sort passed by reference
	 * @param array $fields fields to sort, can be either string with field name or array with 'field' and 'order' keys
	 */
	public static function sort(array &$array, array $fields) {
		foreach ($fields as $fid => $field) {
			if (!is_array($field)) {
				$fields[$fid] = array('field' => $field, 'order' => ZBX_SORT_UP);
			}
		}
		self::$fields = $fields;
		uasort($array, array('self', 'compare'));
	}

	/**
	 * Method to be used as callback for uasort function in sort method.
	 *
	 * @static
	 * @param $a
	 * @param $b
	 * @return int
	 */
	protected static function compare($a, $b) {
		foreach (self::$fields as $field) {
			if (!(isset($a[$field['field']]) && isset($b[$field['field']]))) {
				return 0;
			}

			if ($a[$field['field']] != $b[$field['field']]) {
				if ($field['order'] == ZBX_SORT_UP) {
					return strnatcasecmp($a[$field['field']], $b[$field['field']]);
				}
				else {
					return strnatcasecmp($b[$field['field']], $a[$field['field']]);
				}
			}
		}
		return 0;
	}
}

function order_result(&$data, $sortfield = null, $sortorder = ZBX_SORT_UP) {
	if (empty($data)) {
		return false;
	}

	if (is_null($sortfield)) {
		natcasesort($data);
		if ($sortorder != ZBX_SORT_UP) {
			$data = array_reverse($data, true);
		}
		return true;
	}

	$sort = array();
	foreach ($data as $key => $arr) {
		if (!isset($arr[$sortfield])) {
			return false;
		}
		$sort[$key] = $arr[$sortfield];
	}
	natcasesort($sort);

	if ($sortorder != ZBX_SORT_UP) {
		$sort = array_reverse($sort, true);
	}

	$tmp = $data;
	$data = array();
	foreach ($sort as $key => $val) {
		$data[$key] = $tmp[$key];
	}

	return true;
}

function order_by($def, $allways = '') {
	global $page;
	$orderString = '';

	$sortField = get_request('sort', CProfile::get('web.'.$page['file'].'.sort', null));
	$sortable = explode(',', $def);
	if (!str_in_array($sortField, $sortable)) {
		$sortField = null;
	}
	if ($sortField !== null) {
		$sortOrder = get_request('sortorder', CProfile::get('web.'.$page['file'].'.sortorder', ZBX_SORT_UP));
		$orderString .= $sortField.' '.$sortOrder;
	}
	if (!empty($allways)) {
		$orderString .= ($sortField === null) ? '' : ',';
		$orderString .= $allways;
	}
	return empty($orderString) ? '' : ' ORDER BY '.$orderString;
}

function unsetExcept(&$array, $allowedFields) {
	foreach ($array as $key => $value) {
		if (!isset($allowedFields[$key])) {
			unset($array[$key]);
		}
	}
}

function zbx_implodeHash($glue1, $glue2, $hash) {
	if (is_null($glue2)) {
		$glue2 = $glue1;
	}
	$str = '';
	foreach ($hash as $key => $value) {
		if (!empty($str)) {
			$str .= $glue2;
		}
		$str .= $key.$glue1.$value;
	}
	return $str;
}

// preserve keys
function zbx_array_merge() {
	$args = func_get_args();
	$result = array();
	foreach ($args as &$array) {
		if (!is_array($array)) {
			return false;
		}
		foreach ($array as $key => $value) {
			$result[$key] = $value;
		}
	}
	return $result;
}

function uint_in_array($needle, $haystack) {
	foreach ($haystack as $id => $value) {
		if (bccomp($needle, $value) == 0) {
			return true;
		}
	}
	return false;
}

function zbx_uint_array_intersect(&$array1, &$array2) {
	$result = array();
	foreach ($array1 as $key => $value) {
		if (uint_in_array($value, $array2)) {
			$result[$key] = $value;
		}
	}
	return $result;
}

function str_in_array($needle, $haystack, $strict = false) {
	if (is_array($needle)) {
		return in_array($needle, $haystack, $strict);
	}
	elseif ($strict) {
		foreach ($haystack as $id => $value) {
			if ($needle === $value) {
				return true;
			}
		}
	}
	else {
		foreach ($haystack as $id => $value) {
			if (strcmp($needle, $value) == 0) {
				return true;
			}
		}
	}
	return false;
}

function zbx_value2array(&$values) {
	if (!is_array($values) && !is_null($values)) {
		$tmp = array();
		if (is_object($values)) {
			$tmp[] = $values;
		}
		else {
			$tmp[$values] = $values;
		}
		$values = $tmp;
	}
}

// creates chain of relation parent -> childs, for all chain levels
function createParentToChildRelation(&$chain, $link, $parentField, $childField) {
	if (!isset($chain[$link[$parentField]])) {
		$chain[$link[$parentField]] = array();
	}

	$chain[$link[$parentField]][$link[$childField]] = $link[$childField];
	if (isset($chain[$link[$childField]])) {
		$chain[$link[$parentField]] = zbx_array_merge($chain[$link[$parentField]], $chain[$link[$childField]]);
	}
}

// object or array of objects to hash
function zbx_toHash(&$value, $field = null) {
	if (is_null($value)) {
		return $value;
	}
	$result = array();

	if (!is_array($value)) {
		$result = array($value => $value);
	}
	elseif (isset($value[$field])) {
		$result[$value[$field]] = $value;
	}
	else {
		foreach ($value as $key => $val) {
			if (!is_array($val)) {
				$result[$val] = $val;
			}
			elseif (isset($val[$field])) {
				$result[$val[$field]] = $val;
			}
		}
	}
	return $result;
}

// Value or Array to Object or Array of objects
function zbx_toObject(&$value, $field) {
	if (is_null($value)) {
		return $value;
	}
	$result = array();

	// Value or Array to Object or Array of objects
	if (!is_array($value)) {
		$result = array(array($field => $value));
	}
	elseif (!isset($value[$field])) {
		foreach ($value as $key => $val) {
			if (!is_array($val)) {
				$result[] = array($field => $val);
			}
		}
	}
	return $result;
}

function zbx_toArray($value) {
	if (is_null($value)) {
		return $value;
	}
	$result = array();

	if (!is_array($value)) {
		$result = array($value);
	}
	else {
		// reset() is needed to move internal array pointer to the beginning of the array
		reset($value);

		if (zbx_ctype_digit(key($value))) {
			$result = array_values($value);
		}
		elseif (!empty($value)) {
			$result = array($value);
		}
	}
	return $result;
}

// value OR object OR array of objects TO an array
function zbx_objectValues(&$value, $field) {
	if (is_null($value)) {
		return $value;
	}
	$result = array();

	if (!is_array($value)) {
		$result = array($value);
	}
	elseif (isset($value[$field])) {
		$result = array($value[$field]);
	}
	else {
		foreach ($value as $key => $val) {
			if (!is_array($val)) {
				$result[] = $val;
			}
			elseif (isset($val[$field])) {
				$result[] = $val[$field];
			}
		}
	}
	return $result;
}

function zbx_cleanHashes(&$value) {
	if (is_array($value)) {
		// reset() is needed to move internal array pointer to the beginning of the array
		reset($value);
		if (zbx_ctype_digit(key($value))) {
			$value = array_values($value);
		}
	}
	return $value;
}

function zbx_toCSV($values) {
	$csv = '';
	$glue = '","';
	foreach ($values as $row) {
		if (!is_array($row)) {
			$row = array($row);
		}
		foreach ($row as $num => $value) {
			if (is_null($value)) {
				unset($row[$num]);
			}
			else {
				$row[$num] = str_replace('"', '""', $value);
			}
		}
		$csv .= '"'.implode($glue, $row).'"'."\n";
	}
	return $csv;
}

function zbx_array_mintersect($keys, $array) {
	$result = array();

	foreach ($keys as $field) {
		if (is_array($field)) {
			foreach ($field as $sub_field) {
				if (isset($array[$sub_field])) {
					$result[$sub_field] = $array[$sub_field];
					break;
				}
			}
		}
		elseif (isset($array[$field])) {
			$result[$field] = $array[$field];
		}
	}
	return $result;
}

function zbx_str2links($text) {
	$result = array();
	if (empty($text)) {
		return $result;
	}
	preg_match_all('#https?://[^\n\t\r ]+#u', $text, $matches, PREG_OFFSET_CAPTURE);

	$start = 0;
	foreach ($matches[0] as $match) {
		$result[] = zbx_substr($text, $start, $match[1] - $start);
		$result[] = new CLink($match[0], $match[0], null, null, true);
		$start = $match[1] + zbx_strlen($match[0]);
	}
	$result[] = zbx_substr($text, $start, zbx_strlen($text));
	return $result;
}

function zbx_subarray_push(&$mainArray, $sIndex, $element = null) {
	if (!isset($mainArray[$sIndex])) {
		$mainArray[$sIndex] = array();
	}
	$mainArray[$sIndex][] = is_null($element) ? $sIndex : $element;
}

/**
 * Check if two arrays have same values.
 *
 * @param array $a
 * @param array $b
 * @param bool $strict
 * @return bool
 */
function array_equal(array $a, array $b, $strict=false) {
	if (count($a) !== count($b)) {
		return false;
	}
	sort($a);
	sort($b);
	return $strict ? $a === $b : $a == $b;
}

/*************** PAGE SORTING ******************/
// checking, setting AND saving sort params
function validate_sort_and_sortorder($sort = null, $sortorder = ZBX_SORT_UP) {
	global $page;

	$_REQUEST['sort'] = get_request('sort', CProfile::get('web.'.$page['file'].'.sort', $sort));
	$_REQUEST['sortorder'] = get_request('sortorder', CProfile::get('web.'.$page['file'].'.sortorder', $sortorder));

	if (!is_null($_REQUEST['sort'])) {
		$_REQUEST['sort'] = preg_replace('/[^a-z\.\_]/i', '', $_REQUEST['sort']);
		CProfile::update('web.'.$page['file'].'.sort', $_REQUEST['sort'], PROFILE_TYPE_STR);
	}

	if (!str_in_array($_REQUEST['sortorder'], array(ZBX_SORT_DOWN, ZBX_SORT_UP))) {
		$_REQUEST['sortorder'] = ZBX_SORT_UP;
	}

	CProfile::update('web.'.$page['file'].'.sortorder', $_REQUEST['sortorder'], PROFILE_TYPE_STR);
}

// creates header col for sorting in table header
function make_sorting_header($obj, $tabfield, $url = '') {
	global $page;

	$sortorder = ($_REQUEST['sort'] == $tabfield && $_REQUEST['sortorder'] == ZBX_SORT_UP) ? ZBX_SORT_DOWN : ZBX_SORT_UP;

	$link = new Curl($url);
	if (empty($url)) {
		$link->formatGetArguments();
	}
	$link->setArgument('sort', $tabfield);
	$link->setArgument('sortorder', $sortorder);

	$url = $link->getUrl();

	if ($page['type'] != PAGE_TYPE_HTML && defined('ZBX_PAGE_MAIN_HAT')) {
		$script = "javascript: return updater.onetime_update('".ZBX_PAGE_MAIN_HAT."','".$url."');";
	}
	else {
		$script = "javascript: redirect('".$url."');";
	}

	zbx_value2array($obj);
	$div = new CDiv();
	$div->setAttribute('style', 'float:left;');

	foreach ($obj as $enum => $el) {
		if (is_object($el) || $el === SPACE) {
			$div->addItem($el);
		}
		else {
			$div->addItem(new CSpan($el, 'underline'));
		}
	}
	$div->addItem(SPACE);

	$img = null;
	if (isset($_REQUEST['sort']) && $tabfield == $_REQUEST['sort']) {
		if ($sortorder == ZBX_SORT_UP) {
			$img = new CDiv(SPACE, 'icon_sortdown');
		}
		else {
			$img = new CDiv(SPACE, 'icon_sortup');
		}
		$img->setAttribute('style', 'float: left;');
	}
	$col = new CCol(array($div, $img), 'nowrap hover_grey');
	$col->setAttribute('onclick', $script);
	return $col;
}

function getPageSortField($def) {
	global $page;
	return get_request('sort', CProfile::get('web.'.$page['file'].'.sort', $def));
}

function getPageSortOrder($def = ZBX_SORT_UP) {
	global $page;
	return get_request('sortorder', CProfile::get('web.'.$page['file'].'.sortorder', $def));
}

/************* PAGING *************/
function getPagingLine(&$items, $autotrim = true) {
	global $page;
	$config = select_config();

	$search_limit = '';
	if ($config['search_limit'] < count($items)) {
		array_pop($items);
		$search_limit = '+';
	}

	$start = get_request('start', null);

	if (is_null($start)) {
		$last_page = CProfile::get('web.paging.lastpage');
		$start = ($last_page == $page['file']) ? CProfile::get('web.paging.start', 0) : 0;
	}

	$rows_per_page = CWebUser::$data['rows_per_page'];

	$cnt_items = count($items);
	$cnt_pages = ceil($cnt_items / $rows_per_page);

	if ($cnt_items < $start) {
		$start = 0;
	}
	CProfile::update('web.paging.lastpage', $page['file'], PROFILE_TYPE_STR);
	CProfile::update('web.paging.start', $start, PROFILE_TYPE_INT);

	if ($cnt_pages < 1) {
		$cnt_pages = 1;
	}

	$crnt_page = floor($start / $rows_per_page) + 1;

	if ($autotrim) {
		$items = array_slice($items, $start, $rows_per_page, true);
	}

	// viewed pages (better to use not odd)
	$view_pages = 11;

	$endPage = $crnt_page + floor($view_pages / 2);
	if ($endPage < $view_pages) {
		$endPage = $view_pages;
	}
	if ($endPage > $cnt_pages) {
		$endPage = $cnt_pages;
	}

	$startPage = ($endPage > $view_pages) ? $endPage - $view_pages + 1 : 1;

	$pageline = array();

	$table = BR();
	if ($cnt_pages > 1) {
		if ($startPage > 1) {
			$pagespan = new CSpan('<< '._('First'), 'darklink');
			$pagespan->setAttribute('onclick', 'javascript: openPage(0);');

			$pageline[] = $pagespan;
			$pageline[] = '&nbsp;&nbsp;';
		}

		if ($crnt_page > 1) {
			$pagespan = new CSpan('< '._('Previous'), 'darklink');
			$pagespan->setAttribute('onclick', 'javascript: openPage('.(($crnt_page - 2) * $rows_per_page).');');

			$pageline[] = $pagespan;
			$pageline[] = ' | ';
		}

		for ($p = $startPage; $p <= $cnt_pages; $p++) {
			if ($p > $endPage) {
				break;
			}

			if ($p == $crnt_page) {
				$pagespan = new CSpan($p, 'bold textcolorstyles');
			}
			else {
				$pagespan = new CSpan($p, 'darklink');
				$pagespan->setAttribute('onclick', 'javascript: openPage('.(($p - 1) * $rows_per_page).');');
			}

			$pageline[] = $pagespan;
			$pageline[] = ' | ';
		}

		array_pop($pageline);

		if ($crnt_page < $cnt_pages) {
			$pagespan = new CSpan(_('Next').' >', 'darklink');
			$pagespan->setAttribute('onclick', 'javascript: openPage('.($crnt_page * $rows_per_page).');');

			$pageline[] = ' | ';
			$pageline[] = $pagespan;
		}

		if ($p < $cnt_pages) {
			$pagespan = new CSpan(_('Last').' >>', 'darklink');
			$pagespan->setAttribute('onclick', 'javascript: openPage('.(($cnt_pages - 1) * $rows_per_page).');');

			$pageline[] = '&nbsp;&nbsp;';
			$pageline[] = $pagespan;
		}

		$table = new CTable(null, 'paging');
		$table ->addRow(new CCol($pageline));
	}

	$view_from_page = ($crnt_page-1) * $rows_per_page + 1;

	$view_till_page = $crnt_page * $rows_per_page;
	if ($view_till_page > $cnt_items) {
		$view_till_page = $cnt_items;
	}

	$page_view = array();
	$page_view[] = _('Displaying').SPACE;
	if ($cnt_items > 0) {
		$page_view[] = new CSpan($view_from_page, 'info');
		$page_view[] = SPACE._('to').SPACE;
	}

	$page_view[] = new CSpan($view_till_page, 'info');
	$page_view[] = SPACE._('of').SPACE;
	$page_view[] = new CSpan($cnt_items, 'info');
	$page_view[] = $search_limit;
	$page_view[] = SPACE._('found');

	$page_view = new CSpan($page_view);

	zbx_add_post_js('insertInElement("numrows",'.zbx_jsvalue($page_view->toString()).',"div");');

	return $table;
}

/************* DYNAMIC REFRESH *************/
function add_doll_objects($ref_tab, $pmid = 'mainpage') {
	$upd_script = array();
	foreach ($ref_tab as $id => $doll) {
		$upd_script[$doll['id']] = format_doll_init($doll);
	}
	zbx_add_post_js('initPMaster('.zbx_jsvalue($pmid).','.zbx_jsvalue($upd_script).');');
}

function format_doll_init($doll) {
	$args = array(
		'frequency' => 60,
		'url' => '',
		'counter' => 0,
		'darken' => 0,
		'params' => array()
	);
	foreach ($args as $key => $def) {
		if (isset($doll[$key])) {
			$obj[$key] = $doll[$key];
		}
		else {
			$obj[$key] = $def;
		}
	}
	$obj['url'] .= (zbx_empty($obj['url']) ? '?' : '&').'output=html';
	$obj['params']['favobj'] = 'hat';
	$obj['params']['favref'] = $doll['id'];
	$obj['params']['action'] = 'refresh';
	return $obj;
}

function get_update_doll_script($pmasterid, $dollid, $key, $value = '') {
	return 'PMasters['.zbx_jsvalue($pmasterid).'].dolls['.zbx_jsvalue($dollid).'].'.$key.'('.zbx_jsvalue($value).');';
}

function make_refresh_menu($pmid, $dollid, $cur_interval, $params = null, &$menu, &$submenu, $menu_type = 1) {
	if ($menu_type == 1) {
		$intervals = array('10' => 10, '30' => 30, '60' => 60, '120' => 120, '600' => 600, '900' => 900);
		$title = _('Refresh time in seconds');
	}
	elseif ($menu_type == 2) {
		$intervals = array('x0.25' => 0.25, 'x0.5' => 0.5, 'x1' => 1, 'x1.5' => 1.5, 'x2' => 2, 'x3' => 3, 'x4' => 4, 'x5' => 5);
		$title = _('Refresh time multiplier');
	}

	$menu['menu_'.$dollid][] = array($title, null, null, array('outer' => array('pum_oheader'), 'inner' => array('pum_iheader')));

	foreach ($intervals as $key => $value) {
		$menu['menu_'.$dollid][] = array(
			$key,
			'javascript: setRefreshRate('.zbx_jsvalue($pmid).','.zbx_jsvalue($dollid).','.$value.','.zbx_jsvalue($params).');'.
			'void(0);',
			null,
			array('outer' => ($value == $cur_interval) ? 'pum_b_submenu' : 'pum_o_submenu', 'inner' => array('pum_i_submenu')
		));
	}
	$submenu['menu_'.$dollid][] = array();
}

/************* MATH *************/
function bcfloor($number) {
	if (strpos($number, '.') !== false) {
		if (($tmp = preg_replace('/\.0+$/', '', $number)) !== $number) {
			$number = $tmp;
		}
		elseif ($number[0] != '-') {
			$number = bcadd($number, 0, 0);
		}
		else {
			$number = bcsub($number, 1, 0);
		}
	}
	return $number == '-0' ? '0' : $number;
}

function bcceil($number) {
	if (strpos($number, '.') !== false) {
		if (($tmp = preg_replace('/\.0+$/', '', $number)) !== $number) {
			$number = $tmp;
		}
		elseif ($number[0] != '-') {
			$number = bcadd($number, 1, 0);
		}
		else {
			$number = bcsub($number, 0, 0);
		}
	}
	return $number == '-0' ? '0' : $number;
}

function bcround($number, $precision = 0) {
	if (strpos($number, '.') !== false) {
		if ($number[0] != '-') {
			$number = bcadd($number, '0.' . str_repeat('0', $precision) . '5', $precision);
		}
		else {
			$number = bcsub($number, '0.' . str_repeat('0', $precision) . '5', $precision);
		}
	}
	elseif ($precision != 0) {
		$number .= '.' . str_repeat('0', $precision);
	}
	// according to bccomp(), '-0.0' does not equal '-0'. However, '0.0' and '0' are equal.
	$zero = ($number[0] != '-' ? bccomp($number, '0') == 0 : bccomp(substr($number, 1), '0') == 0);
	return $zero ? ($precision == 0 ? '0' : '0.' . str_repeat('0', $precision)) : $number;
}


/**
 * Sanitizes the data before outputting.
 *
 * @param mixed $data
 *
 * @return mixed
 */
function sanitize($data) {
	if (is_array($data)) {
		foreach ($data as &$value) {
			$value = sanitize($value);
		}
	}
	else {
		$data = zbx_htmlstr($data);
	}
	return $data;
}

?>
