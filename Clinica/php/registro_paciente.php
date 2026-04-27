<?php
error_reporting(E_ALL);
ini_set('display_errors', 1);

include "conexion.php";

if ($_SERVER["REQUEST_METHOD"] == "POST") {

    $cedula = $_POST['Cedula_paciente'] ?? '';
    $nombre = $_POST['Nombre_paciente'] ?? '';
    $apellido = $_POST['Apellido_paciente'] ?? '';
    $fechaNac = $_POST['FechaNac_paciente'] ?? '';
    $telefono = $_POST['Telefono_paciente'] ?? '';
    $correo = $_POST['Correo_paciente'] ?? '';
    $contrasena = $_POST['Contrasena'] ?? '';
    $condicionSalud = $_POST['CondicionSalud'] ?? 'Ninguna';
    
    // IdSeguro es opcional
    $idSeguro = !empty($_POST['IdSeguro']) ? $_POST['IdSeguro'] : null;

    if(empty($cedula) || empty($nombre) || empty($apellido)) {
        die("Error: Cédula, Nombre y Apellido son obligatorios.");
    }

    try {
        // Verificar si la cédula ya existe
        $check = $conn->prepare("SELECT COUNT(*) FROM Pacientes WHERE Cedula_paciente = ?");
        $check->execute([$cedula]);
        if ($check->fetchColumn() > 0) {
            die("<script>alert('Error: Ya existe un paciente registrado con esta cédula.'); window.history.back();</script>");
        }

        $sqlPaciente = "INSERT INTO Pacientes (Cedula_paciente, Nombre_paciente, Apellido_paciente, FechaNac_paciente, Telefono_paciente, Correo_paciente, CondicionSalud, IdSeguro)
                VALUES (?, ?, ?, ?, ?, ?, ?, ?)";
        
        $stmtPaciente = $conn->prepare($sqlPaciente);
        $stmtPaciente->execute([$cedula, $nombre, $apellido, $fechaNac, $telefono, $correo, $condicionSalud, $idSeguro]);

        // Insertar en la tabla Usuarios para permitir el login (IdRol 1604 = Paciente)
        $sqlUsuario = "INSERT INTO Usuarios (Nombre, Apellido, Correo, Contrasena, Telefono, IdRol)
                       VALUES (?, ?, ?, ?, ?, 1604)";
        $stmtUsuario = $conn->prepare($sqlUsuario);
        $stmtUsuario->execute([$nombre, $apellido, $correo, $contrasena, $telefono]);
        
        echo "<script>
                alert('¡Registro exitoso! Ahora puedes iniciar sesión con tu correo y contraseña.');
                window.location.href = '../pags/login.html';
              </script>";

    } catch(PDOException $e) {
        die("Error al registrar paciente: " . $e->getMessage());
    }
} else {
    header("Location: ../pags/registro_paciente.html");
    exit();
}
?>
