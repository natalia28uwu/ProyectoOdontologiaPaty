<?php
header('Content-Type: application/json; charset=utf-8');
require __DIR__ . '/../conexion.php';

if ($_SERVER["REQUEST_METHOD"] == "POST") {
    // Get JSON POST data
    $json = file_get_contents('php://input');
    $data = json_decode($json, true);

    if (!$data) {
        $data = $_POST; // Fallback to normal POST if not JSON
    }

    $paciente = isset($data['paciente']) ? $data['paciente'] : '';
    $doctor = isset($data['doctor']) ? $data['doctor'] : '';
    $servicio = isset($data['servicio']) ? $data['servicio'] : '';
    $fecha = isset($data['fecha']) ? $data['fecha'] : '';
    $hora = isset($data['hora']) ? $data['hora'] : '';
    $motivo = isset($data['motivo']) ? $data['motivo'] : '';

    if (empty($paciente) || empty($doctor) || empty($servicio) || empty($fecha) || empty($hora)) {
        echo json_encode(['success' => false, 'error' => 'Faltan campos obligatorios.']);
        exit;
    }

    // Combine date and time
    $fecha_citas = $fecha . ' ' . $hora . ':00';
    $estado = 'Pendiente';

    try {
        $sql = "INSERT INTO Citas (Fecha_citas, Estado_citas, Motivo_Cita, IdServicio, Cedula_paciente, IdDoctor)
                VALUES (?, ?, ?, ?, ?, ?)";
        $stmt = $conn->prepare($sql);
        $stmt->execute([$fecha_citas, $estado, $motivo, $servicio, $paciente, $doctor]);

        echo json_encode(['success' => true, 'message' => 'Cita registrada correctamente.']);
    } catch (PDOException $e) {
        echo json_encode(['success' => false, 'error' => 'Error BD: ' . $e->getMessage()]);
    }
} else {
    echo json_encode(['success' => false, 'error' => 'Método no permitido.']);
}
?>
