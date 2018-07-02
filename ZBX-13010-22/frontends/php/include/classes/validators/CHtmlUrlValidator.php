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


class CHtmlUrlValidator {

	/**
	 * URL is validated if schema validation is enabled (see VALIDATE_URI_SCHEMES).
	 *
	 * Relative URL should start with .php file name.
	 * Absolute URL schema must match schemes mentioned in ZBX_URL_VALID_SCHEMES comma separated list.
	 *
	 * @static
	 *
	 * @param string $url	URL string to validate.
	 *
	 * @return bool
	 */
	public static function validate($url) {
		if (VALIDATE_URI_SCHEMES === false) {
			return true;
		}

		$url = parse_url($url);
		$allowed_schemes = explode(',', strtolower(ZBX_URI_VALID_SCHEMES));

		return ($url && ((array_key_exists('scheme', $url) && in_array(strtolower($url['scheme']), $allowed_schemes))
					|| (array_key_exists('path', $url) && preg_match('/^[a-z_\.]+\.php/i', $url['path']) == 1)
				));
	}
}
