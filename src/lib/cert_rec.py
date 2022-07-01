'''
This library contains a single class that is use to reconcile the certs
between the certs_tmp and certs table in the DB. The certs table is the 
permanent table for cert reporting. The certs_temp table is the DB were all the cert info gathered from the backup job is first stored.

To use this class -
reconcile = cert_rec.CertReconcile(log) - init the object
   @param log: The logging object from logsimple

reconcile.prepare() - Gathers info of DB changes that need to happen
reconcile.reconcile() - Writes the changes to the DB
'''
import sys
import sqlite3 as sq

class CertReconcile(object):
   '''
   This class reconciles cert data between certs_temp and certs DB.
   '''
   def __init__(self,log):
      '''
   reconcile = CertReconcile(log) - init the object
   @param log: The logging object from logsimple
      '''
      self.log = log
   
   def prepare(self):
      '''
   Gets the cert data from both certs and certs_temp tables and
   prepares several lists and dicts for DB writes.
      '''
      try:
         # Connect to DB
         self.log.debug('Cert prepare, connecting to DB.')
         db = sq.connect(sys.path[0] + '/db/main.db')
         dbc = db.cursor()
      except:
         e = sys.exc_info()[1]
         self.log.critical('Cert prepare, %s' % e)
         raise
      
      # Get list of devices from cert DBs
      self.log.debug('Cert prepare, getting distinct devices that have certs in DB.')
      dbc.execute('''SELECT DISTINCT DEVICE FROM CERTS_TEMP''')
      certs_tmp_dev = [ i[0] for i in dbc.fetchall()]
      dbc.execute('''SELECT DISTINCT DEVICE FROM CERTS''')
      certs_dev = [ i[0] for i in dbc.fetchall()]
      
      # Get list of devices common to temp and certs and diff thereof
      common_dev = list(set(certs_tmp_dev).intersection(certs_dev))
      self.delete_dev = list(set(certs_dev).difference(certs_tmp_dev))
      self.add_dev = list(set(certs_tmp_dev).difference(certs_dev))
      
      self.log.debug('Cert prepare, new devices to add: %s.' % str(self.add_dev) )
      self.log.debug('Cert prepare, old devices to delete: %s.' % str(self.delete_dev) )
      self.log.debug('Cert prepare, common devices: %s.' % str(common_dev) )
      
      #  Get all certs from common device from both tables
      self.add_certs = []
      self.del_certs = []
      if len(common_dev) > 0:
         sql_where = str(common_dev.pop())
         for i in common_dev:
            sql_where += ' OR DEVICE = %d' % i 
         
         self.log.debug('Cert prepare, getting list of certs from certs_temp table.')
         dbc.execute('''SELECT DEVICE,EXPIRE,SN,SUB_CN,NAME,ID 
                        FROM CERTS_TEMP WHERE DEVICE = %s ''' % sql_where)
         certs_tmp = self._lookup_dict(dbc.fetchall())
         
         self.log.debug('Cert prepare, getting list of certs from certs table.')
         dbc.execute('''SELECT DEVICE,EXPIRE,SN,SUB_CN,NAME,ID 
                        FROM CERTS WHERE DEVICE = %s''' % sql_where)
         certs = self._lookup_dict(dbc.fetchall())
         
         # Create list of certs that need to be copied 
         # from certs_tmp to certs DB
         self.log.debug('Cert prepare, getting list of certs from ' + 
                        'certs_temp table the need to be copied.')
         for dev in certs_tmp.keys():
            for cert in certs_tmp[dev].keys():
               try:
                  # Remove matching certs from cert dict
                  del certs[dev][cert]
               except KeyError:
                  self.add_certs.append(certs_tmp[dev][cert])
         
         self.log.debug('Cert prepare, cert IDs to copy: %s.' % str(self.add_certs) )
         
         # From what's left in certs dict make list of what needs
         # to be delete from certs DB
         self.log.debug('Cert prepare, getting list of certs from ' + 
                        'certs table the need to be deleted.')
         for dev in certs.keys():
            if len(certs[dev]):
               self.del_certs.extend(certs[dev].values())
         
         self.log.debug('Cert prepare, cert IDs to delete: %s.' % str(self.del_certs) )
      
      self.log.debug('Cert prepare, closing DB connection.')
      db.close()
   
   def _lookup_dict(self,dbfetch):
      '''
   Builds dict used for reconciling certs of common devices
   Internal use only, do not call from outside class.
      '''
      rtn_dict = {}
      # Iter through sql lookup to build search dict
      for cert in dbfetch:
         if cert[0] not in rtn_dict:
            rtn_dict[cert[0]] = {}
         rtn_dict[cert[0]].update( {str(cert[1]) + str(cert[2]) + str(cert[3]) + str(cert[4]):cert[5]} )
      return rtn_dict
   
   def reconcile(self):
      '''
   Write cert data changes to the DB that was determined by prepare command.
      '''
      self.log.debug('Starting cert reconciliation.')
      try:
         # Connect to DB
         self.log.debug('Certs reconcile, connecting to DB.')
         db = sq.connect(sys.path[0] + '/db/main.db')
         dbc = db.cursor()
      except:
         e = sys.exc_info()[1]
         print (e)
         self.log.critical('Certs reconcile - %s' % e)
         return 
      
      # Delete certs from devices no longer certs_tmp
      if len(self.delete_dev) > 0:
         self.log.debug('Certs reconcile, clearing certs from removed devices.')
         delete_dev = self.delete_dev
         delete_dev = [ [i] for i in delete_dev]
         dbc.executemany( '''DELETE FROM CERTS WHERE DEVICE = ?''', (delete_dev) )
         db.commit()
      
      # Copy certs from new devices from temp to certs
      if len(self.add_dev) > 0:
         self.log.debug('Certs reconcile, copying certs from new devices.')
         add_dev = self.add_dev[:]
         sql_where = str(add_dev.pop())
         for i in add_dev:
            sql_where += ' OR DEVICE = %d' % i 
         
         # Pull certs of new devices from certs_temp
         self.log.debug('Certs reconcile, getting new device certs from certs_tmp table.')
         dbc.execute('''SELECT DEVICE,NAME,ISSUER,EXPIRE,SN,KEY,SUB_C,SUB_S,
                        SUB_L,SUB_O,SUB_OU,SUB_CN FROM CERTS_TEMP WHERE 
                        DEVICE = %s''' % sql_where)
         temp_to_certs = dbc.fetchall()
         
         # Copy certs from new devices into certs
         self.log.debug('Certs reconcile, copying new device certs into certs table.')
         dbc.executemany( '''INSERT INTO CERTS (DEVICE,NAME,ISSUER,EXPIRE,
                           SN,KEY,SUB_C,SUB_S,SUB_L,SUB_O,SUB_OU,SUB_CN,ACK) 
                           VALUES(?,?,?,?,?,?,?,?,?,?,?,?,0)''', (temp_to_certs))
         db.commit()
         del temp_to_certs
      
      # Copy new certs from cert_tmp to certs
      if len(self.add_certs) > 0:
         add_certs = self.add_certs[:]
         sql_where = str(add_certs.pop())
         for i in add_certs:
            sql_where += ' OR ID = %d' % i 
         
         # Pull certs of new devices from certs_temp
         dbc.execute('''SELECT DEVICE,NAME,ISSUER,EXPIRE,SN,KEY,SUB_C,SUB_S,
                        SUB_L,SUB_O,SUB_OU,SUB_CN FROM CERTS_TEMP WHERE 
                        ID = %s''' % sql_where)
         temp_to_certs = dbc.fetchall()
         
         # Copy certs from new devices into certs
         dbc.executemany( '''INSERT INTO CERTS (DEVICE,NAME,ISSUER,EXPIRE,
                           SN,KEY,SUB_C,SUB_S,SUB_L,SUB_O,SUB_OU,SUB_CN,ACK) 
                           VALUES(?,?,?,?,?,?,?,?,?,?,?,?,0)''', (temp_to_certs))
         db.commit()
         del temp_to_certs
      
      # Delete certs not in certs_tmp (what was left after cert matching)
      if len(self.del_certs) > 0:
         self.log.debug('Certs reconcile, clearing old certs from certs table.')
         del_certs = [ [i] for i in self.del_certs]
         dbc.executemany( '''DELETE FROM CERTS WHERE ID = ?''', (del_certs) )
         db.commit()
      
      # Clear certs_temp table
      self.log.debug('Certs reconcile, clearing certs_temp table.')
      dbc.execute('''DELETE FROM CERTS_TEMP''')
      db.commit()      
      
      self.log.debug('Cert prepare, closing DB connection.')
      db.close()
