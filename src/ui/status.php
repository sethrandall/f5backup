<?php
include("include/session.php");
include("include/dbconnect.php");

// include common content
include("include/header.php");
include("include/menu.php");

// Get status details from internal web service
require_once '/opt/f5backup/ui/include/PestJSON.php';
function webstatus () {
	//Connect to internal webservice
	try {
		//Connect to internal webservice
		$pest = new PestJSON('http://127.0.0.1:5380');
		$result = $pest->get('/api/v1.0/status');  
	} catch (Exception $e) {
		$error_msg = $e->getMessage();
		$error_class = get_class($e);
		return "<p><font color=\"red\"><strong>Internal web service error: $error_class - $error_msg</strong></font></p>";
	};

	if ($result['status'] == 'ERROR' ) {
		$error_msg = $result['error'];
		return "<p><font color=\"red\"><strong>ERROR: $error_msg</strong></font></p>";
	} elseif ($result['status'] == 'GOOD' ) {
		return "<p><strong>System status: Online</strong></p>";
	};
};

?>
	<div id="pagelet_title">
		Status
	</div>
<?php
echo webstatus();
include("include/footer.php");
?>