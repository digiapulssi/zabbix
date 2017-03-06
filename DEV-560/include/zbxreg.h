#ifndef ZABBIX_ZBXREG_H
#define ZABBIX_ZBXREG_H

#ifdef _WINDOWS
#	include "gnuregex.h"
#else
#	include "pcreposix.h"
#endif

int	zbx_regcomp(regex_t *compiled, const char *pattern, int cflags);
int	zbx_regexec(const regex_t *compiled, const char *string, size_t nmatch,
		regmatch_t matchptr[], int eflags);
size_t	zbx_regerror(int errcode, const regex_t *compiled, char *buffer, size_t length);
void	zbx_regfree(regex_t *compiled);

#endif /* ZABBIX_ZBXREG_H */
