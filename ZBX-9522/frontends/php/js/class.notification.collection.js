/*
** Zabbix
** Copyright (C) 2001-2019 Zabbix SIA
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
 * Represents DOM node for notification list. Stores the list of notification objects.
 */
function ZBX_NotificationCollection() {
	this.list = {};
	this.makeNodes();
	this.onTimeout = function() {};

	this.node.style.right = '0px';
	this.node.style.top = '126px';
}

/**
 * Creates DOM nodes.
 */
ZBX_NotificationCollection.prototype.makeNodes = function() {
	var header = document.createElement('div'),
		controls = document.createElement('ul');

	this.node = document.createElement('div');
	this.node.hidden = true;
	this.node.className = 'overlay-dialogue notif';

	this.btn_close = document.createElement('button');
	this.btn_close.setAttribute('title', locale['S_CLEAR']);
	this.btn_close.setAttribute('type', 'button');
	this.btn_close.className = 'overlay-close-btn';

	this.node.appendChild(this.btn_close);

	header.className = 'dashbrd-widget-head cursor-move';
	this.node.appendChild(header);

	header.appendChild(controls);

	this.btn_mute = this.makeToggleBtn(
		{class: 'btn-sound-on', title: locale['S_MUTE']},
		{class: 'btn-sound-off', title: locale['S_UNMUTE']}
	);

	this.btn_snooze = this.makeToggleBtn({class: 'btn-alarm-on'}, {class: 'btn-alarm-off'});
	this.btn_snooze.setAttribute('title', locale['S_SNOOZE']);

	controls.appendChild(document.createElement('li').appendChild(this.btn_snooze));
	controls.appendChild(document.createElement('li').appendChild(this.btn_mute));

	this.list_node = document.createElement('ul');
	this.list_node.className = 'notif-body';

	this.node.appendChild(this.list_node);
};

/**
 * Creates a button node with a method 'renderState(bool)'.
 *
 * @param {object} attrs_inactive  Attribute key-value object to be mapped on renderState(true).
 * @param {object} attrs_active    Attribute key-value object to be mapped on renderState(false).
 */
ZBX_NotificationCollection.prototype.makeToggleBtn = function(attrs_inactive, attrs_active) {
	var button = document.createElement('button');
	button.renderState = function(isActive) {
		var attrs = isActive ? attrs_active : attrs_inactive,
			attr_name;

		for (attr_name in attrs) {
			this.setAttribute(attr_name, attrs[attr_name]);
		}
	}

	return button;
};

/**
 * Shows list of notifications.
 */
ZBX_NotificationCollection.prototype.show = function() {
	this.node.style.opacity = 0;
	this.node.hidden = false;

	var op = 0;
	var id = setInterval(function() {
		op += 0.1;
		if (op > 1 || this.hidden) {
			return clearInterval(id);
		}
		this.style.opacity = op;
	}.bind(this.node), 50);
};

/**
 * Hides list of notifications.
 */
ZBX_NotificationCollection.prototype.hide = function() {
	this.node.style.opacity = 1;

	var op = 1;
	var id = setInterval(function() {
		op -= 0.1;
		if (op < 0 || !this.hidden) {
			this.hidden = true;
			return clearInterval(id);
		}
		this.style.opacity = op;
	}.bind(this.node), 50);
};

/**
 * Creates list node contents and replaces current list node children.
 *
 * @param {object} list_obj  Notifications list object in format it is stored in local storage.
 */
ZBX_NotificationCollection.prototype.renderFromStorable = function(list_obj) {
	var frag = document.createDocumentFragment();

	this.list = {};

	Object.keys(list_obj).reverse().forEach(function(id) {
		this.list[id] = new ZBX_Notification(list_obj[id]);
		this.list[id].renderSnoozed(list_obj[id].snoozed);
		this.list[id].onTimeout = this.onTimeout;
		frag.appendChild(this.list[id].node);
	}.bind(this));
	this.list_node.innerHTML = '';

	if (frag.childNodes.length) {
		this.list_node.appendChild(frag);
	}

	return this.list_node.children.length;
};
