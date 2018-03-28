<?php
$id = $_GET["id"];

//Is ID numberic ?
if ( is_numeric($id) ) {
	// Get user from DB
	$sth = $dbh->prepare("SELECT ID,ORD,NAME,STRING,ROLE FROM AUTHGROUPS WHERE ID = ?");
	$sth->bindParam(1,$id); 
	$sth->execute();
	$group = $sth->fetch();

	$name = $group['NAME'];
	$string = $group['STRING'];
	$role = $group['ROLE'];

	// Update post processing
	$message = '';
	if ($_SERVER['REQUEST_METHOD'] == "POST" && $_POST["change"] == "Update") {
		$dbh->beginTransaction();
		$updates = '';
		try{
			// Has role been updated ?
			if (! is_numeric($_POST["role"]) || ! array_key_exists($_POST["role"],$rolearray) ) {
				throw new Exception('Invalid role ID'); 
			};
			if ($role != $_POST["role"]) {
				$sth = $dbh->prepare("UPDATE AUTHGROUPS SET ROLE = ? WHERE ID = ?");
				$sth->bindParam(1,$_POST["role"]); 
				$sth->bindParam(2,$_GET["id"]); 
				$sth->execute();
				$updates .= '"User Role" ';
			};

			// Update group string
			if ($string != $_POST["string"]) {
				$sth = $dbh->prepare("UPDATE AUTHGROUPS SET STRING = ? WHERE ID = ?");
				$sth->bindParam(1,$_POST["string"]); 
				$sth->bindParam(2,$_GET["id"]); 
				$sth->execute();
				$updates .= '"Group String" ';
			};
			
			// If all is well commit the trans
			$string = $_POST["string"];
			$role = $_POST["role"];
			$dbh->commit();
		} catch (Exception $e) {
			$dbh->rollBack();
			$message = '<p class="error"><strong>Error:</strong> '. $e->getMessage() . "</p>\n";
		};

		if ( strlen($updates) > 0) {
			$message = "<p>The following items have been updated: $updates</p>";
		};
	};

	$roleselect = build_select($rolearray,$role);
	$title = "> <a href=\"authgrp.php\">Auth Groups</a> > $name";
	$contents = <<<EOD
	$message
	<form action="authgrp.php?id=$id" method="post">
	<table class="pagelet_table">
	<tr class="pglt_tb_hdr">
		<td>Group Setting</td>
		<td>Value</td>
	</tr> 
	<tr class="odd">
		<td>Group Name</td>
		<td>$name</td>
	</tr>
	<tr class="even">
		<td>Group String</td>
		<td>
			<input type="text" name="string" size="70" maxlength="150"  value="$string">
		</td>
	</tr>
	<tr class="odd">
		<td>Group Role</td>
		<td>
			<select name="role">
				$roleselect
			</select>
		</td>
	</tr>
	</table>
	<input type="submit" name="change" value="Update">
	</form> \n
EOD;

} else {
	$contents = '<p class="error">Error: Invalid ID</p>';
};

?>