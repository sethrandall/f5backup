#!/bin/bash

APP_DIR=/opt/appliance

function error_quit() {
    echo -e "\
        An error occurred:
        $1
        Exiting.
    "
    exit 1
}

echo -e "\
###################################################
Preparing the system to run as an appliance
###################################################"
### Install VMware Tools ###
yum -y install perl
yum -y install open-vm-tools

### Install and enable NTP ###
yum list installed ntp > /dev/null 2>&1
if [ $? -ne 0 ]; then
    echo -e "Installing NTP Daemon"
    yum -y install ntp

    if [ $? -ne 0 ]; then
        error_quit "Installing NTP failed."
    fi
fi
systemctl enable ntpd.service

### Upgrade the password hash algorithm ###
echo -e "Setting secure password algorithm"
authconfig --passalgo=sha512 --update

### Create the 'console' user ###
getent passwd console > /dev/null 2>&1
if [ $? -ne 0 ]; then
    echo -e "Creating console user"
    useradd console
    if [ $? -ne 0 ]; then
        error_quit "Could not create 'console' user."
    fi
fi

usermod -G f5backup console
echo -n "Password1" | passwd console --stdin

echo -e "console\tALL=(ALL) NOPASSWD: ALL" >> /etc/sudoers

### Create and poplulate the appliance folder ###
echo -e "\nCreating $APP_DIR"
if [ -d $APP_DIR ]; then
    echo -e -n "$APP_DIR already exists. Overwrite it? [Y/n]: " 
    read app_clear

    if [ "$app_clear" == "n" -o "$app_clear" == "n" ]; then
        error_quit "$APP_DIR exists and won't be cleared"
    fi
    
    rm -Rf $APP_DIR/
fi

mkdir $APP_DIR/
cp -R src/* $APP_DIR/
chown -R console:console $APP_DIR/
mv $APP_DIR/config.sh $APP_DIR/config

### Add the appliance folder to the 'console' path
cp -f /etc/skel/.bashrc /home/console/.bashrc
echo "PATH=\$PATH:$APP_DIR" >> /home/console/.bashrc

### Set the /etc/issue file ###
echo -e "\nCreating /etc/issue"
cat << EOF > /etc/issue
WARNING: Unauthorized access to this system is forbidden
and will be prosecuted by law. By accessing this system,
you agree that your actions may be monitored if
unauthorized usage is suspected.
EOF

### Stop and disable services ###
echo -e "\nStopping and disabling services"
service httpd stop
service backupapi stop
service f5backup stop

chkconfig f5backup off
chkconfig backupapi off
chkconfig httpd off

### Switch to the config folder for copies ###
echo -e "\nUpdating configuration files"
cd configs

### Disable unneeded httpd modules ###
echo -e " Updating Apache modules"
MODDIR=/etc/httpd/conf.modules.d
mv $MODDIR/00-base.conf $MODDIR/00-base.conf.orig
cp 00-base.conf $MODDIR/
sed -i -e 's/LoadModule/#LoadModule/g' \
    $MODDIR/00-{dav,lua,proxy}.conf $MODDIR/01-cgi.conf

### Change SSH to port 5222 ###
echo -e " Updating SSH configuration"
mv /etc/ssh/sshd_config /etc/ssh/sshd_config.orig
cp sshd_config /etc/ssh/

### Allow SSH to use port 5222 ###
semodule -i sshd_custom_port.pp

### Adjust the firewall ###
echo -e " Updating the firewall"
firewall-cmd --permanent --zone=public --add-port=5222/tcp
firewall-cmd --permanent --zone=public --add-service=http
firewall-cmd --permanent --zone=public --add-service=https
firewall-cmd --permanent --zone=public --remove-service=ssh

### Update sysctl.conf to lock down the OS ###
echo -e " Updating sysctl settings"
cp sysctl.conf /etc/sysctl.d/10-f5backup.conf
chcon system_u:object_r:system_conf_t:s0 /etc/sysctl.d/10-f5backup.conf

echo -e "\nCleaning up packages"
yum -y install yum-utils
package-cleanup -y --oldkernels --count=1
yum -y remove yum-utils
yum -y autoremove
yum clean all

echo -e "\nGoing back to default settings"
### Remove all crypto keys ###
echo -e " Clearing security keys"
rm -rfv /etc/ssh/*key*
rm -vf /opt/f5backup/.keystore/*

### Clear the F5Backup Databases ###
echo -e " Resetting the F5Backup databases"
echo > /opt/f5backup/db/main.db
cat maindb.txt | sqlite3 /opt/f5backup/db/main.db
echo > /opt/f5backup/db/ui.db
cat uidb.txt | sqlite3 /opt/f5backup/db/ui.db

### Clear the F5Backup Devics and Logs ###
echo -e " Clearing the F5Backup backups and logs"
rm -vrf /opt/f5backup/devices/*
rm -vrf /opt/f5backup/log/*

### Clear the user histories ***
echo -e " Clearing histories"
unset HISTFILE
echo > /home/console/.bash_history
echo > /root/.bash_history

### Clear the system logs ###
echo -e " Clearing system logs"
/usr/sbin/logrotate /etc/logrotate.conf --force
rm -f /var/log/*-???????? /var/log/*.gz
rm -f /var/log/dmesg.old
rm -rf /var/log/anaconda
 
# Truncate the audit logs (and other logs we want to keep placeholders for)
cat /dev/null > /var/log/audit/audit.log
cat /dev/null > /var/log/wtmp
cat /dev/null > /var/log/lastlog
cat /dev/null > /var/log/grubby

# Remove the traces of the template MAC address and UUIDs
sed -i '/^\(HWADDR\|UUID\)=/d' /etc/sysconfig/network-scripts/ifcfg-e*
 
# enable network interface onboot
sed -i -e 's@^ONBOOT="no@ONBOOT="yes@' /etc/sysconfig/network-scripts/ifcfg-e*
 
# Clean /tmp out
rm -rf /tmp/*
rm -rf /var/tmp/*

/bin/rm -f /etc/udev/rules.d/70*

### Create the firstboot file ###
echo -e "\nSetting up firstboot settings"
touch $APP_DIR/firstboot

echo -e "\nLocking out root account"
### Lock down the root home folder ###
chmod -R o-rwx /root/
chmod -R g-rwx /root/

### Reset root password and lock ###
openssl rand -base64 129 | tr -d '\n' | sudo passwd root --stdin
sudo passwd root -l > /dev/null

echo -e "\n\
When you are ready, run the following:
history -c
sys-unconfig
"