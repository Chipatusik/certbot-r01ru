#!/bin/bash

cat <(crontab -l) <(echo "0 12 1 */2 * /home/user/certs_update/cert.sh 2>&1 >> /home/user/certs_update/cert.log") | crontab -