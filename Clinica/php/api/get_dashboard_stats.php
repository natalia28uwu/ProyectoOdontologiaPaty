<?php
header('Content-Type: application/json; charset=utf-8');
require __DIR__ . '/../conexion.php';

try {
    $stats = [
        'total_pacientes' => 0,
        'total_doctores' => 0,
        'citas_hoy' => 0,
        'ingresos_mes' => 0,
        'actividades' => []
    ];

    // Total Pacientes
    $stmt = $conn->query("SELECT COUNT(*) as total FROM Pacientes");
    $stats['total_pacientes'] = $stmt->fetch(PDO::FETCH_ASSOC)['total'];

    // Total Doctores
    $stmt = $conn->query("SELECT COUNT(*) as total FROM Doctores");
    $stats['total_doctores'] = $stmt->fetch(PDO::FETCH_ASSOC)['total'];

    // Citas Hoy
    $stmt = $conn->query("SELECT COUNT(*) as total FROM Citas WHERE CAST(Fecha_citas AS DATE) = CAST(GETDATE() AS DATE)");
    $stats['citas_hoy'] = $stmt->fetch(PDO::FETCH_ASSOC)['total'];

    // Ingresos Mes
    $stmt = $conn->query("SELECT ISNULL(SUM(Total), 0) as total FROM Factura WHERE MONTH(FechaFactura) = MONTH(GETDATE()) AND YEAR(FechaFactura) = YEAR(GETDATE())");
    $stats['ingresos_mes'] = number_format($stmt->fetch(PDO::FETCH_ASSOC)['total'], 2);

    // Últimas Actividades (usando Citas como ejemplo)
    $query_actividades = "
        SELECT TOP 4 
            'Cita agendada' as Actividad, 
            p.Nombre_paciente + ' ' + p.Apellido_paciente as Usuario, 
            FORMAT(c.Fecha_citas, 'dd/MM/yyyy HH:mm') as Fecha, 
            c.Estado_citas as Estado
        FROM Citas c
        LEFT JOIN Pacientes p ON c.Cedula_paciente = p.Cedula_paciente
        ORDER BY c.IdCita DESC
    ";
    
    $stmt = $conn->query($query_actividades);
    $stats['actividades'] = $stmt->fetchAll(PDO::FETCH_ASSOC);

    echo json_encode(['success' => true, 'data' => $stats]);

} catch (Exception $e) {
    echo json_encode(['success' => false, 'error' => $e->getMessage()]);
}
?>
