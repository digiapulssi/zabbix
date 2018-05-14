﻿<?php
/*
** Zabbix
** Copyright (C) 2001-2018 Zabbix SIA
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


class CAbsoluteTimeParserTest extends PHPUnit_Framework_TestCase {

	/**
	 * An array of absolute times and parsed results.
	 */
	public static function testProvider() {
		return [
			[
				'2018-04-15 12:45:34', 0,
				[
					'rc' => CParser::PARSE_SUCCESS,
					'match' => '2018-04-15 12:45:34'
				]
			],
			[
				'2018-04-15 12:45', 0,
				[
					'rc' => CParser::PARSE_SUCCESS,
					'match' => '2018-04-15 12:45'
				]
			],
			[
				'2018-04-15 12', 0,
				[
					'rc' => CParser::PARSE_SUCCESS,
					'match' => '2018-04-15 12'
				]
			],
			[
				'2018-04-15', 0,
				[
					'rc' => CParser::PARSE_SUCCESS,
					'match' => '2018-04-15'
				]
			],
			[
				'2018-04 12:45:34', 0,
				[
					'rc' => CParser::PARSE_SUCCESS,
					'match' => '2018-04 12:45:34'
				]
			],
			[
				'2018-04 12:45', 0,
				[
					'rc' => CParser::PARSE_SUCCESS,
					'match' => '2018-04 12:45'
				]
			],
			[
				'2018-04 12', 0,
				[
					'rc' => CParser::PARSE_SUCCESS,
					'match' => '2018-04 12'
				]
			],
			[
				'2018-04', 0,
				[
					'rc' => CParser::PARSE_SUCCESS,
					'match' => '2018-04'
				]
			],
			[
				'2018 12:45:34', 0,
				[
					'rc' => CParser::PARSE_SUCCESS,
					'match' => '2018 12:45:34'
				]
			],
			[
				'2018 12:45', 0,
				[
					'rc' => CParser::PARSE_SUCCESS,
					'match' => '2018 12:45'
				]
			],
			[
				'2018 12', 0,
				[
					'rc' => CParser::PARSE_SUCCESS,
					'match' => '2018 12'
				]
			],
			[
				'2018', 0,
				[
					'rc' => CParser::PARSE_SUCCESS,
					'match' => '2018'
				]
			]
		];
	}

	/**
	 * @dataProvider testProvider
	 *
	 * @param string $source
	 * @param int    $pos
	 * @param array  $expected
	 */
	public function testParse($source, $pos, $expected) {
		$parser = new CAbsoluteTimeParser();

		$this->assertSame($expected, [
			'rc' => $parser->parse($source, $pos),
			'match' => $parser->getMatch()
		]);
		$this->assertSame(strlen($expected['match']), $parser->getLength());
	}
}
