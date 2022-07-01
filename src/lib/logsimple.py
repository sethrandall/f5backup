import os
import sys
from datetime import datetime

class LogSimple(object):
   '''
   LogSimple - Simple logging class. This class was made because the 
   standard python logging module is a swiss army knife, but all I 
   needed was a butter knife
   
   Features of this class -
      - Allows for single unlimited size log file or certain number 
         rotating files of a max size
      - Standard format of "Y-m-d H:M:S.Msec <log_level>: <message>"
      - Selectable log level that can be changed at any time without 
         recreating object
      - Selectable time for UTC or local
      - Each logging object is completely independent of each other,
         writing to different objects in the same script does not effect
         the other and does not require filters
   
   Commands -
      obj = LogSimple(args)
      obj.setlevel(<str>)
      obj.close()
      obj.debug(<str>)
      obj.info(<str>)
      obj.warning(<str>)
      obj.error(<str>)
      obj.critical(<str>)
   
   Example usage single file -
      # Create logging object for single unlimited file,
      # logging level of WARNING and using local time
      log = SimpleLog('test.log')
      log.setlevel('WARNING')
      
      # Log a warning message
      log.warning('This is warning message')
      
      # File output
      2014-08-24 09:54:55.441 WARNING: This is warning message
   
   Example usage a rolling log file -
      # Create a logging object with a maximum of 5 files up to 1 MB 
      log = SimpleLog('test.log',utc=True,max_files=5,max_bytes=1048576)
      log.setlevel('INFO')
   '''
   def __init__(self,filename,utc=False,max_files=0,max_bytes=10485760):
      '''
   __init__ - Create log object, open file for logging
   Use: LogSimple(filename,max_files=0,max_bytes=10485760)
   Returns logger object
   
   @param filename: str,filename of log file
   @param utc: boolean, log in UTC time, default local
   @param max_files: int, max number of _logfiles from a rollover, 
                     default 0 for no rollover
   @param max_bytes: int, maximum size of log file,default 10MB
      '''
      self.filename = filename
      self.utc = utc
      self.max_files = max_files
      self.max_bytes = max_bytes
      self.filename  = filename
      self._loglevel = 0
      self._openfile()
   
   def setlevel(self,level):
      '''
   setlevel - Set logging level, default NOTSET 
   Use: <log_obj>.setlevel('str level')
   
   Log level strings -
      'CRITICAL'
      'ERROR'
      'WARNING'
      'INFO'
      'DEBUG'
      'NOTSET'
      '''
      # Use array to map string level to integer
      _levels = {
         'CRITICAL' : 50,
         'ERROR' : 40,
         'WARNING' : 30,
         'INFO' : 20,
         'DEBUG' : 10,
         'NOTSET' : 0
         }
      self._loglevel = _levels[level]
   
   def _openfile(self):
      '''
   _openfile - Open file for logging
   Internal use only, do not call outside object
      '''
      # How big is the file right now
      try:
         self.fsize = os.stat(self.filename).st_size
      except OSError as e:
         # If this is file does not exist 
         if e.errno == 2:
            self.fsize = 0
         else:
            # pass exception on for all other errors
            raise 
      
      self._logfile = open(self.filename,'a')
   
   def close(self):
      '''
   close - Close log file
   Use: <log_obj>.close()
      '''
      # Reset file size counter
      self.fsize = 0
      self._logfile.close()
      
   def _Rollover(self):
      '''
      _Rollover - Roll over log files
      Internal use only, do not call outside object
      '''
      # Close existing log file
      self.close()
      
      # Generate list of max file numbers
      findex_list = range(self.max_files - 1)
      findex_list.reverse()
      
      # loop through file number renaming
      for findex in findex_list:
         # Prepare file names to roll file down by one
         if findex > 0: 
            # if not the base file move down by one
            # e.g. file.log.3 becomes file.log.4
            old_name = self.filename + '.%d' % findex
            new_name = self.filename + '.%d' % (findex + 1)
         else:
            # If base file add .1
            # e.g. file.log becomes file.log.1
            old_name = self.filename
            new_name = self.filename + '.%d' % (findex + 1)
         
         # Rename file if it exists
         try:
            os.rename(old_name,new_name)
         except OSError as e:
            # skip if file does not exist
            if e.errno == 2:
               continue
            else:
               # pass exception on for all other errors
               raise 
      # Create new log file
      self._openfile()
   
   def _write(self,message,level,levelname):
      '''
   _write - Write message to file
   Internal use only, do not call outside object
   
   @param message: Text of message to log
   @param level: Numeric level of log event
   @param levelname: Level name string
      '''
      # Is this event at or above current log level and not NOTSET?
      if self._loglevel <= level and self._loglevel != 0:
         # UTC time or local?
         if self.utc:
            dt = datetime.utcnow()
         else:
            dt = datetime.now()
         # format time to "Y-m-d H:M:S.Msec"
         time = dt.strftime("%Y-%m-%d %H:%M:%S.") + str(dt.microsecond)[0:3]
         
         # Format log message
         log_message = '%s %s: %s\n' % (time,levelname,message) 
         
         # Is it time for rollover? Only if we want rolling log file 
         if self.max_files > 0:
            message_size = sys.getsizeof(log_message) - 40
            # Does this message size plus current log file size
            # make exceed max_bytes ?
            if (self.fsize + message_size) > self.max_bytes:
               self._Rollover()
            # Add current message size to file size
            self.fsize += message_size
         
         # Write to logfile
         self._logfile.write(log_message)
         self._logfile.flush()
         os.fsync(self._logfile.fileno())

   # Logging events at levels
   def debug(self,message):
      self._write(message,10,'DEBUG')
   
   def info(self,message):
      self._write(message,20,'INFO')
   
   def warning(self,message):
      self._write(message,30,'WARNING')
   
   def error(self,message):
      self._write(message,40,'ERROR')
   
   def critical(self,message):
      self._write(message,50,'CRITICAL')