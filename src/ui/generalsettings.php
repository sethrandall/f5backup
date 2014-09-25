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

// Get settings from DB
$dbh->beginTransaction();

// Get timeout from DB
$sth = $dbh->prepare("SELECT VALUE FROM SETTINGS_INT WHERE NAME = 'timeout'");
$sth->execute();
$timeout = $sth->fetchColumn();

// Get MOTD from DB
$sth = $dbh->prepare("SELECT MOTD FROM MOTD WHERE ID = 1");
$sth->execute();
$motd = $sth->fetchColumn();
$sth = null;
// Commit lookups
$dbh->commit();

// Update values for post
$post = 0;
$updates = '';
$contents = '';

if ($_SERVER['REQUEST_METHOD'] == "POST") {

   $dbh->beginTransaction();
   try {
      
      // Validate timeout is a number
      if ( ! is_numeric($_POST["timeout"]) ) {
         throw new Exception("Timeout is not a number!");
      };
      
      // Timeout update
      if ( $_POST["timeout"] != $timeout ) {
      // Update timeout if value is new
         $post++;
         $updates .= "Timeout ";
         $timeout = $_POST["timeout"];
         
         // Write new timeout to DB
         $sth = $dbh->prepare("UPDATE SETTINGS_INT SET VALUE = ? WHERE NAME = 'timeout'");
         $sth->bindParam(1,$_POST["timeout"]); 
         $sth->execute();
      };
      
      // MOTD update
      $post_motd = strip_tags( str_replace("\r", '',$_POST["motd"]) ); // Remove CR
      if ( $post_motd != $motd ) {
      // Update motd if value is new
         $post++;
         $updates .= "Login Banner ";
         $motd = $post_motd;
         
         $sth = $dbh->prepare("UPDATE MOTD SET MOTD = ? WHERE ID = 1");
         $sth->bindParam(1,$post_motd); 
         $sth->execute();
      };   

      $dbh->commit();
   } catch (Exception $e) {
      if ( $dbh->inTransaction ) { $dbh->rollBack(); };
      $contents = '<p class="error">Error: '.$e->getMessage().'</p>';
   };
};

if ( $post > 0 ) { $contents .= "<p>The following items have been updated: $updates</p>"; };
if ($_SERVER['REQUEST_METHOD'] == "POST" && $post == 0) {
   $contents .= "<p>No settings where updated</p>";
};
$contents .= <<<EOD
   <form action="generalsettings.php" method="post">
   <table class="pagelet_table">
      <tr class="pglt_tb_hdr">
         <td>Setting</td>
         <td>Value</td>
      </tr>
      <tr class="odd">
         <td>Timeout</td>
         <td>
            <input type="text" name="timeout" size="10" maxlength="5" value="$timeout">
         </td>
      </tr>
      <tr class="even">
         <td>Login Banner</td>
         <td>
            <textarea cols="35" rows="10" name="motd">$motd</textarea>
         </td>
      </tr>
   </table>
   <input type="submit" name="submit" value="Update">
   </form>
EOD;
// Close DB connection
$dbh = null;

$title = "System";
$title2 = "<a href=\"generalsettings.php\">General Settings</a>";

// Page HTML
include("include/framehtml.php");
?>
