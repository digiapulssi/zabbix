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
 * In milliseconds, slide animation duration upon remove.
 */
ZBX_Notification.ease = 500;

/**
 * Detached DOM node is created.
 * Closing time is scheduled.
 *
 * @param {object} options
 *        {number} options.ttl  The timeout for this message is determined server side.
 *        {string} options.html
 */
function ZBX_Notification(options) {
	this.uid = options.uid;
	this.node = this.makeNode(options);
	this.ttl = options.ttl;
	this.timeoutid = this.setTimeout(options.ttl);
	this.onTimeout = function() {};
	this.snoozed = options.snoozed;
}

/**
 * Removes previous timeout if it is scheduled, then
 * schedule timeout to close this message.
 *
 * @param {integer} seconds  Timeout in seconds for 'close' to be called.
 *
 * @return integer  Timeout ID.
 */
ZBX_Notification.prototype.setTimeout = function(seconds) {
	if (this.timeoutid) {
		clearTimeout(this.timeout);
	}

	return setTimeout(function() {
		this.onTimeout(this);
	}.bind(this), seconds * 1000);
}

/**
 * Renders this message object.
 *
 * @depends {BBCode}
 *
 * @return {HTMLElement}  Detached DOM node.
 */
ZBX_Notification.prototype.makeNode = function(obj) {
	var node = document.createElement('li');

	var indicator = document.createElement('div');
	indicator.className = 'notif-indic ' + obj.severity_style;
	node.appendChild(indicator);

	var titleNode = document.createElement('h4');
	titleNode.innerHTML = BBCode.Parse(obj.title);
	node.appendChild(titleNode);

	obj.body.forEach(function(line) {
		var p = document.createElement('p');
		p.innerHTML = BBCode.Parse(line);
		node.appendChild(p)
	});

	node.snooze_icon = document.createElement('div');
	node.snooze_icon.className = 'notif-indic-snooze';
	node.snooze_icon.style.opacity = 0;

	node.querySelector('.notif-indic').appendChild(node.snooze_icon)

	return node;
}

ZBX_Notification.prototype.renderSnoozed = function(bool) {
	this.snoozed = bool;
	if (bool) {
		this.node.snooze_icon.style.opacity = 0.5;
	}
	else {
		this.node.snooze_icon.style.opacity = 0;
	}
}

/**
 * Remove this notification from DOM.
 *
 * @param {number} ease  Amount for slide animation or disable.
 * @param {callable} cb  Closer to be called after remove.
 */
ZBX_Notification.prototype.remove = function(ease, cb) {
	var rate = 10;
	ease *= rate;
	if (ease > 0) {
		this.node.style.overflow = 'hidden';
		var t = ease / rate;
		var step = this.node.offsetHeight / t;
		var id = setInterval(function() {
			if (t < rate) {
				// Since there is loaded prototype.js and it extends DOM's native 'remove' method,
				// we have to check explicitly if node is connected.
				// In case of IE11 there is no 'isConnected' method.
				if (this.node.isConnected || this.node.parentNode) {
					this.node.remove();
					cb && cb()
				}
				clearInterval(id);
			}
			else {
				t -= rate;
				this.node.style.height = (step * t).toFixed() +'px';
			}
		}.bind(this), rate);
	}
	else {
		this.node.remove();
		cb && cb()
	}
}
