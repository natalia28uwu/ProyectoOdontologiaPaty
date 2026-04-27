<?php
// Usamos el nombre del servidor que funcionó en tu prueba
$server = "natalia"; 
$database = "SistemaOdontologia";

try {
    // Cambiamos 'odbc' por 'sqlsrv' para usar tus nuevos drivers nativos
    $conn = new PDO(
        "sqlsrv:Server=$server;Database=$database;Encrypt=False;TrustServerCertificate=True",
        null, 
        null,
        [PDO::SQLSRV_ATTR_ENCODING => PDO::SQLSRV_ENCODING_UTF8]
    );

    // Activamos las excepciones para ver errores si algo falla en el futuro
    $conn->setAttribute(PDO::ATTR_ERRMODE, PDO::ERRMODE_EXCEPTION);

    // Dejamos el comentario de que no imprima nada para no romper el JSON
    // Conexión establecida con éxito.
} catch (PDOException $e) {
    // Si falla, devolvemos el error en formato JSON para no romper el frontend
    die(json_encode(['success' => false, 'error' => 'Error de conexión BD: ' . $e->getMessage()]));
}
?>