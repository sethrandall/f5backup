<?php
// use Pest lib
require_once '/opt/f5backup/ui/include/PestJSON.php';

// Time check - verify time entered and convert to minutes
function time_check ($hr,$min) {
   if (! is_numeric($hr) || ! is_numeric($min) ) {
      throw new Exception('Time input must be numeric.');
   };
   if ($hr > 23) {throw new Exception('Hour can\'t exceed 24.'); };
   if ($min > 59) {throw new Exception('Minutes can\'t exceed 60.'); };
   return (intval($hr) * 60) + intval($min);
};

//Start DB writes
$dbcore->beginTransaction();
try {
   // UCS archive size
   if ($ucs != $_POST["ucs"]) {
      // Throw exception if not number
      if (! is_numeric($_POST["ucs"])) {throw new Exception('"UCS Archive Size" must be a number!'); };
      //Write to DB
      $sth = $dbcore->prepare("UPDATE BACKUP_SETTINGS_INT SET VALUE = ? WHERE NAME = 'UCS_ARCHIVE_SIZE'");
      $sth->bindParam(1,intval($_POST["ucs"])); 
      $sth->execute();
      // Incr post and append updates
      $post++;
      $updates .= '"UCS Archive Size" ';
   };

   // Log archive size
   if ($log != $_POST["log"]) {
      if (! is_numeric($_POST["log"])) {throw new Exception('"Log Archive Size" must be a number!'); };
      $sth = $dbcore->prepare("UPDATE BACKUP_SETTINGS_INT SET VALUE = ? WHERE NAME = 'LOG_ARCHIVE_SIZE'");
      $sth->bindParam(1,intval($_POST["log"])); 
      $sth->execute();
      $post++;
      $updates .= '"Log Archive Size" ';
   };

   // Backup time
   $time_new = time_check($_POST["time_hr"],$_POST["time_min"]);
   if ($time != $time_new){
      $sth = $dbcore->prepare("UPDATE BACKUP_SETTINGS_INT SET VALUE = ? WHERE NAME = 'BACKUP_TIME'");
      $sth->bindParam(1,$time_new); 
      $sth->execute();
      $post++;
      $updates .= '"Backup Time" ';
   };

   // Update backup user
   if ($user != $_POST["user"]) {
      $sth = $dbcore->prepare("UPDATE BACKUP_USER SET NAME = ? WHERE ID = '0'");
      $sth->bindParam(1,$_POST["user"]); 
      $sth->execute();
      $post++;
      $updates .= '"Backup User Name" ';
   };

   // Update password
   if ( $_POST["password"] != "nochange" ) {
      // do passwords match ?
      if ($_POST["password"] != $_POST["password2"]) {
         throw new Exception('"Passwords do not match!'); 
      };
      
      // Get encrypted password string
      $pest = new PestJSON('http://127.0.0.1:5380');
      $data = array('string' => $_POST["password"] );
      $pass = $pest->post('/api/v1.0/crypto/encrypt/',$data);
      $pass = $pass['result'];

      // insert into DB
      $sth = $dbcore->prepare("UPDATE BACKUP_USER SET PASS = ? WHERE ID = '0'");
      $sth->bindParam(1,$pass); 
      $sth->execute();

      $post++;
      $updates .= '"Backup User Password"';
   };

   //Reset vars to new (or keep as old) values
   // If exception happens on any item new values  
   // will get thrown out (never makes it here)
   $ucs = intval($_POST["ucs"]);
   $log = intval($_POST["log"]);
   $time_min = intval($_POST["time_min"]); 
   $time_hr = intval($_POST["time_hr"]);
   $user = $_POST["user" ];

   $dbcore->commit();
} catch (Exception $e) {
   if ( $dbcore->inTransaction ) { $dbcore->rollBack(); };
   $error = $e->getMessage();
};
?>