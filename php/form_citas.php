<?php

error_reporting(E_ALL);
ini_set('display_errors', 1);

$host = "127.0.0.1";
$db   = "SistemaOdontologia";
$user = "root";    
$pass = "";         
$charset = "utf8mb4";

try {
    $conn = new PDO(
        "mysql:host=127.0.0.1;dbname=SistemaOdontologia;charset=utf8mb4",
        "root",
        "",
        [
            PDO::ATTR_ERRMODE => PDO::ERRMODE_EXCEPTION,
           
        ]
    );
} 

catch (PDOException $e) {
    die(" Error de conexión: " . $e->getMessage());
}

if ($_SERVER["REQUEST_METHOD"] === "POST") {

    $cedula   = trim($_POST['cedula'] ?? '');
    $fecha    = $_POST['fecha'] ?? '';
    $doctor   = $_POST['doctor'] ?? '';
    $empleado = $_POST['empleado'] ?? '';

    // Validación básica
    if (!$cedula || !$fecha || !$doctor || !$empleado) {
        echo " Todos los campos son obligatorios.";
        exit;
    }

    // Verificar que el paciente exista
    $verificarPaciente = $conn->prepare(
        "SELECT 1 FROM Pacientes WHERE Cedula_paciente = ?"
    );
    $verificarPaciente->execute([$cedula]);

    if ($verificarPaciente->rowCount() === 0) {
        echo " El paciente no existe en el sistema.";
        exit;
    }

    // Verificar que NO tenga una cita el mismo día
    $verificarCita = $conn->prepare(
        "SELECT 1 FROM Citas 
         WHERE Cedula_citas = ? AND Fecha_citas = ?"
    );
    $verificarCita->execute([$cedula, $fecha]);

    if ($verificarCita->rowCount() > 0) {
        echo " El paciente ya tiene una cita para esa fecha.";
        exit;
    }

    // Insertar la nueva cita
    $insertar = $conn->prepare(
        "INSERT INTO Citas 
        (Cedula_citas, Fecha_citas, IdDoctor, IdEmpleado)
        VALUES (?, ?, ?, ?)"
    );

    $insertar->execute([
        $cedula,
        $fecha,
        $doctor,
        $empleado
    ]);

    echo " Cita solicitada y registrada correctamente.";
} 
// by: Ruben
?>
