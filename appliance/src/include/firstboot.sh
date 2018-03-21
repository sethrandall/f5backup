
echo " Accept license -
############################ LICENSE ##############
## Config Backup Appliance for F5. A virtual applaince to 
## manage daily backups of F5 BigIP devices.
## Copyright (C) 2014 Eric Flores
##
## This program is free software; you can redistribute it and/or
## modify it under the terms of the GNU General Public License
## as published by the Free Software Foundation; either version 2
## of the License, or any later version.
##
## This program is distributed in the hope that it will be useful,
## but WITHOUT ANY WARRANTY; without even the implied warranty of
## MERCHANTABILITY or FITNESS FOR A PARTICULAR PURPOSE.  See the
## GNU General Public License for more details.
##
## You should have received a copy of the GNU General Public License
## along with this program; if not, write to the Free Software
## Foundation, Inc., 51 Franklin Street, Fifth Floor, 
## Boston, MA  02110-1301, USA.
###################################################

By using this appliance and Config Backup program you are accepting this agreement.
Type Yes or No:"

# ------------- Ask for agreement -------------
while : ; do
	read -e -r ACCEPT
	ACCEPT=$(echo $ACCEPT | tr '[:upper:]' '[:lower:]')
	if [ "$ACCEPT" == "yes" ]; then
		break
	elif [ "$ACCEPT" == "no" ]; then
		echo "Please accept the license before using the device."
		exit
	fi
	echo "Please type yes or no."
done

# ------------- Reset root password and lock -------------
echo -e "\nCreating random root password. Use \"sudo\" command for all root functions if needed."
openssl rand -base64 129 | tr -d '\n' | sudo passwd root --stdin
sudo passwd root -l > /dev/null

# ------------- Set new console password -------------
echo -e "\nPlease change the password for the user \"console\"."
PASSWORD=$(python /opt/appliance/include/password.py)
echo -en $PASSWORD | sudo passwd console --stdin

# ------------- Create new crypto keys -------------
echo -e "\nCreating new SSL certificate."
sudo bash -c "echo > /opt/f5backup/.keystore/f5backup.key"
sudo bash -c "echo > /opt/f5backup/.keystore/f5backup.crt"
sudo bash -c "echo > /opt/f5backup/.keystore/backup.key"
sudo bash -c "chcon -t cert_t /opt/f5backup/.keystore/f5backup.*"
sudo bash -c "chmod 0600 /opt/f5backup/.keystore/*"
sudo bash -c "chown f5backup:f5backup /opt/f5backup/.keystore/*"

sudo openssl req -x509 -nodes -config /opt/appliance/include/openssl.cnf \
 -days 3650 -newkey rsa:2048 -keyout /opt/f5backup/.keystore/f5backup.key \
 -out /opt/f5backup/.keystore/f5backup.crt

sudo bash -c "openssl rand -base64 129 | tr -d '\n' > /opt/f5backup/.keystore/backup.key"

sudo chkconfig f5backup on
sudo chkconfig backupapi on
sudo chkconfig httpd on
