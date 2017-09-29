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
 * C header menu widget
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
	 * Constructor
	 *
	 * @param array   $menu_map
	 * @param string  $menu_map[]['url']       menu action url
	 * @param boolean $menu_map[]['selected']  identify when menu item is selected
	 * @param string  $menu_map[]['title']     menu item title (can be shown only when selected if menu_name specified)
	 * @param string  $menu_map[]['menu_name'] (optional) menu item title (shown only in dropdown menu)
	 *
	 */
	public function __construct(array $menu_map)
	{
		$this->menu_map = $menu_map;
	}

	/**
	 * Create dropdown menu in title
	 *
	 * @return CDiv
	 */
	protected function createTitle()
	{
		$list = (new CList())
			->addClass(ZBX_STYLE_HEADER_DROPDOWN_LIST)
			->setId(uniqid(ZBX_STYLE_HEADER_DROPDOWN_LIST));

		$header = null;
		foreach ($this->menu_map as $item) {
			if ($item['selected']) {
				$header = (new CLink(new CTag('h1', true, $item['title'])))
					->addClass(ZBX_STYLE_HEADER_DROPDOWN)
					->onClick('javascript: jQuery("#'.$list->getId().'").toggle();');
			}
			$title = array_key_exists('menu_name', $item) ? $item['menu_name'] : $item['title'];
			$list->addItem(
				(new CLink($title, $item['url']))->addClass(ZBX_STYLE_ACTION_MENU_ITEM),
				ZBX_STYLE_HEADER_DROPDOWN_LIST_ITEM
			);
		}
		$div = (new CDiv([$header, $list]))->addClass(ZBX_STYLE_HEADER_DROPDOWN_MENU);

		return $div;
	}
}
