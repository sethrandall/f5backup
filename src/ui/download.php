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
$permissions = array(1,2,3);

include("include/session.php");
include("include/dbconnect.php");
include("include/dbcore.php");

// Does request have file id param and is numeric ?
if ( isset($_GET["id"]) && is_numeric($_GET["id"]) ) {
		// Get file name from DB
		$sth = $dbcore->prepare("SELECT DIR,FILE FROM ARCHIVE WHERE ID = ?");
		$sth->bindParam(1,$_GET["id"]); 
		$sth->execute();
		$row = $sth->fetch();
		
		$file = $row['DIR'].'/'.$row['FILE'];
		if (file_exists($file)) {
			// Close the session to prevent a long download from
			// locking the user session
			session_write_close();

			// Clear any output buffers and stop buffering
			// to prevent memory errors when outputting the file
			while (ob_get_level()) ob_end_clean();
			header('Content-Type: application/octet-stream');
			header('Content-Disposition: attachment; filename='.basename($row['FILE']));
			header('Cache-Control: no-cache');
			header('Content-Length:'.filesize($file));

			readfile($file);
			exit;
		} else {
			// 404 error if file does not exist
			header("HTTP/1.0 404 Not Found");
			echo <<<EOD
	<h1>File not found.</h1>
	<p>Can't find file $file.</p>\n
EOD;
		};
} else {
	header("HTTP/1.0 404 Not Found");
};

/* Close DB  */
$dbcore = null;
$dbh = null;
?>
