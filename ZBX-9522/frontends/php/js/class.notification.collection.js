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


function ZBX_NotificationCollection() {
	this.list = {};
	this.makeNodes()
	this.onTimeout = function() {}

	this.node.style.right = '0px';
	this.node.style.top = '126px';
}

ZBX_NotificationCollection.prototype.makeNodes = function() {
	this.node = document.createElement('div');
	this.node.hidden = true;
	this.node.className = 'overlay-dialogue notif';

	this.btnClose = document.createElement('button');
	this.btnClose.setAttribute('title', locale['S_CLEAR']);
	this.btnClose.className = 'overlay-close-btn';
	this.node.appendChild(this.btnClose);

	var header = document.createElement('div');
	header.className = 'dashbrd-widget-head cursor-move';
	this.node.appendChild(header);

	var controls = document.createElement('ul');
	header.appendChild(controls);

	this.btnMute = this.makeToggleBtn('btn-sound-on', 'btn-sound-off');
	this.btnMute.setAttribute('title', locale['S_MUTE'] + '/' + locale['S_UNMUTE']);

	this.btnSnooze = this.makeToggleBtn('btn-alarm-on', 'btn-alarm-off');
	this.btnSnooze.setAttribute('title', locale['S_SNOOZE']);

	controls.appendChild(document.createElement('li').appendChild(this.btnSnooze));
	controls.appendChild(document.createElement('li').appendChild(this.btnMute));

	this.listNode = document.createElement('ul');
	this.listNode.className = 'notif-body';

	this.node.appendChild(this.listNode);
}

ZBX_NotificationCollection.prototype.makeToggleBtn = function(classInactive, classActive) {
	var button = document.createElement('button');
	button.renderState = function(isActive) {
		this.className = isActive ? classActive : classInactive;
	}
	return button;
}

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
}

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
}

ZBX_NotificationCollection.prototype.renderFromStorable = function(listObj) {
	var frag = document.createDocumentFragment();

	this.list = {};

	Object.keys(listObj).reverse().forEach(function(id) {
		this.list[id] = new ZBX_Notification(listObj[id]);
		this.list[id].renderSnoozed(listObj[id].snoozed);
		this.list[id].onTimeout = this.onTimeout;
		frag.appendChild(this.list[id].node);
	}.bind(this));
	this.listNode.innerHTML = '';

	if (frag.childNodes.length) {
		this.listNode.appendChild(frag);
	}

	return this.listNode.children.length;
}
