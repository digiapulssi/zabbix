#ifndef INCLUDE_ZBXREG_H_
#define INCLUDE_ZBXREG_H_

#if defined(_WINDOWS)
#	include "gnuregex.h"
#else
#	include "pcreposix.h"
#endif

int	zbx_regcomp(regex_t *restrict compiled, const char *restrict pattern, int cflags);
int	zbx_regexec(const regex_t *restrict compiled, const char *restrict string, size_t nmatch,
		regmatch_t matchptr[restrict], int eflags);
size_t	zbx_regerror(int errcode, const regex_t *restrict compiled, char *restrict buffer, size_t length);
void	zbx_regfree(regex_t *compiled);

#endif /* INCLUDE_ZBXREG_H_ */
