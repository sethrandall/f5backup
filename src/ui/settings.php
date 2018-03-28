<?php
include("include/session.php");
include("include/dbconnect.php");

// include common content
include("include/header.php");
include("include/menu.php");

// Page HTML
$contents = <<<EOD
   <table class="pagelet_table">
      <tr class="pglt_tb_hdr">
         <td>Settings</td>
      </tr>
EOD;

// Array of settings menu links
$menu = array (
   array('General','generalsettings.php'),
   array('Users','users.php'),
   array('Authentication','auth.php'),
   array('Auth Groups','authgrp.php'),
   array('Backups','backupsettings.php'),
   array('Certificate Report','certreport.php'),
   array('Admin Password','admin.php')
);


//Build HTML menu
$count = 1;
foreach ($menu as $i) {
   $class = '';
   $link = $i[1];
   $name = $i[0];
   $class = "even";
   if ($count & 1 ) {$class = "odd";};
   $count ++;
   $contents .= <<<EOD
      <tr class="$class">
         <td><a href="$link">$name</a></td>
      </tr>\n
EOD;

};
?>
   <div id="pagelet_title">
      <a href="settings.php">Settings</a> 
   </div>
   <div id="pagelet_body">
<?php
$contents .= "   </table>\n";

echo $contents;

echo "\t</div>\n";
include("include/footer.php");

// Close DB 
$dbh = null;
?>