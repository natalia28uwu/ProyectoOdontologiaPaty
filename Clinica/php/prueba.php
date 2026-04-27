<?php
$server = "natalia";
$database = "SistemaOdontologia";

try {
    $conn = new PDO("odbc:Driver={ODBC Driver 17 for SQL Server};Server=$server;Database=$database;Trusted_Connection=yes;");
    echo "¡FELICIDADES! Tu página ya está conectada a la base de datos.";
} catch (Exception $e) {
    echo "Todavía hay un error: " . $e->getMessage();
}
?>