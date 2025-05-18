<?php
// config/database . php
if (session_status() === PHP_SESSION_NONE) {
    session_start();
}

define('DB_HOST', 'localhost');
define('DB_USER', 'root');
define('DB_PASS', '');
define('DB_NAME', 'e_commerce');

$conn = new mysqli(DB_HOST, DB_USER, DB_PASS, DB_NAME);

if ($conn->connect_error) {
    die("Connection failed: " . $conn->connect_error);
}

$conn->set_charset("utf8mb4");

function dbSanitizeInput($conn, $data) {
    return mysqli_real_escape_string($conn, htmlspecialchars(trim($data)));
}

function dbExecuteQuery($conn, $sql) {
    $result = $conn->query($sql);
    
    if (!$result) {
        die("Query failed: " . $conn->error);
    }
    
    return $result;
}

function getRow($conn, $sql) {
    $result = dbExecuteQuery($conn, $sql);
    return $result->fetch_assoc();
}

function getRows($conn, $sql) {
    $result = dbExecuteQuery($conn, $sql);
    $rows = array();
    
    while ($row = $result->fetch_assoc()) {
        $rows[] = $row;
    }
    
    return $rows;
}

function insertData($conn, $sql) {
    if ($conn->query($sql) === TRUE) {
        return $conn->insert_id;
    } else {
        die("Error: " . $sql . "<br>" . $conn->error);
    }
}

function updateData($conn, $sql) {
    if ($conn->query($sql) === TRUE) {
        return true;
    } else {
        die("Error: " . $sql . "<br>" . $conn->error);
    }
}

function deleteData($conn, $sql) {
    if ($conn->query($sql) === TRUE) {
        return true;
    } else {
        die("Error: " . $sql . "<br>" . $conn->error);
    }
}
?>