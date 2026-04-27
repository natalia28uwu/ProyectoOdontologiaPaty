<?php
session_start();
ini_set('display_errors', 0); // Desactivado para producción, usa logs en su lugar
error_reporting(E_ALL);
include "conexion.php";

$correo = isset($_POST['Correo']) ? trim($_POST['Correo']) : '';
$contrasena_ingresada = isset($_POST['Contrasena']) ? trim($_POST['Contrasena']) : '';

if (empty($correo) || empty($contrasena_ingresada)) {
    echo "<script>alert('Por favor, rellena todos los campos'); window.history.back();</script>";
    exit();
}

try {
    // Consulta preparada para evitar SQL Injection
    $sql = "SELECT IdUsuario, Nombre, Correo, Contrasena, IdRol, IdEmpleado FROM Usuarios WHERE Correo = ?";
    $stmt = $conn->prepare($sql);
    $stmt->execute([$correo]);
    $usuario = $stmt->fetch(PDO::FETCH_ASSOC);

    if ($usuario) {
        $contrasena_db = trim($usuario['Contrasena']);
        $id_rol = (int)$usuario['IdRol'];

        // Verificación de contraseña
        if ($contrasena_ingresada === $contrasena_db) {
            // Regenerar ID de sesión por seguridad
            session_regenerate_id(true);

            // Guardar datos esenciales en la sesión
            $_SESSION['usuario_id'] = $usuario['IdUsuario'];
            $_SESSION['usuario_nombre'] = $usuario['Nombre'];
            $_SESSION['rol'] = $id_rol;
            $_SESSION['id_empleado'] = $usuario['IdEmpleado'];

            // Restringir acceso: Admin y Paty van al Dashboard Administrativo
            $correo_lower = strtolower(trim($usuario['Correo']));
            if ($correo_lower === 'admin@gmail.com' || $correo_lower === 'paty@mail.com' || $id_rol === 1600) {
                header("Location: ../pags/home.html");
                exit();
            } elseif ($id_rol === 1605) {
                // Es Natalia (Secretaria)
                $nombre_lower = strtolower(trim($usuario['Nombre']));
                if (stripos($nombre_lower, 'natalia') !== false) {
                    header("Location: ../pags/secretaria_dash.html");
                    exit();
                } else {
                    // Si por algún error otro usuario tiene rol 1605 pero no es Natalia, lo mandamos al dashboard general o error
                    echo "<script>alert('Acceso restringido: Este panel es exclusivo para Natalia.'); window.history.back();</script>";
                    exit();
                }
            } elseif ($id_rol === 1604) {
                // Es un paciente confirmado por su Rol
                $stmtP = $conn->prepare("SELECT Cedula_paciente FROM Pacientes WHERE Correo_paciente = ?");
                $stmtP->execute([$correo]);
                $paciente = $stmtP->fetch(PDO::FETCH_ASSOC);
                
                if ($paciente) {
                    $_SESSION['cedula_paciente'] = $paciente['Cedula_paciente'];
                    header("Location: dashboard.php");
                    exit();
                } else {
                    echo "<script>alert('Error: No se encontró registro clínico para este paciente.'); window.history.back();</script>";
                    exit();
                }
            } else {
                // Otros roles o casos no contemplados
                header("Location: ../Index.html");
                exit();
            }

        } else {
            echo "<script>alert('Contraseña incorrecta'); window.history.back();</script>";
            exit();
        }
    } else {
        echo "<script>alert('El correo proporcionado no está registrado'); window.history.back();</script>";
        exit();
    }

} catch (PDOException $e) {
    // Error genérico para el usuario, log detallado para el admin
    error_log("Error de login: " . $e->getMessage());
    echo "Hubo un error interno. Por favor, inténtelo más tarde.";
}
?>