<?php
$host = 'localhost';
$dbname = 'tracking_pedidos';
$username = 'root';  // Cambia esto por tu usuario de MySQL
$password = '123456';       // Cambia esto por tu contraseña de MySQL

$conn = new mysqli($host, $username, $password, $dbname);

if ($conn->connect_error) {
    die("Error de conexión: " . $conn->connect_error);
}

?>