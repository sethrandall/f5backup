<?php
require_once '/opt/f5backup/ui/include/PestJSON.php';

$updates = '';
//Start DB writes
if (!$dbh->beginTransaction()) {
   $message = '<p class="error">Could not start dbs transaction</p>';
} elseif (!$dbcore->beginTransaction()) {
      $message = '<p class="error">Could not start dbcore transaction</p>';
      $dbh->rollBack();
} else {
   try {
      // check for whitespaces 
      if ( preg_grep('/ /',$_POST) ) {
         throw new Exception("Inputs cannot contain spaces!");
      };
      
      // Are there any blank fields ?
      foreach ($_POST as $key => $value) {
         if ($value == '') {
            throw new Exception("Inputs cannot be empty! - $key");
         }
      };

      // Update mode
      if ( $mode != $_POST['mode'] ) {

         // Is this a valid a valid mode
         $modes = array('ad','local');
         if ( ! in_array($_POST['mode'], $modes) ) {
            $badmode = $_POST['mode'];
            throw new Exception("Mode \"$badmode\" is not valid"); 
         };
         
         // Write mode to DB
         $sth = $dbh->prepare("UPDATE AUTH SET MODE = ? WHERE ID = '0'");
         $sth->bindParam(1,$_POST['mode']); 
         $sth->execute();
         $updates .= '"Auth mode" ';
      };
      
      //Update domain
      if ($_POST['domain'] != $domain ) {
         $sth = $dbcore->prepare("UPDATE AUTH SET DOMAIN = ? WHERE ID = '0'");
         $sth->bindParam(1,$_POST['domain']); 
         $sth->execute();
         $updates .= '"Domain" ';
      };
      
      //Update auth user
      if ($_POST['user'] != $authacct ) {
         $sth = $dbcore->prepare("UPDATE AUTH SET AUTHACCT = ? WHERE ID = '0'");
         $sth->bindParam(1,$_POST['user']); 
         $sth->execute();
         $updates .= '"Auth User" ';
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
         $sth = $dbcore->prepare("UPDATE AUTH SET AUTHHASH = ? WHERE ID = '0'");
         $sth->bindParam(1,$authhash['result']); 
         $sth->execute();

         $updates .= '"Auth User Password" ';
      };

      // Update servers
      if ( $_POST['server1'] != $server1 || $_POST['server2'] != $server2 ) {
         // Server 1
         $sth = $dbcore->prepare("UPDATE AUTHSERVERS SET SERVER = ?, TIMEDOWN = 0 WHERE ID = '1'");
         $sth->bindParam(1,$_POST['server1']); 
         $sth->execute();
         
         // Server 2
         $sth = $dbcore->prepare("UPDATE AUTHSERVERS SET SERVER = ?, TIMEDOWN = 0 WHERE ID = '2'");
         $sth->bindParam(1,$_POST['server2']); 
         $sth->execute();
         
         $updates .= '"Servers" ';
      };

      // Update TLS
      $tls_chg = 0;
      if ($_POST['tls'] != $tls && is_numeric($_POST['tls'])) {
         $sth = $dbcore->prepare("UPDATE AUTHSERVERS SET TLS = ?");
         $sth->bindParam(1,$_POST['tls']); 
         $sth->execute();
         $updates .= '"TLS" ';
      };
      if ($_POST['tls'] == '1') {
         $tls_tmp = 1;
      } else {
         $tls_tmp = 0;
      };
      
      //Reset vars to new (or keep as old) values
      // If exception happens on any item new values  
      // will get thrown out (never makes it here)
      $mode = $_POST['mode'];
      $domain = $_POST['domain'];
      $authacct = $_POST['user'];
      $tls = $tls_tmp;
      $server1 = $_POST['server1'];
      $server2 = $_POST['server2'];   
      
      $dbh->commit();
      $dbcore->commit();
   } catch (Exception $e) {
      $dbh->rollBack();
      $dbcore->rollBack(); 
      $message = '<p class="error">Error: '.$e->getMessage().'</p>';
      $updates = '';
   };
};
?>