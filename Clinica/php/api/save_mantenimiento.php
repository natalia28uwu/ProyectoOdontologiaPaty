<?php
header('Content-Type: application/json; charset=utf-8');
include_once "../conexion.php";

if (!isset($conn)) {
    echo json_encode(['success' => false, 'error' => 'Error de conexión']);
    exit();
}

$data = json_decode(file_get_contents('php://input'), true);
if (!$data || !isset($data['type'])) {
    echo json_encode(['success' => false, 'error' => 'Datos inválidos']);
    exit();
}

$type = $data['type'];
$fields = $data['fields'];
$id = $data['id'] ?? null;

try {
    if ($type === 'usuario') {
        if ($id) {
            $stmt = $conn->prepare("UPDATE Usuario SET Nombre=?, Apellido=?, Correo=?, Telefono=?, IdRol=? WHERE IdUsuario=?");
            $stmt->execute([$fields['Nombre'], $fields['Apellido'], $fields['Correo'], $fields['Telefono'], $fields['IdRol'], $id]);
        } else {
            $stmt = $conn->prepare("INSERT INTO Usuario (Nombre, Apellido, Correo, Telefono, Contraseña, IdRol) VALUES (?, ?, ?, ?, ?, ?)");
            $stmt->execute([$fields['Nombre'], $fields['Apellido'], $fields['Correo'], $fields['Telefono'], password_hash($fields['Contraseña'], PASSWORD_DEFAULT), $fields['IdRol']]);
        }
    } 
    else if ($type === 'seguro') {
        if ($id) {
            $stmt = $conn->prepare("UPDATE Seguros SET Nombre_seguros=?, Telefono_seguro=? WHERE IdSeguro=?");
            $stmt->execute([$fields['Nombre_seguros'], $fields['Telefono_seguro'], $id]);
        } else {
            $stmt = $conn->prepare("INSERT INTO Seguros (Nombre_seguros, Telefono_seguro) VALUES (?, ?)");
            $stmt->execute([$fields['Nombre_seguros'], $fields['Telefono_seguro']]);
        }
    }
    else if ($type === 'pacientes') {
        if ($id) {
            $stmt = $conn->prepare("UPDATE Pacientes SET Nombre_paciente=?, Apellido_paciente=?, FechaNac_paciente=?, Telefono_paciente=?, Correo_paciente=?, Direccion_paciente=?, IdSeguro=? WHERE Cedula_paciente=?");
            $stmt->execute([$fields['Nombre_paciente'], $fields['Apellido_paciente'], $fields['FechaNac_paciente'], $fields['Telefono_paciente'], $fields['Correo_paciente'], $fields['Direccion_paciente'], $fields['IdSeguro'], $id]);
        } else {
            $stmt = $conn->prepare("INSERT INTO Pacientes (Cedula_paciente, Nombre_paciente, Apellido_paciente, FechaNac_paciente, Telefono_paciente, Correo_paciente, Direccion_paciente, IdSeguro) VALUES (?, ?, ?, ?, ?, ?, ?, ?)");
            $stmt->execute([$fields['Cedula_paciente'], $fields['Nombre_paciente'], $fields['Apellido_paciente'], $fields['FechaNac_paciente'], $fields['Telefono_paciente'], $fields['Correo_paciente'], $fields['Direccion_paciente'], $fields['IdSeguro']]);
        }
    }
    else if ($type === 'citas') {
        if ($id) {
            $stmt = $conn->prepare("UPDATE Citas SET Fecha_citas=?, Cedula_paciente=?, IdDoctor=?, IdServicio=?, Motivo_Cita=?, Estado=? WHERE IdCita=?");
            $stmt->execute([$fields['Fecha_citas'], $fields['Cedula_paciente'], $fields['IdDoctor'], $fields['IdServicio'], $fields['Motivo_Cita'], $fields['Estado'], $id]);
        } else {
            $stmt = $conn->prepare("INSERT INTO Citas (Fecha_citas, Cedula_paciente, IdDoctor, IdServicio, Motivo_Cita, Estado) VALUES (?, ?, ?, ?, ?, ?)");
            $stmt->execute([$fields['Fecha_citas'], $fields['Cedula_paciente'], $fields['IdDoctor'], $fields['IdServicio'], $fields['Motivo_Cita'], $fields['Estado']]);
        }
    }

    echo json_encode(['success' => true]);

} catch (Exception $e) {
    echo json_encode(['success' => false, 'error' => $e->getMessage()]);
}
?>
