<?php

$title = "Add Auth Group";

// If post then update DB
if ($_SERVER['REQUEST_METHOD'] == "POST" && $_POST["change"] == "Add") {

   // Build ID-ORD array
   foreach ($groups as $i) {
      $id_ord[ $i['ID']] = $i['ORD'];
   };

   $dbh->beginTransaction();
   try {
      // check for whitespaces 
      if ( preg_match('/ /',$_POST['name']) ) {
         throw new Exception("Name cannot contain spaces!");
      };
      
      // Are there any blank fields ?
      foreach ($_POST as $key => $value) {
         if ($value == '') {
            throw new Exception("Values cannot be empty! - $key");
         }
      };

      // Validate that role ID is valid
      if (! is_numeric($_POST["role"]) || ! array_key_exists($_POST["role"],$rolearray) ) {
         throw new Exception('Invalid role ID'); 
      };

      // Check for duplicates
      foreach ($groups as $i) {
         if ( strtolower($i['NAME']) == strtolower($_POST["name"]) ) {
         throw new Exception("Group name already exists!");
         };
      };

      // Determine order of group
      switch ( $_POST["order"] ) {
         case "first":
            $order = 0.5;
            break;
         case "before":
            $order = $id_ord[$_POST["order_before"]] - .5;
            break;
         case "last";
            $order = count($groups) + 1;
            break;
         default:
            throw new Exception("Invalid Order!");
            break;
      };

      $sth = $dbh->prepare("INSERT INTO AUTHGROUPS ('ORD','NAME','STRING','ROLE') 
                           VALUES(:order,:name,:string,:role)");
      $sth->bindValue(':order',$order); 
      $sth->bindValue(':name',$_POST["name"]); 
      $sth->bindValue(':string',$_POST["string"]); 
      $sth->bindValue(':role',$_POST["role"]); 
      $sth->execute();
      $dbh->commit();

      $contents = "<p>Created auth group: ". $_POST["name"] ."</p>";
      grp_reorder ($dbh);
   
   } catch (Exception $e) {
      if ( $dbh->inTransaction ) { $dbh->rollBack(); };
      $contents = '<p class="error">Error: '.$e->getMessage().'</p>';
   };

} else {

   //remove first element of group array for "before" list
   $groups = array_slice($groups,1); 
   
   $group_select = '';
   foreach ($groups as $i) {
      $id = $i['ID'];
      $name = $i['NAME'];
      $group_select .= "<option value=\"$id\">$name</option>";
   };



   $roleselect = build_select($rolearray,$role);
   $contents = <<<EOD
      <form action="authgrpmod.php?page=Add" method="post">
      <table class="pagelet_table">
         <tr class="pglt_tb_hdr"><td colspan="2">Add New Auth Group</td></tr>
         <tr class="odd">
            <td>Group Name</td>
            <td><input type="text" name="name" class="input" maxlength="50"></td>
         </tr>
         <tr class="even">
            <td>Group String</td>
            <td>
               <input type="text" name="string" size="70" maxlength="250">
            </td>
         </tr>
         <tr class="odd">
            <td>Group Order</td>
            <td>
               <input type="radio" name="order" value="first" checked>First</input> <br />
               <input type="radio" name="order" value="before">Before</input>
               <select name="order_before"> 
                  $group_select
               </select>
               <br />
               <input type="radio" name="order" value="last">Last</input>  <br />
            </td>
         </tr>
         <tr class="even">
            <td>Group Role</td>
            <td>
               <select name="role">
                  $roleselect
               </select>
            </td>
         </tr>
      </table>
      <input type="submit" name="change" value="Add">
      </form>\n
EOD;
};

?>