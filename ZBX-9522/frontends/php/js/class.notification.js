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
 * In miliseconds, slide animation duration upon remove.
 */
ZBX_Notification.ease = 500;

/**
 * Detatched node is created upon construction.
 * Closing time is scheduled upon construction.
 *
 * @param options
 * @param options[ttl] integer  The timeout for this message is determined server side.
 * @param options[html] string  Already parsed as string @see srvToStore
 */
function ZBX_Notification(options) {
	this.uid = options.uid;
	this.node = this.makeNode(options.html, options.uid);
	this.ttl = options.ttl;
	this.timeoutid = this.setTimeout(options.ttl);
	this.onTimedout = function() {};
	this.snoozed = options.snoozed;
}

/**
 * Removes previous timeout if it is scheduled, then
 * schedule timeout to close this message.
 *
 * @param seconds integer  Timeout in seconds for close call.
 *
 * @return integer  Timeout ID.
 */
ZBX_Notification.prototype.setTimeout = function(seconds) {
	if (this.timeoutid) {
		clearTimeout(this.timeout);
	}

	return setTimeout(function() {
		this.onTimedout(this);
	}.bind(this), seconds * 1000);
}

/**
 * Renders this message object.
 *
 * @return HTMLElement  Detatched DOM node.
 */
ZBX_Notification.prototype.makeNode = function(htmlString, uid) {
	var parse = document.createElement('div');
	parse.innerHTML = htmlString;
	var node = parse.firstChild;

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
 * Remove this notification from dom. TODO rather animate using css
 *
 * @param ease int  Amount for slide animation or disable.
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
				// Since there is loaded prototype.js and it extends DOM's native remove method,
				// we have to check explicitly if node is connected (it may not be, because we have page reloads).
				if (this.node.isConnected) {
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

/**
 * Method that transforms notification object received from server
 * into format that is used for for notification instance.
 *
 * @depends BBCode
 *
 * @return Object
 */
ZBX_Notification.srvToStore = function(obj) {
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

	return {
		html: node.outerHTML,
		priority: obj.priority,
		ttl: obj.ttl,
		uid: obj.uid,
		id: obj.id,
		file: obj.file,
		snoozed: obj.snoozed
	};
}

