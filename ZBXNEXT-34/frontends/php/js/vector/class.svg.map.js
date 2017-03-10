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

function SVGMap(options) {
	this.layers = {};
	this.options = options;
	this.elements = {};
	this.shapes = {};
	this.links = {};
	this.background = null;
	/* TODO: predefined set of attributes, strict check and merge*/
	this.imageUrl = 'imgstore.php?iconid=';
	this.imageCache = new ImageCache();
	this.canvas = new SVGCanvas(options.canvas, true);

	/* extra group for font styles */
	/* TODO: additional styles needed in global css? */
	var container = this.canvas.add('g', {
		class: 'map-container',
		'font-family': 'Dejavu Sans',
		'font-size': '10px'
	});

	var layers = container.add([
		/* Background */
		{
			type: 'g',
			attributes: {
				class: 'map-background',
				fill: '#' + options.theme.backgroundcolor
			}
		},
		/* Grid */
		{
			type: 'g',
			attributes: {
				class: 'map-grid',
				stroke: '#' + options.theme.gridcolor,
				fill: '#' + options.theme.gridcolor,
				'stroke-width': '1',
				'stroke-dasharray': '4,4',
				'shape-rendering': 'crispEdges'
			}
		},
		/* Custom shapes */
		{
			type: 'g',
			attributes: {
				class: 'map-shapes'
			}
		},
		/* Highlights of elements */
		{
			type: 'g',
			attributes: {
				class: 'map-highlights'
			}
		},
		/* Links */
		{
			type: 'g',
			attributes: {
				class: 'map-links'
			}
		},
		/* Elements */
		{
			type: 'g',
			attributes: {
				class: 'map-elements'
			}
		},
		/* Marks */
		{
			type: 'g',
			attributes: {
				class: 'map-marks',
				fill: 'rgba(150,150,150,0.75)',
				'font-size': '8px',
				'shape-rendering': 'crispEdges'
			},
			/* TODO: there is some magic in positioning of marks */
			content: [
				{
					type: 'text',
					attributes: {
						class: 'map-date',
						x: options.canvas.width - 107,
						y: options.canvas.height - 6
					}
				},

				{
					type: 'text',
					attributes: {
						x: options.canvas.width,
						y: options.canvas.height - 50,
						transform: 'rotate(270 ' + (options.canvas.width) + ', ' + (options.canvas.height - 50) + ')'
					},
					content: options.homepage
				}
			]
		}
	]);

	['background', 'grid', 'shapes', 'highlights', 'links', 'elements', 'marks'].forEach(function (attribute, index) {
		this.layers[attribute] = layers[index];
	}, this);

	this.layers.background.add('rect', {
		x: 0,
		y: 0,
		width: this.options.canvas.width,
		height: this.options.canvas.height
	});

	/* Render goes first as it is needed for getBBox to work */
	/* TODO: move shadow buffer into predefined container (body?) and make this obsolete */
	if (this.options.container) {
		this.render(this.options.container);
	}

	this.update(this.options);
}

SVGMap.toHashmap = function (array, key) {
	var hashMap = {};

	array.forEach(function (item) {
		if (typeof item !== 'object' || item[key] === undefined) {
			/* skip */
			return;
		}

		hashMap[item[key]] = item;
	});

	return hashMap;
};

SVGMap.prototype.getImageUrl = function (id) {
	return this.imageUrl + id;
};

SVGMap.prototype.getImage = function (id) {
	if (id !== undefined && this.imageCache.images[id] !== undefined) {
		return this.imageCache.images[id];
	}

	return null;
};

SVGMap.prototype.updateBackground = function (background) {
	var element = null;

	if (background && background !== '0') {
		if (this.background !== null && background === this.options.background) {
			/* not changed */
			return;
		}

		var image = this.getImage(background);
		element = this.layers.background.add('image', {
			x: 0,
			y: 0,
			width: image.naturalWidth,
			height: image.naturalHeight,
			href: this.getImageUrl(background)
		});
	}

	if (this.background !== null) {
		this.background.remove();
	}

	this.background = element;
};

SVGMap.prototype.setGrid = function (size) {
	this.layers.grid.clear();

	if (size === 0) {
		return;
	}

	/* TODO: some magic numbers */
	for (var x = size; x < this.options.canvas.width; x += size) {
		this.layers.grid.add('line', {
			'x1': x,
			'y1': 0,
			'x2': x,
			'y2': this.options.canvas.height
		});

		this.layers.grid.add('text', {
			'x': x + 3,
			'y': 9 + 3,
			'stroke-width': 0
		}, x);
	}

	for (var y = size; y < this.options.canvas.height; y += size) {
		this.layers.grid.add('line', {
			'x1': 0,
			'y1': y,
			'x2': this.options.canvas.width,
			'y2': y
		});

		this.layers.grid.add('text', {
			'x': 3,
			'y': y + 12,
			'stroke-width': 0
		}, y);
	}

	this.layers.grid.add('text', {
		'x': 2,
		'y': 12,
		'stroke-width': 0
	}, 'Y X:');
};

SVGMap.isChanged = function (source, target) {
	if (typeof source !== 'object') {
		return true;
	}

	var keys = Object.keys(target);
	for (var i = 0; i < keys.length; i++) {
		if (typeof target[keys[i]] === 'object') {
			if (SVGMap.isChanged(source[keys[i]], target[keys[i]])) {
				return true;
			}
		}
		else {
			if (target[keys[i]] !== source[keys[i]]) {
				return true;
			}
		}
	}

	return false;
};

SVGMap.prototype.updateItems = function (type, className, items, incremental) {
	var keys = Object.keys(items);

	if (incremental !== true) {
		Object.keys(this[type]).forEach(function (key) {
			if (keys.indexOf(key) === -1) {
				this[type][key].remove();
			}
		}, this);
	}

	keys.forEach(function (key) {
		if (typeof this[type][key] !== 'object') {
			this[type][key] = new window[className](this, {});
		}

		this[type][key].update(items[key]);
	}, this);
};

SVGMap.prototype.updateTimestamp = function (timestamp) {
	if (this.timestamp === undefined) {
		var elements = this.canvas.getElementsByAttributes({class: 'map-date'});

		if (elements.length === 1) {
			this.timestamp = elements[0];
		}
		else {
			throw "Timestamp element is missing";
		}
	}

	this.timestamp.element.textContent = timestamp;
};

SVGMap.prototype.update = function (options, incremental) {
	var images = {};
	var rules = [
		{
			name: 'elements',
			field: 'selementid'
		},
		{
			name: 'links',
			field: 'linkid'
		},
		{
			name: 'shapes',
			field: 'id'
		}
	];

	rules.forEach(function (rule) {
		if (options[rule.name] !== undefined) {
			options[rule.name] = SVGMap.toHashmap(options[rule.name], rule.field);
		}
		else {
			options[rule.name] = {};
		}
	});

	Object.keys(options.elements).forEach(function (key) {
		var element = options.elements[key];
		if (element.icon !== undefined) {
			images[element.icon] = this.getImageUrl(element.icon);
		}
	}, this);

	if (options.background) {
		images[options.background] = this.getImageUrl(options.background);
	}

	this.imageCache.preload(images, function () {
		this.updateItems('elements', 'SVGMapElement', options.elements, incremental);
		this.updateItems('shapes', 'SVGMapShape', options.shapes, incremental);
		this.updateItems('links', 'SVGMapLink', options.links, incremental);
		this.updateBackground(options.background, incremental);

		this.options = SVGElement.mergeAttributes(this.options, options);
	}, this);

	if (options.timestamp) {
		this.updateTimestamp(options.timestamp);
	}
};

SVGMap.prototype.render = function (container) {
	if (typeof container === 'string') {
		container = jQuery(container)[0];
	}
	this.canvas.render(container);
};

SVGMap.isChanged = function (source, target) {
	if (typeof source !== 'object' || source === null) {
		return true;
	}

	var keys = Object.keys(target);
	for (var i = 0; i < keys.length; i++) {
		if (typeof target[keys[i]] === 'object') {
			if (SVGMap.isChanged(source[keys[i]], target[keys[i]])) {
				return true;
			}
		}
		else {
			if (target[keys[i]] !== source[keys[i]]) {
				return true;
			}
		}
	}

	return false;
};

function SVGMapElement(map, options) {
	this.map = map;
	this.options = options;

	this.highlight = null;
	this.image = null;
	this.label = null;
	this.markers = null;
}

SVGMapElement.LABEL_POSITION_NONE	= null;
SVGMapElement.LABEL_POSITION_DEFAULT	= -1;
SVGMapElement.LABEL_POSITION_BOTTOM	= 0;
SVGMapElement.LABEL_POSITION_LEFT	= 1;
SVGMapElement.LABEL_POSITION_RIGHT	= 2;
SVGMapElement.LABEL_POSITION_TOP	= 3;

SVGMapElement.prototype.removeItem = function (item) {
	if (this[item] !== null) {
		this[item].remove();
		this[item] = null;
	}
};

SVGMapElement.prototype.remove = function () {
	['highlight', 'image', 'label', 'markers'].forEach(function (name) {
		this.removeItem(name);
	}, this);
};

SVGMapElement.prototype.updateHighlight = function() {
	var type = null,
		options = null;

	if (this.options.latelyChanged) {
		var radius = Math.floor(this.width / 2) + 12;

		var markers = [];
		if (this.options.label_location !== SVGMapElement.LABEL_POSITION_DEFAULT &&
			this.options.label_location !== SVGMapElement.LABEL_POSITION_BOTTOM) {
			markers.push({
				type: 'path',
				attributes: {
					d: 'M11, 2.91 L5.87, 8 L11, 13.09 L8.07, 16 L0, 8 L8.07, 0, L11, 2.91',
					transform: 'rotate(90 ' + (this.center.x+8) + ',' + (this.center.y+radius) + ') translate(' + (this.center.x+8) + ',' + (this.center.y+radius) + ')'
				}
			});
		}

		if (this.options.label_location !== SVGMapElement.LABEL_POSITION_LEFT) {
			markers.push({
				type: 'path',
				attributes: {
					d: 'M11, 2.91 L5.87, 8 L11, 13.09 L8.07, 16 L0, 8 L8.07, 0, L11, 2.91',
					transform: 'rotate(180 ' + (this.center.x-radius) + ',' + (this.center.y+8) + ') translate(' + (this.center.x-radius) + ',' + (this.center.y+8) + ')'
				}
			});
		}

		if (this.options.label_location !== SVGMapElement.LABEL_POSITION_RIGHT) {
			markers.push({
				type: 'path',
				attributes: {
					d: 'M11, 2.91 L5.87, 8 L11, 13.09 L8.07, 16 L0, 8 L8.07, 0, L11, 2.91',
					transform: 'translate(' + (this.center.x+radius) + ',' + (this.center.y-8) + ')'
				}
			});
		}

		if (this.options.label_location !== SVGMapElement.LABEL_POSITION_TOP) {
			markers.push({
				type: 'path',
				attributes: {
					d: 'M11, 2.91 L5.87, 8 L11, 13.09 L8.07, 16 L0, 8 L8.07, 0, L11, 2.91',
					transform: 'rotate(270 ' + (this.center.x-8) + ',' + (this.center.y-radius) + ') translate(' + (this.center.x-8) + ',' + (this.center.y-radius) + ')'
				}
			});
		}

		/* TODO: update only if changed */
		var element = this.map.layers.highlights.add('g', {
			fill: '#F44336',
			stroke: '#B71C1C'
		}, markers);
		this.removeItem('markers');
		this.markers = element;
	}
	else {
		this.removeItem('markers');
	}

	if (typeof this.options.highlight === 'object' && this.options.highlight !== null) {
		/* TODO: move paddings to options? */
		if (this.options.highlight.st !== null) {
			type = 'rect';
			options = {
				x: this.x - 2,
				y: this.y - 2,
				width: this.width + 4,
				height: this.height + 4,
				fill: '#' + this.options.highlight.st,
				'fill-opacity': 0.5
			};
		}

		if (this.options.highlight.hl !== null) {
			type = 'ellipse';
			options = {
				cx: this.center.x,
				cy: this.center.y,
				rx: Math.floor(this.width / 2) + 10,
				ry: Math.floor(this.width / 2) + 10,
				fill: '#' + this.options.highlight.hl
			};

			if (this.options.highlight.ack === true) {
				options.stroke = '#329632';
				options['stroke-width'] = '4px';
			}
			else {
				options['stroke-width'] = '0';
			}
		}
	}

	if (type !== null) {
		if (this.highlight === null || type !== this.highlight.type) {
			var element = this.map.layers.highlights.add(type, options);
			this.removeItem('highlight');
			this.highlight = element;
		}
		else {
			this.highlight.update(options);
		}
	}
	else {
		this.removeItem('highlight');
	}
};

SVGMapElement.prototype.updateImage = function() {
	var image,
		options =  {
			x: this.x,
			y: this.y,
			width: this.width,
			height: this.height
		};

	if (this.options.actions !== null) {
		options['data-menu-popup'] = this.options.actions;
		options['style'] = 'cursor: pointer';
	}

	if (this.options.icon !== undefined) {
		var href = this.map.getImageUrl(this.options.icon);

		if (this.image === null || this.image.attributes.href !== href) {
			options.href = href;

			var image = this.map.layers.elements.add('image', options);
			this.removeItem('image');
			this.image = image;
		}
		else {
			this.image.update(options);
		}
	}
	else {
		this.removeItem('image');
	}
};

SVGMapElement.prototype.updateLabel = function() {
	var x = this.center.x,
		y = this.center.y,
		anchor = {
			horizontal: 'left',
			vertical: 'top'
		};

	switch (this.options.label_location) {
		case SVGMapElement.LABEL_POSITION_DEFAULT:
		case SVGMapElement.LABEL_POSITION_BOTTOM:
			y = this.y + this.height + this.map.canvas.textPadding;
			anchor.horizontal = 'center';
			break;

		case SVGMapElement.LABEL_POSITION_LEFT:
			x = this.x - this.map.canvas.textPadding;
			anchor.horizontal = 'right';
			anchor.vertical = 'middle';
			break;

		case SVGMapElement.LABEL_POSITION_RIGHT:
			x = this.x + this.width + this.map.canvas.textPadding;
			anchor.vertical = 'middle';
			break;

		case SVGMapElement.LABEL_POSITION_TOP:
			y = this.y - this.map.canvas.textPadding;
			anchor.horizontal = 'center';
			anchor.vertical = 'bottom';
			break;
	}

	if (this.options.label !== null) {
		var element = this.map.layers.elements.addText(x, y, this.options.label, {
			fill: '#' + this.map.options.theme.textcolor
		}, anchor);
		this.removeItem('label');
		this.label = element;
	}
	else {
		this.removeItem('label');
	}
};

SVGMapElement.prototype.update = function(options) {
	var image = this.map.getImage(options.icon);
	if (image === null) {
		throw "Invalid element configuration!";
	}

	/* data type normalization */
	['x', 'y', 'width', 'height', 'label_location'].forEach(function(name) {
		if (options[name] !== undefined) {
			options[name] = parseInt(options[name]);
		}
	});

	if (options.width !== undefined && options.height !== undefined) {
		options.x += Math.floor(options.width / 2) - Math.floor(image.naturalWidth / 2);
		options.y += Math.floor(options.height / 2) - Math.floor(image.naturalHeight / 2);
	}

	options.width = image.naturalWidth;
	options.height = image.naturalHeight;

	if (options.label === null) {
		options.label_location = SVGMapElement.LABEL_POSITION_NONE;
	}

	if (SVGMap.isChanged(this.options, options) === false) {
		/* no need to update */
		return;
	}

	this.options = options;

	if (this.x !== options.x || this.y !== options.y || this.width !== options.width || this.height !== options.height) {
		['x', 'y', 'width', 'height'].forEach(function(name) {
			this[name] = options[name];
		}, this);

		this.center = {
			x: this.x + Math.floor(this.width / 2),
			y: this.y + Math.floor(this.height / 2)
		};
	}

	this.updateHighlight();
	this.updateImage();
	this.updateLabel();
};

function SVGMapLink(map, options) {
	this.map = map;
	this.options = options;
	this.element = null;
}

SVGMapLink.LINE_STYLE_DEFAULT	= 0;
SVGMapLink.LINE_STYLE_BOLD	= 2;
SVGMapLink.LINE_STYLE_DOTTED	= 3;
SVGMapLink.LINE_STYLE_DASHED	= 4;

SVGMapLink.prototype.update = function(options) {
	/* data type normalization */
	options.drawtype = parseInt(options.drawtype);

	options.elements = [this.map.elements[options.selementid1], this.map.elements[options.selementid2]];
	if (options.elements[0] === undefined || options.elements[1] === undefined) {
		var remove = true;

		if (options.elements[0] === options.elements[1]) {
			/* check if hostgroup to hostgroup */
			options.elements = [this.map.shapes[options.selementid1], this.map.shapes[options.selementid2]];

			remove = (options.elements[0] === undefined || options.elements[1] === undefined);
		}

		if (remove) {
			/* invalid link configuration */
			this.remove();
			return;
		}
	}

	options.elements[0] = options.elements[0].center;
	options.elements[1] = options.elements[1].center;
	options.center = {
		x: options.elements[0].x + Math.floor((options.elements[1].x - options.elements[0].x)/2),
		y: options.elements[0].y + Math.floor((options.elements[1].y - options.elements[0].y)/2)
	};

	if (SVGMap.isChanged(this.options, options) === false) {
		/* no need to update */
		return;
	}

	this.options = options;
	this.remove();

	var attributes = {
		stroke: '#' + options.color,
		'stroke-width': 1,
		fill: '#' + this.map.options.theme.backgroundcolor
	};

	switch (options.drawtype) {
		case SVGMapLink.LINE_STYLE_BOLD:
			attributes['stroke-width'] = 2;
		break;

		case SVGMapLink.LINE_STYLE_DOTTED:
			attributes['stroke-dasharray'] = '1,2';
		break;

		case SVGMapLink.LINE_STYLE_DASHED:
			attributes['stroke-dasharray'] = '4,4';
		break;
	}

	this.element = this.map.layers.links.add('g', attributes, [
		{
			type: 'line',
			attributes: {
				x1: options.elements[0].x,
				y1: options.elements[0].y,
				x2: options.elements[1].x,
				y2: options.elements[1].y
			}
		}
	]);

	this.element.addText(options.center.x, options.center.y, options.label, {
			fill: '#' + this.map.options.theme.textcolor,
			'font-size': '10px',
			'stroke-width': 0
		}, {
			horizontal: 'center',
			vertical: 'middle'
		}, {
			'shape-rendering':'crispEdges'
		}
	);
};

SVGMapLink.prototype.remove = function () {
	if (this.element !== null) {
		this.element.remove();
		this.element = null;
	}
};

function SVGMapShape(map, options) {
	this.map = map;
	this.options = options;
	this.element = null;
}

SVGMapShape.prototype.update = function(options) {
	if (SVGMap.isChanged(this.options, options) === false) {
		/* no need to update */
		return;
	}

	this.options = options;
	this.remove();
	this.element = this.map.layers.shapes.add(options.type, options.attributes);

	['x', 'y', 'width', 'height'].forEach(function(name) {
		this[name] = parseInt(options.attributes[name]);
	}, this);

	this.center = {
		x: this.x + Math.floor(this.width / 2),
		y: this.y + Math.floor(this.height / 2)
	};
};

SVGMapShape.prototype.remove = function () {
	if (this.element !== null) {
		this.element.remove();
		this.element = null;
	}
};
