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

$title = '';
$contents = '';

// Get auth groups for order list
$sth = $dbh->query("SELECT ID,ORD,NAME FROM AUTHGROUPS ORDER BY ORD");
$sth->execute();
$groups = $sth->fetchAll();

// Reorder group function
function grp_reorder ($db) {
   // Get new group list
   $sth = $db->query("SELECT ID,ORD,NAME FROM AUTHGROUPS ORDER BY ORD");
   $sth->execute();
   $groups = $sth->fetchAll();

   $count = 1;
   $db->beginTransaction();
   // Loop through all devices and reorder
   foreach ($groups as $i) {
      $sth = $db->prepare("UPDATE AUTHGROUPS SET ORD = ? WHERE ID = ?");
      $sth->bindParam(1,$count); 
      $sth->bindParam(2,$i['ID']); 
      $sth->execute();
      $count ++;
   };
   $db->commit();
};

//Which group mod page is this ?
if ( isset($_GET['page'])) {
   if ( $_GET["page"] == "Delete") {
      include ("include/authgrp_delete.php");
   } elseif ( $_GET["page"] == "Add") {
      include ("include/authgrp_add.php");
   };
} elseif ( isset($_GET['order'])) {
   include ("include/authgrp_order.php");
} else {
   // if none, redirect to devices page
   header("Location: /authgrp.php"); 
   die;
};

?>
   <div id="pagelet_title">
      <a href="settings.php">Settings</a> > 
      <a href="authgrp.php"> Auth Groups</a> >
      <?php echo  " $title" ?>
   </div>
<?php

echo $contents;

include("include/footer.php");

// Close DB 
$dbh = null;
?>