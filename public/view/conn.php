<!-- Connect to database function (no pass required) -->

<?php
        $dbhost = "localhost";
        $dbuser = "root";
        $dbpass = "";
        $dbname = "ucr_chatbot";
        $conn = new mysqli($dbhost, $dbuser, $dbpass,$dbname);
        if (!$conn) {
        echo 'Error; cannot connect to database';    
        }
?>