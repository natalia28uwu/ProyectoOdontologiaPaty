<?php
error_reporting(E_ALL);
ini_set('display_errors', 1);

include "conexion.php";

if ($_SERVER["REQUEST_METHOD"] == "POST") {

$nombre = $_POST['Nombre'];
$apellido = $_POST['Apellido'];
$correo = $_POST['Correo'];
$telefono = $_POST['Telefono'];
$contrasena = $_POST['Contrasena'];

$sql = "INSERT INTO Usuarios (Nombre, Apellido, Correo, Contrasena, Telefono)
VALUES (?, ?, ?, ?, ?)";

$stmt = $conn->prepare($sql);
$stmt->execute([$nombre,$apellido,$correo,$contrasena,$telefono]);

echo "Usuario registrado correctamente";

}
?>