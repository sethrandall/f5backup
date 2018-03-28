#!/bin/bash
BASE_DIR="/opt/f5backup"
APACHE_USER="apache"
APACHE_CONF="/etc/httpd/conf/httpd.conf"
PHP_CONF="/etc/php.ini"

# F5 backup installation script
echo -e "Starting installation of the Backup Program.\n"

# ------------- Check for root install -------------
if [ $(whoami) != "root" ]; then
   echo "Not root user. Must be root user or sudo."
   exit
fi

# ------------- Check for python -------------
PY=$(python -c "print 'python'")
if [ "$PY" != "python" ]; then
   echo -e "\nPython not installed. Please install and try again."
   exit
fi

# ------------- Check python version -------------
echo -e "import sys \nprint 'Your version of python is %s' % sys.version.split(' ')[0]" | python

PVER=$(echo -e "
import sys
ver = sys.version_info[0] + (sys.version_info[1] / 10)
if (ver < 2.6 or ver > 2.8):
  print 'yes'
else:
  print 'not'" | python)

if [ "$PVER" != "yes" ]; then
   echo 'Error: Your version of python is not compatible with this script!'
   echo 'Must use python version 2.6 to 2.7'
   exit
fi

# ------------ Check for python modules -------------
echo "Checking for python for required modules -"

# Put python modules in this list
MODULES="
tornado
daemon
sqlite3
flask
bigsuds
M2Crypto
ldap
"

# Loop through module list and break if module not present
ERROR=""
NOT_INSTALLED=0

for i in $MODULES ; do
   RESULT=$(echo -e "try: \n  import $i\nexcept: \n  print 'not'" | python)
   RESULT+="-in"     # prevents  unary operator expected error
   if [ $RESULT == "not-in" ]; then
      ERROR+="$i is not installed. Please install before continuing.\n"
      (( NOT_INSTALLED++ ))
   else
      echo "  $i is installed."
   fi
done

# Exit if module is not installed
if [ $NOT_INSTALLED != 0 ] ; then 
   echo -e "\n\n$ERROR"
   exit
fi
echo "Python modules are good!"

# ------------ SQLite3 installation check -----------------------------------
echo -e "\nChecking for SQLite"
SQL=$(sqlite3 -version 2> /dev/null | cut -d' ' -f-1)
if [ -z "$SQL" ]; then
   echo -e "\nSQLite not installed. Please install and try again.\n"
   exit
fi
echo -e " Version info - $SQL"

# ------------ Apache installation check -----------------------------------
echo -e "\nChecking for Apache."
APACHE=$(apachectl -v 2> /dev/null | sed q | cut -d ' ' -f3)
if [ -z "$APACHE" ]; then 
   echo -e 'Apache not installed! Please install.'
   exit
fi
echo -e " Version info - $APACHE"

#------------- Check for mod_ssl ------------- 
echo -e "\nChecking for mod_ssl in apache."
SSL=$(apachectl -M 2> /dev/null | grep ssl)
if [ -z "$SSL" ]; then 
   echo -e " mod_ssl not installed! Please install."
   exit
fi
echo -e " mod_ssl is installed."

#------------- Check for php ------------- 
echo -e "\nChecking for PHP."
PHP=$(php -v 2> /dev/null | sed q | cut -d ' ' -f 1-2)
if [ -z "$PHP" ]; then
   echo -e ' PHP is not installed! Please install.'
   exit
fi
echo -e " Version info - $PHP"
PHPVER=$(echo $PHP | cut -d ' ' -f 2 | cut -d . -f 1-2)

#------------- Check for mod_php ------------- 
echo -e "\nChecking for mod_php."
MOD_PHP=$(apachectl -M 2> /dev/null | grep php)
if [ -z "$MOD_PHP" ]; then 
   echo -e ' mod_php not installed! Please install.'
   exit
fi
echo -e " mod_php is installed."

#------------- Check for PDO ------------- 
echo -e "\nChecking for PHP PDO SQLite driver."
PDO=$(php -m 2> /dev/null | grep pdo_sqlite)
if [ -z "$PDO" ]; then
   echo -e " PDO_Sqlite is not installed! Please install."
   exit
fi
echo -e " PDO_Sqlite is installed."

# ------------- Create directories -------------
echo -e "\nCreating directories to $BASE_DIR."
mkdir $BASE_DIR
mkdir $BASE_DIR/devices
mkdir $BASE_DIR/db
mkdir $BASE_DIR/.keystore
mkdir $BASE_DIR/log
mkdir $BASE_DIR/pid

#------------- Copy files -------------
echo -e "\nCopying files to $BASE_DIR."
cp -R src/* $BASE_DIR

#------------- Create f5ackup user -------------
echo -e "\nCreating user \"f5backup\"."
useradd f5backup
openssl rand -base64 129 | tr -d '\n' | passwd f5backup --stdin
passwd f5backup -l > /dev/null

#------------- Create backup key -------------
touch $BASE_DIR/.keystore/backup.key
chmod 0600 $BASE_DIR/.keystore/backup.key
openssl rand -base64 129 | tr -d '\n' > $BASE_DIR/.keystore/backup.key

# ------------- Create new SSL certificate -------------
echo -e "\nCreating new SSL certificate."
echo > $BASE_DIR/.keystore/f5backup.key
echo > $BASE_DIR/.keystore/f5backup.crt
chown f5backup:f5backup $BASE_DIR/.keystore/f5backup.*
chmod 0600 $BASE_DIR/.keystore/f5backup.*
chcon -R -t cert_t $BASE_DIR/.keystore/f5backup.*
openssl req -x509 -nodes -config openssl.cnf -days 3650 \
 -newkey rsa:2048 -keyout $BASE_DIR/.keystore/f5backup.key \
 -out $BASE_DIR/.keystore/f5backup.crt

#------------- apache config -------------
cp $APACHE_CONF $APACHE_CONF.orig
cp httpd.conf $APACHE_CONF

cp $PHP_CONF $PHP_CONF.orig
cp php.ini $PHP_CONF

#------------- Create DB -------------
echo -e "\nCreating DB files."

echo > $BASE_DIR/db/main.db
echo > $BASE_DIR/db/ui.db
cat maindb.txt | sqlite3 $BASE_DIR/db/main.db
cat uidb.txt | sqlite3 $BASE_DIR/db/ui.db

#------------- Change file ownership -------------
echo -e "\nSetting directory ownership."
chown -R f5backup:f5backup $BASE_DIR/
chown -R f5backup:$APACHE_USER $BASE_DIR/devices
chown -R f5backup:$APACHE_USER $BASE_DIR/db
chown -R f5backup:f5backup $BASE_DIR/lib
chown -R f5backup:f5backup $BASE_DIR/.keystore
chown f5backup:$APACHE_USER $BASE_DIR/log
chown f5backup:f5backup $BASE_DIR/pid
chown -R f5backup:$APACHE_USER $BASE_DIR/redirect
chown -R f5backup:$APACHE_USER $BASE_DIR/ui


#------------- Change file permissions -------------
echo -e "\nSetting file permissions."
chmod 0660 $BASE_DIR/*
chmod 0740 $BASE_DIR/*.py
chmod 0660 $BASE_DIR/db/*
chmod 0660 $BASE_DIR/lib/*
chmod 0400 $BASE_DIR/.keystore/*
chmod 0770 $BASE_DIR/redirect/*
chmod -R 0660 $BASE_DIR/ui/*
chmod 0770 $BASE_DIR/ui/*.php
chmod -R 0770 $BASE_DIR/ui/include/
chmod 0700 $BASE_DIR/passwd.sh

#------------- Change folder permissions -------------
echo -e "\nSetting directory permissions."
chmod 0775 $BASE_DIR
chmod 0770 $BASE_DIR/devices
chmod 0770 $BASE_DIR/db
chmod 0770 $BASE_DIR/lib
chmod 0700 $BASE_DIR/.keystore
chmod 0770 $BASE_DIR/log
chmod 0770 $BASE_DIR/pid
chmod 0770 $BASE_DIR/redirect
chmod 0770 $BASE_DIR/ui
chmod 0770 $BASE_DIR/ui/css
chmod 0770 $BASE_DIR/ui/images

#------------- SELinux tag for apache -------------
echo -e "\nSetting SELinux permissions."
chcon -R --type=httpd_sys_content_t $BASE_DIR/db
chcon -R --type=httpd_sys_content_t $BASE_DIR/devices
chcon -R --type=httpd_sys_content_t $BASE_DIR/ui 
chcon -R --type=httpd_sys_content_t $BASE_DIR/redirect
chcon -R --type=httpd_sys_content_t $BASE_DIR/log
setsebool -P httpd_can_network_connect 1

#------------- Inint scripts -------------------
echo -e "\nCopying init scripts."
cp backupapi /etc/init.d/backupapi
cp f5backup /etc/init.d/f5backup

chmod u+x /etc/init.d/backupapi
chmod u+x /etc/init.d/f5backup

chkconfig --add f5backup
chkconfig --add backupapi

chkconfig f5backup on
chkconfig backupapi on
chkconfig httpd on

#********************** ALL DONE ***********************
echo -e "\nInstallation completed."