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
$permissions = array(1,2);

include("include/session.php");
include("include/dbconnect.php");
include("include/dbcore.php");

// include common content
include("include/header.php");
include("include/menu.php");

//Which device mod page is this ?
$contents = '';
switch ( $_GET["page"] ) {
   case "Delete" :
      include ("include/device_delete.php");
      break;
   case "Add" :
      include ("include/device_add.php");
      break;
   default:
      // if none, redirect to devices page
      header("Location: /devices.php"); 
      die;
};

// Page HTML
?>
   <div id="pagelet_title">
      <a href="devices.php">F5 Devices</a> <?php if ( isset($title) ) {echo "> $title";} ?> 
   </div>
   <div id="pagelet_body">
<?php
echo $contents;

echo "</div>";
include("include/footer.php");

/* Close DB  */
$dbh = null;
$dbcore = null;

?>