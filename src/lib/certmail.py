'''
This library contains a single class that creates an email report for
expired certificates.

To use this class -
report = certmail.CertReport(log) - initialize report
   @param log: The logging object from logsimple

report.prepare() - Prepare the report for sending
report.send() - Send the report
'''
import smtplib
import time
import sys
import sqlite3 as sq
import m2secret

class CertReport(object):
   '''
   This class pulls the cert list from the database, packages
   it in HTML and emails off to the recipient.
   '''
   def __init__(self,log):
      '''
   report = CertReport(log)  - initialize report
   @param log: The logging object from logsimple
      '''
      self.log = log
      # Get times
      self.now = int( time.time() )
      self.thirty_days = self.now + 2592000
      self.seven_days = self.now + 604800
   
   def _get_certs(self,certs):
      '''
   Converts the certs into a list of dict from the sql call
   @param certs: The list of tuples from the sql query
   '''
      # Format DB lookup results into dict
      return [ {'id':idn, 'name':name, 'device':device, 'cn':cn, 'expire':expire} 
               for idn, name, device, cn, expire in certs ]
   
   def _cert_table(self,certs):
      '''
   Builds HTML table of certs
   @param certs: The list of cert dicts as formatted by _get_certs
      '''
      # If nothing in the list return None
      if len(certs) <= 0:
         return '<div align="center">None</div>'
      
      # Table headers
      cert_table = '''<table class="cert" align="center">
      <th>Cert CN</th>
      <th>Expiration Date (GMT)</th>
      <th>F5 Device</th>
      <th>Cert NAME</th>''' 
      
      # make one row for each cert
      for cert in certs:
         expire = time.strftime('%Y-%m-%d %H:%M',time.gmtime(cert['expire']) )
         device = self.dev_dict[cert['device']]
         cert_table += '''
      <tr>
         <td>%s</td>
         <td>%s</td>
         <td>%s</td>
         <td>%s</td>
      </tr>''' % (cert['cn'],expire,device,cert['name'])
      cert_table +=  '\r\n</table>'
      return cert_table
      
   def prepare(self):
      '''
   Prepares a the message for sending. Gets setting from DB. Also
   pulls certs from DB and creates email message and headers sending.
      '''
      try:
         # Connect to DB
         self.log.debug('Email prepare, connecting to DB.')
         db = sq.connect(sys.path[0] + '/db/main.db')
         dbc = db.cursor()
         
         # Get email settings from DB
         self.log.debug('Email prepare, getting email settings from DB.')
         dbc.execute('''SELECT SENDER,SENDER_TITLE,TO_MAIL,SUBJECT,HIDE_ACK,
         TLS,SERVER,PORT,LOGIN,LOGIN_USER,LOGIN_PASS FROM EMAIL WHERE ID =0''')
         db_values_temp = dbc.fetchone()
      except:
         e = sys.exc_info()[1]
         self.log.critical('Email prepare - %s' % e)
         raise
      
      # Convert unicode into str
      self.log.debug('Email prepare, convert unicode.')
      db_values = []
      for i in db_values_temp:
         if type(i).__name__ == 'unicode':
            i = str(i)
         # Add new string to new list
         db_values.append(i)
      
      # Create email settings dict
      self.log.debug('Email prepare, creating email dict.')
      db_keys = ['sender','sender_title','to_mail','subject','hide_ack','tls',
                 'server','port','login','login_user','login_crypt']
      self.email_set = dict(zip(db_keys,db_values))
      
      # Get user password
      try:
         # Get crypto key
         self.log.debug('Email prepare, getting key from keystore.')
         with open(sys.path[0] + '/.keystore/backup.key','r') as psfile:
            key =  psfile.readline().rstrip()
      except:
         e = sys.exc_info()[1]
         self.log.critical('Can\'t get key from keystore - %s' % e)
         raise
      
      # Decrypting user password
      if self.email_set['login']:
         self.log.debug('Email prepare, decrypting email password.')
         try:
            secret = m2secret.Secret()
            secret.deserialize(self.email_set['login_crypt'])
            self.email_set['login_pass'] = secret.decrypt(key)    
         except:
            e = sys.exc_info()[1]
            self.log.critical('Can\'t decrypt email login password - %e' % e)
            raise
      
      # build device dict
      self.log.debug('Email prepare, building device list.')
      dbc.execute('SELECT ID,NAME FROM DEVICES') 
      self.dev_dict = {}
      for idn, name in dbc.fetchall():
         self.dev_dict[idn] = str(name)
      
      # Get certs expiring between 30 and 7 days
      self.log.debug('Email prepare, getting 30 to 7 certs.')
      dbc.execute('''SELECT ID,NAME,DEVICE,SUB_CN,EXPIRE FROM CERTS WHERE 
                     EXPIRE < ? AND EXPIRE > ? AND ACK = 0 ORDER BY EXPIRE''',
                     (self.thirty_days,self.seven_days) )
      thirty_to_seven = self._get_certs(dbc.fetchall())
      
      # Get certs expiring between 7 days and now
      self.log.debug('Email prepare, getting 7 to now certs.')
      dbc.execute('''SELECT ID,NAME,DEVICE,SUB_CN,EXPIRE FROM CERTS WHERE 
                     EXPIRE < ? AND EXPIRE > ? AND ACK = 0 ORDER BY EXPIRE''', 
                     (self.seven_days,self.now) )
                     
      seven_to_now =  self._get_certs(dbc.fetchall())
      
      # Get expired certs
      self.log.debug('Email prepare, getting expired certs.')
      dbc.execute('''SELECT ID,NAME,DEVICE,SUB_CN,EXPIRE FROM CERTS WHERE 
                     EXPIRE < ?  AND ACK = 0 ORDER BY EXPIRE''', (self.now,) )
      expired = self._get_certs(dbc.fetchall())
      
      # Get expired and expiring with in 30 days that have been acked
      self.log.debug('Email prepare, getting acknowledged certs.')
      dbc.execute('''SELECT ID,NAME,DEVICE,SUB_CN,EXPIRE FROM CERTS WHERE 
                     EXPIRE < ? AND ACK = 1 ORDER BY EXPIRE''', (self.thirty_days,) )
      acked = self._get_certs(dbc.fetchall())
      
      # build table of acked certs
      acked_certs = ''
      if not self.email_set['hide_ack']:
         acked_certs = '''
   <div align="center"><strong>Certs Expired or Expiring Within 30 Days (Acknowledged)</strong></div>
   %s
   <br>''' % self._cert_table(acked)
      
      # Build email header
      self.log.debug('Email prepare, building header.')
      self.header = '''From: %s <%s>
To: %s
MIME-Version: 1.0
Content-type: text/html
Subject: %s 
''' % (self.email_set['sender_title'],
               self.email_set['sender'],
               self.email_set['to_mail'],
               self.email_set['subject']
             )
      
      # Build Email body
      self.log.debug('Email prepare, building message body.')
      self.msg_body = '''
<html>
<body>
<style>
body {
   text-align:center;
}
table.cert {
   border: 1px solid #999999;
   border-collapse:collapse;
   text-align:center;
}
table.cert th {
   border: 1px solid #999999;
   border-collapse:collapse;
   padding:2px 12px 2px 12px;
   height: 25px;
  /* background-image:url('/images/banner.png');
   background-repeat:repeat-x; */
}
table.cert td {
   border: 1px solid #999999;
   border-collapse:collapse;
   padding:2px 12px 2px 12px;
}
table.title {
   text-align: center;
}
table.title th {
   font-size:30px;
   font-weight:bold;
}
</style>

   <table class="title" align="center">
      <th>Certificate Report</th>
      <tr><td>Config Backup for F5</td></tr>
   </table>
   <br />
   
   <div align="center"><strong>Certs Expiring in 30 to 7 Days (Not Acknowledged)</strong></div>
   %s
   <br>

   <div align="center"><strong>Certs Expiring Within 7 Days (Not Acknowledged)</strong></div>
   %s
   <br>
   
   <div align="center"><strong>Expired Certs (Not Acknowledged)</strong></div>
   %s
   <br>
   %s
</body>
</html>''' % (self._cert_table(thirty_to_seven),
         self._cert_table(seven_to_now),
         self._cert_table(expired),
         acked_certs )
      #Close DB connection
      self.log.debug('Email prepare, closing DB.')
      db.close
   
   def send(self):
      '''
   Connects to the email server and send message.
      '''
      # Connect to server
      self.log.debug('Email send, connecting to email server')
      try:
         email = smtplib.SMTP(self.email_set['server'],self.email_set['port'])
      except:
         e = sys.exc_info()[1]
         self.log.critical('Email send, cant connect to server - %s' % e)
         return
      
      # Use TLS
      if self.email_set['tls']:
         self.log.debug('Email send, attempting STARTTLS.')
         try:
            email.starttls()
         except smtplib.SMTPException as e:
            self.log.critical('TLS Error: %s' % e)
            return
      # Login
      if self.email_set['login']:
         self.log.debug('Email send, attempting to authenticate.')
         try:
            email.login(self.email_set['login_user'],self.email_set['login_pass'])
         except (smtplib.SMTPAuthenticationError,smtplib.SMTPException) as e:
            self.log.critical('Email send, auth Error: %s' % e)
            return
      
      # Sending email
      self.log.info('Sending email report.')
      try:
         email.sendmail(self.email_set['sender'], 
                        self.email_set['to_mail'].split(';'), 
                        self.header + self.msg_body) 
      except (smtplib.SMTPDataError,smtplib.SMTPException) as e:
         self.log.critical('Email send, Send Error: %s' % e)
         return
      self.log.debug('Email send, closing connection.')
      email.quit()
