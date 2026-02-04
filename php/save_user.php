<?php
error_reporting(E_ALL);
ini_set('display_errors', 1);

include "conexion.php";


$username = $_POST['Nombre_Usuario'] ?? '';
$password = $_POST['Contrasena_usuario'] ?? '';

if ($username === '' || $password === '') {
    die("Todos los campos son obligatorios");
}

$sql = "INSERT INTO usuarios (Nombre_Usuario, Contrasena_usuario) 
        VALUES ('$username', '$password')";

if ($conexion->query($sql) === TRUE) {
    header("Location: ../pags/login.html");
    exit();
} else {
    echo "Error al registrar: " . $conexion->error;
}
?>
<?php
include('../php/footer.php');
?>




