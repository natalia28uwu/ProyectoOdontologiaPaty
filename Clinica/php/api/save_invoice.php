<?php
header('Content-Type: application/json; charset=utf-8');
include_once "../conexion.php";
session_start();

if (!isset($conn)) {
    echo json_encode(['success' => false, 'error' => 'Error de conexión']);
    exit();
}

// Obtener datos del POST
$data = json_decode(file_get_contents('php://input'), true);

if (!$data) {
    echo json_encode(['success' => false, 'error' => 'No se recibieron datos válidos']);
    exit();
}

$cedula = $data['cedula'];
$items = $data['items']; // Array de objetos
$total = $data['total'];
$id_empleado = $_SESSION['id_empleado'] ?? 700; // Paty es 700 (Fallback válido)

try {
    $conn->beginTransaction();

    // 1. Insertar Factura
    $stmtFactura = $conn->prepare("INSERT INTO Factura (FechaFactura, Cedula_paciente, IdEmpleado, Total) VALUES (GETDATE(), ?, ?, ?)");
    $stmtFactura->execute([$cedula, $id_empleado, $total]);
    
    $idFactura = $conn->lastInsertId();
    if (!$idFactura) {
        // En SQL Server con PDO sqlsrv, lastInsertId puede no funcionar siempre.
        // Buscamos el último insertado si es necesario, o usamos SCOPE_IDENTITY() en el insert.
        $idFactura = $conn->query("SELECT @@IDENTITY")->fetchColumn();
    }

    // 2. Insertar Detalles
    $stmtDetalle = $conn->prepare("INSERT INTO DetalleFactura (IdFactura, IdServicio, Cantidad, Precio) VALUES (?, ?, ?, ?)");
    
    foreach ($items as $item) {
        $stmtDetalle->execute([
            $idFactura,
            $item['id_servicio'] ?? 1,
            $item['cantidad'],
            $item['precio']
        ]);
    }

    $conn->commit();
    echo json_encode(['success' => true, 'idFactura' => $idFactura]);

} catch (Exception $e) {
    if ($conn->inTransaction()) $conn->rollBack();
    echo json_encode(['success' => false, 'error' => $e->getMessage()]);
}
?>
