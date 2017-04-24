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
	 * url related to selected menu item
	 *
	 * @var string|null
	 */
	private $selected_url;

	/**
	 * Constructor
	 *
	 * @param array $menu_map
	 * @param string $menu_map[]['url']       menu action url
	 * @param string $menu_map[]['title']     menu item title (can be shown only when selected if menu_name specified)
	 * @param string $menu_map[]['menu_name'] (optional) menu item title (shown only in dropdown menu)
	 *
	 * @param null|string $selected_url       url related to selected menu item
	 */
	public function __construct(array $menu_map, $selected_url = null)
	{
		$this->menu_map = $menu_map;
		$this->selected_url = $selected_url;
		return $this;
	}

	protected function createTitle()
	{
		$list = new CList();
		$list->addClass('header-dropdown-list')
			->setId('adm-menu-dropdown-list');

		$header = null;
		foreach ($this->menu_map as $item) {
			if (array_key_exists('url', $item) && $item['url'] !== $this->selected_url) {
				$title = array_key_exists('menu_name', $item) ? $item['menu_name'] : $item['title'];
				$link = new CLink($title, $item['url']);
				$link->addClass('action-menu-item');
				$list->addItem($link, 'header-dropdown-list-item');
			} else {
				$header = new CLink(new CTag('h1', true, $item['title']), '#');
				$header->addClass('header-dropdown');
				$header->onClick('javascript: showHide($(this).next(\'.header-dropdown-list\'));');
			}
		}
		$div = (new CDiv([$header, $list]))->addClass('header-dropdown-menu');

		return $div;
	}
}
