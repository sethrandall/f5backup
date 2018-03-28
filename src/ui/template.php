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

include("include/session.php");
include("include/dbconnect.php");

// include common content
include("include/header.php");
include("include/menu.php");

// Build site body here and put in var $contents

// Page HTML
?>
	<div id="pagelet_title">
		<a href="template.php">Template</a>  
	</div>
	<div id="pagelet_body">
<?php
echo $contents;

echo "\t</div>\n";
include("include/footer.php");

// Close DB 
$dbh = null;
?>