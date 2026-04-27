<?php
header('Content-Type: application/json; charset=utf-8');
include_once "../conexion.php";

if (!isset($conn)) {
    echo json_encode(['success' => false, 'error' => 'No se pudo establecer la conexión con la base de datos (conexion.php no cargado)']);
    exit();
}

$query = isset($_GET['query']) ? trim($_GET['query']) : '';
$query = str_replace('  ', ' ', $query); // Limpiar espacios dobles

if (empty($query)) {
    echo json_encode(['success' => false, 'error' => 'No se proporcionó nombre o correo']);
    exit();
}

try {
    // Buscar usuario por nombre o correo
    $stmt = $conn->prepare("SELECT IdUsuario, Nombre, Correo FROM Usuarios WHERE Nombre LIKE ? OR Correo = ?");
    $stmt->execute(["%$query%", $query]);
    $usuario = $stmt->fetch(PDO::FETCH_ASSOC);

    if (!$usuario) {
        // Búsqueda simplificada al máximo para asegurar conexión
        $stmtP = $conn->prepare("SELECT Cedula_paciente, Nombre_paciente, Apellido_paciente FROM Pacientes WHERE Cedula_paciente LIKE ? OR Nombre_paciente LIKE ? OR Apellido_paciente LIKE ?");
        $stmtP->execute(["%$query%", "%$query%", "%$query%"]);
        $paciente = $stmtP->fetch(PDO::FETCH_ASSOC);
        
        if (!$paciente) {
            // Reintento: Ver si hay coincidencia parcial en el nombre completo
            $stmtP = $conn->prepare("SELECT Cedula_paciente, Nombre_paciente, Apellido_paciente FROM Pacientes");
            $stmtP->execute();
            $todos = $stmtP->fetchAll(PDO::FETCH_ASSOC);
            foreach($todos as $p) {
                $full = strtolower($p['Nombre_paciente'] . ' ' . $p['Apellido_paciente']);
                if (strpos($full, strtolower($query)) !== false) {
                    $paciente = $p;
                    break;
                }
            }
        }

        if (!$paciente) {
            echo json_encode(['success' => false, 'error' => 'Usuario no encontrado']);
            exit();
        }

        $cedula = $paciente['Cedula_paciente'];
        // Limpiamos espacios
        $nom = trim($paciente['Nombre_paciente']);
        $ape = trim($paciente['Apellido_paciente']);
        $nombre = $nom . ' ' . $ape;
    } else {
        // Es un usuario, buscamos su cédula asociada en Pacientes
        $stmtP = $conn->prepare("SELECT Cedula_paciente FROM Pacientes WHERE Correo_paciente = ?");
        $stmtP->execute([$usuario['Correo']]);
        $p = $stmtP->fetch(PDO::FETCH_ASSOC);
        $cedula = $p ? $p['Cedula_paciente'] : null;
        $nombre = $usuario['Nombre'];
    }

    $data = [
        'nombre' => $nombre,
        'citas' => [],
        'deudas' => []
    ];

    if ($cedula) {
        // Buscar citas pendientes
        $stmtC = $conn->prepare("SELECT Fecha_citas, Motivo_Cita FROM Citas WHERE Cedula_paciente = ? AND (Estado_citas = 'Pendiente' OR Estado_citas = 'pendiente')");
        $stmtC->execute([$cedula]);
        $citasRaw = $stmtC->fetchAll(PDO::FETCH_ASSOC);
        foreach($citasRaw as $c) {
            $data['citas'][] = [
                'Fecha_citas' => $c['Fecha_citas'],
                'Motivo_Cita' => $c['Motivo_Cita']
            ];
        }

        // Buscar cotizaciones pendientes (deudas) - JOIN necesario para llegar a la cédula
        $sqlD = "SELECT c.Monto_cotizacion, c.Estado 
                 FROM Cotizaciones c
                 JOIN Diagnostico d ON c.IdDiagnostico = d.IdDiagnostico
                 JOIN Evaluaciones e ON d.IdEvaluacion = e.IdEvaluacion
                 WHERE e.Cedula_evaluacion = ? AND (c.Estado = 'Pendiente' OR c.Estado = 'pendiente')";
        
        $stmtD = $conn->prepare($sqlD);
        $stmtD->execute([$cedula]);
        $deudasRaw = $stmtD->fetchAll(PDO::FETCH_ASSOC);
        foreach($deudasRaw as $d) {
            $data['deudas'][] = [
                'Monto' => $d['Monto_cotizacion'],
                'Estado' => $d['Estado']
            ];
        }
    }

    echo json_encode([
        'success' => true, 
        'data' => $data,
        'debug_query' => $query
    ]);

} catch (Exception $e) {
    echo json_encode(['success' => false, 'error' => $e->getMessage(), 'debug_query' => $query]);
}
?>
