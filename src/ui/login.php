<?php
session_start();
include("include/dbconnect.php");
include("include/functions.php");

// login post
if ($_SERVER['REQUEST_METHOD'] == "POST") {
   // admin login
   if ( strtolower($_POST["username"]) == "admin" ) {
      // Connect to DB
      $sth = $dbh->prepare("SELECT HASH FROM ADMIN WHERE ID = 1");
      $sth->execute();
      $db_hash = $sth->fetchColumn();

      // Hashed input password
      $post_hash = crypt($_POST["password"], $db_hash);
 
      // Is this a valid login?
      if ($db_hash == $post_hash) {
         $login = 1;
         $role = 1;
      } else {
         $error = "Login Failed.";
      };

   } else {
   // other user login
      // What auth mode is set ?
      $sth = $dbh->prepare("SELECT MODE FROM AUTH");
      $sth->execute();
      $mode = $sth->fetchColumn();

      switch ($mode) {
         case "ad":
         // AD auth mode
            // Make ad call to API
            require_once '/opt/f5backup/ui/include/PestJSON.php';
            $data = array('user' => $_POST["username"],
                        'passwd' => $_POST["password"] );
            $pest = new PestJSON('http://127.0.0.1:5380');
            $auth = $pest->post('/api/v1.0/adauth/authenticate/', $data);
            
            // Valid login
            if ( $auth['result'] == "True" ) {
               // Get auth groups
               $sth = $dbh->query("SELECT STRING,ROLE FROM AUTHGROUPS ORDER BY ORD");
               $sth->execute();
               $groups = $sth->fetchAll();

               //lowercase memberOf array
               $membership = array_map("strtolower",$auth['memberOf']);

               //loop through list until match is found
               foreach ($groups as $i) {
                  $string = strtolower($i['STRING']);
                  // If auth group is in user's group membership allow login
                  if ( preg_grep("/$string/",$membership) ) {
                     $login = 1;
                     $role = $i['ROLE'];
                     break;
                  };
               };
               
               // If not in a group then give error
               if ( ! isset($login) ) {$error = "Login Failed.";};
            } else {
               $error = "Login Failed.";
            };
            break;
         default:
            // Connect to DB
            try {
               // If any bad chars in post contents dont allow DB lookup
               if ( bad_chars($_POST["username"]) || bad_chars($_POST["password"]) ) {
                  throw new Exception("Input will not accept those special characters!");
               };
               
               // is user valid ??
               $sth = $dbh->prepare("SELECT count(HASH) FROM USERS WHERE NAME = ?");
               $sth->bindParam(1,$_POST["username"]); 
               $sth->execute();
               $user_valid = $sth->fetchColumn();
               $sth = null;
               
               if ( $user_valid ) {
               // if user is valid get hash
                  $sth = $dbh->prepare("SELECT HASH,ROLE FROM USERS WHERE NAME = ?");
                  $sth->bindParam(1,$_POST["username"]); 
                  $sth->execute();
                  $row = $sth->fetch();
                  $db_hash = $row['HASH'];
                  $role = $row['ROLE'];
               } else {
               // otherwise null hash
                  $db_hash = "null";
               };
               // Hashed input password
               $post_hash = crypt($_POST["password"], $db_hash);
               
               // Is this a valid login?
               if ($db_hash == $post_hash) {
                  $login = 1;
               } else {
                  $error = "Login Failed.";
               };
               
            } catch (Exception $e) {
               $error = $e->getMessage();
            };
      };
   };
};

// If user POST  
if (isset($login) ) {
   // Get timeout value from DB
   $sth = $dbh->prepare("SELECT VALUE FROM SETTINGS_INT WHERE NAME = 'timeout'");
   $sth->execute();
   $timeout = $sth->fetchColumn();

   // Set session vars
   session_regenerate_id(true);
   $_SESSION['clientip'] = $_SERVER['REMOTE_ADDR'];
   $_SESSION['active'] = 1;
   $_SESSION['user'] = $_POST["username"];
   $_SESSION['time'] = time();
   $_SESSION['timeout'] = $timeout;
   $_SESSION['role'] = $role;
   $location = "https://".$_SERVER['HTTP_HOST'].urldecode($_GET["page"]);
   header("Location: $location");
   die;
} else {
// Otherwise give them the login page
   // Get page that send to login and add as param of form action url
   $URL = "";
   if (isset($_GET["page"])) {
     $URL="?page=".urlencode($_GET["page"]);
   };

   
   // Get MOTD from DB
   $sth = $dbh->prepare("SELECT MOTD FROM MOTD WHERE ID = 1");
   $sth->execute();
   $motd = $sth->fetchColumn();


?>
<html>
<head>
<link rel="stylesheet" type="text/css" href="css/login.css">
</head>
<body>
<div id="login">
   <div id="header">
      <div id="title">Config Backup for F5</div>
      <div id="title2">Config Backups. Your way. <sup>( ! TM)</sup></div>
   </div>
   <div id="body">
      <div id="form">
         <form action="login.php<?php echo $URL?>" method="post">
         <p>Username<br />
         <input type="text" name="username" class="input" autocomplete="off">
         </p>
         <p>Password<br />
         <input type="password" name="password" class="input">
         </p>
         <input type="submit" name="submit" value="Log In">
         </form>
<?php // Error messages
if (isset($error)) { 
?>
         <p id="error"><?php echo  $error ?></p>
<?php } ?>
      </div>
      <div id="message">
         <div style="padding-left:10px"><pre><?php echo  $motd ?></pre></div>
      </div>
   </div>
</div>
</body>
</html>
<?php
};

// Close DB
$dbh = null;
?>