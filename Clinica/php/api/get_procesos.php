<?php
header('Content-Type: application/json; charset=utf-8');
require __DIR__ . '/../conexion.php';

$type = isset($_GET['type']) ? $_GET['type'] : '';
$data = [];

try {
    switch ($type) {
        case 'consultas':
            $stmt = $conn->query("
                SELECT c.IdConsulta, FORMAT(c.Fecha_consulta, 'dd/MM/yyyy') as Fecha,
                       p.Nombre_paciente + ' ' + p.Apellido_paciente as Paciente,
                       d.Nombre_doctor + ' ' + d.Apellido_doctor as Doctor,
                       c.Motivo
                FROM Consultas c
                LEFT JOIN Pacientes p ON c.Cedula_consulta = p.Cedula_paciente
                LEFT JOIN Doctores d ON c.IdDoctor = d.IdDoctor
                ORDER BY c.Fecha_consulta DESC
            ");
            $data = $stmt->fetchAll(PDO::FETCH_ASSOC);
            break;

        case 'diagnostico':
            $stmt = $conn->query("
                SELECT d.IdDiagnostico, 
                       p.Nombre_paciente + ' ' + p.Apellido_paciente as Paciente,
                       d.DescripcionDiagnostico as Descripcion,
                       FORMAT(e.FechaEvaluacion, 'dd/MM/yyyy') as Fecha
                FROM Diagnostico d
                LEFT JOIN Evaluaciones e ON d.IdEvaluacion = e.IdEvaluacion
                LEFT JOIN Pacientes p ON e.Cedula_evaluacion = p.Cedula_paciente
                ORDER BY e.FechaEvaluacion DESC
            ");
            $data = $stmt->fetchAll(PDO::FETCH_ASSOC);
            break;

        case 'citas':
            $stmt = $conn->query("
                SELECT c.IdCita, FORMAT(c.Fecha_citas, 'HH:mm') as Hora, FORMAT(c.Fecha_citas, 'dd/MM/yyyy') as Fecha,
                       p.Nombre_paciente + ' ' + p.Apellido_paciente as Paciente,
                       d.Nombre_doctor + ' ' + d.Apellido_doctor as Doctor,
                       c.Estado_citas as Estado,
                       c.Motivo_Cita as Motivo
                FROM Citas c
                LEFT JOIN Pacientes p ON c.Cedula_paciente = p.Cedula_paciente
                LEFT JOIN Doctores d ON c.IdDoctor = d.IdDoctor
                ORDER BY c.Fecha_citas DESC
            ");
            $data = $stmt->fetchAll(PDO::FETCH_ASSOC);
            break;

        case 'evaluaciones':
            $stmt = $conn->query("
                SELECT e.IdEvaluacion, 
                       p.Nombre_paciente + ' ' + p.Apellido_paciente as Paciente,
                       e.Descripcion_evaluacion as Descripcion,
                       FORMAT(e.FechaEvaluacion, 'dd/MM/yyyy') as Fecha
                FROM Evaluaciones e
                LEFT JOIN Pacientes p ON e.Cedula_evaluacion = p.Cedula_paciente
                ORDER BY e.FechaEvaluacion DESC
            ");
            $data = $stmt->fetchAll(PDO::FETCH_ASSOC);
            break;

        case 'cotizaciones':
            $stmt = $conn->query("
                SELECT c.IdCotizacion, 
                       p.Nombre_paciente + ' ' + p.Apellido_paciente as Paciente,
                       c.Monto_cotizacion as Monto,
                       c.Estado,
                       FORMAT(e.FechaEvaluacion, 'dd/MM/yyyy') as Fecha
                FROM Cotizaciones c
                LEFT JOIN Diagnostico d ON c.IdDiagnostico = d.IdDiagnostico
                LEFT JOIN Evaluaciones e ON d.IdEvaluacion = e.IdEvaluacion
                LEFT JOIN Pacientes p ON e.Cedula_evaluacion = p.Cedula_paciente
            ");
            $data = $stmt->fetchAll(PDO::FETCH_ASSOC);
            break;

        case 'pagos':
            $stmt = $conn->query("
                SELECT p.IdPago, 
                       FORMAT(p.Fecha_pago, 'dd/MM/yyyy') as Fecha,
                       pac.Nombre_paciente + ' ' + pac.Apellido_paciente as Paciente,
                       p.MontoPago as Monto,
                       p.FormaPago as Metodo
                FROM Pagos p
                LEFT JOIN Cotizaciones c ON p.IdCotizacion = c.IdCotizacion
                LEFT JOIN Diagnostico d ON c.IdDiagnostico = d.IdDiagnostico
                LEFT JOIN Evaluaciones e ON d.IdEvaluacion = e.IdEvaluacion
                LEFT JOIN Pacientes pac ON e.Cedula_evaluacion = pac.Cedula_paciente
                ORDER BY p.Fecha_pago DESC
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
