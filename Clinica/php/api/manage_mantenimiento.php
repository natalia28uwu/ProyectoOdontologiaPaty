<?php
header('Content-Type: application/json; charset=utf-8');
require __DIR__ . '/../conexion.php';

$method = $_SERVER['REQUEST_METHOD'];
$json = file_get_contents('php://input');
$data = json_decode($json, true);

if (!$data && $method !== 'DELETE') {
    $data = $_POST;
}

$type = isset($_GET['type']) ? $_GET['type'] : '';
$action = isset($_GET['action']) ? $_GET['action'] : 'save'; // save (insert/update) or delete

try {
    if ($action === 'delete') {
        $id = isset($_GET['id']) ? $_GET['id'] : '';
        if (!$id) throw new Exception("ID no proporcionado");

        switch ($type) {
            case 'usuario': $sql = "DELETE FROM Usuarios WHERE IdUsuario = ?"; break;
            case 'factura': $sql = "DELETE FROM Factura WHERE IdFactura = ?"; break;
            case 'seguro': $sql = "DELETE FROM Seguros WHERE IdSeguro = ?"; break;
            case 'paciente': $sql = "DELETE FROM Pacientes WHERE Cedula_paciente = ?"; break;
            case 'cita': $sql = "DELETE FROM Citas WHERE IdCita = ?"; break;
            default: throw new Exception("Tipo no válido para eliminar");
        }
        
        $stmt = $conn->prepare($sql);
        $stmt->execute([$id]);
        echo json_encode(['success' => true, 'message' => 'Registro eliminado correctamente']);
        exit;
    }

    if ($method === 'POST') {
        switch ($type) {
            case 'usuario':
                if (isset($data['IdUsuario']) && !empty($data['IdUsuario'])) {
                    $sql = "UPDATE Usuarios SET Nombre=?, Apellido=?, Correo=?, Telefono=? WHERE IdUsuario=?";
                    $stmt = $conn->prepare($sql);
                    $stmt->execute([$data['Nombre'], $data['Apellido'], $data['Correo'], $data['Telefono'], $data['IdUsuario']]);
                } else {
                    $sql = "INSERT INTO Usuarios (Nombre, Apellido, Correo, Contrasena, Telefono, IdRol) VALUES (?, ?, ?, ?, ?, 1602)"; // 1602 = Secretaria
                    $stmt = $conn->prepare($sql);
                    $stmt->execute([$data['Nombre'], $data['Apellido'], $data['Correo'], $data['Contrasena'], $data['Telefono']]);
                }
                break;

            case 'seguro':
                if (isset($data['IdSeguro']) && !empty($data['IdSeguro'])) {
                    $sql = "UPDATE Seguros SET Nombre_seguros=?, Telefono_seguro=? WHERE IdSeguro=?";
                    $stmt = $conn->prepare($sql);
                    $stmt->execute([$data['Nombre_seguros'], $data['Telefono_seguro'], $data['IdSeguro']]);
                } else {
                    $sql = "INSERT INTO Seguros (Nombre_seguros, Telefono_seguro) VALUES (?, ?)";
                    $stmt = $conn->prepare($sql);
                    $stmt->execute([$data['Nombre_seguros'], $data['Telefono_seguro']]);
                }
                break;

            case 'paciente':
                if (isset($data['is_edit']) && $data['is_edit'] == 'true') {
                    $sql = "UPDATE Pacientes SET Nombre_paciente=?, Apellido_paciente=?, FechaNac_paciente=?, Telefono_paciente=?, Correo_paciente=?, CondicionSalud=?, IdSeguro=? WHERE Cedula_paciente=?";
                    $stmt = $conn->prepare($sql);
                    $stmt->execute([$data['Nombre_paciente'], $data['Apellido_paciente'], $data['FechaNac_paciente'], $data['Telefono_paciente'], $data['Correo_paciente'], $data['CondicionSalud'], $data['IdSeguro'], $data['Cedula_paciente']]);
                } else {
                    $sql = "INSERT INTO Pacientes (Cedula_paciente, Nombre_paciente, Apellido_paciente, FechaNac_paciente, Telefono_paciente, Correo_paciente, CondicionSalud, IdSeguro) VALUES (?, ?, ?, ?, ?, ?, ?, ?)";
                    $stmt = $conn->prepare($sql);
                    $stmt->execute([$data['Cedula_paciente'], $data['Nombre_paciente'], $data['Apellido_paciente'], $data['FechaNac_paciente'], $data['Telefono_paciente'], $data['Correo_paciente'], $data['CondicionSalud'], $data['IdSeguro']]);
                }
                break;

            case 'cita':
                if (isset($data['IdCita']) && !empty($data['IdCita'])) {
                    $sql = "UPDATE Citas SET Fecha_citas=?, Estado_citas=?, Motivo_Cita=?, IdServicio=?, Cedula_paciente=?, IdDoctor=? WHERE IdCita=?";
                    $stmt = $conn->prepare($sql);
                    $stmt->execute([$data['Fecha_citas'], $data['Estado_citas'], $data['Motivo_Cita'], $data['IdServicio'], $data['Cedula_paciente'], $data['IdDoctor'], $data['IdCita']]);
                } else {
                    $sql = "INSERT INTO Citas (Fecha_citas, Estado_citas, Motivo_Cita, IdServicio, Cedula_paciente, IdDoctor) VALUES (?, ?, ?, ?, ?, ?)";
                    $stmt = $conn->prepare($sql);
                    $stmt->execute([$data['Fecha_citas'], 'Pendiente', $data['Motivo_Cita'], $data['IdServicio'], $data['Cedula_paciente'], $data['IdDoctor']]);
                }
                break;
                
            case 'factura':
                if (isset($data['IdFactura']) && !empty($data['IdFactura'])) {
                    $sql = "UPDATE Factura SET FechaFactura=?, Cedula_paciente=?, Total=? WHERE IdFactura=?";
                    $stmt = $conn->prepare($sql);
                    $stmt->execute([$data['FechaFactura'], $data['Cedula_paciente'], $data['Total'], $data['IdFactura']]);
                } else {
                    $sql = "INSERT INTO Factura (FechaFactura, Cedula_paciente, Total) VALUES (GETDATE(), ?, ?)";
                    $stmt = $conn->prepare($sql);
                    $stmt->execute([$data['Cedula_paciente'], $data['Total']]);
                }
                break;

            default:
                throw new Exception("Tipo de guardado no válido");
        }
        echo json_encode(['success' => true, 'message' => 'Datos guardados correctamente']);
    }

} catch (Exception $e) {
    echo json_encode(['success' => false, 'error' => $e->getMessage()]);
}
?>
