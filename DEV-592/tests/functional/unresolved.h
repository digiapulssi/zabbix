#ifndef UNRESOLVED_H
#define UNRESOLVED_H

#define ZBXTEST_OK	"[  OK  ]"
#define ZBXTEST_FAIL	"[ FAIL ]"

#define ZBXTEST_RV(DESCR, RET, ERROR)		ZBXTEST_IMPL((DESCR "()"), (SUCCEED == (RET)), (ERROR))

#define ZBXTEST_EXPR(DESCR, EXPR)		ZBXTEST_IMPL((DESCR), (EXPR), "expression (" ZBX_STR(EXPR) ") failed")

#define ZBXTEST_IMPL(DESCR, EXPR, ERROR)								\
do													\
{													\
	if (!(EXPR))											\
	{												\
		printf("%s %s: %s (%s:%d)\n", ZBXTEST_FAIL, (DESCR), (ERROR), __FILE__, __LINE__);	\
	}												\
	else												\
		printf("%s %s\n", ZBXTEST_OK, (DESCR));							\
}													\
while (0)

#define ZBXTEST_STRCMP(DESCR, EXP_STR, GOT_STR)								\
do													\
{													\
	if ((NULL == (EXP_STR) && NULL == (GOT_STR)) ||							\
			(NULL != (EXP_STR) && NULL != (GOT_STR) && 0 == strcmp((EXP_STR), (GOT_STR))))	\
	{												\
		printf("%s %s matches\n", ZBXTEST_OK, (DESCR));						\
	}												\
	else												\
	{												\
		printf("%s %s differs (expected:'%s' got:'%s')\n", ZBXTEST_FAIL, (DESCR),		\
				ZBX_NULL2STR(EXP_STR), ZBX_NULL2STR(GOT_STR));				\
	}												\
}													\
while (0)

int	parse_opts(int argc, char *const *argv);

#endif	/* UNRESOLVED_H */
