<?php
/*
** Zabbix
** Copyright (C) 2001-2017 Zabbix SIA
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


require_once dirname(__FILE__).'/../include/class.czabbixtest.php';

class testMap extends CZabbixTest {
	/**
	 * Contains debug information for API calls.
	 * @var string
	 */
	public $debug = '';

	public function testMap_backup() {
		DBsave_tables('sysmaps');
		DBsave_tables('sysmaps_elements');
	}

	/**
	 * Create map tests data provider.
	 *
	 * @return array
	 */
	public static function createMapDataProvider() {
		return [
			// Success. Map A1, map B1 with submap having sysmapid=1 be created. Map with sysmapid=1 should exist.
			[
				'request_data' => [
					[
						'name' => 'A1',
						'width' => '800',
						'height' => '600',
						'backgroundid' => '0',
						'label_type' => '0',
						'label_location' => '0',
						'highlight' => '0',
						'expandproblem' => '1',
						'markelements' => '0',
						'show_unack' => '0',
						'grid_size' => '50',
						'grid_show' => '1',
						'grid_align' => '1',
						'label_format' => '0',
						'label_type_host' => '2',
						'label_type_hostgroup' => '2',
						'label_type_trigger' => '2',
						'label_type_map' => '2',
						'label_type_image' => '2',
						'label_string_host' => '',
						'label_string_hostgroup' => '',
						'label_string_trigger' => '',
						'label_string_map' => '',
						'label_string_image' => '',
						'iconmapid' => '0',
						'expand_macros' => '0',
						'severity_min' => '0',
						'userid' => '1',
						'private' => '1',
						'selements' => []
					],
					[
						'name' => 'B1',
						'width' => '800',
						'height' => '600',
						'backgroundid' => '0',
						'label_type' => '0',
						'label_location' => '0',
						'highlight' => '0',
						'expandproblem' => '1',
						'markelements' => '0',
						'show_unack' => '0',
						'grid_size' => '50',
						'grid_show' => '1',
						'grid_align' => '1',
						'label_format' => '0',
						'label_type_host' => '2',
						'label_type_hostgroup' => '2',
						'label_type_trigger' => '2',
						'label_type_map' => '2',
						'label_type_image' => '2',
						'label_string_host' => '',
						'label_string_hostgroup' => '',
						'label_string_trigger' => '',
						'label_string_map' => '',
						'label_string_image' => '',
						'iconmapid' => '0',
						'expand_macros' => '0',
						'severity_min' => '0',
						'userid' => '1',
						'private' => '1',
						'selements' => [
							[
								'elementtype' => '1',
								'iconid_off' => '151',
								'iconid_on' => '0',
								'label' => 'Y -> 1',
								'label_location' => '-1',
								'x' => '339',
								'y' => '227',
								'iconid_disabled' => '0',
								'iconid_maintenance' => '0',
								'elementsubtype' => '0',
								'areatype' => '0',
								'width' => '200',
								'height' => '200',
								'viewtype' => '0',
								'use_iconmap' => '1',
								'application' => '',
								'urls' => [],
								'elements' => [
									['sysmapid' => '1']
								],
								'permission' => 3
							]
						]
					]
				],
				'error' => null
			],
			// Fail. Map name is unique.
			[
				'request_data' => [
					[
						'name' => 'A1',
						'width' => '800',
						'height' => '600',
						'backgroundid' => '0',
						'label_type' => '0',
						'label_location' => '0',
						'highlight' => '0',
						'expandproblem' => '1',
						'markelements' => '0',
						'show_unack' => '0',
						'grid_size' => '50',
						'grid_show' => '1',
						'grid_align' => '1',
						'label_format' => '0',
						'label_type_host' => '2',
						'label_type_hostgroup' => '2',
						'label_type_trigger' => '2',
						'label_type_map' => '2',
						'label_type_image' => '2',
						'label_string_host' => '',
						'label_string_hostgroup' => '',
						'label_string_trigger' => '',
						'label_string_map' => '',
						'label_string_image' => '',
						'iconmapid' => '0',
						'expand_macros' => '0',
						'severity_min' => '0',
						'userid' => '1',
						'private' => '1',
						'selements' => []
					]
				],
				'error' => [
					'data' => 'Map "A1" already exists.'
				]
			]
		];
	}

	/**
	 * @dataProvider createMapDataProvider
	 */
	public function testMapCreate($request_data, $error_expected = null) {
		$response = $this->api_acall('map.create', $request_data, $this->debug);
		// Remove debug information.
		unset($response['error']['debug']);
		$error_json = array_key_exists('error', $response) ? json_encode($response['error'], JSON_PRETTY_PRINT) : null;

		if ($error_expected != null) {
			$this->assertArrayHasKey('error', $response, $error_json);
			$same_key_values = array_merge($response['error'], $error_expected);
			$this->assertSame($response['error'], $same_key_values);
		}
		else {
			$this->assertArrayNotHasKey('error', $response, $error_json);
		}
	}

	/**
	 * Update map tests data provider.
	 *
	 * @return array
	 */
	public static function updateMapDataProvider() {
		return [
			// Fail. Can not add map as sub map for itself.
			[
				'request_data' => [
					'sysmapid' => '10001',
					'selements' => [
						[
							'elementtype' => '1',
							'elements' => [
								[
									'sysmapid' => '10001'
								]
							]
						]
					]
				],
				'error' => [
					'data' => 'Cannot add "A" element of the map "A" due to circular reference.'
				]
			],
			// Fail. Can not add map with sub maps having virvular reference.
			[
				'request_data' => [
					[
						'sysmapid' => '10001',
						'name' => 'A',
						'selements' => [
							[
								'elementtype' => '1',
								'elements' => [
									[
										'sysmapid' => '10002'
									]
								]
							]
						]
					],
					[
						'sysmapid' => '10002',
						'name' => 'B',
						'selements' => [
							[
								'elementtype' => '1',
								'elements' => [
									[
										'sysmapid' => '10003'
									]
								]
							]
						]
					],
					[
						'sysmapid' => '10003',
						'name' => 'C',
						'selements' => [
							[
								'elementtype' => '1',
								'elements' => [
									[
										'sysmapid' => '10001'
									]
								]
							]
						]
					]
				],
				'error' => [
					'data' => 'Cannot add "B" element of the map "A" due to circular reference.'
				]
			],
			// Success. Can add existing map as sub map.
			[
				'request_data' => [
					[
						'sysmapid' => '10001',
						'name' => 'A',
						'selements' => [
							[
								'elementtype' => '1',
								'iconid_off' => '154',
								'elements' => [
									[
										'sysmapid' => '10002'
									]
								]
							]
						]
					],
					[
						'sysmapid' => '10003',
						'name' => 'C',
						'selements' => [
							[
								'elementtype' => '1',
								'iconid_off' => '154',
								'elements' => [
									[
										'sysmapid' => '10004'
									]
								]
							]
						]
					]
				],
				'error' => null
			],
			// Fail. Can not update map and create circular reference.
			[
				'request_data' => [
					[
						'sysmapid' => '10004',
						'name' => 'D',
						'selements' => [
							[
								'elementtype' => '1',
								'iconid_off' => '154',
								'elements' => [
									[
										'sysmapid' => '10001'
									]
								]
							]
						]
					],
					[
						'sysmapid' => '10002',
						'name' => 'B',
						'selements' => [
							[
								'elementtype' => '1',
								'iconid_off' => '154',
								'elements' => [
									[
										'sysmapid' => '10003'
									]
								]
							]
						]
					]
				],
				'error' => [
					'data' => 'Cannot add "A" element of the map "D" due to circular reference.'
				]
			]
		];
	}

	/**
	 * @dataProvider updateMapDataProvider
	 */
	public function testMapUpdate($request_data, $error_expected = null) {
		$response = $this->api_acall('map.update', $request_data, $this->debug);
		// Remove debug information.
		unset($response['error']['debug']);
		$error_json = array_key_exists('error', $response) ? json_encode($response['error'], JSON_PRETTY_PRINT) : null;

		if ($error_expected != null) {
			$this->assertArrayHasKey('error', $response, $error_json);
			$same_key_values = array_merge($response['error'], $error_expected);
			$this->assertSame($response['error'], $same_key_values);
		}
		else {
			$this->assertArrayNotHasKey('error', $response, $error_json);
		}
	}

	public function testMap_restore() {
		DBrestore_tables('sysmaps');
		DBrestore_tables('sysmaps_elements');
	}
}
