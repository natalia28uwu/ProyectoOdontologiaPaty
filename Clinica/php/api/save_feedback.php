<?php
header('Content-Type: application/json; charset=utf-8');
include_once "../conexion.php";

if (!isset($conn)) {
    echo json_encode(['success' => false, 'error' => 'Error de conexión']);
    exit();
}

$cedula = isset($_POST['cedula']) ? trim($_POST['cedula']) : '';
$sentimiento = isset($_POST['sentimiento']) ? trim($_POST['sentimiento']) : '';

if (empty($cedula) || empty($sentimiento)) {
    echo json_encode(['success' => false, 'error' => 'Datos incompletos']);
    exit();
}

try {
    $stmt = $conn->prepare("INSERT INTO CRM_Feedback (Cedula_paciente, Sentimiento) VALUES (?, ?)");
    $stmt->execute([$cedula, $sentimiento]);
    
    echo json_encode(['success' => true]);
} catch (Exception $e) {
    echo json_encode(['success' => false, 'error' => $e->getMessage()]);
}
?>
