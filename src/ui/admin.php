<?php
/* RBAC permissions
Add the role ID to the permissions array for the required
level to restrict access. Remove the permissions array to 
allow all. 

$permissions = array(1,2,3);

1 - Administrator
2 - Device Admin
3 - Operator
4 - Guest
*/
$permissions = array(1);

include("include/session.php");
include("include/dbconnect.php");

// include common content
include("include/header.php");
include("include/menu.php");

include("include/functions.php");
$contents = '';

// Update post processing
if ($_SERVER['REQUEST_METHOD'] == "POST" && $_POST["change"] == "Update") {
	// Is this an update and has password been updated ?

	$dbh->beginTransaction();
	try {
		// Was password really changed ?
		if ( $_POST["password"] == "nochange" ) {
			throw new Exception("Password was not changed.");
		};
		// Password validation and hash creation
		$hash = password_func($_POST["password"],$_POST["password2"]);
		
		// Write hash to DB
		$sth = $dbh->prepare("UPDATE ADMIN SET HASH = ? WHERE ID = 1");
		$sth->bindParam(1,$hash); 
		$sth->execute();
		$contents = "<p>Admin password has been updated.</p>";

		$dbh->commit();
	} catch (Exception $e) {
		$dbh->rollBack();
		$contents = $e->getMessage();
		$contents = "<p class=\"error\"><strong>Error:</strong> $contents </p>\n";
	};
};

// User info
$contents .= <<<EOD
	<form action="admin.php" method="post">
	<table class="pagelet_table">
		<tr class="pglt_tb_hdr"><td colspan="2">Admin User</td></tr>
		<tr class="even"><td>Change Password</td>
			<td><input type="password" name="password" class="input" maxlength="30" value="nochange"></td>
		</tr>
		<tr class="odd"><td>Confirm Password</td>
			<td><input type="password" name="password2" class="input" maxlength="30" value="nochange"></td>
		</tr>
	</table>
	<input type="submit" name="change" value="Update">
	</form>\n
EOD;


// Page HTML
?>
	<div id="pagelet_title">
		<a href="settings.php">Settings</a> > Admin Password
	</div>
	<div id="pagelet_body">
<?php

echo $contents;

echo "</div>";
include("include/footer.php");
?>
