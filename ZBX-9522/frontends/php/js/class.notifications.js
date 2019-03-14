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


ZBX_Notifications.POLL_INTERVAL = 30000;
ZBX_Notifications.POLL_INTERVAL = 5000;

/**
 * @param store ZBX_LocalStorage
 * @param tab ZBX_BrowserTab
 */
function ZBX_Notifications(store, tab) {
	if (!(store instanceof ZBX_LocalStorage) || !(tab instanceof ZBX_BrowserTab)) {
		throw 'Unmatched signature!';
	}

	this.player = new ZBX_NotificationsAudio();

	this.store = store;

	this.tab = tab;
	this.tab.onFocus(this.onTabFocus.bind(this));
	this.tab.onUnload(this.onTabUnload.bind(this));

	this.dom = new ZBX_NotificationCollection();
	this.dom.onTimedout = this.onNotifTimedout.bind(this);

	this.doPollServer = false;

	// We must not rely on notifications list from store if this is first created instace across tabs.
	// So we truncate that list. The polling will begin as usual.
	if (tab.isSingleSession()) {
		this.store.resetKey('notifications.listid');
		this.store.resetKey('notifications.list');
	}

	this.dom.btnClose.onclick = this.btnCloseClicked.bind(this);
	this.dom.btnSnooze.onclick = this.btnSnoozeClicked.bind(this);
	this.dom.btnMute.onclick = this.btnMuteClicked.bind(this);

	this.store.onUpdate(this.onStoreUpdate.bind(this));

	this.onSnoozeChange(this.store.readKey('notifications.alarm.snoozed'));
	this.onMuteChange(this.store.readKey('notifications.alarm.muted'));
	this.onNotificationsList(this.store.readKey('notifications.list'));
	this.onTabFocusChanged(this.store.readKey('tabs.lastfocused'));

	this.player.seek(this.store.readKey('notifications.alarm.seek'));
	this.player.file(this.store.readKey('notifications.alarm.wave'));

	setInterval(this.mainLoop.bind(this), ZBX_Notifications.POLL_INTERVAL);

	// Upon object creation we invoke tab.onFocus hook if tab was not opened in background.
	// Restack exists because of IE11.
	setTimeout(function(){
		document.hasFocus() && this.onTabFocus(this.tab);
		this.mainLoop();
	}.bind(this), 0);
}

ZBX_Notifications.prototype.onStoreUpdate = function(key, value, source) {
	switch (key) {
		case 'notifications.alarm.end':
		case 'notifications.alarm.start':
			this.renderPlayer();
			break;
		case 'notifications.alarm.wave':
			this.player.file(value);
			break;
		case 'notifications.alarm.seek':
			this.player.seek(value);
			break;
		case 'notifications.alarm.timeout':
			this.doPollServer && this.player.timeout(value);
			break;
		case 'notifications.alarm.muted':
			this.onMuteChange(value);
			break;
		case 'notifications.alarm.snoozed':
			this.onSnoozeChange(value);
			break;
		case 'notifications.list':
			this.onNotificationsList(value);
			break;
		case 'tabs.lastfocused':
			this.onTabFocusChanged(value);
			break;
	}
}

ZBX_Notifications.prototype.onPollerReceive = function(resp) {
	if (typeof resp.settings === 'undefined') {
		// This means messaging is disabled at the time.
		return;
	}

	this.writeSettings(resp.settings);
	if (this.store.readKey('notifications.listid') == resp.listid) {
		return;
	}

	this.store.writeKey('notifications.listid', resp.listid);
	this.applySnoozeProp(resp.notifications);

	var listObj = this.toStorableList(resp.notifications);
	var notifId = this.findNotificationToPlay(resp.notifications);
	this.writeAlarm(listObj[notifId], resp.settings);

	this.store.writeKey('notifications.list', listObj);
	this.onNotificationsList(listObj);

	this.store.writeKey('notifications.alarm.snoozed', false);
	this.onSnoozeChange(false);
}

ZBX_Notifications.prototype.onNotifTimedout = function(notif) {
	notif.remove(ZBX_Notification.ease, function() {
		if (!this.dom.listNode.children.length) {
			this.dom.hide();
		}


		if (this.store.readKey('notifications.alarm.start') == notif.uid) {
			this.store.writeKey('notifications.alarm.end', notif.uid);
			this.renderPlayer();
		}

		// This may be unneeded overkill TODO ?
		this.store.mutateObject('notifications.list', function(listObj) {
			delete listObj[notif.uid];
		});

	}.bind(this));
}

ZBX_Notifications.prototype.onNotificationsList = function(listObj) {
	var length = this.dom.renderFromStorable(listObj);
	if (length) {
		this.dom.node.hidden && this.dom.show();
	}
	else {
		!this.dom.node.hidden && this.dom.hide();
	}
}

ZBX_Notifications.prototype.onSnoozeChange = function(bool) {
	this.dom.btnSnooze.renderState(bool);
	if (!bool) {
		return;
	}

	var listObj = this.store.readKey('notifications.list');
	var snoozedids = {};
	for (var id  in listObj) {
		snoozedids[id] = bool;
		listObj[id].snoozed = bool;
	}

	this.store.writeKey('notifications.snoozedids', snoozedids);
	this.store.writeKey('notifications.list', listObj);
	this.onNotificationsList(listObj);
	this.renderPlayer();
}

ZBX_Notifications.prototype.onMuteChange = function(bool) {
	this.dom.btnMute.renderState(bool);
	this.player.mute(bool);
}

ZBX_Notifications.prototype.onTabUnload = function(tab) {
	if (this.doPollServer) {
		this.store.writeKey('notifications.alarm.seek', this.player.getSeek());
		this.store.writeKey('notifications.alarm.timeout', this.player.getTimeout());
	}
}

ZBX_Notifications.prototype.onTabFocusChanged = function(tabId) {
	var activeBlured = this.doPollServer && this.tab.uid != tabId;

	if (activeBlured) {
		this.store.writeKey('notifications.alarm.seek', this.player.getSeek());
		this.store.writeKey('notifications.alarm.timeout', this.player.getTimeout());
		this.player.stop();
	}

	this.doPollServer = this.tab.uid == tabId;
}

ZBX_Notifications.prototype.onTabFocus = function(tab) {
	this.onTabFocusChanged(tab.uid);
}

ZBX_Notifications.prototype.writeAlarm = function(notif, opts) {
	if (!notif) {
		this.store.resetKey('notifications.alarm.start');
		this.store.resetKey('notifications.alarm.end');
		this.store.resetKey('notifications.alarm.seek');
		this.store.resetKey('notifications.alarm.wave');
		this.store.resetKey('notifications.alarm.timeout');
		return;
	}

	var start = this.store.readKey('notifications.alarm.start');
	var end = this.store.readKey('notifications.alarm.end');
	if (start == end) {
		this.player.seek(0);
		this.store.resetKey('notifications.alarm.seek');
	}

	if (opts.timeout === -1) { // Play in loop till end of notification timeout.
		this.store.writeKey('notifications.alarm.timeout', notif.ttl);
	}
	else if (opts.timeout === 1) { // Play once till end of audio file.
		this.store.writeKey('notifications.alarm.timeout', -1);
	}
	else { // Play in loop till end of arbitraty timeout.
		this.store.writeKey('notifications.alarm.timeout', opts.timeout);
	}

	this.store.writeKey('notifications.alarm.wave', opts.files[notif.file]);
	// This write event is an action trigger for other tabs.
	this.store.writeKey('notifications.alarm.start', notif.uid);
	// this.renderPlayer();
}

ZBX_Notifications.prototype.writeSettings = function(settings) {
	this.store.writeKey('notifications.alarm.muted', settings.muted);
	this.onMuteChange(settings.muted);
}

ZBX_Notifications.prototype.toStorableList = function(list) {
	if (list && list.constructor != Array) {
		throw 'Expected array in ZBX_Notifications.prototype.toStorableList';
	}

	var listObj = {};
	list.forEach(function(rawNotif) {
		listObj[rawNotif.uid] = ZBX_Notification.srvToStore(rawNotif);
	});

	return listObj;
}

ZBX_Notifications.prototype.applySnoozeProp = function(list) {
	if (!(list instanceof Array)) {
		throw 'Expected array in ZBX_Notifications.prototype.mergeSnoozed';
	}
	var snoozes = this.store.readKey('notifications.snoozedids');
	list.forEach(function(rawNotif) {
		if (snoozes[rawNotif.uid]) {
			rawNotif.snoozed = true;
		}
		else {
			rawNotif.snoozed = false;
		}
	});
}


/**
 * Finds most severe, most recent unsnoozed notification.
 *
 * A list we got from server reflects current notifications within timeout.
 * To find a notification to play we must filter out any snoozed notifications
 * we sort by severity first, then by timeout.
 *
 * @param list array  Notification objects in server provided format.
 *
 * @return string|null  Notification uid if it is found.
 */
ZBX_Notifications.prototype.findNotificationToPlay = function(list) {
	if (!list.length) {
		return null;
	}

	return list.reduce(function(acc, cur) {
		if (cur.snoozed) {
			return acc;
		}
		if (cur.priority > acc.priority) {
			return cur;
		}
		if (cur.priority == acc.priority && cur.ttl > acc.ttl) {
			return cur;
		}
		return acc;
	}).uid;
}

ZBX_Notifications.prototype.btnCloseClicked = function() {
	var params = {ids: []};
	var list = this.store.readKey('notifications.list');
	for (var uid in list) {
		params.ids.push(list[uid].id);
	}

	this.fetch('notifications.read', params)
		.catch(console.error)
		.then(function(resp) {
			this.store.resetKey('notifications.list');
			this.onNotificationsList({});
		}.bind(this));
}

ZBX_Notifications.prototype.btnSnoozeClicked = function() {
	if (this.store.readKey('notifications.alarm.snoozed')) {
		return;
	}
	this.store.writeKey('notifications.alarm.snoozed', true);
	this.onSnoozeChange(true);
}

ZBX_Notifications.prototype.btnMuteClicked = function() {
	var newValue = this.store.readKey('notifications.alarm.muted') ? 0 : 1;
	this.fetch('notifications.mute', {mute: newValue})
		.catch(console.error)
		.then(function() {
			this.store.writeKey('notifications.alarm.muted', newValue);
			this.onMuteChange(newValue);
		}.bind(this));
}

ZBX_Notifications.prototype.renderPlayer = function(source) {
	if (!this.doPollServer) {
		return this.player.stop();
	}

	var start = this.store.readKey('notifications.alarm.start');
	var end = this.store.readKey('notifications.alarm.end');

	if (!start || start && start == end) {
		return this.player.stop();
	}

	if (this.store.readKey('notifications.alarm.muted')) {
		return this.player.stop();
	}

	if (this.store.readKey('notifications.alarm.snoozed')) {
		return this.player.stop();
	}

	var wave = this.store.readKey('notifications.alarm.wave');

	if (wave) {
		this.player.file(wave);
	}

	this.player.seek(this.store.readKey('notifications.alarm.seek'))
	this.player.timeout(this.store.readKey('notifications.alarm.timeout'));

	this.player.onTimeout = function() {
		if (this.doPollServer) {
			this.store.writeKey('notifications.alarm.end', this.store.readKey('notifications.alarm.start'));
		}
	}.bind(this)

	return this.player;
}

ZBX_Notifications.prototype.fetch = function(resource, params) {
	return new Promise(function(resolve, reject) {
		sendAjaxData('zabbix.php?action=' + resource, {
			data: params || {},
			success: resolve,
			error: reject
		});
	});
}

ZBX_Notifications.prototype.mainLoop = function() {
	if (!this.doPollServer) {
		return;
	}

	this.fetch('notifications.get')
		.catch(console.error)
		.then(this.onPollerReceive.bind(this))
}

// DEV INIT PART
var LS = new ZBX_LocalStorage(env.ZABBIX_VERSION);
var BT = new ZBX_BrowserTab(LS);
var NN = new ZBX_Notifications(LS, BT);

jQuery(function() {
	document.body.appendChild(NN.dom.node);
	jQuery(NN.dom.node).draggable();
});
