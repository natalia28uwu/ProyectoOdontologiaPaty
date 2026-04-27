<?php
header('Content-Type: application/json; charset=utf-8');
require __DIR__ . '/../conexion.php';

if ($_SERVER["REQUEST_METHOD"] == "POST") {
    $json = file_get_contents('php://input');
    $data = json_decode($json, true);

    if (!$data) {
        $data = $_POST;
    }

    $idCita = isset($data['idCita']) ? $data['idCita'] : '';
    $estado = isset($data['estado']) ? $data['estado'] : '';

    if (empty($idCita) || empty($estado)) {
        echo json_encode(['success' => false, 'error' => 'Faltan campos obligatorios.']);
        exit;
    }

    try {
        $stmt = $conn->prepare("UPDATE Citas SET Estado_citas = ? WHERE IdCita = ?");
        $stmt->execute([$estado, $idCita]);

        echo json_encode(['success' => true, 'message' => 'Estado de cita actualizado.']);
    } catch (PDOException $e) {
        echo json_encode(['success' => false, 'error' => 'Error BD: ' . $e->getMessage()]);
    }
} else {
    echo json_encode(['success' => false, 'error' => 'Método no permitido.']);
}
?>
