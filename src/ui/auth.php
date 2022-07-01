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
include("include/dbcore.php");

// include common content
include("include/header.php");
include("include/menu.php");

// Get auth settings from core DB
$sth = $dbcore->prepare("SELECT AUTHACCT,DOMAIN FROM
									AUTH WHERE ID = 0 ORDER BY ID");
$sth->execute();
$row = $sth->fetch();

$authacct = $row['AUTHACCT'];
$domain = $row['DOMAIN'];

// Get mode from UI DB
$sth = $dbh->prepare("SELECT MODE FROM AUTH WHERE ID = 0 ORDER BY ID");
$sth->execute();
$mode = $sth->fetchColumn();

// Get auth servers
$sth = $dbcore->query("SELECT SERVER,TLS FROM AUTHSERVERS");
$sth->execute();
$servers = $sth->fetchAll();

$server1 = $servers[0]['SERVER'];
$server2 =  $servers[1]['SERVER'];
$tls = 0;

// Is TLS set on any server ?
if ( $servers[0]['TLS'] || $servers[1]['TLS']) {
	$tls = 1;
};

// Is this a POST ?
$message = '' ;
if ($_SERVER['REQUEST_METHOD'] == "POST") {
	include("include/auth_post.php");
	// Are there any updates ?
	if ( strlen($updates) > 0) {
		$message = "<p>The following items have been updated: $updates</p>";
	} else {
		$fields = array('mode','tls','domain','server1','server2','authacct');

		foreach($fields as $field) {
			if (isset($_POST[$field])) {
				$$field = $_POST[$field];
			}
		}
	};
};

// Create select items after post
// mode dropdown box
$ad = '';
$local = 'selected';
if ( $mode == 'ad' ) {
	$ad = ' selected';
	$local = '';
};

// TLS radio button
$tlsyes = '';
$tlsno = 'checked';

// Is TLS set ?
if ( $tls ) {
	$tlsyes = 'checked';
	$tlsno = '';
};


// Build site body here and put in var $contents
$contents = <<<EOD
	$message
	<form action="auth.php" method="post">
	<table class="pagelet_table">
	<tr class="pglt_tb_hdr">
		<td>Authentication Settings</td>
		<td>Value</td>
	</tr>
	<tr class="odd">
		<td>Mode</td>
		<td>
			<select name="mode">
			  <option value="local" $local>Local</option>
			  <option value="ad" $ad>Active Directory</option>
			</select>
		</td>
	</tr>
	<tr class="even">
		<td>Domain Name (eg. us.acme.local)</td>
		<td>
			<input type="text" name="domain" size="15" maxlength="50"  value="$domain">
		</td>
	</tr>
	<tr class="odd">
		<td>Use SSL?</td>
		<td>
			<input type="radio" name="tls" value="1" $tlsyes>Yes 
			<input type="radio" name="tls" value="0" $tlsno>No (Not Recommended)
		</td>
	</tr>
	<tr class="even">
		<td>Primary Auth Server</td>
		<td>
			<input type="text" name="server1" size="12" maxlength="50"  value="$server1">
		</td>
	</tr>
	<tr class="odd">
		<td>Secondary Auth Server</td>
		<td><input type="text" name="server2" size="12" maxlength="50"  value="$server2"></td>
	</tr>
	<tr class="even">
		<td>Auth User UPN</td>
		<td>
			<input type="text" name="user" size="25" maxlength="100"  value="$authacct">
		</td>
		</tr>
		<tr class="odd">
		<td>Auth User Password</td>
		<td>
			<input type="password" name="password" class="input" size="10" maxlength="50" value="nochange">
		</td>
	</tr>
	<tr class="even">
		<td>Confirm Password</td>
		<td>
			<input type="password" name="password2" class="input" size="10" maxlength="50" value="nochange">
		</td>
	</tr>
	</table>
	<input type="submit"name="submit"value="Update">
	</form> \n
EOD;
// Page HTML
?>
	<div id="pagelet_title">
		<a href="settings.php">Settings</a> > Authentication
	</div>
	<div id="pagelet_body">
<?php 
echo $contents;

echo "</div>";
include("include/footer.php");

// Close DB 
$dbh = null;
$dbcore = null;
?>