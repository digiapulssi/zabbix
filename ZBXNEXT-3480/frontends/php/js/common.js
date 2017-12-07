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


jQuery.noConflict();

function isset(key, obj) {
	return (is_null(key) || is_null(obj)) ? false : (typeof(obj[key]) != 'undefined');
}

/**
 * @deprecated  use strict comparison instead
 *
 * @param obj
 * @returns {*}
 */
function empty(obj) {
	if (is_null(obj)) {
		return true;
	}
	if (obj === false) {
		return true;
	}
	if (is_string(obj) && obj === '') {
		return true;
	}
	if (typeof(obj) == 'undefined') {
		return true;
	}

	return is_array(obj) && obj.length == 0;
}

/**
 * @deprecated use === null instead
 *
 * @param obj
 * @returns {boolean}
 */
function is_null(obj) {
	return (obj == null);
}

function is_number(obj) {
	return isNaN(obj) ? false : (typeof(obj) === 'number');
}

function is_object(obj, instance) {
	if (typeof(instance) === 'object' || typeof(instance) === 'function') {
		if (typeof(obj) === 'object' && obj instanceof instance) {
			return true;
		}
	}
	else {
		if (typeof(obj) === 'object') {
			return true;
		}
	}

	return false;
}

function is_string(obj) {
	return (typeof(obj) === 'string');
}

function is_array(obj) {
	return (obj != null) && (typeof obj == 'object') && ('splice' in obj) && ('join' in obj);
}

function addListener(element, eventname, expression, bubbling) {
	bubbling = bubbling || false;
	element = $(element);

	if (element.addEventListener) {
		element.addEventListener(eventname, expression, bubbling);
		return true;
	}
	else if (element.attachEvent) {
		element.attachEvent('on' + eventname, expression);
		return true;
	}
	else {
		return false;
	}
}

function removeListener(element, eventname, expression, bubbling) {
	bubbling = bubbling || false;
	element = $(element);

	if (element.removeEventListener) {
		element.removeEventListener(eventname, expression, bubbling);
		return true;
	}
	else if (element.detachEvent) {
		element.detachEvent('on' + eventname, expression);
		return true;
	}
	else {
		return false;
	}
}

function cancelEvent(e) {
	if (!e) {
		e = window.event;
	}

	e.stopPropagation();
	e.preventDefault();

	if (IE) {
		e.cancelBubble = true;
		e.returnValue = false;
	}

	return false;
}

function add_variable(o_el, s_name, x_value, s_formname, o_document) {
	var form;

	if (!o_document) {
		o_document = document;
	}

	if (s_formname) {
		if (!(form = o_document.forms[s_formname])) {
			throw "Missing form with name '" + s_formname + "'.";
		}
	}
	else if (o_el) {
		if (!(form = o_el.form)) {
			throw "Missing form in 'o_el' object";
		}
	}
	else {
		if (!(form = this.form)) {
			throw "Missing form in 'this' object";
		}
	}

	var o_variable = o_document.createElement('input');
	if (!o_variable) {
		throw "Can't create element";
	}
	o_variable.type = 'hidden';
	o_variable.name = s_name;
	o_variable.id = s_name;
	o_variable.value = x_value;
	form.appendChild(o_variable);

	return true;
}

function checkAll(form_name, chkMain, shkName) {
	var frmForm = document.forms[form_name],
		value = frmForm.elements[chkMain].checked;

	chkbxRange.checkObjectAll(shkName, value);
	chkbxRange.update(shkName);
	chkbxRange.saveCookies(shkName);

	return true;
}

function checkLocalAll(form_name, chkMain, chkName) {
	var frmForm = document.forms[form_name];
	var checkboxes = $$('input[name=' + chkName + ']');

	for (var i = 0; i < checkboxes.length; i++) {
		if (isset('type', checkboxes[i]) && checkboxes[i].type == 'checkbox') {
			checkboxes[i].checked = frmForm.elements[chkMain].checked;
		}
	}

	return true;
}

function close_window() {
	window.setTimeout('window.close();', 500); // solve bug for Internet Explorer
	return false;
}

function Confirm(msg) {
	return confirm(msg);
}

function create_var(form_name, var_name, var_value, doSubmit) {
	var objForm = is_string(form_name) ? document.forms[form_name] : form_name;
	if (!objForm) {
		return false;
	}

	var objVar = (typeof(objForm[var_name]) != 'undefined') ? objForm[var_name] : null;
	if (is_null(objVar)) {
		objVar = document.createElement('input');
		objVar.setAttribute('type', 'hidden');
		if (!objVar) {
			return false;
		}
		objVar.setAttribute('name', var_name);
		objVar.setAttribute('id', var_name.replace(']', '').replace('[', '_'));
		objForm.appendChild(objVar);
	}

	if (is_null(var_value)) {
		objVar.parentNode.removeChild(objVar);
	}
	else {
		objVar.value = var_value;
	}

	if (doSubmit) {
		objForm.submit();
	}

	return false;
}

function getDimensions(obj) {
	obj = $(obj);

	var dim = {
		left:		0,
		top:		0,
		right:		0,
		bottom:		0,
		width:		0,
		height:		0,
		offsetleft:	0
	};

	if (!is_null(obj) && typeof(obj.offsetParent) != 'undefined') {
		var dim = {
			left:	parseInt(obj.style.left, 10),
			top:	parseInt(obj.style.top, 10),
			width:	parseInt(obj.style.width, 10),
			height:	parseInt(obj.style.height, 10),
			offsetleft: parseInt(jQuery(obj).offset().left, 10)
		};

		if (!is_number(dim.top)) {
			dim.top = parseInt(obj.offsetTop, 10);
		}
		if (!is_number(dim.left)) {
			dim.left = parseInt(obj.offsetLeft, 10);
		}
		if (!is_number(dim.width)) {
			dim.width = parseInt(obj.offsetWidth, 10);
		}
		if (!is_number(dim.height)) {
			dim.height = parseInt(obj.offsetHeight, 10);
		}

		dim.right = dim.left + dim.width;
		dim.bottom = dim.top + dim.height;
	}

	return dim;
}

function getPosition(obj) {
	obj = $(obj);
	var pos = {top: 0, left: 0};

	if (!is_null(obj) && typeof(obj.offsetParent) != 'undefined') {
		pos.left = obj.offsetLeft;
		pos.top = obj.offsetTop;

		try {
			while (!is_null(obj.offsetParent)) {
				obj = obj.offsetParent;
				pos.left += obj.offsetLeft;
				pos.top += obj.offsetTop;

				if (IE && obj.offsetParent.toString() == 'unknown') {
					break;
				}
			}
		} catch(e) {
		}
	}

	return pos;
}

function get_bodywidth() {
	var w = parseInt(document.body.scrollWidth);
	var w2 = parseInt(document.body.offsetWidth);

	return (w2 < w) ? w2 : w;
}

function get_cursor_position(e) {
	e = e || window.event;
	var cursor = {x: 0, y: 0};

	if (e.pageX || e.pageY) {
		cursor.x = e.pageX;
		cursor.y = e.pageY;
	}
	else {
		var de = document.documentElement;
		var b = document.body;
		cursor.x = e.clientX + (de.scrollLeft || b.scrollLeft) - (de.clientLeft || 0);
		cursor.y = e.clientY + (de.scrollTop || b.scrollTop) - (de.clientTop || 0);
	}

	return cursor;
}

function get_scroll_pos() {
	var scrOfX = 0, scrOfY = 0;

	// netscape compliant
	if (typeof(window.pageYOffset) == 'number') {
		scrOfY = window.pageYOffset;
		scrOfX = window.pageXOffset;
	}
	// DOM compliant
	else if (document.body && (document.body.scrollLeft || document.body.scrollTop)) {
		scrOfY = document.body.scrollTop;
		scrOfX = document.body.scrollLeft;
	}
	// IE6 standards compliant mode
	else if (document.documentElement && (document.documentElement.scrollLeft || document.documentElement.scrollTop)) {
		scrOfY = document.documentElement.scrollTop;
		scrOfX = document.documentElement.scrollLeft;
	}

	return [scrOfX, scrOfY];
}

function openWinCentered(url, name, width, height, params) {
	var top = Math.ceil((screen.height - height) / 2),
		left = Math.ceil((screen.width - width) / 2);

	if (params.length > 0) {
		params = ', ' + params;
	}

	var windowObj = window.open(new Curl(url).getUrl(), name,
		'width=' + width + ', height=' + height + ', top=' + top + ', left=' + left + params
	);
	windowObj.focus();
}

/**
 * Opens popup content in overlay dialogue.
 *
 * @param {string} action		Popup controller related action.
 * @param {array} options		Array with key/value pairs that will be used as query for popup request.
 * @param {string} dialogueid	(optional) id of overlay dialogue.
 *
 * @returns false
 */
function PopUp(action, options, dialogueid) {
	var ovelay_properties = {
		'title': '',
		'content': jQuery('<div>')
			.css({'height': '68px'})
			.append(jQuery('<div>')
				.addClass('preloader-container')
				.append(jQuery('<div>').addClass('preloader'))
			),
		'class': 'modal-popup',
		'buttons': [],
		'dialogueid': (typeof dialogueid === 'undefined') ? getOverlayDialogueId() : dialogueid
	};
	var url = new Curl('zabbix.php');

	url.setArgument('action', action);
	jQuery.each(options, function(key, value) {
		url.setArgument(key, value);
	});

	jQuery.ajax({
		url: url.getUrl(),
		type: 'get',
		dataType: 'json',
		beforeSend: function() {
			overlayDialogue(ovelay_properties);
		},
		success: function(resp) {
			if (typeof resp.errors !== 'undefined') {
				ovelay_properties['content'] = resp.errors;
			}
			else {
				var buttons = resp.buttons !== null ? resp.buttons : [];

				buttons.push({
					'title': t('Cancel'),
					'class': 'btn-alt',
					'action': function() {}
				});

				ovelay_properties['title'] = resp.header;
				ovelay_properties['content'] = resp.body;
				ovelay_properties['controls'] = resp.controls;
				ovelay_properties['buttons'] = buttons;

				if (typeof resp.script_inline !== 'undefined') {
					ovelay_properties['script_inline'] = resp.script_inline;
				}
			}

			overlayDialogue(ovelay_properties);
		}
	});

	return false;
}

/**
 * Reload content of Modal Overlay dialogue without closing it.
 *
 * @param {object} form		Filter form in which element has been changed. Assumed that form is inside Overlay Dialogue.
 * @param {string} action	(optional) action value that is used in CRouter. Default value is 'popup.generic'.
 */
function reloadPopup(form, action) {
	var dialogueid = jQuery(form).closest('[data-dialogueid]').attr('data-dialogueid'),
		action = action || 'popup.generic',
		options = {};

	jQuery(form.elements).each(function() {
		options[this.name] = this.value;
	});

	PopUp(action, options, dialogueid);
}

/**
 * Pass value to add.popup trigger.
 *
 * @param {string} object			refers to destination object
 * @param {string} single_value		value passed to destination object
 * @param {string} parentid			parent id
 */
function addValue(object, single_value, parentid) {
	var value = {};
	if (isset(single_value, popup_reference)) {
		value = popup_reference[single_value];
	}
	else {
		value[object] = single_value;
	}

	if (typeof parentid === 'undefined') {
		var parentid = null;
	}
	var data = {
		object: object,
		values: [value],
		parentId: parentid
	};

	jQuery(document).trigger('add.popup', data);
}

/**
 * Adds multiple values to destination form.
 *
 * @param {string} frame			refers to destination form
 * @param {object} values			values added to destination form
 * @param {boolean} submit_parent	indicates that after adding values, form must be submitted
 */
function addValues(frame, values, submit_parent) {
	var forms = document.getElementsByTagName('FORM')[frame],
		submit_parent = submit_parent || false,
		frm_storage = null;

	for (var key in values) {
		if (values[key] === null) {
			continue;
		}

		if (typeof forms !== 'undefined') {
			frm_storage = jQuery(forms).find('#' + key).get(0);
		}
		if (typeof frm_storage === 'undefined' || frm_storage === null) {
			frm_storage = document.getElementById(key);
		}

		if (jQuery(frm_storage).is('span')) {
			jQuery(frm_storage).html(values[key]);
		}
		else {
			jQuery(frm_storage).val(values[key]).change();
		}
	}

	if (frm_storage !== null && submit_parent) {
		frm_storage.form.submit();
	}
}

/**
 * Collects checked values and passes them to add.popup trigger.
 *
 * @param {string} form			source form where checkbox are collected
 * @param {string} object		refers to object that is selected from popup
 * @param {string} parentid		parent id
 */
function addSelectedValues(form, object, parentid) {
	form = $(form);

	if (typeof parentid === 'undefined') {
		var parentid = null;
	}

	var data = {object: object, values: [], parentId: parentid};
	var chk_boxes = form.getInputs('checkbox');

	for (var i = 0; i < chk_boxes.length; i++) {
		if (chk_boxes[i].checked && (chk_boxes[i].name.indexOf('all_') < 0)) {
			var value = {};
			if (isset(chk_boxes[i].value, popup_reference)) {
				value = popup_reference[chk_boxes[i].value];
			}
			else {
				value[object] = chk_boxes[i].value;
			}
			data['values'].push(value);
		}
	}

	jQuery(document).trigger('add.popup', data);
}

/**
 * Add media.
 *
 * @param {string} formname			name of destination form
 * @param {integer} media			media id. If -1, then assumes that this is new media
 * @param {integer} mediatypeid		media type id
 * @param {string} sendto			media sendto value
 * @param {string} period			media period value
 * @param {string} active			media active value
 * @param {string} severity			media severity value
 *
 * @returns true
 */
function add_media(formname, media, mediatypeid, sendto, period, active, severity) {
	var form = window.document.forms[formname];
	var media_name = (media > -1) ? 'user_medias[' + media + ']' : 'new_media';

	window.create_var(form, media_name + '[mediatypeid]', mediatypeid);
	window.create_var(form, media_name + '[sendto]', sendto);
	window.create_var(form, media_name + '[period]', period);
	window.create_var(form, media_name + '[active]', active);
	window.create_var(form, media_name + '[severity]', severity);

	form.submit();
	return true;
}

/**
 * Send media form data to server for validation before adding them to user media tab.
 *
 * @param {string} formname		form name that is sent to server for validation
 */
function validate_media(formname) {
	var form = window.document.forms[formname];

	jQuery.ajax({
		url: jQuery(form).attr('action'),
		data: jQuery(form).serialize(),
		success: function(ret) {
			jQuery(form).parent().find('.msg-bad, .msg-good').remove();

			if (typeof ret.errors !== 'undefined') {
				jQuery(ret.errors).insertBefore(jQuery(form));
			}
			else {
				add_media(ret.dstfrm, ret.media, ret.mediatypeid, ret.sendto, ret.period, ret.active, ret.severity);
			}
		},
		dataType: 'json',
		type: 'post'
	});
}

/**
 * Send trigger expression form data to server for validation before adding it to trigger expression field.
 *
 * @param {string} formname		form name that is sent to server for validation
 * @param {string} dialogueid	(optional) id of overlay dialogue.
 */
function validate_trigger_expression(formname, dialogueid) {
	var form = window.document.forms[formname],
		url = new Curl(jQuery(form).attr('action')),
		dialogueid = dialogueid || null;

	url.setArgument('add', 1);

	jQuery.ajax({
		url: url.getUrl(),
		data: jQuery(form).serialize(),
		success: function(ret) {
			jQuery(window.document.forms[formname]).parent().find('.msg-bad, .msg-good').remove();

			if (typeof ret.errors !== 'undefined') {
				jQuery(ret.errors).insertBefore(jQuery(window.document.forms[formname]));
			}
			else {
				var form = window.document.forms[ret.dstfrm];
				var obj = (typeof form !== 'undefined')
					? jQuery(form).find('#' + ret.dstfld1).get(0)
					: document.getElementById(ret.dstfld1);

				if (ret.dstfld1 === 'expression' || ret.dstfld1 === 'recovery_expression') {
					jQuery(obj).val(jQuery(obj).val() + ret.expression);
				}
				else {
					jQuery(obj).val(ret.expression);
				}

				if (dialogueid) {
					overlayDialogueDestroy(dialogueid);
				}
			}
		},
		dataType: 'json',
		type: 'post'
	});
}

/**
 * Send http test step form data to server for validation before adding it to web scenarion tab.
 *
 * @param {string} formname		form name that is sent to server for validation
 * @param {string} dialogueid	(optional) id of overlay dialogue.
 */
function validate_httpstep(formname, dialogueid) {
	var form = window.document.forms[formname],
		url = new Curl(jQuery(form).attr('action')),
		dialogueid = dialogueid || null;

	url.setArgument('validate', 1);

	jQuery.ajax({
		url: url.getUrl(),
		data: jQuery(form).serialize(),
		success: function(ret) {
			jQuery(form).parent().find('.msg-bad, .msg-good').remove();

			if (typeof ret.errors !== 'undefined') {
				jQuery(ret.errors).insertBefore(jQuery(form));
			}
			else {
				if (typeof ret.params.stepid !== 'undefined') {
					update_httpstep(ret.dstfrm, ret.list_name, ret.params);
				}
				else {
					add_httpstep(ret.dstfrm, ret.params);
				}

				if (dialogueid) {
					overlayDialogueDestroy(dialogueid);
				}
			}
		},
		dataType: 'json',
		type: 'post'
	});
}

function redirect(uri, method, needle, invert_needle) {
	method = method || 'get';
	var url = new Curl(uri);

	if (method.toLowerCase() == 'get') {
		window.location = url.getUrl();
	}
	else {
		// useless param just for easier loop
		var action = '';
		var domBody = document.getElementsByTagName('body')[0];
		var postForm = document.createElement('form');
		domBody.appendChild(postForm);
		postForm.setAttribute('method', 'post');

		invert_needle = (typeof(invert_needle) != 'undefined' && invert_needle);

		var args = url.getArguments();
		for (var key in args) {
			if (empty(args[key])) {
				continue;
			}

			var is_needle = (typeof(needle) != 'undefined' && key.indexOf(needle) > -1);

			if ((is_needle && !invert_needle) || (!is_needle && invert_needle)) {
				action += '&' + key + '=' + args[key];
				continue;
			}

			var hInput = document.createElement('input');
			hInput.setAttribute('type', 'hidden');
			postForm.appendChild(hInput);
			hInput.setAttribute('name', key);
			hInput.setAttribute('value', args[key]);
		}

		postForm.setAttribute('action', url.getPath() + '?' + action.substr(1));
		postForm.submit();
	}

	return false;
}

function showHide(obj) {
	if (jQuery(obj).is(':hidden')) {
		jQuery(obj).css('display', 'block');
	}
	else {
		jQuery(obj).css('display', 'none');
	}
}

function showHideVisible(obj) {
	if (is_string(obj)) {
		obj = document.getElementById(obj);
	}
	if (!obj) {
		throw 'showHideVisible(): Object not found.';
	}

	if (obj.style.visibility != 'hidden') {
		obj.style.visibility = 'hidden';
	}
	else {
		obj.style.visibility = 'visible';
	}
}

function showHideByName(name, style) {
	if (typeof(style) == 'undefined') {
		style = 'none';
	}

	var objs = $$('[name=' + name + ']');

	if (empty(objs)) {
		throw 'showHideByName(): Object not found.';
	}

	for (var i = 0; i < objs.length; i++) {
		var obj = objs[i];
		obj.style.display = style;
	}
}

/**
 * Switch element classes and return final class.
 *
 * @param object|string obj			object or object id
 * @param string        class1
 * @param string        class2
 *
 * @return string
 */
function switchElementClass(obj, class1, class2) {
	obj = (typeof obj === 'string') ? jQuery('#' + obj) : jQuery(obj);

	if (obj.length > 0) {
		if (obj.hasClass(class1)) {
			obj.removeClass(class1);
			obj.addClass(class2);

			return class2;
		}
		else if (obj.hasClass(class2)) {
			obj.removeClass(class2);
			obj.addClass(class1);

			return class1;
		}
	}

	return null;
}

/**
 * Returns the file name of the given path.
 *
 * @param string path
 * @param string suffix
 *
 * @return string
 */
function basename(path, suffix) {
	var name = path.replace(/^.*[\/\\]/g, '');

	if (typeof suffix === 'string' && name.substr(name.length - suffix.length) == suffix) {
		name = name.substr(0, name.length - suffix.length);
	}

	return name;
}

/**
 * Transform datetime parts to two digits e.g., 2 becomes 02.
 *
 * @param int val
 *
 * @return string
 */
function appendZero(val) {
	return val < 10 ? '0' + val : val;
}

/**
 * Trims selected element values.
 *
 * @param array selectors
 */
jQuery.fn.trimValues = function(selectors) {
	var form = this,
		obj;

	jQuery.each(selectors, function(i, value) {
		obj = jQuery(value, form);
		obj.val(jQuery.trim(obj.val()));
	});
};

/**
 * Inserts hidden input into a form
 *
 * @param string form_name
 * @param string input_name
 * @param string input_value
 */
function submitFormWithParam(form_name, input_name, input_value) {
	var input = document.createElement('input');
	input.setAttribute('type', 'hidden');
	input.setAttribute('name', input_name);
	input.setAttribute('value', input_value);
	document.forms[form_name].appendChild(input);
	jQuery(document.forms[form_name]).trigger('submit');
}
