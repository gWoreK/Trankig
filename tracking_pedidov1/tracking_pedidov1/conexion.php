<?php
$host = 'localhost';
$dbname = 'tracking_pedidos';
$username = 'tracking_pagos';  
$password = 'tracking_pagos';       

$conn = new mysqli($host, $username, $password, $dbname);

if ($conn->connect_error) {
    die("Error de conexión: " . $conn->connect_error);
}

?>