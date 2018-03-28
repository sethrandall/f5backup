<?php
require_once '/opt/f5backup/ui/include/PestJSON.php';

$updates = '';
//Start DB writes
$dbcore->beginTransaction();
try {
   foreach ($_POST as $key => $value) {
      // Are there any blank fields ?
      if ($value == '') {
         throw new Exception("Inputs cannot be empty! - $key");
      };
      // check for whitespaces , but ignore title and subject
      $ignore = array('sender_title','subject');
      if ( preg_match('/ /',$value) && ! in_array($key,$ignore)) {
         throw new Exception("Inputs cannot contain spaces! - $key");
      };
   };

   // Update enabled
   if ( $enabled != $_POST['enabled'] ) {
      // Is this input valid
      if (! in_array( $_POST['enabled'],array(0,1)) ) {
         throw new Exception("Enabled input not valid"); 
      };
      
      // Write mode to DB
      $sth = $dbcore->prepare("UPDATE EMAIL SET SEND_REPORT = ? WHERE ID = '0'");
      $sth->bindParam(1,$_POST['enabled']); 
      $sth->execute();
      $updates .= '"Enabled" ';
   };
   
   //Update sender
   if ($_POST['sender'] != $sender ) {
      $sth = $dbcore->prepare("UPDATE EMAIL SET SENDER = ? WHERE ID = '0'");
      $sth->bindParam(1,$_POST['sender']); 
      $sth->execute();
      $updates .= '"Sender" ';
   };
   
   //Update sender title
   if ($_POST['sender_title'] != $sender_title ) {
      $sth = $dbcore->prepare("UPDATE EMAIL SET SENDER_TITLE = ? WHERE ID = '0'");
      $sth->bindParam(1,$_POST['sender_title']); 
      $sth->execute();
      $updates .= '"Sender Title" ';
   };

   //Update recipients
   if ($_POST['recipients'] != $recipients ) {
      $sth = $dbcore->prepare("UPDATE EMAIL SET TO_MAIL = ? WHERE ID = '0'");
      $sth->bindParam(1,$_POST['recipients']); 
      $sth->execute();
      $updates .= '"Recipients" ';
   };   

   //Update subject
   if ($_POST['subject'] != $subject ) {
      $sth = $dbcore->prepare("UPDATE EMAIL SET SUBJECT = ? WHERE ID = '0'");
      $sth->bindParam(1,$_POST['subject']); 
      $sth->execute();
      $updates .= '"Subject" ';
   };

   // Update hide ack
   if ( $hide != $_POST['hide'] ) {
      // Is this input valid
      if ( ! in_array($_POST['hide'],array(0,1) ) ) {
         throw new Exception("Hide input not valid"); 
      };
      
      // Write mode to DB
      $sth = $dbcore->prepare("UPDATE EMAIL SET HIDE_ACK = ? WHERE ID = '0'");
      $sth->bindParam(1,$_POST['hide']); 
      $sth->execute();
      $updates .= '"Hide Acked Certs" ';
   };
   
   // Update daily
   if ( $daily != $_POST['daily'] ) {
      // Is this input valid
      if ( ! in_array($_POST['daily'],array(0,1)) ) {
         throw new Exception("Daily input not valid"); 
      };
      
      // Write mode to DB
      $sth = $dbcore->prepare("UPDATE EMAIL SET DAILY = ? WHERE ID = '0'");
      $sth->bindParam(1,$_POST['daily']); 
      $sth->execute();
      $updates .= '"Report Interval" ';
   };

   // Update on day
   if ( $on_day != $_POST['on_day'] ) {
      // Is this input valid
      if ( ! in_array($_POST['on_day'], array(0,1,2,3,4,5,6) ) ) {
         throw new Exception("Weekly input not valid"); 
      };
      
      // Write mode to DB
      $sth = $dbcore->prepare("UPDATE EMAIL SET ON_DAY = ? WHERE ID = '0'");
      $sth->bindParam(1,$_POST['on_day']); 
      $sth->execute();
      $updates .= '"Report Interval Day" ';
   };

   // Update TLS
   if ( $tls != $_POST['tls'] ) {
      // Is this input valid
      if ( ! in_array($_POST['tls'], array(0,1)) ) {
         throw new Exception("TLS input not valid"); 
      };
      
      // Write mode to DB
      $sth = $dbcore->prepare("UPDATE EMAIL SET TLS = ? WHERE ID = '0'");
      $sth->bindParam(1,$_POST['tls']); 
      $sth->execute();
      $updates .= '"TLS" ';
   };
   
   //Update email server
   if ($_POST['server'] != $server ) {
      $sth = $dbcore->prepare("UPDATE EMAIL SET SERVER = ? WHERE ID = '0'");
      $sth->bindParam(1,$_POST['server']); 
      $sth->execute();
      $updates .= '"SMTP Server" ';
   };

   //Update email server port
   if ($_POST['port'] != $port ) {
      // Is port input numeric ?
      if ( ! is_numeric($_POST['port']) ) {
         throw new Exception("Server Port input not valid"); 
      };

      $sth = $dbcore->prepare("UPDATE EMAIL SET PORT = ? WHERE ID = '0'");
      $sth->bindParam(1,$_POST['port']); 
      $sth->execute();
      $updates .= '"Server Port" ';
   };
   
   // Update login   
   if ( $login != $_POST['login'] ) {
      // Is this input valid
      if ( ! in_array( $_POST['login'], array(0,1) ) ) {
         throw new Exception("Login input not valid"); 
      };
      
      // Write mode to DB
      $sth = $dbcore->prepare("UPDATE EMAIL SET LOGIN = ? WHERE ID = '0'");
      $sth->bindParam(1,$_POST['login']); 
      $sth->execute();
      $updates .= '"Login" ';
   };   
 
   //Update login user
   if ($_POST['login_user'] != $login_user ) {
      $sth = $dbcore->prepare("UPDATE EMAIL SET LOGIN_USER = ? WHERE ID = '0'");
      $sth->bindParam(1,$_POST['login_user']); 
      $sth->execute();
      $updates .= '"Login User" ';
   };
 
   // Update auth password
   if ( $_POST["password"] != "nochange" && strlen($_POST["password"]) > 0) {
      // do passwords match ?
      if ($_POST["password"] != $_POST["password2"]) {
         throw new Exception('Passwords do not match!'); 
      };
      
      //Connect to internal webservice
      $pest = new PestJSON('http://127.0.0.1:5380');
      $authhash = $pest->post('/api/v1.0/crypto/encrypt/', array('string' => $_POST["password"]) );
   
      // insert into DB
      $sth = $dbcore->prepare("UPDATE EMAIL SET LOGIN_PASS = ? WHERE ID = '0'");
      $sth->bindParam(1,$authhash['result']); 
      $sth->execute();

      $updates .= '"Login User Password" ';
   };
   
   //Reset vars to new (or keep as old) values
   // If exception happens on any item new values  
   // will get thrown out (never makes it here)
   $enabled = $_POST['enabled'];
   $sender = $_POST['sender'];
   $sender_title = $_POST['sender_title'];
   $recipients = $_POST['recipients'];
   $subject = $_POST['subject'];
   $hide = $_POST['hide'];
   $daily = $_POST['daily'];
   $on_day = $_POST['on_day'];
   $tls = $_POST['tls'];
   $server = $_POST['server'];
   $port = $_POST['port'];
   $login = $_POST['login'];
   $login_user = $_POST['login_user'];
    
   $dbcore->commit();
} catch (Exception $e) {
   if ( $dbcore->inTransaction ) { $dbcore->rollBack(); };
   $message = '<p class="error">Error: '.$e->getMessage().'</p>';
   $updates = '';
};
?>