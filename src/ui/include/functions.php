<?php
// Functions file

// check for bad characters 
function bad_chars ($input) {
	if ( preg_match('/([\'\"%=;<>\s]|--)/', $input) ) {
		return 1;
	} else {
		return 0;
	};
};
	
// Post passwords and return hash
function password ($password,$password2) {
	// Do passwords match ?
	if ( $password != $password2 ) {
		global $PASS_ERR;
		$PASS_ERR = "Passwords do not match.";
		return;
	};

	// Passwords can not contain special characters 
	if ( bad_chars($password) ) {
		global $PASS_ERR;
		$PASS_ERR = "Passwords can't contain spaces or the following special characters: ' \" = % ; < > --";
		return;
	};
	
	// Password complexity
	if ( ! (strlen($password) >= 7 &&  preg_match('/^(?=.*[a-z])(?=.*[A-Z])((?=.*\d)|(?=.*\W)).+$/',$password)) ) {
		global $PASS_ERR;
		$PASS_ERR = "Password does not meet complexity requirements: <br />\n";
		$PASS_ERR .= "Minimum 7 characters with at least one capital letter, one lowercase letter";
		$PASS_ERR .= " and one number or one approved special character. $test\n";
		return;
	};
			
	// Create hash
	$salt = substr(str_replace('+', '.', base64_encode(sha1(microtime(true), true))), 0, 22);
	$hash = crypt($password, '$2a$12$' . $salt);
	return $hash;
};

function password_func ($password,$password2) {
	// Do passwords match ?
	if ( $password != $password2 ) {
		throw new Exception('Passwords do not match.');
	};

	// Passwords can not contain special characters 
	if ( bad_chars($password) ) {
		throw new Exception("Passwords can't contain spaces or the following special characters: ' \" = % ; < > --");
	};
	
	// Password complexity
	if ( ! (strlen($password) >= 7 &&  preg_match('/^(?=.*[a-z])(?=.*[A-Z])((?=.*\d)|(?=.*\W)).+$/',$password)) ) {
		$ERROR = "Password does not meet complexity requirements: <br />\n";
		$ERROR .= "Minimum 7 characters with at least one capital letter, one lowercase letter";
		$ERROR .= " and one number or one approved special character.\n";
		throw new Exception("$ERROR");
	};
			
	// Create hash
	$salt = substr(str_replace('+', '.', base64_encode(sha1(microtime(true), true))), 0, 22);
	$hash = crypt($password, '$2a$12$' . $salt);
	return $hash;
};
?>