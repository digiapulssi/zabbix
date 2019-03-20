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
 * Timeout controlled player.
 *
 * It plays, meanwhile decrementing timeout.
 * Pausing and playing is done by 'volume', 'muted' media properties adjust only.
 * It hold infinite loop so it allows us easily adjust timeout while player is running.
 *
 * Fluent setters may be used in any order,
 * still it is suggested to use 'timeout' as last one.
 *
 * Since it is very specific player which has to share timeout and audio across tabs,
 * it is not global, but notifications specific.
 */
function ZBX_NotificationsAudio() {
	this.audio = new Audio();

	this.audio.volume = 0;
	this.audio.muted = true;
	this.audio.autoplay = true;
	this.audio.loop = true;

	this.audio.onloadeddata = this.handleOnloadeddata.bind(this)
	this.onTimeout = function() {};

	this.audio.load();

	this.wave = '';
	this.msTimeout = 0;
	this.listen();
}

/**
 * Starts main loop.
 *
 * @return int  Interval id.
 */
ZBX_NotificationsAudio.prototype.listen = function() {
	var msStep = 10;

	return setInterval(function(){
		if (this.playOnceOnReady) {
			return this.once();
		}

		this.msTimeout -= msStep;

		if (this.msTimeout < 1) {
			!this.audio.muted && this.onTimeout();
			this.audio.muted = true;
			this.audio.volume = 0;
			this.msTimeout = 0;
			return;
		}

		this.audio.muted = false;
		this.audio.volume = 1;

	}.bind(this), msStep);
}

/**
 * File is applied only if it is different than on instate, so this method
 * may be called repeatedly, and will not interrupt playback.
 *
 * @param {string} file  Audio file path relative to DOCUMENT_ROOT/audio/ directory.
 *
 * @return {ZBX_NotificationsAudio}
 */
ZBX_NotificationsAudio.prototype.file = function(file) {
	if (this.wave == file) {
		return this;
	}

	this.wave = file;

	if (!this.wave) {
		this.audio.removeAttribute('src');
	}
	else {
		this.audio.src = 'audio/' + this.wave;
	}

	return this;
}

/**
 * Set player seek position.
 *
 * There are no safety checks, if one decides to seek out of bounds - no audio.
 *
 * @param {float} seconds
 *
 * @return {ZBX_NotificationsAudio}
 */
ZBX_NotificationsAudio.prototype.seek = function(seconds) {
	if (this.audio.readyState > 0) {
		this.audio.currentTime = seconds;
	}
	return this;
}

/**
 * Once file duration is known, this method seeks player to beginning
 * and sets timeout equal to file duration.
 *
 * @return {ZBX_NotificationsAudio}
 */
ZBX_NotificationsAudio.prototype.once = function() {
	if (this.playOnceOnReady && this.audio.readyState >= 3) {
		this.playOnceOnReady = false;
		return this.seek(0).timeout(this.audio.duration);
	}

	this.playOnceOnReady = true;

	return this;
}

/**
 * An alias method. Player is stopped by truncating timeout.
 *
 * @return {ZBX_NotificationsAudio}
 */
ZBX_NotificationsAudio.prototype.stop = function() {
	return this.timeout(0);
}

/**
 * Will play for seconds given, since this call.
 * If "0" given - will just not play.
 *
 * @return {ZBX_NotificationsAudio}
 */
ZBX_NotificationsAudio.prototype.timeout = function(seconds) {
	if (seconds == -1) {
		return this.once();
	}

	this.msTimeout = seconds * 1000;

	return this;
}

/**
 * Get current player seek position.
 *
 * @return {float}  Amount of seconds.
 */
ZBX_NotificationsAudio.prototype.getSeek = function() {
	return this.audio.currentTime;
}

/**
 * Get the time player will play for.
 *
 * @return {float}  Amount of seconds.
 */
ZBX_NotificationsAudio.prototype.getTimeout = function() {
	return this.msTimeout / 1000;
}

/**
 * This handler will be invoked once audio file has successfully pre-loaded.
 * We attempt to auto play and see if we have policy error.
 */
ZBX_NotificationsAudio.prototype.handleOnloadeddata = function() {
	var promise = this.audio.play();

	if (typeof promise === 'undefined') {
		return; // Internet explorer does not return promise.
	}

	promise.catch(function (error) {
		if (error.name == 'NotAllowedError' && this.audio.paused) {
			console.warn(error.message);
			console.warn('Zabbix was not able to play audio due to "Autoplay policy". Please see manual for more information.');
		}
	}.bind(this));
}
