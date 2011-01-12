<?php
/*
** ZABBIX
** Copyright (C) 2000-2010 SIA Zabbix
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
** Foundation, Inc., 675 Mass Ave, Cambridge, MA 02139, USA.
**/
?>
<?php
class CButton extends CInput{
	public function __construct($name='button', $caption='', $action=NULL, $class=null){
		parent::__construct('button', $name, $caption, $class);
		$this->addAction('onclick', $action);

		return $this;
	}

	public function setAccessKey($value='B'){
		if(isset($value))
			if(!isset($this->attributes['title']))
				$this->setTitle($this->attributes['value'].' [Alt+'.$value.']');

		return $this->setAttribute('accessKey', $value);
	}
}
?>
