<?php
/*
** Zabbix
** Copyright (C) 2001-2013 Zabbix SIA
**
** This program is free software; you can redistribute it and/or modify
** it under the terms of the GNU General Public License as published by
** the Free Software Foundation; either version 2 of the License, or
** (at your option) any later version.
**
** This program is distributed in the hope that it will be useful,
** but WITHOUT ANY WARRANTY; without even the implied warranty of
** MERCHANTABILITY or FITNESS FOR A PARTICULAR PURPOSE. See the
** GNU General Public License for more details.
**
** You should have received a copy of the GNU General Public License
** along with this program; if not, write to the Free Software
** Foundation, Inc., 51 Franklin Street, Fifth Floor, Boston, MA  02110-1301, USA.
**/


function getRegexp($regexpId) {
	return DBfetch(DBselect(
		'SELECT re.*'.
		' FROM regexps re'.
		' WHERE regexpid='.zbx_dbstr($regexpId).
			andDbNode('re.regexpid')
	));
}

function getRegexpExpressions($regexpId) {
	$expressions = array();

	$dbExpressions = DBselect(
		'SELECT e.expressionid,e.expression,e.expression_type,e.exp_delimiter,e.case_sensitive'.
		' FROM expressions e'.
		' WHERE regexpid='.zbx_dbstr($regexpId).
			andDbNode('e.expressionid')
	);
	while ($expression = DBfetch($dbExpressions)) {
		$expressions[$expression['expressionid']] = $expression;
	}

	return $expressions;
}

function addRegexp(array $regexp, array $expressions) {
	try {
		// check required fields
		$dbFields = array('name' => null, 'test_string' => '');

		if (!check_db_fields($dbFields, $regexp)) {
			throw new Exception(_('Incorrect arguments passed to function').' [addRegexp]');
		}

		// check duplicate name
		$sql = 'SELECT re.regexpid'.
				' FROM regexps re'.
				' WHERE re.name='.zbx_dbstr($regexp['name']).
					andDbNode('re.regexpid');
		if (DBfetch(DBselect($sql))) {
			throw new Exception(_s('Regular expression "%s" already exists.', $regexp['name']));
		}

		$regexpIds = DB::insert('regexps', array($regexp));
		$regexpId = reset($regexpIds);

		addRegexpExpressions($regexpId, $expressions);
	}
	catch (Exception $e) {
		error($e->getMessage());
		return false;
	}

	return true;
}

function updateRegexp(array $regexp, array $expressions) {
	try {
		$regexpId = $regexp['regexpid'];
		unset($regexp['regexpid']);

		// check existence
		if (!getRegexp($regexpId)) {
			throw new Exception(_('Regular expression does not exist.'));
		}

		// check required fields
		$dbFields = array('name' => null);
		if (!check_db_fields($dbFields, $regexp)) {
			throw new Exception(_('Incorrect arguments passed to function').' [updateRegexp]');
		}

		// check duplicate name
		$dbRegexp = DBfetch(DBselect(
			'SELECT re.regexpid'.
			' FROM regexps re'.
			' WHERE re.name='.zbx_dbstr($regexp['name']).
				andDbNode('re.regexpid')
		));
		if ($dbRegexp && bccomp($regexpId, $dbRegexp['regexpid']) != 0) {
			throw new Exception(_s('Regular expression "%s" already exists.', $regexp['name']));
		}

		rewriteRegexpExpressions($regexpId, $expressions);

		DB::update('regexps', array(
			'values' => $regexp,
			'where' => array('regexpid' => $regexpId)
		));
	}
	catch (Exception $e) {
		error($e->getMessage());
		return false;
	}

	return true;
}

/**
 * Rewrite Zabbix regexp expressions.
 * If all fields are equal to existing expression, that expression is not touched.
 * Other expressions are removed and new ones created.
 *
 * @param string $regexpId
 * @param array  $expressions
 */
function rewriteRegexpExpressions($regexpId, array $expressions) {
	$dbExpressions = getRegexpExpressions($regexpId);

	$expressionsToAdd = array();
	$expressionsToUpdate = array();

	foreach ($expressions as $expression) {
		if (!isset($expression['expressionid'])) {
			$expressionsToAdd[] = $expression;
		}
		elseif (isset($dbExpressions[$expression['expressionid']])) {
			$expressionsToUpdate[] = $expression;
			unset($dbExpressions[$expression['expressionid']]);
		}
	}

	if ($dbExpressions) {
		$dbExpressionIds = zbx_objectValues($dbExpressions, 'expressionid');
		deleteRegexpExpressions($dbExpressionIds);
	}

	if ($expressionsToAdd) {
		addRegexpExpressions($regexpId, $expressionsToAdd);
	}

	if ($expressionsToUpdate) {
		updateRegexpExpressions($expressionsToUpdate);
	}
}

function addRegexpExpressions($regexpId, array $expressions) {
	$dbFields = array('expression' => null, 'expression_type' => null);

	foreach ($expressions as &$expression) {
		if (!check_db_fields($dbFields, $expression)) {
			throw new Exception(_('Incorrect arguments passed to function').' [add_expression]');
		}

		$expression['regexpid'] = $regexpId;
	}
	unset($expression);

	DB::insert('expressions', $expressions);
}

function updateRegexpExpressions(array $expressions) {
	foreach ($expressions as &$expression) {
		$expressionId = $expression['expressionid'];
		unset($expression['expressionid']);

		DB::update('expressions', array(
			'values' => $expression,
			'where' => array('expressionid' => $expressionId)
		));
	}
	unset($expression);
}

function deleteRegexpExpressions(array $expressionIds) {
	DB::delete('expressions', array('expressionid' => $expressionIds));
}

function expression_type2str($type = null) {
	$types = array(
		EXPRESSION_TYPE_INCLUDED => _('Character string included'),
		EXPRESSION_TYPE_ANY_INCLUDED => _('Any character string included'),
		EXPRESSION_TYPE_NOT_INCLUDED => _('Character string not included'),
		EXPRESSION_TYPE_TRUE => _('Result is TRUE'),
		EXPRESSION_TYPE_FALSE => _('Result is FALSE')
	);

	if ($type === null) {
		return $types;
	}
	elseif (isset($types[$type])) {
		return $types[$type];
	}
	else {
		return _('Unknown');
	}
}

function expressionDelimiters() {
	return array(
		',' => ',',
		'.' => '.',
		'/' => '/'
	);
}
