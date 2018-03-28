<?php
session_start();
if(!(isset( $_SESSION['active']))) {
//If user does not have active session then logout user
   // Is this a 404 from an un-auth session? 
   if ( isset($_SERVER['REDIRECT_STATUS']) ) {
      switch ($_SERVER['REDIRECT_STATUS']) {
         case "404" :
            echo "<html>\n<h1>Error 404</h1>\n<p>The page cannot be found.</p>\n</html>";
            die;
      };
   };   
   $location = "https://".$_SERVER['HTTP_HOST']."/login.php?page=".urlencode($_SERVER['REQUEST_URI']);
   header("Location: $location");
   die();
} elseif ( $_SESSION['clientip'] != $_SERVER['REMOTE_ADDR'] ) {
// If users IP has changed
   header("Location: /logout.php");
} else {
// Check if the user is timed out
   if ( (time() - $_SESSION['time']) > $_SESSION['timeout'] ) { 
   // If current time - session time is > timeout, logout user   
      header("Location: /logout.php?page=".urlencode($_SERVER['REQUEST_URI'])); 
   } else {
   // If not reset session time
      $_SESSION['time'] = time();
   };
};
// Check permissions for RBAC
if ( isset($permissions) ) {
   // Search permissions array for users level,
   if ( ! in_array($_SESSION['role'],$permissions) ) {
      // Redirect to index.php with error code if no match
      $page = urlencode(strtok($_SERVER["REQUEST_URI"],'?'));
      header("Location: /index.php?error=403&page=$page");
      die;
   };
};

date_default_timezone_set(@date_default_timezone_get());
?>
