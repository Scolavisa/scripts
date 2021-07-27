# scripts
Contains all kinds of cli / cron like scripts. 

## show_latest.php
This script shows the most recent filename and it's datatime. This helps to spot whether the backups did run successfully. 
Should be run in a mounted directory of the backup server.
You can mount the remote ftp by using `curlftpfs`: 
```shell
# uses .netrc for password
curlftpfs -o user={username}: "ftp-u.backupit.nl" mnt
```
.netrc: 
```shell
machine ftp-u.backupit.nl
login {username}
password {password}
```
copy the script to the mount dir and run
```shell
php show_latest.php
```

## call_wp_cron
Calls  wp-cron.php for every wordpress website under the configured root (like `/var/www/wpsites`), therefore triggering the wp-cron to run any scheduled tasks. 
Wp-cron usually runs on visits of the website, but some are never visited during a day or longer. That would e.g. prevent backups to run. 
copy the .env.example to .env and configure the parameters. 
Next call the script from a cron job e.g. every 10 minutes: 
```shell
*\10 * * * * php /var/www/deployments/scripts/call_wp_cron.php
```

