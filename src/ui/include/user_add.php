<?php

// Update post processing
$post = 0;
if ($_SERVER['REQUEST_METHOD'] == "POST" && $_POST["change"] == "Create") {
	
	$dbh->beginTransaction();

	try{
		$username = strtolower($_POST["username"]); //lower username
		
		// Cannot create admin user
		if ($username == 'admin') {
			throw new Exception("Cannot create a user named \"admin\".");
		};

		// Check if user name exists
		$sth = $dbh->prepare("SELECT count(NAME) FROM USERS WHERE NAME= ?");
		$sth->bindParam(1,$username); 
		$sth->execute();
		$user_exist = $sth->fetchColumn();
		if ($user_exist) {
			throw new Exception("Username \"$username\" already exists.");
		};
		
		// Password validation and hash creation
		$hash = password_func($_POST["password"],$_POST["password2"]);

		// Validate that role ID is valid
		if (! is_numeric($_POST["role"]) || ! array_key_exists($_POST["role"],$rolearray) ) {
			throw new Exception('Invalid role ID'); 
		};

		// If all checks pass then insert into DB
		$contents = "<p>Created user: $username</p>";
		$time = time();
		$added_by = $_SESSION['user'];
		$sth = $dbh->prepare("INSERT INTO USERS ('NAME','HASH','ADDED_BY','DATE_ADDED','ROLE') VALUES (:username,:hash,'$added_by',$time,:role)");
		$sth->bindValue(':username',$username); 
		$sth->bindValue(':hash',$hash); 
		$sth->bindValue(':role',$_POST["role"]); 
		$sth->execute();

		$dbh->commit();
	} catch (Exception $e) {
		$dbh->rollBack();
		$contents = $e->getMessage();
		$contents = "<p class=\"error\"><strong>Error:</strong> $contents </p>\n";
	};
	
} else {
	// Page body - Create
	$roleselect = build_select($rolearray,$row['ROLE']);
	$contents = <<<EOD
		<form action="users.php?page=Add" method="post">
		<table class="pagelet_table">
			<tr class="pglt_tb_hdr"><td colspan="2">Create New User</td></tr>
			<tr class="odd"><td>User Name</td><td><input type="text" name="username" class="input" maxlength="30"></td></tr>
			<tr class="even"><td>Password</td><td><input type="password" name="password" class="input" maxlength="30"></td></tr>
			<tr class="odd"><td>Confirm Password</td><td><input type="password" name="password2" class="input" maxlength="30"></td></tr>
			<tr class="even"><td>Role</td><td>
				<select name="role">
					$roleselect
				</select>
			</td></tr>
		</table>
		<input type="submit" name="change" value="Create">
		</form>
EOD;

};

$title = "<a href=\"users.php\">Users</a> > Create New User";
?>	