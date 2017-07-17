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

class CNavigationWidgetForm extends CWidgetForm {

	public function __construct($data) {
		parent::__construct($data);

		// Widget reference field.
		$field_reference = new CWidgetFieldReference();
		if (array_key_exists($field_reference->getName(), $data)) {
			$field_reference->setValue($data[$field_reference->getName()]);
		}
		$this->fields[] = $field_reference;

		// map widget reference
		$field_map_widget = (new CWidgetFieldWidgetListComboBox('map_widget_reference', _('Map'), 'type', 'sysmap'));
		if (array_key_exists('map_widget_reference', $data)) {
			$field_map_widget->setValue($data['map_widget_reference']);
		}

		$this->fields[] = $field_map_widget;

		// Register dynamically created item fields.
		foreach ($data as $field_key => $value) {
			preg_match('/^map\.name\.(\d+)$/', $field_key, $field_details);

			if ($field_details) {
				$item_id = $field_details[1];

				// map.name.#
				$this->fields[] = (new CWidgetFieldHidden($field_key, ZBX_WIDGET_FIELD_TYPE_STR))
					->setValue($value);

				// map.parent.#
				$field_parent = (new CWidgetFieldHidden('map.parent.'.$item_id, ZBX_WIDGET_FIELD_TYPE_INT32))
					->setDefault(0);
				if (array_key_exists('map.parent.'.$item_id, $data)) {
					$field_parent->setValue((int) $data['map.parent.'.$item_id]);
				}
				$this->fields[] = $field_parent;

				// map.order.#
				$field_order = new CWidgetFieldHidden('map.order.'.$item_id, ZBX_WIDGET_FIELD_TYPE_INT32);
				if (array_key_exists('map.order.'.$item_id, $data)) {
					$field_order->setValue((int) $data['map.order.'.$item_id]);
				}
				$this->fields[] = $field_order;

				// mapid.#
				// TODO VM: make defualt value 0
				if (array_key_exists('mapid.'.$item_id, $data) && $data['mapid.'.$item_id]) {
					$field_mapid = (new CWidgetFieldHidden('mapid.'.$item_id, ZBX_WIDGET_FIELD_TYPE_MAP))
						->setValue($data['mapid.'.$item_id]);
					$this->fields[] = $field_mapid;
				}
			}
		}
	}
}
