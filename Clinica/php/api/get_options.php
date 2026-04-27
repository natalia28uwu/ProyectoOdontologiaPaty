<?php
header('Content-Type: application/json; charset=utf-8');
require __DIR__ . '/../conexion.php';

$type = isset($_GET['type']) ? $_GET['type'] : '';
$data = [];

try {
    switch ($type) {
        case 'pacientes':
            $stmt = $conn->query("
                SELECT Cedula_paciente as id, Nombre_paciente + ' ' + Apellido_paciente as text
                FROM Pacientes
                ORDER BY Nombre_paciente
            ");
            $data = $stmt->fetchAll(PDO::FETCH_ASSOC);
            break;

        case 'doctores':
            $stmt = $conn->query("
                SELECT IdDoctor as id, Nombre_doctor + ' ' + Apellido_doctor + ' - ' + Especialidad_doctor as text
                FROM Doctores
                ORDER BY Nombre_doctor
            ");
            $data = $stmt->fetchAll(PDO::FETCH_ASSOC);
            break;

        case 'servicios':
            $stmt = $conn->query("
                SELECT IdServicio as id, Nombre as text
                FROM Servicios
                ORDER BY Nombre
            ");
            $data = $stmt->fetchAll(PDO::FETCH_ASSOC);
            break;

        case 'citas_pendientes':
            // Citas for today that are not yet "Atendido" or "Cancelado"
            $stmt = $conn->query("
                SELECT c.IdCita as id, 
                       FORMAT(c.Fecha_citas, 'HH:mm') + ' - ' + p.Nombre_paciente + ' ' + p.Apellido_paciente + ' - ' + ISNULL(c.Motivo_Cita, 'Sin motivo') as text,
                       c.Motivo_Cita as motivo,
                       FORMAT(c.Fecha_citas, 'yyyy-MM-dd') as fecha
                FROM Citas c
                JOIN Pacientes p ON c.Cedula_paciente = p.Cedula_paciente
                WHERE CAST(c.Fecha_citas AS DATE) <= CAST(GETDATE() AS DATE) 
                  AND (c.Estado_citas IS NULL OR c.Estado_citas NOT IN ('Atendido', 'Cancelado'))
                ORDER BY c.Fecha_citas ASC
            ");
            $data = $stmt->fetchAll(PDO::FETCH_ASSOC);
            break;

        default:
            throw new Exception("Tipo no válido");
    }

    echo json_encode(['success' => true, 'data' => $data]);

} catch (Exception $e) {
    echo json_encode(['success' => false, 'error' => $e->getMessage()]);
}
?>
