<?php
header('Content-Type: application/json; charset=utf-8');
require __DIR__ . '/../conexion.php';

$type = isset($_GET['type']) ? $_GET['type'] : '';
$data = [];

try {
    switch ($type) {
        case 'pacientes':
            $stmt = $conn->query("
                SELECT p.Cedula_paciente as ID, 
                       p.Nombre_paciente + ' ' + p.Apellido_paciente as Nombre,
                       p.Cedula_paciente as DNI,
                       p.Telefono_paciente as Telefono,
                       p.Correo_paciente as Email,
                       s.Nombre_seguros as Seguro,
                       'Activo' as Estado
                FROM Pacientes p
                LEFT JOIN Seguros s ON p.IdSeguro = s.IdSeguro
            ");
            $data = $stmt->fetchAll(PDO::FETCH_ASSOC);
            break;

        case 'diagnosticos':
            $stmt = $conn->query("
                SELECT d.IdDiagnostico as ID, 
                       FORMAT(e.FechaEvaluacion, 'dd/MM/yyyy') as Fecha,
                       p.Nombre_paciente + ' ' + p.Apellido_paciente as Paciente,
                       doc.Nombre_doctor + ' ' + doc.Apellido_doctor as Doctor,
                       d.DescripcionDiagnostico as Diagnostico,
                       'N/A' as CIE10,
                       'Moderado' as Gravedad
                FROM Diagnostico d
                LEFT JOIN Evaluaciones e ON d.IdEvaluacion = e.IdEvaluacion
                LEFT JOIN Pacientes p ON e.Cedula_evaluacion = p.Cedula_paciente
                LEFT JOIN Doctores doc ON d.IdDoctor = doc.IdDoctor
            ");
            $data = $stmt->fetchAll(PDO::FETCH_ASSOC);
            break;

        case 'seguros':
            $stmt = $conn->query("
                SELECT s.IdSeguro as ID, 
                       s.Nombre_seguros as Nombre,
                       'N/A' as RUC,
                       s.Telefono_seguro as Telefono,
                       '100%' as Cobertura,
                       (SELECT COUNT(*) FROM Pacientes WHERE IdSeguro = s.IdSeguro) as PacientesAfiliados,
                       'Activo' as Estado
                FROM Seguros s
            ");
            $data = $stmt->fetchAll(PDO::FETCH_ASSOC);
            break;

        case 'citas':
            $stmt = $conn->query("
                SELECT c.IdCita as ID, 
                       FORMAT(c.Fecha_citas, 'dd/MM/yyyy') as Fecha,
                       FORMAT(c.Fecha_citas, 'HH:mm') as Hora,
                       p.Nombre_paciente + ' ' + p.Apellido_paciente as Paciente,
                       doc.Nombre_doctor + ' ' + doc.Apellido_doctor as Doctor,
                       doc.Especialidad_doctor as Especialidad,
                       c.Motivo_Cita as Motivo,
                       c.Estado_citas as Estado
                FROM Citas c
                LEFT JOIN Pacientes p ON c.Cedula_paciente = p.Cedula_paciente
                LEFT JOIN Doctores doc ON c.IdDoctor = doc.IdDoctor
            ");
            $data = $stmt->fetchAll(PDO::FETCH_ASSOC);
            break;

        case 'doctores':
            $stmt = $conn->query("
                SELECT d.IdDoctor as ID, 
                       d.Nombre_doctor + ' ' + d.Apellido_doctor as Nombre,
                       'CMP' as CMP,
                       d.Especialidad_doctor as Especialidad,
                       'N/A' as Telefono,
                       '08:00 - 18:00' as Horario,
                       (SELECT COUNT(*) FROM Citas WHERE IdDoctor = d.IdDoctor AND CAST(Fecha_citas AS DATE) = CAST(GETDATE() AS DATE)) as CitasHoy,
                       'Disponible' as Estado
                FROM Doctores d
            ");
            $data = $stmt->fetchAll(PDO::FETCH_ASSOC);
            break;

        case 'empleados':
            $stmt = $conn->query("
                SELECT e.IdEmpleado as ID, 
                       e.Nombre_empleado + ' ' + e.Apellido_empleado as Nombre,
                       'DNI' as DNI,
                       e.Cargo_empleado as Cargo,
                       'General' as Area,
                       e.Telefono_empleado as Telefono,
                       '01/01/2020' as FechaIngreso,
                       'Activo' as Estado
                FROM Empleados e
            ");
            $data = $stmt->fetchAll(PDO::FETCH_ASSOC);
            break;

        case 'evaluaciones':
            $stmt = $conn->query("
                SELECT e.IdEvaluacion as ID, 
                       FORMAT(e.FechaEvaluacion, 'dd/MM/yyyy') as Fecha,
                       p.Nombre_paciente + ' ' + p.Apellido_paciente as Paciente,
                       e.Descripcion_evaluacion as Tipo,
                       doc.Nombre_doctor + ' ' + doc.Apellido_doctor as DoctorSolicitante,
                       e.Observacion_evaluacion as Resultado,
                       'Completado' as Estado
                FROM Evaluaciones e
                LEFT JOIN Pacientes p ON e.Cedula_evaluacion = p.Cedula_paciente
                LEFT JOIN Doctores doc ON e.IdDoctor = doc.IdDoctor
            ");
            $data = $stmt->fetchAll(PDO::FETCH_ASSOC);
            break;

        case 'cotizaciones':
            $stmt = $conn->query("
                SELECT c.IdCotizacion as Numero, 
                       'N/A' as Fecha,
                       p.Nombre_paciente + ' ' + p.Apellido_paciente as Paciente,
                       c.Descripcion_cotizacion as Servicios,
                       c.Monto_cotizacion as Subtotal,
                       0 as Descuento,
                       c.Monto_cotizacion as Total,
                       c.Estado as Estado
                FROM Cotizaciones c
                LEFT JOIN Diagnostico d ON c.IdDiagnostico = d.IdDiagnostico
                LEFT JOIN Evaluaciones e ON d.IdEvaluacion = e.IdEvaluacion
                LEFT JOIN Pacientes p ON e.Cedula_evaluacion = p.Cedula_paciente
            ");
            $data = $stmt->fetchAll(PDO::FETCH_ASSOC);
            break;

        case 'pagos':
            $stmt = $conn->query("
                SELECT pg.IdPago as Numero, 
                       FORMAT(pg.Fecha_pago, 'dd/MM/yyyy') as Fecha,
                       p.Nombre_paciente + ' ' + p.Apellido_paciente as Paciente,
                       c.Descripcion_cotizacion as Concepto,
                       pg.FormaPago as Metodo,
                       pg.MontoPago as Monto,
                       'Factura' as Comprobante,
                       'Pagado' as Estado
                FROM Pagos pg
                LEFT JOIN Cotizaciones c ON pg.IdCotizacion = c.IdCotizacion
                LEFT JOIN Diagnostico d ON c.IdDiagnostico = d.IdDiagnostico
                LEFT JOIN Evaluaciones e ON d.IdEvaluacion = e.IdEvaluacion
                LEFT JOIN Pacientes p ON e.Cedula_evaluacion = p.Cedula_paciente
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
