<?php
header('Content-Type: application/json; charset=utf-8');
require __DIR__ . '/../conexion.php';

if ($_SERVER["REQUEST_METHOD"] == "POST") {
    $json = file_get_contents('php://input');
    $data = json_decode($json, true);
    if (!$data) $data = $_POST;

    $idCita = isset($data['idCita']) ? $data['idCita'] : '';
    $diagnostico = isset($data['diagnostico']) ? $data['diagnostico'] : '';
    $tratamiento = isset($data['tratamiento']) ? $data['tratamiento'] : '';
    $observacion = isset($data['observacion']) ? $data['observacion'] : '';
    $estado = isset($data['estado']) ? $data['estado'] : '';

    if (empty($idCita)) {
        echo json_encode(['success' => false, 'error' => 'Falta seleccionar la cita.']);
        exit;
    }

    try {
        $conn->beginTransaction();

        // 1. Obtener datos de la Cita
        $stmtCita = $conn->prepare("SELECT Cedula_paciente, IdDoctor FROM Citas WHERE IdCita = ?");
        $stmtCita->execute([$idCita]);
        $citaData = $stmtCita->fetch(PDO::FETCH_ASSOC);

        if (!$citaData) {
            throw new Exception("Cita no encontrada.");
        }

        $cedula = $citaData['Cedula_paciente'];
        $idDoctor = $citaData['IdDoctor'];

        // 2. Insertar en Evaluaciones
        $stmtEval = $conn->prepare("
            INSERT INTO Evaluaciones (IdDoctor, Cedula_evaluacion, FechaEvaluacion, Descripcion_evaluacion, Observacion_evaluacion)
            OUTPUT INSERTED.IdEvaluacion
            VALUES (?, ?, GETDATE(), 'Atención Médica', ?)
        ");
        $stmtEval->execute([$idDoctor, $cedula, $observacion]);
        $idEvaluacion = $stmtEval->fetchColumn();

        // 3. Insertar en Diagnostico si hay texto
        if (!empty($diagnostico)) {
            $stmtDiag = $conn->prepare("
                INSERT INTO Diagnostico (IdEvaluacion, IdDoctor, DescripcionDiagnostico)
                VALUES (?, ?, ?)
            ");
            $stmtDiag->execute([$idEvaluacion, $idDoctor, $diagnostico]);
        }

        // 4. Insertar en Tratamientos si hay texto
        if (!empty($tratamiento)) {
            $stmtTrat = $conn->prepare("
                INSERT INTO Tratamientos (Nombre_tratamiento, Descripcion_tratamiento, IdDoctor, IdCita, Costo_tratamiento)
                VALUES ('Tratamiento General', ?, ?, ?, 0)
            ");
            $stmtTrat->execute([$tratamiento, $idDoctor, $idCita]);
        }

        // 5. Actualizar estado de la Cita
        if (!empty($estado)) {
            $stmtUpd = $conn->prepare("UPDATE Citas SET Estado_citas = ? WHERE IdCita = ?");
            $stmtUpd->execute([$estado, $idCita]);
        }

        $conn->commit();
        echo json_encode(['success' => true, 'message' => 'Atención registrada correctamente.']);

    } catch (Exception $e) {
        $conn->rollBack();
        echo json_encode(['success' => false, 'error' => 'Error BD: ' . $e->getMessage()]);
    }
} else {
    echo json_encode(['success' => false, 'error' => 'Método no permitido.']);
}
?>
