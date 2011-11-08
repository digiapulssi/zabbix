/*
** Zabbix
** Copyright (C) 2000-2011 Zabbix SIA
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

var ZBX_SCREENS = [];			// screens obj reference
Position.includeScrollOffsets = true;

// screenid ALWAYS must be a STRING (js doesn't support uint64) !!!!
function init_screen(screenid, obj_id, id) {
	if (typeof(id) == 'undefined') {
		id = ZBX_SCREENS.length;
	}

	if (is_number(screenid) && (screenid > 100000000000000)) {
		throw('Error: Wrong type of arguments passed to function [init_screen]');
	}

	ZBX_SCREENS[id] = new Object;
	ZBX_SCREENS[id].screen = new Cscreen(screenid, obj_id, id);
}

var Cscreen = Class.create();
Cscreen.prototype = {
	id: 0, // inner js class id
	screenid: 0,
	screen_obj: null, // DOM ref to screen obj

	initialize: function(screenid, obj_id, id) {
		this.screenid = screenid;
		this.id = id;

		this.screen_obj = $(obj_id);

		function wedge() {
			return false
		}

		jQuery('.draggable').draggable({
			revert: 'invalid',
			opacity: 0.8,
			zIndex: 999,
			start: function() {
				if (IE) {
					Event.observe(document.body, "drag", wedge, false);
					Event.observe(document.body, "selectstart", wedge, false);
				}
			}
		});

		jQuery(".cntr_mdl").droppable({
			accept: '.draggable',
			hoverClass: 'ui-sortable-placeholder',
			drop: this.on_drop,
			tolerance: 'pointer'
		});
	},

	on_drop: function(event, ui) {
		var element = ui.draggable;
		var dropDiv = jQuery(this).children('.draggable');

		var x1 = element.data('xcoord');
		var y1 = element.data('ycoord');
		var x2 = dropDiv.data('xcoord');
		var y2 = dropDiv.data('ycoord');

		var url = new Curl(location.href);
		var params = {
			ajaxAction: 'sw_pos',
			output: 'ajax',
			"sw_pos[0]": y1,
			"sw_pos[1]": x1,
			"sw_pos[2]": y2,
			"sw_pos[3]": x2,
			screenid: url.getArgument('screenid'),
			sid: url.getArgument('sid')
		};

		jQuery.post("screenedit.php", params, function(data) {
			if (!isset('result', data) || !data.result) {
				jQuery('<p>Ajax request error</p>').dialog({
					modal: true,
					resizable: false,
					draggable: false
				});
			}
			else {
				var parent = dropDiv.parent().get(0);
				element.parent().get(0).appendChild(dropDiv.get(0));
				parent.appendChild(element.get(0));

				element.data({ycoord: y2, xcoord: x2});
				dropDiv.data({ycoord: y1, xcoord: x1});
			}
		}, 'json');

		element.css({top: '0px', left: '0px'});
	}
};
