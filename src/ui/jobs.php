<?php
include("include/session.php");
include("include/dbconnect.php");
include("include/dbcore.php");


# include common content
include("include/header.php");
include("include/menu.php");

# Is this request for the main page?
$main_page = 1;
if (isset($_GET["id"])) { $main_page = 0 ;};

# Is URL requesting job info ?
if ( $main_page ) {
# If not present device list
	$contents = "<table class=\"pagelet_table\">\n";
	$contents .= "\t<tr class=\"pglt_tb_hdr\"><td>Date</td></tr>\n";

	# Get list of jobs from DB
	$sql = "SELECT ID,DATE,TIME FROM JOBS ORDER BY ID DESC";

	# loop through array to make device table
	$count = 1;
	foreach ($dbcore->query($sql) as $row) {
		$id = $row['ID'];
		$date = $row['DATE'];
		$time = date('Y-m-d H:i:s',$row['TIME']);
		$class = "even_ctr";
		if ($count & 1 ) {$class = "odd_ctr";};
		
		$contents .= "\t<tr class=\"$class\"><td><a href=\"jobs.php?id=$id\">$date</a></tr>\n";
		$count++;
	};
	$contents .= "</table> \n";

} else {
# If yes show details on job
	# Check ID query param, error page if not number
	$ID = $_GET["id"];
	if (is_numeric($ID)) {
		# Get jobs from DB
		$sth = $dbcore->prepare("SELECT DATE,TIME,ERRORS,COMPLETE,DEVICE_TOTAL,DEVICE_COMPLETE"
									.",DEVICE_W_ERRORS FROM JOBS WHERE ID = ?");
		$sth->bindParam(1,$ID); 
		$sth->execute();
		$row = $sth->fetch();

		$date = $row['DATE'];
		$time = date('Y-m-d H:i:s',$row['TIME']);
		$errors = $row['ERRORS'];
		$complete = "No";
		if ($row['COMPLETE'] == 1) {$complete = "Yes";};
		$device_w_errors = explode(' ', $row['DEVICE_W_ERRORS']);
		$log_file = htmlspecialchars(file_get_contents("../log/$date-backup.log"));

		// Get device ID for hyperlink to device w/ errors
		$error_list = '';
		foreach ($device_w_errors as $i) {
			$sth = $dbcore->prepare("SELECT NAME FROM DEVICES WHERE ID = '$i'");
			$sth->execute();
			$devname = $sth->fetchColumn();
			$error_list .= "<a href=\"/devices.php?page=device&id=$i\">$devname</a> &nbsp";
		};
		
		$contents = <<<EOD
	<table class="pagelet_table">
		<tr class="pglt_tb_hdr">
			<td>Time</td><td># of Errors</td><td>Job Complete</td><td>Devices /w Errors</td>
		</tr>
		<tr class="odd_ctr">
			<td>$time</td><td>$errors</td><td>$complete</td><td>$error_list</td>
		</tr>
	</table>
	<h3>Backup Job Log -</h3>
	<pre class="log">
$log_file
	</pre>\n
EOD;
	} else {
	# Error message if id is not number
		$contents = "<p><strong>Error:</strong> \"$ID\" is not a valid input</p>\n";
	};
};

# Page HTML
?>
	<div id="pagelet_title">
		<a href="jobs.php">Backup Jobs</a>
		<?php if ( ! $main_page ) {echo "> $date";} ?>
	</div>
	<div id="pagelet_body">
<?php
echo $contents;

echo "</div>";
include("include/footer.php");

/* Close DB  */
$dbcore = null;
?>