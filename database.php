<?php 

// db connection
$db_handle = @mysql_pconnect("localhost", "username", "password"); 

// connection handle error
if(!$db_handle)
{ die("Database could not be reached at this time."); }


// select database
$db_select = @mysql_select_db("mailQueuerDB"); 

// database selection error
if(!$db_select)
{ die("Database could not be reached at this time."); }

?>