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
$sth = $dbcore->prepare("SELECT SEND_REPORT,SENDER,SENDER_TITLE,TO_MAIL,SUBJECT,HIDE_ACK,
                         DAILY,ON_DAY,TLS,SERVER,PORT,LOGIN,LOGIN_USER FROM EMAIL WHERE 
                         ID = 0");
$sth->execute();
$row = $sth->fetch();

$enabled = $row['SEND_REPORT'];
$sender = $row['SENDER'];
$sender_title = $row['SENDER_TITLE'];
$recipients = $row['TO_MAIL'];
$subject = $row['SUBJECT'];
$hide = $row['HIDE_ACK'];
$daily = $row['DAILY'];
$on_day = $row['ON_DAY'];
$tls = $row['TLS'];
$server = $row['SERVER'];
$port = $row['PORT'];
$login = $row['LOGIN'];
$login_user = $row['LOGIN_USER'];

// Is this a POST ?
$message = '' ;
if ($_SERVER['REQUEST_METHOD'] == "POST") {
   include("include/certreport_post.php");
   // Are there any updates ?
   if ( strlen($updates) > 0) {
      $message = "<p>The following items have been updated: $updates</p>";
   };
};

// Which radio buttons are checked, build after post
$enableY = '';
$enableN = '';
if ( $enabled ) {
   $enableY = 'checked';
} else {
   $enableN = 'checked';
};

$dailyY = '';
$dailyN = '';
if ( $daily ) {
   $dailyY = 'checked';
} else {
   $dailyN = 'checked';
};

$tlsY = '';
$tlsN = '';
if ( $tls ) {
   $tlsY = 'checked';
} else {
   $tlsN = 'checked';
};

$loginY = '';
$loginN = '';
if ( $login ) {
   $loginY = 'checked';
} else {
   $loginN = 'checked';
};

$hideY = '';
$hideN = '';
if ( $hide ) {
   $hideY = 'checked';
} else {
   $hideN = 'checked';
};

// Build select options for interval
// Array of settings menu links
$week = array (
   6 => 'Sunday',
   0 => 'Monday',
   1 => 'Tuesday',
   2 => 'Wednesday',
   3 => 'Thursday',
   4 => 'Friday',
   5 => 'Saturday'
);
$day = '';
foreach ($week as $index => $value) {
   $select = '';
   if ($index == $on_day) { $select = 'selected';};
   $day .= "\t\t\t\t<option value=\"$index\" $select>$value</option>\n" ;
};

// Build site body here and put in var $contents
$contents = <<<EOD
   $message
   <form action="certreport.php" method="post">
   <table class="pagelet_table">
   <tr class="pglt_tb_hdr">
      <td>Certificate Report Settings</td>
      <td>Value</td>
   </tr>
   <tr class="odd">
      <td>Enable Cert Reports Email</td>
      <td>
         <input type="radio" name="enabled" value="1" $enableY>Yes
         <input type="radio" name="enabled" value="0" $enableN>No
      </td>
   </tr>
   <tr class="even">
      <td>Sender E-mail</td>
      <td>
         <input type="text" name="sender" size="25" maxlength="50"  value="$sender">
      </td>
   </tr>
   <tr class="odd">
      <td>Sender Title</td>
      <td>
         <input type="text" name="sender_title" size="25" maxlength="50"  value="$sender_title">
      </td>
   </tr>
   <tr class="even">
      <td>Recipients</td>
      <td>
         <input type="text" name="recipients" size="40" maxlength="150"  value="$recipients">
      </td>
   </tr>
   <tr class="odd">
      <td>Email Subject</td>
      <td>
         <input type="text" name="subject" size="30" maxlength="50"  value="$subject">
      </td>
   </tr>
   <tr class="even">
      <td>Hide Acknowledged Certs<br /> in E-mail</td>
      <td>
         <input type="radio" name="hide" value="1" $hideY>Yes
         <input type="radio" name="hide" value="0" $hideN>No
      </td>
   </tr>
   <tr class="odd">
      <td>Interval</td>
      <td>
         <input type="radio" name="daily" value="1" $dailyY>Daily<br />
         <input type="radio" name="daily" value="0" $dailyN>Weekly
         <select name="on_day">
$day
         </select>
      </td>
   </tr>
   <tr class="even">
      <td>Use SSL?</td>
      <td>
         <input type="radio" name="tls" value="1" $tlsY>Yes <br />
         <input type="radio" name="tls" value="0" $tlsN>No (Not recommended if login required)
      </td>
   </tr>
   <tr class="odd">
      <td>SMTP Server IP or FQDN</td>
      <td>
         <input type="text" name="server" size="15" maxlength="50"  value="$server">
      </td>
   </tr>
   <tr class="even">
      <td>Server Port</td>
      <td>
         <input type="text" name="port" size="6" maxlength="6"  value="$port">
      </td>
   </tr>
   <tr class="odd">
      <td>Does server require login?</td>
      <td>
         <input type="radio" name="login" value="1" $loginY>Yes <br />
         <input type="radio" name="login" value="0" $loginN>No
      </td>
   </tr>
   <tr class="even">
      <td>Email Login User</td>
      <td>
         <input type="text" name="login_user" size="25" maxlength="100"  value="$login_user">
      </td>
   </tr>
   <tr class="odd">
      <td>Email Login Password</td>
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
      <a href="settings.php">Settings</a> > Certificate Reports
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