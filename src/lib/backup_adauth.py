#!/usr/bin/env python
import sys
import ldap
import time
import traceback
import sqlite3 as sq

sys.path.append('%s/lib' % sys.path[0])
import adauth
import m2secret
import logsimple 

# Default action for uncaught errors
def execption_hook(type,value,tb):
   crash_time = int(time.time())
   exception = open('/opt/f5backup/log/auth.trace-%d' % crash_time,'w',0)
   exception.write('Traceback (most recent call last):\n')
   exception.write( ''.join(traceback.format_tb(tb)) )
   exception.write( type.__name__ + ': ' + str(value) + '\n')
   exception.close()
   exit()

sys.excepthook = execption_hook

def adauthenicate(user,passwd):
   '''
   Config backup function for authenticating user in active directory
   
   @param user:   Username you wish to authenticate
   @param passwd: Password of user
   @param log:    Log object
   
   This function gets the AD info from the DB and tracks which servers 
   in and up/down state. If AD server is down it is not attempted again 
   for another 10 minutes. 
   '''
   # Create logging for authentication
   logfile = '%s/log/auth.log' % sys.path[0]
   authlog = logsimple.LogSimple(logfile,max_files=5)
   authlog.setlevel('INFO')

   # Connect to DB
   try:
      db = sq.connect(sys.path[0] + '/db/main.db')
      dbc = db.cursor()
      authlog.debug('Connected to DB.')
      
      # Retrieve bind user, password, domain from DB
      authlog.debug('Getting bind username and password.')
      dbc.execute("SELECT AUTHACCT,AUTHHASH,DOMAIN FROM AUTH WHERE ID = 0")
      bind = dbc.fetchone()
   except:
      e = sys.exc_info()[1]
      authlog.critical('DB connect failed - %s' % e)
      authlog.close()
      raise StandardError(e)
   
   # Get log level from DB and reset in logging object
   dbc.execute("SELECT LEVEL FROM LOGGING WHERE NAME = 'AUTH'")
   authlog.setlevel( str(dbc.fetchone()[0]) )
   
   # Check for value in list bind
   if type(bind) is tuple:
      #Convert result into dict
      bind = {'acct' : bind[0],'passwd' : bind[1],'domain' : bind[2]}
   else:
      authlog.error('No credentials available in DB.')
      authlog.close()
      return [False, 'No credentials available in DB.']
   
   #Decrypt password 
   try:
      authlog.debug('Getting crypto key from key store.')
      with open(sys.path[0] + '/.keystore/backup.key','r') as psfile:
         cryptokey = psfile.readline().rstrip()
      authlog.debug('Decrypting bind password.')
      secret = m2secret.Secret()
      secret.deserialize(bind['passwd'])
      bind['passwd'] = secret.decrypt(cryptokey) 
   except:
      e = sys.exc_info()[1]
      authlog.critical('Can\'t get credentials from DB - %s' % e)
      authlog.close()
      raise StandardError(e)
   
   # Retrieve list of servers from DB
   authlog.debug('Getting list of servers from DB.')
   dbc.execute("SELECT ID,SERVER,TLS,TIMEDOWN FROM AUTHSERVERS")
   servers = [ {'id' : id, 'server' : server, 'tls' : tls, 'timedown' : timedown} 
                  for id, server, tls, timedown in dbc.fetchall() ]
   
   # loop through list of servers
   for server in servers:
      try:
         # Has server been declared down within the last 10 min ?
         if (server['timedown'] + 600) > int(time.time()): 
            # If yes skip to next server
            next_try = (server['timedown'] + 600) - int(time.time())
            authlog.debug('Server %s was down within 10 minutes. %d seconds until next retry.' % 
                                 (server['server'],next_try) )
            continue
         
         # If this server works do auth and return result
         authlog.debug('Authenticating to server %s.' % server['server'])
         authlog.info('Authenticating user %s.' % user)
         auth = adauth.ADAuth(
                     server['server'],
                     bind['acct'],
                     bind['passwd'],
                     bind['domain'],
                     server['tls'])
         result = auth.Authenticate(user,passwd)
         if result[0] == True:
            authlog.info('User successfully authenticated. User:%s.' % user)
            authlog.debug('User %s attr - %s' % (user,str(result[1])) )
         elif result[0] == False:
            authlog.info('User auth failed - User:%s, Reason:%s.' % (user,result[1]) )
         authlog.close()
         return result
      except ldap.SERVER_DOWN as e:
         # If server down mark in DB and go to next  
         authlog.error('Server %s is not up. Marking server down.' % server['server'])
         try:
            dbc.execute('UPDATE AUTHSERVERS SET TIMEDOWN = ? WHERE ID = ?',
                                          ( int(time.time()),server['id']) )
            db.commit()
         except:
            e = sys.exc_info()[1]
            authlog.error('Server down DB update - %s' % e)
         #Skip to next device
         continue
   else:
      authlog.error('No AD servers available.')
      authlog.close()
      return [False, 'No auth servers available.']
