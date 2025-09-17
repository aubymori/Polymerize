<?php
/**
 * This script generates new SSL keys/certs for the Polymerize server.
 * Run it from the command line:
 * 
 *     php sslgen.php
 * 
 * This script should only be run when a new SSL cert is *absolutely*
 * needed. The default cert will expire in 2035, so this will probably
 * not be needed by me (aubymori) ever, but it's here just in case.
 */

/* == C O N S T A N T S == */

/* Country code */
$C  = "US";
/* State/province */
$ST = "Illinois";
/* Company */
$O  = "Polymerize";
/* Domain name */
$CN = "www.youtube.com";
/* Self-explanatory */
$emailAddress = "aubyomori@gmail.com";
/* # of days the cert will last */
$DAYS = 3650;

/* == S C R I P T == */

$subj = "/C=$C/ST=$ST/O=$O/CN=$CN/emailAddress=$emailAddress";

echo "Creating certificate authority (root-ca.key, root-ca.crt)...\n";
echo shell_exec(
    "openssl req -x509 -nodes -newkey RSA:2048 -keyout root-ca.key -days $DAYS -out root-ca.crt -subj $subj"
);

echo "Creating private key and CSR (server.key, server.csr)...\n";
echo shell_exec(
    "openssl req -nodes -newkey rsa:2048 -keyout server.key -out server.csr -subj $subj"
);

file_put_contents(
    "extfile",

    "subjectAltName = DNS:$CN\n" .
    "authorityKeyIdentifier = keyid,issuer\n" .
    "basicConstraints = CA:FALSE\n" .
    "keyUsage = digitalSignature, keyEncipherment\n" .
    "extendedKeyUsage=serverAuth"
);

echo "Creating server certificate (server.crt)...\n";
echo shell_exec(
    "openssl x509 -req -CA root-ca.crt -CAkey root-ca.key -in server.csr -out server.crt -days $DAYS -CAcreateserial -extfile extfile"
);

unlink("extfile");