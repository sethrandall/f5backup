<?php
// Do passwords match ?
if ( $_POST["password"] == $_POST["password2"] ) {
	// Passwords can not contain special characters 
	if ( ! preg_match('/[[:punct:]]/', $_POST["password"]) ) {
		// Password change is valid
		$post++;
		$updates .= "Password ";
		
		// Create hash
		$salt = substr(str_replace('+', '.', base64_encode(sha1(microtime(true), true))), 0, 22);
		$hash = crypt($_POST["password"], '$2a$12$' . $salt);
		
		// Write hash to DB
		$sth = $dbh->prepare("UPDATE USERS SET HASH = ? WHERE ID = $id");
		$sth->bindParam(1,$hash); 
		$sth->execute();
	} else {
		$error = "Passwords can't contain special characters !";
	};
} else {
	// Confirm password does not match
	$error = "Passwords do not match";
};
?>
