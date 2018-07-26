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

package com.zabbix.gateway;

import java.net.Socket;
import java.net.InetAddress;

import org.json.*;

import org.slf4j.Logger;
import org.slf4j.LoggerFactory;

class SocketProcessor implements Runnable
{
	private static final Logger logger = LoggerFactory.getLogger(SocketProcessor.class);

	private Socket socket;

	SocketProcessor(Socket socket)
	{
		this.socket = socket;
	}

	@Override
	public void run()
	{
		BinaryProtocolSpeaker speaker = null;
		InetAddress ipaddr = socket.getInetAddress();

		logger.debug("starting to process incoming connection on host \"{}\"", ipaddr);

		try
		{
			speaker = new BinaryProtocolSpeaker(socket);

			JSONObject request = new JSONObject(speaker.getRequest());

			ItemChecker checker;

			if (request.getString(ItemChecker.JSON_TAG_REQUEST).equals(ItemChecker.JSON_REQUEST_INTERNAL))
				checker = new InternalItemChecker(request);
			else if (request.getString(ItemChecker.JSON_TAG_REQUEST).equals(ItemChecker.JSON_REQUEST_JMX))
				checker = new JMXItemChecker(request);
			else
				throw new ZabbixException("bad request tag value: '%s'", request.getString(ItemChecker.JSON_TAG_REQUEST));

			logger.debug("socket processor dispatched request to class {} on host \"{}\"", new Object[] {checker.getClass().getName(), ipaddr});
			JSONArray values = checker.getValues();

			JSONObject response = new JSONObject();
			response.put(ItemChecker.JSON_TAG_RESPONSE, ItemChecker.JSON_RESPONSE_SUCCESS);
			response.put(ItemChecker.JSON_TAG_DATA, values);

			speaker.sendResponse(response.toString());
		}
		catch (Exception e1)
		{
			String error = ZabbixException.getRootCauseMessage(e1);
			logger.warn("socket processor on host \"{}\" failed: {}", new Object[] {ipaddr, error});
			logger.debug("error caused by", e1);

			try
			{
				JSONObject response = new JSONObject();
				response.put(ItemChecker.JSON_TAG_RESPONSE, ItemChecker.JSON_RESPONSE_FAILED);
				response.put(ItemChecker.JSON_TAG_ERROR, error);

				speaker.sendResponse(response.toString());
			}
			catch (Exception e2)
			{
				logger.warn("socket processor encountered an error while sending failure notification: {}", ZabbixException.getRootCauseMessage(e1));
				logger.debug("error caused by", e2);
			}
		}
		finally
		{
			try { if (null != speaker) speaker.close(); } catch (Exception e) { }
			try { if (null != socket) socket.close(); } catch (Exception e) { }
		}

		logger.debug("socket processor finished processing incoming connection on host \"{}\"", ipaddr);
	}
}
