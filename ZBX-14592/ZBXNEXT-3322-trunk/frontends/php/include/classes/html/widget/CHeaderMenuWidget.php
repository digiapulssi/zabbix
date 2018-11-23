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


/**
 * Header menu widget with navigation drop down list.
 *
 */
class CHeaderMenuWidget extends CWidget
{
	/**
	 * Menu_map
	 *
	 * @var array
	 */
	private $menu_map = [];

	/**
	 * Unique HTML id used for header menu.
	 */
	private $header_menuid;

	/**
	 * Constructor
	 *
	 * @param array   $menu_map
	 * @param string  $menu_map[]['url']       menu action url
	 * @param boolean $menu_map[]['selected']  identify when menu item is selected
	 * @param string  $menu_map[]['title']     menu item title (can be shown only when selected if menu_name specified)
	 * @param string  $menu_map[]['menu_name'] (optional) menu item title (shown only in dropdown menu)
	 *
	 */
	public function __construct(array $menu_map) {
		$this->menu_map = $menu_map;
		$this->header_menuid = uniqid(ZBX_STYLE_HEADER_DROPDOWN_LIST);
	}

	/**
	 * Get widget content as array.
	 *
	 * @return array
	 */
	public function get() {
		return [$this->createTopHeader(), $this->body];
	}

	/**
	 * Create dropdown header control.
	 *
	 * @return CDiv
	 */
	private function createTopHeader() {
		$divs = [(new CDiv($this->createTitle()))->addClass(ZBX_STYLE_CELL)];

		if ($this->controls !== null) {
			$divs[] = (new CDiv($this->controls))
				->addClass(ZBX_STYLE_CELL)
				->addClass(ZBX_STYLE_NOWRAP);
		}

		return (new CDiv($divs))
			->addClass(ZBX_STYLE_HEADER_TITLE)
			->addClass(ZBX_STYLE_TABLE);
	}

	/**
	 * Create dropdown menu in title.
	 *
	 * @return CDiv
	 */
	private function createTitle() {
		$list = (new CList())
			->addClass(ZBX_STYLE_HEADER_DROPDOWN_LIST)
			->setAttribute('tabindex', '0')
			->setAttribute('role', 'menu')
			->setId($this->header_menuid);

		$header_button = null;

		foreach ($this->menu_map as $item) {
			if ($item['selected']) {
				$header_button = (new CButton('content-header', $item['title']))
					->addClass(ZBX_STYLE_HEADER_DROPDOWN)
					->setAttribute('data-dropdown-list', '#'.$this->header_menuid)
					->setAttribute('aria-haspopup', 'true')
					->setAttribute('aria-expanded', 'false');
			}
			$title = array_key_exists('menu_name', $item) ? $item['menu_name'] : $item['title'];

			$list->addItem((new CLink($title, $item['url']))
				->addClass(ZBX_STYLE_ACTION_MENU_ITEM)
				->setAttribute('role', 'menuitem')
				->setAttribute('tabindex', '-1')
				->setAttribute('aria-label', $title)
			);
		}

		return (new CTag('nav', true))
			->addItem($header_button)
			->addItem($list)
			->setAttribute('aria-label', _('Content controls: header'));
	}
}
