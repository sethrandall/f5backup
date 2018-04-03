#!/bin/bash

## TODO: check if running as root

LOGFILE=prereqs_install-$(date +"%F_%H%M%S").log

declare -a YUM_PACKAGES=("sqlite"
                         "httpd"
                         "php"
                         "php-pdo"
                         "mod_ssl"
                         "m2crypto"
                         "python2-pip"
                         "python2-simplejson"
                         "python-daemon"
                         "python-tornado"
                         "python-flask"
                         "python-ldap")
declare -a PIP_PACKAGES=("suds"
                         "bigsuds==1.0.4"
                         "m2secret")
echo -e -n "\
########################################################
Ready to install prerequisites for the F5Backup program.

The following packages will be installed:
- EPEL                      - SQLite3
- Apache                    - PHP
- PHP-PDO (For SQLite3)     - mod_ssl
- m2crypto                  - python-pip
- python-simplejson         - python-daemon
- python-flask              - python-ldap
- suds (from pip)           - bigsuds (1.0.4) (from pip)
- m2secret (from pip)
########################################################

Enter 'y' to begin the install (any other key to quit): "

read entry

if [ "$entry" != "y" -a "$entry" != "Y" ]; then
    echo -e "\nExiting without changes"
    exit
fi

echo -e "\nBeginning the package install"

function package_install () {
    PACKAGE=$1

    # Check if the package is already installed
    yum -C list installed $PACKAGE >> $LOGFILE 2>&1

    if [ $? -eq 0 ]; then
        printf "%-20s is already installed, skipping.\n" $PACKAGE
        return 0
    fi

    yum -y install $PACKAGE >> $LOGFILE 2>&1

    if [ $? -ne 0 ]; then
        echo -e "\nAn error occurred installing $PACKAGE, exiting."
        exit 1
    else
        printf "%-20s has been installed successfully.\n" $PACKAGE
        return 0
    fi
}

function pip_install () {
    PACKAGE=$1

    # Get the name of the package without a version specifier
    PKGNAME=$(echo $PACKAGE | cut -d= -f-1)
    PKGVER=$(echo $PACKAGE | cut -d= -f3-)

    # Check if the package is already installed
    if [ "$PKGNAME" == "$PKGVER" ]; then
        # No specified version
        pip list --disable-pip-version-check | grep "^$PKGNAME " >> $LOGFILE 2>&1
    else
        # Check for specific version
        pip list --disable-pip-version-check | grep "^$PKGNAME ($PKGVER)" >> $LOGFILE 2>&1
    fi

    if [ $? -eq 0 ]; then
        printf "%-20s is already installed, skipping.\n" $PACKAGE
        return 0
    fi

    pip install --disable-pip-version-check $PACKAGE >> $LOGFILE 2>&1

    if [ $? -ne 0 ]; then
        echo -e "\nAn error occurred installing $PACKAGE, exiting."
        exit 1
    else
        printf "%-20s has been installed successfully.\n" $PACKAGE
        return 0
    fi
}

echo -e "Installing EPEL"
yum -y install epel-release >> $LOGFILE 2>&1

if [ $? -ne 0 ]; then
    echo -e "An error occurred installing EPEL. Exiting."
    exit
fi

echo -e "\nUpdating the package cache"
yum makecache >> $LOGFILE 2>&1

for package in "${YUM_PACKAGES[@]}"; do
    package_install $package
done

for package in "${PIP_PACKAGES[@]}"; do
    pip_install $package
done