<?php
    $dbhost = getenv("DB_HOST");
    $dbuser = getenv("DB_USER");
    $dbpass = getenv("DB_PASSWORD");
    $dbname = getenv("DB_NAME");
    $conn = null;

    if ($dbhost == "" || $dbuser == "" || $dbpass == "" || $dbname == "") {
        echo "Database configuration is missing. Please check your .env file.";
    } else {
        $conn = new mysqli($dbhost, $dbuser, $dbpass, $dbname);
        if ($conn->connect_error) {
            die("Connection failed: " . $conn->connect_error);
        }
    }
?>
