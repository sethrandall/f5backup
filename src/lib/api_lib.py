#!/usr/bin/env python
import os
import sys
from flask import Flask, jsonify, abort, request, make_response

# Get program libs
sys.path.append('%s/lib' % sys.path[0])
import m2secret
import backup_adauth 

app = Flask(__name__)


############################################################
### Error handling 
############################################################
@app.errorhandler(400)
def bad_request(error):
   return make_response(jsonify( { 'error': 'BAD REQUEST' } ), 400)

@app.errorhandler(404)
def not_found(error):
   return make_response(jsonify( { 'error': 'RESOURCE NOT FOUND' } ), 404)

@app.errorhandler(405)
def bad_method(error):
   return make_response(jsonify( { 'error': 'METHOD NOT ALLOWED' } ), 405)

@app.errorhandler(500)
def server_error(error):
   return make_response(jsonify( { 'error': 'INTERNAL SERVER ERROR' } ), 500)

############################################################
### Start web service functions
############################################################
@app.route("/")
def hello():
   return '''<html>
<h1>F5 Backup API Functions</h1>
<p><strong>status</strong><br />
URI - /api/v1.0/status<br />
Description - Function to use as a health check for web service</p>
<p><strong>encrypt</strong><br />
URI - /api/v1.0/crypto/encrypt/<br />
Description - Encrypt string using key from file</p>
<html>'''

@app.route("/api/v1.0/status")
def status():
   '''
Health check for web service and f5backup daemon
   '''
   try:
      # Check for f5backup daemon
      with open('%s/pid/f5backup.pid' % sys.path[0],'r') as psfile:
         pid = int( psfile.readline().rstrip() )
      os.kill(pid,0)
      return jsonify( {'status' : 'GOOD'} )
   except:
      return jsonify( {'status' : 'ERROR','error': 'Backup service is down!' } )

@app.route('/api/v1.0/crypto/encrypt/', methods = ['POST'])
def encrypt():
   '''
Encryption function - encrypt string using key from file
   '''
   try:
      # Check for element string and that it is not blank
      if 'string' not in request.json.keys():
         abort(400)
      elif not request.json or len(request.json['string']) == 0:
         abort(400)
   except:
      abort(400)
   
   # Get encryption password or give 500 error
   try:
      with open('%s/.keystore/backup.key' % sys.path[0],'r') as psfile:
         cryptokey = psfile.readline().rstrip()
   except:
      abort(500)
   
   # Encrypt string and return response, m2secret does not like unicode
   secret = m2secret.Secret()
   secret.encrypt(str(request.json['string']), cryptokey)
   serialized = secret.serialize()
   
   return jsonify( {'result' : serialized } )

@app.route('/api/v1.0/adauth/authenticate/', methods = ['POST'])
def adauth():
   '''
   adauth function - authenticate user against AD.
   
   POST JSON -
   @param user:   The username string to authenticate. 
   @param passwd: The password string of the user.
   
   Return JSON -
   @param result:    A True/False string for user credential validity.
   @param memberOf:  An array of users group memberships if auth passed.
   
   if False instead of memberOf -
   @param error:  A string containing the error of why the user 
   '''
   #Input validation
   try:
      # Check for user and passwd in POST
      if 'user' and 'passwd' not in request.json.keys():
         abort(400)
      elif not request.json or len(request.json['user']) == 0:
         abort(400)
   except:
      abort(400)
   
   # Authenicate
   try:
      result = backup_adauth.adauthenicate(
                  str(request.json['user']),
                  str(request.json['passwd'])
                  )
   except:
      e = sys.exc_info()[1]
      #log.error('%s' % e )
      abort(500)
   if result[0] == False:
      return jsonify( {'result': 'False' ,'error': result[1]} )
   elif result[0] == True:
      return jsonify( {'result': 'True','memberOf': result[1]} )
   else:
      abort(500)