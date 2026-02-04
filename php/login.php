<?php
error_reporting(E_ALL);
ini_set('display_errors', 1);

include "conexion.php";

$usuario = $_POST['Nombre_Usuario'] ?? '';
$contrasena = $_POST['Contrasena_usuario'] ?? '';

$sql = "SELECT * FROM usuarios WHERE Nombre_Usuario = '$usuario'";
$resultado = $conexion->query($sql);

if ($resultado && $resultado->num_rows > 0) {

    $fila = $resultado->fetch_assoc();

    if ($contrasena === $fila['Contrasena_usuario']) {

        header("Location: ../pags/form.html");
        exit();

    } else {
        echo "Contraseña incorrecta";
    }

} else {
    echo "Usuario no encontrado";
}
?>

<?php
include('../php/footer.php');
?>




