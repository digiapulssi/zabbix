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

import org.slf4j.Logger;
import org.slf4j.LoggerFactory;

class HelperFunctionChest
{
	private static final Logger logger = LoggerFactory.getLogger(HelperFunctionChest.class);

	static <T> boolean arrayContains(T[] array, T key)
	{
		for (T element : array)
		{
			if (key.equals(element))
				return true;
		}

		return false;
	}

	static int separatorIndex(String input)
	{
		for (int i = 0; i < input.length(); i++)
		{
			if ('\\' == input.charAt(i))
			{
				if (i + 1 < input.length() && ('\\' == input.charAt(i + 1) || '.' == input.charAt(i + 1)))
					i++;
			}
			else if ('.' == input.charAt(i))
			{
				return i;
			}
		}

		return -1;
	}

	static String unescapeUserInput(String input)
	{
		StringBuilder builder = new StringBuilder(input.length());

		for (int i = 0; i < input.length(); i++)
		{
			if ('\\' == input.charAt(i) && i + 1 < input.length() &&
					('\\' == input.charAt(i + 1) || '.' == input.charAt(i + 1)))
			{
				i++;
			}

			builder.append(input.charAt(i));
		}

		return builder.toString();
	}

	static String unescapeString(String oldstr)
	{
		StringBuffer newstr = new StringBuffer(oldstr.length());
		boolean backslash = false;

		for (int i = 0; i < oldstr.length(); i++)
		{
			int cp = oldstr.codePointAt(i);

			if (oldstr.codePointAt(i) > Character.MAX_VALUE)
			{
				i++;
			}

			if (!backslash)
			{
				if (cp == '\\')
				{
					backslash = true;
				}
				else
				{
					newstr.append(Character.toChars(cp));
				}

				continue;
			}

			if (cp == '\\')
			{
				backslash = false;
				newstr.append('\\');
				newstr.append('\\');
				continue;
			}

			switch (cp)
			{
				case 'r':
					newstr.append('\r');
					break;

				case 'n':
					newstr.append('\n');
					break;

				case 'f':
					newstr.append('\f');
					break;

				case 'b':
					newstr.append("\\b");
					break;

				case 't':
					newstr.append('\t');
					break;

				case 'a':
					newstr.append('\007');
					break;

				case 'e':
					newstr.append('\033');
					break;

				case 'c':
				{
					if (++i == oldstr.length())
					{
						throw new IllegalArgumentException("Trailing \\c");
					}

					cp = oldstr.codePointAt(i);

					if (cp > 0x7f)
					{
						throw new IllegalArgumentException("Expected ASCII after \\c");
					}

					newstr.append(Character.toChars(cp ^ 64));
					break;
				}

				case '8':
				case '9':
					throw new IllegalArgumentException("Illegal octal digit");

				case '1':
				case '2':
				case '3':
				case '4':
				case '5':
				case '6':
				case '7':
					--i;

				case '0':
				{
					if (i + 1 == oldstr.length())
					{
						newstr.append(Character.toChars(0));
						break;
					}

					i++;
					int digits = 0;
					int j;

					for (j = 0; j <= 2; j++)
					{
						if (i + j == oldstr.length())
						{
							break;
						}

						int ch = oldstr.charAt(i + j);

						if (ch < '0' || ch > '7')
						{
							break;
						}

						digits++;
					}

					if (digits == 0)
					{
						--i;
						newstr.append('\0');
						break;
					}

					int value = 0;

					try
					{
						value = Integer.parseInt(oldstr.substring(i, i + digits), 8);
					}
					catch (NumberFormatException e)
					{
						throw new IllegalArgumentException("Invalid octal value for \\0 escape");
					}

					newstr.append(Character.toChars(value));
					i += digits - 1;
					break;
				}

				case 'x':
				{
					if (i + 2 > oldstr.length())
					{
						throw new IllegalArgumentException("String too short for \\x escape");
					}

					i++;
					boolean brace = false;

					if (oldstr.charAt(i) == '{')
					{
						i++;
						brace = true;
					}

					int j;

					for (j = 0; j < 8; j++)
					{
						if (!brace && j == 2)
						{
							break;
						}

						int ch = oldstr.charAt(i+j);

						if (ch > 127)
						{
							throw new IllegalArgumentException("Illegal non-ASCII hex digit in \\x escape");
						}

						if (brace && ch == '}')
						{
							break;
						}

						if (! ( (ch >= '0' && ch <= '9') || (ch >= 'a' && ch <= 'f') || (ch >= 'A' && ch <= 'F')))
						{
							throw new IllegalArgumentException(String.format("Illegal hex digit #%d '%c' in \\x", ch, ch));
						}
					}

					if (j == 0)
					{
						throw new IllegalArgumentException("Empty braces in \\x{} escape");
					}

					int value = 0;

					try
					{
						value = Integer.parseInt(oldstr.substring(i, i + j), 16);
					}
					catch (NumberFormatException e)
					{
						throw new IllegalArgumentException("Invalid hex value for \\x escape");
					}

					newstr.append(Character.toChars(value));

					if (brace)
					{
						j++;
					}

					i += j - 1;
					break;
				}

				case 'u':
				{
					if (i + 4 > oldstr.length())
					{
						throw new IllegalArgumentException("String too short for \\u escape");
					}

					i++;
					int j;

					for (j = 0; j < 4; j++)
					{
						if (oldstr.charAt(i + j) > 127)
						{
							throw new IllegalArgumentException("Illegal non-ASCII hex digit in \\u escape");
						}
					}

					int value = 0;

					try
					{
						value = Integer.parseInt( oldstr.substring(i, i + j), 16);
					}
					catch (NumberFormatException e)
					{
						throw new IllegalArgumentException("Invalid hex value for \\u escape");
					}

					newstr.append(Character.toChars(value));
					i += j - 1;
					break;
				}

				case 'U':
				{
					if (i + 8 > oldstr.length())
					{
						throw new IllegalArgumentException("String too short for \\U escape");
					}

					i++;
					int j;

					for (j = 0; j < 8; j++)
					{
						if (oldstr.charAt(i + j) > 127)
						{
							throw new IllegalArgumentException("Illegal non-ASCII hex digit in \\U escape");
						}
					}

					int value = 0;

					try
					{
						value = Integer.parseInt(oldstr.substring(i, i + j), 16);
					}
					catch (NumberFormatException e)
					{
						throw new IllegalArgumentException("Invalid hex value for \\U escape");
					}

					newstr.append(Character.toChars(value));
					i += j - 1;
					break;
				}

				default:
				{
					newstr.append('\\');
					newstr.append(Character.toChars(cp));
					logger.warn(String.format("Unrecognised escape %c passed through", cp));
					break;
				}
			}

			backslash = false;
		}

		if (backslash)
		{
			newstr.append('\\');
		}

		return newstr.toString();
	}

	static boolean isStringQuoted(String input, boolean single)
	{
		String q = (single) ? "'" : "\"";

		return input.startsWith(q) && input.endsWith(q);
	}
}
