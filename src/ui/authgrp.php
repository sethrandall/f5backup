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

// Show me a specific group
if ( isset($_GET["id"]) ) {
	include ("include/authgrp_single.php");
} else {
	$title = "> Auth Groups";
	// Get auth groups
	$sth = $dbh->query("SELECT ID,NAME FROM AUTHGROUPS ORDER BY ORD");
	$sth->execute();
	$groups = $sth->fetchAll();

	// Build site body here and put in var $contents
	$contents = <<<EOD
	<form action="authgrpmod.php" method="get">
	<table class="pagelet_table">
	<tr class="pglt_tb_hdr">
			<td>
				<input type="checkbox" name="" value="" checked disabled="disabled">
			</td>
			<td>Group</td>
		<td>Order</td>
	</tr> \n
EOD;

	// 
	$count = 1;
	$grp_count = count($groups);
	foreach ($groups as $i) {
		$id = $i['ID'];
		$name = $i['NAME'];
		$class = "even_ctr";
		if ($count & 1 ) {$class = "odd_ctr";};
		
		// If not the last element in array add down button
		$button = '';
		if ($count != $grp_count ){
			$button .= <<<EOD
			<button type="submit" name="order[$id]" value="down">&#8681</button>\n
EOD;
		} ;
		
		// If not 1st element add up button
		if ($count > 1 ){
			$button .= <<<EOD
			<button type="submit" name="order[$id]" value="up">&#8679</button>\n
EOD;
		};
		
		$contents .= <<<EOD
	<tr class="$class">
		<td><input type="checkbox" name="id[]" value="$id"></td>
		<td><a href="authgrp.php?id=$id">$name</a></td>
		<td>
$button
		</td>
	</tr> \n
EOD;
		$count ++;
	};

	$contents .= <<<EOD
	</table>
	<input type="submit" name="page" value="Add">
	<input type="submit" name="page" value="Delete">
	</form> \n
EOD;

};
// Page HTML
?>
	<div id="pagelet_title">
		<a href="settings.php">Settings</a> <?php echo $title ?>
	</div>
	<div id="pagelet_body">
<?php
echo $contents;

echo "</div>";
include("include/footer.php");

// Close DB 
$dbh = null;
?>