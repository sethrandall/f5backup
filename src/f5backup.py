#!/usr/bin/env python
import sys
import time
import traceback
import sqlite3 as sq
from daemon import runner

sys.path.append('%s/lib' % sys.path[0])
import f5backup_lib
import logsimple
import cert_rec
import certmail

# Default action for uncaught errors
def execption_hook(type,value,tb):
   crash_time = int(time.time())
   exception = open('/opt/f5backup/log/backupd.trace-%d' % crash_time,'w',0)
   exception.write('Traceback (most recent call last):\n')
   exception.write( ''.join(traceback.format_tb(tb)) )
   exception.write( type.__name__ + ': ' + str(value) + '\n')
   exception.close()
   exit()

sys.excepthook = execption_hook


class f5backup():
   def __init__(self):
      self.stdin_path = '/dev/null'
      self.stdout_path = '/dev/null'
      self.stderr_path = '/dev/null'
      self.pidfile_path = '/opt/f5backup/pid/f5backup.pid'
      self.pidfile_timeout = 5
   
   def backup_time(self):
      '''
   backup_time() - Get backup time from DB
      '''
      try:
         # Connect to DB
         db = sq.connect(sys.path[0] + '/db/main.db')
         dbc = db.cursor()
         
         # Get backup time from DB
         dbc.execute("""SELECT VALUE FROM BACKUP_SETTINGS_INT
                      WHERE NAME = 'BACKUP_TIME'""")
         db_time = dbc.fetchone()[0]
         db.close()
      except:
         e = sys.exc_info()[1]
         self.log.critical('Error: Cant open DB,backup_time - %s' % e)
         raise
      return db_time
   
   def email_time(self):
      '''
   email_time() - Get email report setting from DB
      '''
      try:
         # Connect to DB
         db = sq.connect(sys.path[0] + '/db/main.db')
         dbc = db.cursor()
         
         # Get email
         dbc.execute("""SELECT SEND_REPORT,DAILY,ON_DAY 
                        FROM EMAIL WHERE ID = 0""")
         (send,daily,on_day) = dbc.fetchone()
         db.close()
      except:
         e = sys.exc_info()[1]
         self.log.critical('Error: Cant open DB, email_time - %s' % e)
         raise
      return {'send':send,'daily':daily,'on_day':on_day}
   
   def run(self):
      # Open new log file. Quit if permission denied.
      try:
         logfile = '%s/log/backupd.log' % sys.path[0]
         self.log = logsimple.LogSimple(logfile,max_files=1,max_bytes=1048576)
         self.log.setlevel('INFO')
      except:
         e = sys.exc_info()[1]
         print 'Unable to open logfile - %s' % e
         raise
      
      # Get log level from DB and reset in logging object
      self.log.info('Getting log level from db.')
      try:
         db = sq.connect(sys.path[0] + '/db/main.db')
         dbc = db.cursor()
         dbc.execute("SELECT LEVEL FROM LOGGING WHERE NAME = 'BACKUPD'")
      except:
         e = sys.exc_info()[1]
         print 'Error: Cant open DB - %s' % e
         raise
      self.log.setlevel( str(dbc.fetchone()[0]) )
      db.close()

      # Check time every 10 seconds
      self.log.info('Starting backup daemon.')
      while True:
         # Get local time of day in minutes
         ltime = time.localtime()
         tcheck = (ltime[3] * 60) + ltime[4]

         # Get DB time
         self.log.debug('Checking DB backup time.')
         db_time = self.backup_time()
         self.log.debug('DB backup time is %d' % db_time)
         
         # Does minute time match DB time ?
         if (tcheck == db_time):
            # Run backup job
            # Catch errors 
            self.log.info('Backup time is now. Running backup job.')
            try:
               f5backup_lib.main()
            except f5backup_lib.BackupError as e:
               # If there is an error skip all else
               self.log.error('An error has occurred with the backup job - %s' % e)
               self.log.error('Please fix issue before resuming.')
               time.sleep(61)
               continue
            self.log.info('Backup job completed.')
            
            # Reconcile certs
            self.log.info('Starting cert reconcile.')
            try:
               reconcile = cert_rec.CertReconcile(self.log)
               reconcile.prepare()
               reconcile.reconcile()
               del reconcile
            except:
               # If there is an error skip all else
               e = sys.exc_info()[1]
               self.log.error('An error has occurred with cert reconciling - %s' % e)
               self.log.error('Please fix issue before resuming.')
               time.sleep(61)
               continue
            self.log.info('Completed cert reconcile.')
            
            #Email cert report
            self.log.info('Preparing cert report for email.')
            try:
               email = self.email_time()
               # Are reports turned on ?
               if email['send']:
                  if email['daily']:
                     # If report interval is daily send report
                     report = certmail.CertReport(self.log)
                     report.prepare()
                     report.send()
                     self.log.info('Cert report sent.')
                  else:
                     # Otherwise check day
                     if email['on_day'] == time.localtime()[6]:
                        report = certmail.CertReport(self.log)
                        report.prepare()
                        report.send()
                        self.log.info('Cert report sent.')
                     else:
                        self.log.info('No report for today.')
               else:
                  self.log.info('Email reports turned off.')
            except:
               # If there is an error skip all else
               e = sys.exc_info()[1]
               self.log.error('An error has occurred with cert report email - %s' % e)
               self.log.error('Please fix issue before resuming.')
               time.sleep(61)
               continue
               
            # wait 60 seconds after finishing to 
            # ensure job does not run twice
            time.sleep(60)
         
         # wait 10 secs before trying again
         time.sleep(10)

# Run main daemon
daemon_runner = runner.DaemonRunner( f5backup() )
daemon_runner.do_action()