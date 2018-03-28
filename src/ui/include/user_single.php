<?php
$contents = '';
if (is_numeric($_GET["id"])) {

	// Get user from DB
	$sth = $dbh->prepare("SELECT ID,NAME,ADDED_BY,DATE_ADDED,ROLE FROM USERS WHERE ID = ?");
	$sth->bindParam(1,$_GET["id"]); 
	$sth->execute();
	$row = $sth->fetch();

	$id = $row['ID'];
	$name = $row['NAME'];
	$created_by = $row['ADDED_BY'];
	$date_added = date('Y-m-d H:i:s',$row['DATE_ADDED']);
	$roleselect = build_select($rolearray,$row['ROLE']);

	// Update post processing
	if ($_SERVER['REQUEST_METHOD'] == "POST" && $_POST["change"] == "Update") {
		$dbh->beginTransaction();
		$post = 0;
		$post_message = '';
		try{
			// Has password been updated ?
			if ( $_POST["password"] != "nochange" ) {
				// Password validation and hash creation
				$hash = password_func($_POST["password"],$_POST["password2"]);
				// Write hash to DB
				$sth = $dbh->prepare("UPDATE USERS SET HASH = ? WHERE ID = ?");
				$sth->bindParam(1,$hash); 
				$sth->bindParam(2,$id); 
				$sth->execute();
				$post_message .= '"Password" ';
				$post ++;
			};

			// Has role been updated ?
			if (! is_numeric($_POST["role"]) || ! array_key_exists($_POST["role"],$rolearray) ) {
				throw new Exception('Invalid role ID'); 
			};
			if ($row['ROLE'] != $_POST["role"]) {
					$sth = $dbh->prepare("UPDATE USERS SET ROLE = ? WHERE ID = ?");
					$sth->bindParam(1,$_POST["role"]); 
					$sth->bindParam(2,$id); 
					$sth->execute();
					$post_message .= '"User Role" ';
					$post ++;
					
					// Set new role for page refresh
					$roleselect = build_select($rolearray,$_POST["role"]);
			};

			// Was anything updated -- Do something about errors
			if ( $post > 0 ) { 
				$post_message = "<p>The following items have been updated: $post_message</p>"; 
			} else {
				$post_message = "<p>No settings where updated.</p>";
			};

			// If all is well commit the trans
			$dbh->commit();
		} catch (Exception $e) {
			$dbh->rollBack();
			$post_message = $e->getMessage();
			$post_message = "<p class=\"error\"><strong>Error:</strong> $post_message </p>\n";
			$post = 1;
		};
	
	};
	
	
	// Page body
	$title = "<a href=\"users.php\">Users</a> > $name";
	if ( isset($post) && $post > 0 ) { 
		$contents .= $post_message;
	} ;
	if ( isset($error) ) {$contents .= $error;}; // did an error occur ?
	
	// User info
	$contents .= <<<EOD
	<form action="users.php?page=user&id=$id" method="post">
	<table class="pagelet_table">
		<tr class="pglt_tb_hdr">
			<td>Created By</td>
			<td>Date Created</td>
		</tr>
		<tr class="odd">
			<td>$created_by</td>
			<td>$date_added</td>
		</tr>
		<tr class="even">
			<td>Change Password</td>
			<td><input type="password" name="password" class="input" maxlength="30" value="nochange"></td>
		</tr>
		<tr class="odd">
			<td>Confirm Password</td>
			<td><input type="password" name="password2" class="input" maxlength="30" value="nochange"></td>
		</tr>
		<tr class="even">
			<td>Role</td>
			<td>
				<select name="role">
					$roleselect
				</select>
			</td>
		</tr>
	</table>
	<input type="submit" name="change" value="Update">
	</form>
EOD;
} else {
// Error message if id is not number
	$contents .= "<p class=\"error\"><strong>Error:</strong> \"$ID\" is not a valid input</p>\n";
};
?>