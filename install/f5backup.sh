#! /bin/bash
### BEGIN INIT INFO
# Provides: f5backup
# Default-Start:  3 5
# Default-Stop:   0 1 2 6
# Short-Description: Config Backup for F5 service
# Description:    Starts the F5 backup program
### END INIT INFO

# Source function library.
. /etc/rc.d/init.d/functions

PID='/opt/f5backup/pid/f5backup.pid'
DAEMON='/opt/f5backup/f5backup.py'

start() {
	echo -n "Starting F5 Backup Program: "
	if [ ! -e $PID ] ; then
		su -c "$DAEMON start" f5backup
		echo_success
		echo 
	else
		echo_failure
		echo 
		echo "Service already running."
	fi
}

stop() {
	echo -n "Shutting Down F5 Backup Program: "
	if [ -e $PID ] ; then
		su -c "$DAEMON stop" f5backup
		echo_success
		echo 
	else
		echo_failure
		echo 
		echo "Service not running."
	fi			
}

case "$1" in
	start)
		start
		;;
	stop)
		stop
		;;
	restart)
		stop
		start
		;;
  *)
    # Refuse to do other stuff
    echo "Usage: service f5backup {start|stop|restart}"
    exit 1
    ;;
esac

exit 0