/*
** Zabbix
** Copyright (C) 2001-2017 Zabbix SIA
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

function SVGCanvas(options, shadowBuffer) {
	this.id = 0;
	/* TODO: predefined set of attributes, strict check and merge*/
	this.options = options;
	this.elements = [];
	/* TODO: do we need to configure this? */
	this.textPadding = 5;

	this.buffer = null;

	this.root = this.createElement('svg', {
		'width': options.width,
		'height': options.height
	}, null);

	if (shadowBuffer === true) {
		this.buffer = this.root.add('g', {
			id: 'shadow-buffer',
			style: 'visibility: hidden;'
		});
	}
}

SVGCanvas.prototype.createElement = function (type, attributes, parent, content) {
	var element = new SVGElement(this, type, attributes, parent, content);
	this.elements.push(element);

	return element;
};

SVGCanvas.prototype.getElementsByAttributes = function (attributes) {
	var names = Object.keys(attributes),
		elements = this.elements.filter(function (item) {
			for (var i = 0; i < names.length; i++) {
				if (item.attributes[names[i]] !== attributes[names[i]]) {
					return false;
				}
			}

			return true;
		});

	return elements;
};

SVGCanvas.prototype.add = function (type, attributes, content) {
	return this.root.add(type, attributes, content);
};

SVGCanvas.prototype.render = function (container) {
	if (this.root.element.parentNode) {
		this.root.element.parentNode.removeChild(this.root.element);
	}

	container.appendChild(this.root.element);
};

function ImageCache() {
	this.lock = 0;
	this.images = {};
	this.context = null;
	this.callback = null;

	this.queue = [];
}

ImageCache.prototype.invokeCallback = function () {
	if (typeof this.callback === 'function') {
		this.callback.call(this.context);
	}

	var task = this.queue.pop();
	if (task !== undefined) {
		this.preload(task.urls, task.callback, task.context);
	}
};

ImageCache.prototype.handleCallback = function () {
	this.lock--;

	if (this.lock === 0) {
		this.invokeCallback();
	}
};

ImageCache.prototype.onImageLoaded = function (id, image) {
	this.images[id] = image;
	this.handleCallback();
};

ImageCache.prototype.onImageError = function (id) {
	this.images[id] = null;
	this.handleCallback();
};

ImageCache.prototype.preload = function (urls, callback, context) {
	if (this.lock !== 0) {
		this.queue.push({
			'urls':  urls,
			'callback': callback,
			'context': context
		});

		return false;
	}

	this.context = context;
	this.callback = callback;

	var images = 0;
	var object = this;
	Object.keys(urls).forEach(function (key) {
		var url = urls[key];
		if (typeof url !== 'string') {
			object.onImageError.call(object, key);
			return;
		}

		if (object.images[key] !== undefined) {
			return; /* preloaded */
		}

		var image = new Image();
		image.onload = function () {
			object.onImageLoaded.call(object, key, image);
		};
		image.onerror = function () {
			object.onImageError.call(object, key);
		};
		image.src = url;

		object.lock++;
		images++;
	});

	if (images === 0) {
		this.invokeCallback();
	}

	return true;
};

function SVGElement(renderer, type, attributes, parent, content) {
	this.id = renderer.id++;
	this.type = type;
	this.attributes = attributes;

	/* TODO: do we need this as a part of SVGElement? */
	if (type === 'image') {
		if ((this.attributes.width === undefined || this.attributes.height === undefined) && this.attributes.href !== undefined) {
			var image = new Image();
			var target = this;
			image.onload = function () {
				target.attributes.width = this.naturalWidth;
				target.attributes.height = this.naturalHeight;
				target.create();
			};

			image.src = this.attributes.href;
			/* makes sure that element will not be created */
			type = null;
		}
	}

	this.content = content;
	this.canvas = renderer;

	this.parent = parent;
	this.items = [];
	this.element = null;

	if (type !== null) {
		this.create();
	}
}

SVGElement.prototype.add = function (type, attributes, content) {
	/* multiple items to add */
	if (Array.isArray(type)) {
		var items = [];
		type.forEach(function (element) {
			if (typeof element !== 'object' || typeof element.type !== 'string') {
				throw 'Invalid element configuration!';
			}

			items.push(this.add(element.type, element.attributes, element.content));
		}, this);

		return items;
	}

	if (attributes === undefined || attributes === null) {
		attributes = {};
	}

	var element = this.canvas.createElement(type, attributes, this, content);
	this.items.push(element);

	return element;
};

SVGElement.prototype.clear = function () {
	for (var i = 0; i < this.items.length; i++) {
		this.items[i].remove();
	}

	this.items = [];

	return this;
};

SVGElement.prototype.update = function (attributes) {
	Object.keys(attributes).forEach(function (name) {
		this.element.setAttribute(name, attributes[name]);
	}, this);

	return this;
};

SVGElement.prototype.moveTo = function (target) {
	this.parent.items = this.parent.items.filter(function (item) {
		return item.id !== this.id;
	});

	this.parent = target;
	this.parent.items.push(this);

	target.element.appendChild(this.element);

	return this;
};

SVGElement.prototype.remove = function () {
	this.clear();

	if (this.element !== null) {
		this.element.remove();
		this.element = null;
	}

	this.parent.items = this.parent.items.filter(function (item) {
		return item.id !== this.id;
	});

	return this;
};

SVGElement.prototype.create = function () {
	var element = document.createElementNS('http://www.w3.org/2000/svg', this.type),
		names = Object.keys(this.attributes);

	/* TODO: do we need this #2 */
	/* images are preloaded in shadow buffer */
	if (this.parent !== null && this.parent.element !== null && this.type === 'image' && this.canvas.buffer !== null) {
		var target = this.parent.element;
		element.onload = function () {
			target.appendChild(this);
		};
	}

	/* direct mapping */
	for (var i = 0; i < names.length; i++) {
		if (typeof this.attributes[names[i]] === 'function') {
			continue;
		}

		element.setAttributeNS(null, names[i], this.attributes[names[i]]);
	}

	if (this.element !== null) {
		this.element.remove();
	}

	this.element = element;

	if (Array.isArray(this.content)) {
		this.content.forEach(function (element) {
			/* TODO: better check */
			if (typeof element !== 'object' || typeof element.type !== 'string') {
				throw 'Invalid element configuration!';
			}

			this.add(element.type, element.attributes, element.content);
		}, this);

		this.content = null;
	}
	else if ((/string|number|boolean/).test(typeof this.content)) {
		element.textContent = this.content;
	}

	if (this.parent !== null && this.parent.element !== null) {
		/* TODO: do we need this #3 */
		if (this.type === 'image' && this.canvas.buffer !== null) {
			this.canvas.buffer.element.appendChild(element);
		}
		else {
			this.parent.element.appendChild(element);
		}
	}

	return element;
};

SVGElement.prototype.addText = function (x, y, text, attributes, anchor, background) {
	var group = this.add('g'),
		lines = [],
		pos = [x, y],
		rect = null,
		lineOptions = {
			x: 0,
			dy: '1.2em',
			'text-anchor': 'middle'
		};

	if (typeof text === 'string' && text.trim() === '') {
		return;
	}

	if (typeof anchor !== 'object') {
		/* TODO: parse from string? */
		anchor = {};
	}

	if (typeof background === 'object') {
		rect = group.add('rect', background);
		pos[0] -= this.canvas.textPadding;
		pos[1] -= this.canvas.textPadding;

		lineOptions.x = this.canvas.textPadding;
	}

	if (typeof text === 'string') {
		text.split("\n").forEach(function (line) {
			lines.push( {
				type: 'tspan',
				attributes: lineOptions,
				content: line
			});
		});
	}
	else {
		text.forEach(function (line) {
			lines.push( {
				type: 'tspan',
				attributes: SVGElement.mergeAttributes(lineOptions, line.attributes),
				content: line.content
			});
		});
	}

	var text = group.add('text', attributes, lines);
	var size = group.element.getBBox();
	size.width = Math.ceil(size.width);
	size.height = Math.ceil(size.height);

	text.element.setAttribute('transform', 'translate(' + Math.floor(size.width/2) + ' 0)');

	switch (anchor.horizontal) {
		case 'center':
			pos[0] -= Math.floor(size.width/2);
		break;

		case 'right':
			pos[0] -= size.width;
		break;
	}

	switch (anchor.vertical) {
		case 'middle':
			pos[1] -= Math.floor(size.height/2);
		break;

		case 'bottom':
			pos[1] -= size.height;
		break;
	}

	if (rect !== null) {
		rect.element.setAttribute('width', size.width + (this.canvas.textPadding * 2));
		rect.element.setAttribute('height', size.height + this.canvas.textPadding);
	}
	group.element.setAttribute('transform', 'translate(' + pos.join(' ') + ')');

	return group;
};

SVGElement.mergeAttributes = function (source, attributes) {
	/* Shallow copy of the attributes */
	/* TODO: do we need recursive? */
	var merged = {};
	if (typeof source === 'object') {
		Object.keys(source).forEach(function (key){
			merged[key] = source[key];
		});
	}

	if (typeof attributes === 'object') {
		Object.keys(attributes).forEach(function (key){
			merged[key] = attributes[key];
		});
	}

	return merged;
};
