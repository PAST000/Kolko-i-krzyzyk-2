<?php
    session_start();
    $servername = "localhost";
    $username = "admin";
    $password = "ZAQ!2wsxCDE#";

    $conn = new mysqli($servername, $username, $password);
    if ($conn->connect_error) {
        die("Connection failed: " . $conn->connect_error);
    } 
?>