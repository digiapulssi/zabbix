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


ZBX_LocalStorage.defines = {
	EVT_WRITE: 0,
	EVT_CHANGE: 1,
	EVT_MAP: 2,
	ANY_KEY: '*'
}

/**
 * Ref: https://www.w3.org/TR/webstorage/#the-storage-event
 * Local storage wraper. Implements singleton.
 *
 * @param version string  Mandatory parameter - zabbix version.
 */
function ZBX_LocalStorage(version) {
	if (!version) {
		throw 'Unversioned local storage instantiation.';
	}

	if (ZBX_LocalStorage.intsance) {
		return ZBX_LocalStorage.intsance;
	}
	ZBX_LocalStorage.intsance = this;

	this.keys = {
		// Store versioning.
		'version': this.wrap(version),
		// {(string) tabId: (int) lastSeen}
		// An object where every tab updates timestamp at key of it's id, in order to assume if there are crashed tabs.
		'tabs.lastseen': {},
		// Browser tab id that was the last one focused.
		'tabs.lastfocused': '',
		// Browser tab id that was the last one left focus.
		'tabs.lastblured': '',
		// Stores manifest for notifications that currently are in DOM. Keyed by id.
		'notifications.list': {},
		// {id: boolean} this is not a part of Notification, because it is not sent by server,
		// we must merge this property into list upon receive new / old notifications from server.
		'notifications.snoozedids': {},
		// When we receive list of messages, this signifies the time of latest message. This way we know if list is updated.
		'notifications.listid': '',
		// This setting will disable it self upon 'notifications.timestamp' update.
		'notifications.alarm.snoozed': '',
		// If this is true - notifications are still received, but no audio related things are ever performed.
		'notifications.alarm.muted': false,
		// If this value is not empty - that means it is playing. Seek and timeout is read from store on focus gain.
		'notifications.alarm.wave': '',
		// Optional seek position, it will always be read on focus, if playing.
		'notifications.alarm.seek': 0,
		// Set notifications audio player timeout.
		// Current timeout is written when playing tab lost focus or unloads.
		// Zero value is written along with notifications.alarm.start if (newValue != oldValue).
		'notifications.alarm.timeout': 0,
		// Notification start id is written when we receive a notification that should be played.
		'notifications.alarm.start': '',
		// Notification end id is written when notification has completed it's alert.
		// It is then checked if these keys are equal to know that we do not play notification again.
		'notifications.alarm.end': '',
		// Event object
		'notifications.event': {},
	}

	if (this.readKey('version') != this.keys.version) {
		this.truncate();
	}
}

/**
 * Callback gets passed a reference of object under this key.
 * The reference then is written back into local storage.
 *
 * @param string key
 * @param closure callback
 */
ZBX_LocalStorage.prototype.mutateObject = function(key, callback) {
	var obj = this.readKey(key);
	callback(obj);
	this.writeKey(key, obj);
}

/**
 * Validates if key is used by this version of localStorage.
 *
 * @param string key  Key to test.
 *
 * @return boolean
 */
ZBX_LocalStorage.prototype.hasKey = function(key) {
	return typeof this.keys[key] !== 'undefined';
}

/**
 * Alias to throw error on invalid key access.
 *
 * @param string key  Key to test.
 */
ZBX_LocalStorage.prototype.ensureKey = function(key) {
	if (typeof key !== 'string') {
		throw 'Key must be a string, ' + (typeof key) + ' given instead.';
	}
	else if (key == ZBX_LocalStorage.defines.ANY_KEY) {
		throw 'This key is reserved and cannot be used: ' + key;
	}

	if (!this.hasKey(key)) {
		throw 'Unknown localStorage key access at "'+key+'"';
	}
}

/**
 * Writes an underlaying value.
 *
 * @param string key
 * @param string value
 */
ZBX_LocalStorage.prototype.writeKey = function(key, value) {
	if (value instanceof Array) {
		throw 'Arrays are not supported. Unsuccessful key: ' + key;
	}
	this.ensureKey(key);

	localStorage.setItem(key, this.wrap(value));
	this.onWriteCb && this.onWriteCb(key, value, ZBX_LocalStorage.defines.EVT_WRITE);
}

/**
 * Writes default value.
 */
ZBX_LocalStorage.prototype.resetKey = function(key) {
	this.ensureKey(key);
	this.writeKey(key, this.keys[key]);
}

/**
 * Fetches underlaying value.
 *
 * @param string key  Key to test.
 *
 * @return mixed
 */
ZBX_LocalStorage.prototype.readKey = function(key) {
	this.ensureKey(key);

	try {
		return this.unwrap(localStorage.getItem(key));
	} catch (e) {
		console.warn('failed to parse storage item "'+key+'"');
		this.truncate();
		return null;
	}
}

/**
 * @param value mixed
 *
 * @return string
 */
ZBX_LocalStorage.prototype.wrap = function(value) {
	return JSON.stringify(value);
}

/**
 * @param value string
 *
 * @throws Error
 *
 * @return mixed
 */
ZBX_LocalStorage.prototype.unwrap = function(value) {
	return JSON.parse(value);
}

/**
 * Removes all local storage and creates default objects.
 */
ZBX_LocalStorage.prototype.truncate = function() {
	localStorage.clear();
	for (key in this.keys) {
		this.writeKey(key, this.keys[key]);
	}
	console.warn('Zabbix local storage has been truncated.');
}

/**
 * Since storage event is not fired for current session.
 * This binding can be used to explicitly proxy updates events for current session as well.
 *
 * @param callable callback
 */
ZBX_LocalStorage.prototype.onWrite = function(callback) {
	this.onWriteCb = callback;
}

/**
 * Registers an event handler.
 * A callback will get passed key that were modified and the new value it now holds.
 * Note: handle is fired only when there was a change (not in case of any writeKey).
 *
 * @param callable callback
 */
ZBX_LocalStorage.prototype.onUpdate = function(callback) {
	window.addEventListener('storage', function(event) {
		// This means, storage has been truncated.
		if (event.key === null || event.key === '') {
			this.mapCallback(callback);
		}
		else {
			callback(event.key, this.unwrap(event.newValue), ZBX_LocalStorage.defines.EVT_CHANGE);
		}
	}.bind(this));
}

/**
 * Apply every callback for each localStorage entry.
 *
 * @param callable callback
 */
ZBX_LocalStorage.prototype.mapCallback = function(callback) {
	for (var i = 0; i < localStorage.length; i++) {
		var key = localStorage.key(i);
		callback(key, this.readKey(key), ZBX_LocalStorage.defines.EVT_MAP);
	}
}
