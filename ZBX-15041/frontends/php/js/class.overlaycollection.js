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

function OverlayCollection() {
	this.stack = [];
	this.map = {}
}

OverlayCollection.prototype._fetchIndex = function(id) {
	for (var i = this.length - 1; i >= 0; i--) {
		if (this.stack[i] == id) {
			return i;
		}
	}

	throw new Error('Fetching unexistent overlay: ' + id);
}

OverlayCollection.prototype._write = function(overlay, position) {
	this.stack[position] = overlay.dialogueid;
	this.map[overlay.dialogueid] = overlay;
}

/**
 * before push it is checked if existing id is in stack
 */
OverlayCollection.prototype.pushUnique = function(overlay) {
	if (this.map[overlay.dialogueid]) {
		this.restackEnd(overlay.dialogueid);
	} else {
		this._write(overlay, this.length)
	}
}

/**
 * stack control,
 * reorders given overlay address onto the top of stack (end of queue)
 */
OverlayCollection.prototype.restackEnd = function(id) {
	this.stack.splice(this._fetchIndex(id), 1);
	this.stack.push(id)
}

/**
 * Removes overlay object, returns reference, that will be garbage collected if not used.
 */
OverlayCollection.prototype.removeById = function(id) {
	var overlay = this.getById(id);

	if (overlay) {
		delete this.map[id];
		this.stack.splice(this._fetchIndex(id), 1)
	}

	return overlay;
}

/**
 * @return Overlay  Most recent Overlay in stack or undefined
 */
OverlayCollection.prototype.end = function() {
	return this.getById(this.stack[this.length - 1]);
}

OverlayCollection.prototype.getById = function(id) {
	return this.map[id];
}

/**
 * creates read-only dynamic property
 */
Object.defineProperty(OverlayCollection.prototype, 'length', {
	get: function () {
		return this.stack.length;
	},
	writeable: false
})
