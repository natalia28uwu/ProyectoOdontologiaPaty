<?php
header('Content-Type: application/json; charset=utf-8');
require __DIR__ . '/../conexion.php';

$type = isset($_GET['type']) ? $_GET['type'] : '';
$data = [];

try {
    switch ($type) {
        case 'seguro':
        case 'seguros':
            $stmt = $conn->query("SELECT IdSeguro, Nombre_seguros, Telefono_seguro FROM Seguros");
            $data = $stmt->fetchAll(PDO::FETCH_ASSOC);
            break;
            
        case 'doctor':
            $stmt = $conn->query("SELECT IdDoctor, Nombre_doctor, Apellido_doctor, Especialidad_doctor FROM Doctores");
            $data = $stmt->fetchAll(PDO::FETCH_ASSOC);
            break;
            
        case 'pacientes':
            $stmt = $conn->query("
                SELECT p.Cedula_paciente, p.Nombre_paciente, p.Apellido_paciente, 
                       FORMAT(p.FechaNac_paciente, 'dd/MM/yyyy') as FechaNac_paciente, 
                       p.Telefono_paciente, p.Correo_paciente, s.Nombre_seguros
                FROM Pacientes p
                LEFT JOIN Seguros s ON p.IdSeguro = s.IdSeguro
            ");
            $data = $stmt->fetchAll(PDO::FETCH_ASSOC);
            break;
            
        case 'empleados':
            $stmt = $conn->query("SELECT IdEmpleado, Nombre_empleado, Apellido_empleado, Direccion_empleado, Telefono_empleado, Cargo_empleado FROM Empleados");
            $data = $stmt->fetchAll(PDO::FETCH_ASSOC);
            break;
            
        case 'usuario':
        case 'usuarios':
            $stmt = $conn->query("SELECT IdUsuario, Nombre, Apellido, Correo, Telefono FROM Usuarios");
            $data = $stmt->fetchAll(PDO::FETCH_ASSOC);
            break;

        case 'factura':
        case 'facturas':
            $stmt = $conn->query("
                SELECT f.IdFactura, f.FechaFactura, f.Cedula_paciente, p.Nombre_paciente, p.Apellido_paciente, f.Total
                FROM Factura f
                LEFT JOIN Pacientes p ON f.Cedula_paciente = p.Cedula_paciente
                ORDER BY f.FechaFactura DESC
            ");
            $data = $stmt->fetchAll(PDO::FETCH_ASSOC);
            break;

        case 'citas':
            $stmt = $conn->query("
                SELECT c.IdCita, c.Fecha_citas, c.Estado_citas, c.Motivo_Cita, p.Nombre_paciente, p.Apellido_paciente, d.Nombre_doctor
                FROM Citas c
                LEFT JOIN Pacientes p ON c.Cedula_paciente = p.Cedula_paciente
                LEFT JOIN Doctores d ON c.IdDoctor = d.IdDoctor
                ORDER BY c.Fecha_citas DESC
            ");
            $data = $stmt->fetchAll(PDO::FETCH_ASSOC);
            break;

        case 'servicios':
            $stmt = $conn->query("SELECT IdServicio, Nombre, Precio FROM Servicios");
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
