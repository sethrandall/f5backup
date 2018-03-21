#!/usr/bin/env python
import re , getpass ,sys

# Loop until passwords match
while True:
	while True:
		pass1=getpass.getpass('Type Password:')
		
		#Check password complexity 
		if not re.match('^(?=.*[a-z])(?=.*[A-Z])((?=.*\d)|(?=.*\W)).+$',pass1) or (len(pass1) < 8):
			sys.stderr.write('\nPassword does not meet complexity requirements -\n')
			sys.stderr.write('Must be at least 8 characters with one CAPITAL letter, one\n')
			sys.stderr.write('lowercase letter and one number or specail character.\n')
			continue
		
		break
	
	pass2=getpass.getpass('Confirm Password:')
	
	# Compare passwords
	if pass1 == pass2:
		break
	
	sys.stderr.write('Password do not match. Please try again.\n')

sys.stdout.write(pass1)

