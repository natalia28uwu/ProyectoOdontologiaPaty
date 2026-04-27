<?php
header('Content-Type: application/json; charset=utf-8');
require __DIR__ . '/../conexion.php';

$type = isset($_GET['type']) ? $_GET['type'] : '';
$data = [];

try {
    switch ($type) {
        case 'pacientes':
            $stmt = $conn->query("SELECT COUNT(*) as Total FROM Pacientes");
            $total = $stmt->fetchColumn();
            
            $stats = [
                'total' => $total,
                'nuevos' => $total,
                'activos' => $total,
                'inactivos' => 0
            ];

            $stmt = $conn->query("
                SELECT FORMAT(Fecha_citas, 'MMMM yyyy', 'es-ES') as Mes,
                       COUNT(DISTINCT Cedula_paciente) as Atendidos,
                       YEAR(Fecha_citas) as Y, MONTH(Fecha_citas) as M
                FROM Citas
                WHERE Estado_citas = 'Atendido'
                GROUP BY FORMAT(Fecha_citas, 'MMMM yyyy', 'es-ES'), YEAR(Fecha_citas), MONTH(Fecha_citas)
                ORDER BY Y DESC, M DESC
            ");
            $tableRaw = $stmt->fetchAll(PDO::FETCH_ASSOC);
            $tableData = [];
            foreach($tableRaw as $row) {
                $tableData[] = [
                    'Mes' => ucfirst($row['Mes']),
                    'Nuevos' => '-',
                    'Atendidos' => $row['Atendidos'],
                    'Retencion' => '100%'
                ];
            }

            $data = ['stats' => $stats, 'table' => $tableData];
            break;

        case 'citas':
            $stmt = $conn->query("
                SELECT COUNT(*) as Total,
                       SUM(CASE WHEN Estado_citas = 'Atendido' THEN 1 ELSE 0 END) as Atendidas,
                       SUM(CASE WHEN Estado_citas = 'Cancelado' THEN 1 ELSE 0 END) as Canceladas,
                       SUM(CASE WHEN Estado_citas = 'Pendiente' THEN 1 ELSE 0 END) as Pendientes
                FROM Citas
            ");
            $statsRaw = $stmt->fetch(PDO::FETCH_ASSOC);
            $stats = [
                'total' => $statsRaw['Total'],
                'atendidas' => $statsRaw['Atendidas'] ?? 0,
                'canceladas' => $statsRaw['Canceladas'] ?? 0,
                'no_asistieron' => $statsRaw['Pendientes'] ?? 0
            ];

            $stmt = $conn->query("
                SELECT d.Especialidad_doctor as Especialidad,
                       COUNT(c.IdCita) as TotalCitas,
                       SUM(CASE WHEN c.Estado_citas = 'Atendido' THEN 1 ELSE 0 END) as Atendidas,
                       SUM(CASE WHEN c.Estado_citas = 'Cancelado' THEN 1 ELSE 0 END) as Canceladas
                FROM Doctores d
                LEFT JOIN Citas c ON d.IdDoctor = c.IdDoctor
                GROUP BY d.Especialidad_doctor
            ");
            $tableData = $stmt->fetchAll(PDO::FETCH_ASSOC);

            $data = ['stats' => $stats, 'table' => $tableData];
            break;

        case 'ingresos':
            $stmt = $conn->query("SELECT ISNULL(SUM(Total), 0) as Total FROM Factura");
            $total = $stmt->fetchColumn() ?? 0;

            $stmt = $conn->query("
                SELECT s.Nombre, SUM(df.Subtotal) as Total
                FROM DetalleFactura df
                JOIN Servicios s ON df.IdServicio = s.IdServicio
                GROUP BY s.Nombre
            ");
            $detalles = $stmt->fetchAll(PDO::FETCH_ASSOC);
            $totalConsultas = 0;
            $totalTratamientos = 0;
            foreach($detalles as $d) {
                if (stripos($d['Nombre'], 'consulta') !== false) {
                    $totalConsultas += $d['Total'];
                } else {
                    $totalTratamientos += $d['Total'];
                }
            }

            $stats = [
                'total' => '$' . number_format($total, 2),
                'consultas' => '$' . number_format($totalConsultas, 2),
                'tratamientos' => '$' . number_format($totalTratamientos, 2),
                'examenes' => '$0.00'
            ];

            $stmt = $conn->query("
                SELECT s.Nombre as Concepto, SUM(df.Cantidad) as Cantidad, MAX(s.Precio) as PrecioUnit, SUM(df.Subtotal) as Total
                FROM DetalleFactura df
                JOIN Servicios s ON df.IdServicio = s.IdServicio
                GROUP BY s.Nombre
            ");
            $tableData = $stmt->fetchAll(PDO::FETCH_ASSOC);

            $data = ['stats' => $stats, 'table' => $tableData];
            break;

        case 'doctores':
            $stmt = $conn->query("
                SELECT d.Nombre_doctor + ' ' + d.Apellido_doctor as Doctor,
                       d.Especialidad_doctor as Especialidad,
                       COUNT(DISTINCT c.Cedula_paciente) as PacientesAtendidos,
                       COUNT(c.IdCita) as CitasCumplidas,
                       SUM(s.Precio) as IngresosGenerados
                FROM Doctores d
                LEFT JOIN Citas c ON d.IdDoctor = c.IdDoctor AND c.Estado_citas = 'Atendido'
                LEFT JOIN Servicios s ON c.IdServicio = s.IdServicio
                GROUP BY d.IdDoctor, d.Nombre_doctor, d.Apellido_doctor, d.Especialidad_doctor
            ");
            $tableData = $stmt->fetchAll(PDO::FETCH_ASSOC);

            $data = ['table' => $tableData];
            break;

        default:
            throw new Exception("Tipo no válido");
    }

    echo json_encode(['success' => true, 'data' => $data]);

} catch (Exception $e) {
    echo json_encode(['success' => false, 'error' => $e->getMessage()]);
}
?>
