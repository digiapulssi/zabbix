<?php
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


class CFilter extends CDiv {

	private $columns = [];
	private $form;
	private $footer = null;
	private $navigator = false;
	private $name = 'zbx_filter';
	private $opened = true;
	private $show_buttons = true;
	private $hidden = false;

	protected $headers = [];
	protected $tabs = [];
	// jQuery.tabs disabled tabs list.
	protected $tabs_disabled = [];
	// jQuery.tabs initialization options.
	protected $tabs_options = [
		'collapsible' => true,
		'active' => false
	];
	// Profile data associated with filter object.
	protected $idx = null;
	protected $idx2 = 0;

	/**
	 * List of predefined time ranges. Start and end of time range are separated by semicolon.
	 */
	protected $time_ranges = [
		['now-2d/d:now', 'now-7d/d:now', 'now-30d/d:now', 'now-3M/M:now', 'now-6M/M:now', 'now-1y/y:now',
			'now-2y/y:now'
		],
		['now-1d/d:now-1d/d', 'now-2d/d:now-2d/d', 'now-1w/d:now-1w/d', 'now-1w/w:now-1w/w', 'now-1M/M:now-1M/M',
			'now-1y/y:now-1y/y'
		],
		['now/d:now/d', 'now/d:now', 'now/w:now/w', 'now/w:now', 'now/M:now/M', 'now/M:now', 'now/y:now/y', 'now/y:now'],
		['now-5m/m:now', 'now-15m/m:now', 'now-30m/m:now', 'now-1h/h:now', 'now-3h/h:now', 'now-6h/h:now',
			'now-12h/h:now', 'now-24h/h:now'
		]
	];

	public function __construct() {
		parent::__construct();

		$this->setId('filter-space');

		$this->form = (new CForm('get'))
			->cleanItems()
			->setAttribute('name', $this->name)
			->setId('id', $this->name);
	}

	public function getName() {
		return $this->name;
	}

	public function addColumn($column) {
		$this->columns[] = (new CDiv($column))->addClass(ZBX_STYLE_CELL);
		return $this;
	}

	public function setFooter($footer) {
		$this->footer = $footer;
		return $this;
	}

	public function removeButtons() {
		$this->show_buttons = false;
		return $this;
	}

	public function addNavigator() {
		$this->navigator = true;
		return $this;
	}

	public function addVar($name, $value) {
		$this->form->addVar($name, $value);
		return $this;
	}

	public function setHidden() {
		$this->hidden = true;
		$this->addStyle('display: none;');

		return $this;
	}

	/**
	 * Set profile 'idx' and 'idx2' data. Set current expanded tab from profile.
	 *
	 * @param string $idx
	 * @param int    $idx2
	 *
	 * @return CFilter
	 */
	public function setProfile($idx, $idx2) {
		$this->setActiveTab(CProfile::get($this->idx.'.expanded', $idx2));
		$this->setAttribute('data-profile-idx', $idx);
		$this->setAttribute('data-profile-idx2', $idx2);

		return $this;
	}

	/**
	 * Set active tab.
	 *
	 * @param int $tab  Zero based index of active tab. If set to false all tabs will be collapsed.
	 *
	 * @return CFilter
	 */
	public function setActiveTab($tab) {
		$this->tabs_options['active'] = $tab;

		return $this;
	}

	/**
	 * Add tab with filter form.
	 *
	 * @param string $header    Tab header title string.
	 * @param array  $columns   Array of filter columns markup.
	 *
	 * @return CFilter
	 */
	public function addFilterTab($header, $columns) {
		$body = [];
		$row = (new CDiv())->addClass(ZBX_STYLE_ROW);

		foreach ($columns as $column) {
			$row->addItem((new CDiv($column))->addClass(ZBX_STYLE_CELL));
		}

		$body[] = (new CDiv())
			->addClass(ZBX_STYLE_TABLE)
			->addClass(ZBX_STYLE_FILTER_FORMS)
			->addItem($row);

		if ($this->show_buttons) {
			$url = (new CUrl())
				->removeArgument('filter_set')
				->removeArgument('ddreset')
				->setArgument('filter_rst', 1);

			$body[] = (new CDiv())
				->addClass(ZBX_STYLE_FILTER_FORMS)
				->addItem(
					(new CSubmitButton(_('Apply'), 'filter_set', 1))
						->onClick('javascript: chkbxRange.clearSelectedOnFilterChange();')
				)
				->addItem(
					(new CRedirectButton(_('Reset'), $url->getUrl()))
						->addClass(ZBX_STYLE_BTN_ALT)
						->onClick('javascript: chkbxRange.clearSelectedOnFilterChange();')
				);
		}

		return $this->addTab((new CSimpleButton($header))->addClass(ZBX_STYLE_FILTER_TRIGGER), $body);
	}

	/**
	 * Add time selector specific tab. Should be called before any tab is added. Adds two tabs:
	 * - time selector range changes: back, zoom out, forward.
	 * - time selector range form with predefined ranges.
	 *
	 * @param string $header    Header text. (ex: Last 7 days)
	 *
	 * @return CFilter
	 */
	public function addTimeSelector($from, $to) {
		// Disable time selector range changes tab.
		$this->tabs_disabled[] = count($this->tabs);
		$header = relativeDateToText($from, $to);

		$this->addTab([
			(new CSimpleButton())->addClass('btn-time-left'),
			(new CSimpleButton(_('Zoom out')))->addClass('btn-time-out'),
			(new CSimpleButton())->addClass('btn-time-right')
		], null);

		$predefined_ranges = [];

		foreach ($this->time_ranges as $column_ranges) {
			$column = (new CList())->addClass('time-quick');

			foreach ($column_ranges as $range) {
				list($range_from, $range_to) = explode(':', $range);
				$column->addItem((new CLink(relativeDateToText($range_from, $range_to)))
					->setAttribute('data-from', $range_from)
					->setAttribute('data-to', $range_to)
					->addClass(($from == $range_from && $to == $range_to) ? ZBX_STYLE_SELECTED : null)
				);
			}
			$predefined_ranges[] = (new CDiv($column))->addClass(ZBX_STYLE_CELL);
		}

		$this->addTab(
			(new CSimpleButton($header))->addClass('btn-time'),
			(new CDiv([
				(new CDiv(
					(new CList([
						new CLabel(_('From:'), 'from'), new CTextBox('from', $from),
						(new CButton('from_calendar'))->addClass(ZBX_STYLE_ICON_CAL),
						new CLabel(_('To:'), 'to'), new CTextBox('to', $to),
						(new CButton('to_calendar'))->addClass(ZBX_STYLE_ICON_CAL),
						(new CButton('apply', _('Apply')))
					]))->addClass(ZBX_STYLE_TABLE_FORMS)
				))->addClass('time-input'),
				(new CDiv($predefined_ranges))->addClass('time-quick-range')
			]))->addClass('time-selection-container')
		);

		return $this;
	}

	/**
	 * Add tab.
	 *
	 * @param string $header    Tab header title string.
	 * @param array  $body      Array of body elements.
	 *
	 * @return CFilter
	 */
	public function addTab($header, $body) {
		$tabs = count($this->tabs);

		// By default first non timeselect filter type tab will be set as active.
		if (!in_array($tabs, $this->tabs_disabled) && $this->tabs_options['active'] === false) {
			$this->setActiveTab($tabs);
		}

		$this->headers[] = $header;
		$this->tabs[] = $body;

		return $this;
	}

	/**
	 * Return javascript code for jquery-ui initialization.
	 *
	 * @return string
	 */
	public function getJS() {
		return 'jQuery("#'.$this->getId().'").tabs('.
			CJs::encodeJson(array_merge($this->tabs_options, ['disabled' => $this->tabs_disabled])).
		')';
	}

	/**
	 * Render current CFilter object as HTML string.
	 *
	 * @return string
	 */
	public function toString($destroy = true) {
		$headers = (new CList())->addClass(ZBX_STYLE_FILTER_BTN_CONTAINER);

		foreach ($this->headers as $index => $header) {
			$id = 'tab_'.$index;
			$headers->addItem(new CLink($header, '#'.$id));

			if ($this->tabs[$index] !== null) {
				$this->tabs[$index] = (new CDiv($this->tabs[$index]))
					->addClass(ZBX_STYLE_FILTER_CONTAINER)
					->setId($id);
			}
		}

		$this->form->addItem($this->tabs);

		$this->addItem($headers)
			->addItem($this->form);

		zbx_add_post_js($this->getJS());

		return parent::toString($destroy);
	}
}
