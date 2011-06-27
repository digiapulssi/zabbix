/*!
 * This file is part of Zabbix software.
 *
 * Copyright 2000-2011, Zabbix SIA
 * Licensed under the GPL Version 2 license.
 * http://www.zabbix.com/licence.php
 */

// JSON RPC by Artem "Aly" Suharev (based on Prototype)

var RPC = {
'_rpcurl':		'jsrpc.php?output=json-rpc',		// rpc url
'_callid':		0,					// rpc request id
'_auth':		null,				// authentication hash

auth: function(auth_){
	if(is_null(this._auth)) this._auth = cookie.read('zbx_sessionid');

	if('undefined' == typeof(url_)) return this._auth;
	else this._auth = auth_;
},

callid: function(){
	this._callid++;
	return this._callid;
},

rpcurl: function(rpcurl_){
	if('undefined' == typeof(rpcurl_)) return this._rpcurl;
	else this._rpcurl = rpcurl_;
}
};

RPC.Base = Class.create({
// PRIVATE
'userParams':		{},		// user OPtions
'auth':				null,	// authentication hash
'callid':			0,		// rpc request id

'debug_status': 0,		// debug status: 0 - off, 1 - on, 2 - SDI;
'debug_info': 	'',		// debug string
'debug_prev':	'',		// don't log repeated fnc

initialize: function(userParams){
	this.userParams = {
		'method': null,
		'params': {},
		'notification': 0,
		'request':	{},
		'onSucces': function(){},
		'onFailure': function(){}
	};

	Object.extend(this.userParams, userParams || { });

	this.callid = RPC.callid();
	this.auth = RPC.auth();
},

debug: function(fnc_name, id){
	if(this.debug_status){
		var str = 'RPC.Call['+RPC.id+'].'+fnc_name;
		if(typeof(id) != 'undefined') str+= ' :'+id;

//		if(this.debug_prev == str) return true;

		this.debug_info += str + '\n';
		if(this.debug_status == 2){
			SDI(str);
		}

		this.debug_prev = str;
	}
}
});

RPC.Call = Class.create(RPC.Base, {
initialize: function($super, userParams) {
	$super(userParams);
	this.call();
},

call: function(){
	this.debug('call');
//---
	var header = {
		'Content-type': 'application/json-rpc'
	};

	var body = {
		'jsonrpc': '2.0',
		'method': this.userParams.method,
		'params': this.userParams.params,
		'auth': this.auth
	};

	var request = {
		'requestHeaders': header
	};

	if(this.userParams.notification == 0){
		body.id = this.callid;

		request.onSuccess = this.processRespond.bind(this);
		request.onFailure = this.processError.bind(this);
	}

	Object.extend(request, this.userParams.request);
	request.postBody = Object.toJSON(body),

	new Ajax.Request(RPC.rpcurl(), request);

//SDI(this.callid);
},

processRespond: function(resp){
	this.debug('processRespond');
//--
//SDJ(resp);
	var isError = this.processError(resp);
	if(isError) return false;
//SDJ(resp.responseJSON.result);
	if(isset('onSuccess', this.userParams))
		this.userParams.onSuccess(resp.responseJSON.result);

return true;
},

processError: function(resp){
	this.debug('error');
//---

// JSON request failed OR server responded with incorrect JSON
	if(is_null(resp) || !isset('responseJSON', resp)){
		throw('RPC call ['+this.userParams.method+'] request failed');
	}

// JSON have wrong header or no respond at all
	if(empty(resp.responseJSON)){
		throw('RPC: Server call ['+this.userParams.method+'] responded with incorrect JSON.');
	}

// RPC responded with error || with incorrect JSON
	if(isset('error', resp.responseJSON) && isset('onFailure', this.userParams)){
		this.userParams.onFailure(resp.responseJSON.error);
		return true;
	}
	else if(!isset('result', resp.responseJSON)){
		throw('RPC: Server call ['+this.userParams.method+'] responded with incorrect JSON.');
	}

	return false;
}
}
);
