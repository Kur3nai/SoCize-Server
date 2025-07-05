<?php

$db_hostname = "localhost"; // Replace with your database hostname, e.g. 'localhost' or '127.0.0.1'
$db_username = "root"; // Replace with your database username, e.g. 'root'
$db_password = "actPs123!"; // Replace with your database password, e.g. '12345678'
$db_database = "socize_filestorage"; // Replace with your database's database name, e.g. 'hcq_db'

// Used for argument unpacking for functions (the '...' keyword), for example : mysqli_connect(...$db_credentials)
$db_credentials = [$db_hostname, $db_username, $db_password, $db_database];

?>