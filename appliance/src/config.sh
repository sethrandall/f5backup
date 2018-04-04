#!/bin/bash

############################ LICENSE #################################################
## Config Backup for F5 program to manage daily backups of F5 BigIP devices
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
## Foundation, Inc., 51 Franklin Street, Fifth Floor, Boston, MA  02110-1301, USA.
#####################################################################################

# Set VARs
HOSTNAME_FLAG=0
DNS_FLAG=0
IP_FLAG=0
NTP_FLAG=0
TIME_FLAG=0

### nmcli expects netmasks as /xx
### conversion function from https://forums.gentoo.org/viewtopic-p-6771484.html?sid=8a49a6acc7881b7106df1712606dcd0b#6771484
function mask2cdr () 
{ 
   # Assumes there's no "255." after a non-255 byte in the mask 
   local x=${1##*255.} 
   set -- 0^^^128^192^224^240^248^252^254^ $(( (${#1} - ${#x})*2 )) ${x%%.*} 
   x=${1%%$3*} 
   echo $(( $2 + (${#x}/4) )) 
}

# Check for first boot file
if [ -e "/opt/appliance/firstboot" ]; then
	source /opt/appliance/include/firstboot.sh
	
	# Set flags to configure all items
	DNS_FLAG=1
	HOSTNAME_FLAG=1
	IP_FLAG=1
	NTP_FLAG=1
	TIME_FLAG=1
	FIRST_BOOT=1
	
	# Clear first boot file
	rm -f /opt/appliance/firstboot
else

	# Individual settings
	echo -e "Select the following configuration task:
   1) IP/Default Gateway Settings
   2) DNS Settings
   3) Hostname
   4) NTP Settings
   5) Timezone"

	read -e -p "Enter the number for your selection: " SELECT
	case $SELECT in
		1 ) IP_FLAG=1 ;;
		2 ) DNS_FLAG=1 ;;
		3 ) HOSTNAME_FLAG=1 ;;
		4 ) NTP_FLAG=1 ;;
		5 ) TIME_FLAG=1 ;;
	esac

fi

# Find the name of the first interface on the system
INTUUID=$(nmcli -t con show | cut -d: -f2-2)

# Set IP address and gateway 
if [ $IP_FLAG == 1 ] ; then
	echo 
	read -e -p "Please enter the IP address for this device: " IPADDR
	read -e -p "Please enter the subnet mask (e.g. 255.255.255.0): " NETMASK
	read -e -p "Please enter the default gateway: " GATEWAY

	# Convert the netmask to /xx notation
	CDR=$(mask2cdr $NETMASK)

	# Set the interface
	sudo nmcli con mod uuid $INTUUID ipv4.addresses $IPADDR/$CDR gw4 $GATEWAY ipv4.method manual

	if [ $? -ne 0 ]; then
		echo -e "An error occurred configuring the network interface"
	fi
fi

# Set DNS servers and domain name
if [ $DNS_FLAG == 1 ] ; then
	echo 
	read -e -p "Please enter a space separated domain search list (probably just your domain name): " DOMAIN
	sudo nmcli con mod uuid $INTUUID ipv4.dns-search "$DOMAIN"
	read -e -p "Please enter a space separated list of DNS servers: " DNS
	sudo nmcli con mod uuid $INTUUID ipv4.dns "$DNS"
fi

# Set the hostname
if [ $HOSTNAME_FLAG == 1 ] ; then
	echo 
	read -e -p "Please enter the device hostname (i.e. f5backup.example.com): " HOSTNAME
	sudo hostnamectl set-hostname $HOSTNAME
fi

# Set NTP
if [ $NTP_FLAG == 1 ] ; then

	sudo bash -c "echo -e \"driftfile /var/lib/ntp/drift
restrict default kod nomodify notrap nopeer noquery
restrict -6 default kod nomodify notrap nopeer noquery
restrict 127.0.0.1
restrict -6 ::1\" > /etc/ntp.conf"

	echo 
	read -e -p "Please enter a space separated list of NTP servers (FQDN or IP addresses): " NTP
	for i in $NTP; do
		sudo bash -c "echo -e \"server $i\" >> /etc/ntp.conf"
	done

fi

# Set timezone
if [ $TIME_FLAG == 1 ] ; then
	source /opt/appliance/include/timezone.sh
	sudo cp /usr/share/zoneinfo/$TZ /etc/localtime
	
	# Write timezone to php.ini file
	sudo bash -c "echo -e \"; Defines the default timezone used by the date functions
; http://www.php.net/manual/en/datetime.configuration.php#ini.date.timezone
date.timezone = $TZ\" >  /etc/php.d/timezone.ini"
	
fi

### Restart services or machine 
# if not first boot, restart services
if [ "$FIRST_BOOT" != 1 ] ; then
	if [ $NTP_FLAG == 1 ] ; then
		echo "Restarting NTPD."
		sudo service ntpd restart
	fi

	# If any networking config then restart networking
	if [[ $IP_FLAG == 1 || $HOSTNAME_FLAG == 1 || $DNS_FLAG == 1 ]] ; then
		echo "Restarting Networking."
		sudo service network restart
	fi
	
	# If timezone has changed restart apache
	if [ $TIME_FLAG == 1 ] ; then
		echo "Restarting Apache."
		sudo service httpd restart
	fi
else
	echo "A reboot is required for first time setup."
	read -e -p "Would you like to reboot now ? [yes/no]: " REBOOT
	REBOOT=$(echo $REBOOT | tr '[:upper:]' '[:lower:]')
	if [ "$REBOOT" == "yes" ]; then	
		echo "Rebooting system."
		sudo shutdown -r now
	else
		echo "Please reboot before you use system."
	fi
fi