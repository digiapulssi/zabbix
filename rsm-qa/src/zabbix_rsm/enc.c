#include <string.h>
#include <sys/types.h>
#include <sys/stat.h>
#include <fcntl.h>
#include <unistd.h>

#include "common.h"
#include "rsm.h"

unsigned char	program_type = ZBX_PROGRAM_TYPE_GET;

const char      *progname = NULL;
const char	title_message[] = "Zabbix encrypter";
const char	syslog_app_name[] = "rsm_epp_enc";
const char	*help_message[] = {NULL};
const char	*usage_message[] = {
	"<secretkey_enc_b64> <secretkey_salt_b64> [optional parameters]", NULL,
	"Encrypt sensitive data using secret key.", NULL,
	"Parameters:", NULL,
	"    <secretkey_enc_b64>         secret key, encrypted with passphrase, base64-encoded", NULL,
	"    <secretkey_salt_b64>        secret key salt, base64-encoded", NULL,
	"Optional parameters:", NULL,
	"    -n                          do not print prompts when requesting passphrase and sensitive data", NULL,
	"    -f <file>                   do not request sensitive data from STDIN, instead read it from file", NULL,
	NULL	/* end of text */
};

int	main(int argc, char *argv[])
{
	const char	*secretkey_enc_b64, *secretkey_salt_b64, *prompt = "";
	char		*sensdata_enc_b64 = NULL, *sensdata_salt_b64 = NULL, passphrase[128],
			sensdata[RSM_EPP_SENSDATA_MAX], *file = NULL, err[128];
	int		i, noprompt = 0;

	progname = get_program_name(argv[0]);
	secretkey_enc_b64 = argv[1];
	secretkey_salt_b64 = argv[2];

	if (3 > argc)
	{
		usage();
		goto out;
	}

	for (i = 3; i < argc; i++)
	{
		if (0 == strcmp("-n", argv[i]))
		{
			noprompt = 1;
		}
		else if (0 == strcmp("-f", argv[i]))
		{
			if (i == argc - 1)
			{
				usage();
				goto out;
			}

			file = argv[++i];
		}
		else
		{
			printf("%s: invalid argument\n", argv[i]);
			usage();
			goto out;
		}
	}

	if (0 == noprompt)
		prompt = "Enter EPP passphrase: ";
	if (SUCCEED != zbx_read_stdin(prompt, passphrase, sizeof(passphrase), err, sizeof(err)))
	{
		fprintf(stderr, "cannot get EPP passphrase: %s\n", err);
		goto out;
	}

	if (NULL != file)
	{
		char	buf[128], *contents = NULL;
		size_t	contents_alloc = 512, contents_offset = 0;
		int	f, nbytes;

		if (-1 == (f = zbx_open(file, O_RDONLY)))
		{
			fprintf(stderr, "cannot read file \"%s\": %s\n", file, strerror(errno));
			goto out;
		}

		contents = zbx_malloc(contents, contents_alloc);
		*contents = '\0';
		while (0 < (nbytes = zbx_read(f, buf, sizeof(buf), "")))
			zbx_strncpy_alloc(&contents, &contents_alloc, &contents_offset, buf, nbytes);

		zbx_strlcpy(sensdata, contents, sizeof(sensdata));
		zbx_free(contents);

		if (-1 == nbytes)
		{
			fprintf(stderr, "an error occured while reading file \"%s\": %s", file, strerror(errno));
			goto out;
		}
	}
	else
	{
		if (0 == noprompt)
			prompt = "Enter EPP sensitive data to encrypt: ";
		if (SUCCEED != zbx_read_stdin(prompt, sensdata, sizeof(sensdata), err, sizeof(err)))
		{
			fprintf(stderr, "cannot get EPP sensitive data: %s\n", err);
			goto out;
		}
	}

	/* initialize the library */
	if (SUCCEED != rsm_ssl_init())
	{
		fprintf(stderr, "cannot initialize SSL library\n");
		goto out;
	}

	if (SUCCEED != encrypt_cleartext(passphrase, strlen(passphrase), secretkey_enc_b64, strlen(secretkey_enc_b64),
			secretkey_salt_b64, strlen(secretkey_salt_b64), sensdata, strlen(sensdata), &sensdata_enc_b64,
			&sensdata_salt_b64, err, sizeof(err)))
	{
		fprintf(stderr, "cannot encrypt sensitive data: %s\n", err);
		goto out;
	}

	printf("%s|%s\n", sensdata_enc_b64, sensdata_salt_b64);
out:
	zbx_free(sensdata_enc_b64);
	zbx_free(sensdata_salt_b64);

	return 0;
}
