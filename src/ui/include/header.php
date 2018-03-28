<?php
require_once '/opt/f5backup/ui/include/PestJSON.php';
// Internal web service connect
function webcheck () {
	try {
		//Connect to internal webservice
		$pest = new PestJSON('http://127.0.0.1:5380');
		$result = $pest->get('/api/v1.0/status');  
	} catch (Exception $e) {
		return '<img style="vertical-align: middle;" src="/images/red_button.png"> Status: OFFLINE';
	};

	if ($result['status'] == 'ERROR' ) {
		 return '<img style="vertical-align: middle;" src="/images/yellow_button.png"> Status: ERROR';
	} elseif ($result['status'] == 'GOOD' ) {
		return '<img style="vertical-align: middle;" src="/images/green_button.png"> Status: ONLINE';
	};

};

// Make array of role names from DB
$sth = $dbh->query("SELECT ID,NAME FROM ROLES ORDER BY ID");
$sth->execute();
foreach ($sth->fetchAll() as $role) {
	$rolearray[$role['ID']] = $role['NAME'];
};
?>
<!DOCTYPE html> 
<html>
<head>
<meta http-equiv="X-UA-Compatible" content="IE=Edge,chrome=1" />
<link rel="stylesheet" type="text/css" href="css/main.css">
</head>
<body>
<table class="main">
<tr> <!-- Page Header ------------>
	<td class="header" colspan="2">
		<div id="title"><a href="/">Config Backup for F5</a></div>
		<div id="logout"><a href="/logout.php">Log out</a></div>
		<div id="status"><a href="/status.php"><?php echo  webcheck()?></a></div>
		<div id="user">Username: <?php echo  $_SESSION['user'] ?></div>
		<div id="role">User Role: <?php echo  $rolearray[$_SESSION['role']] ?></div>
		<div id="ip">User IP: <?php echo  $_SERVER['REMOTE_ADDR'] ?></div>
		<div id="date">Date: <?php echo  date('Y-m-d',time()) ?></div>
		<div id="time">Time: <?php echo  date('H:i',time()) ?></div>
	</td>
</tr>
