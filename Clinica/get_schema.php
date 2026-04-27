<?php
require 'php/conexion.php';
$stmt = $conn->query("SELECT TABLE_NAME FROM INFORMATION_SCHEMA.TABLES WHERE TABLE_TYPE = 'BASE TABLE'");
$tables = [];
while($row = $stmt->fetch(PDO::FETCH_ASSOC)) {
    $tables[] = $row['TABLE_NAME'];
}
$schema = [];
foreach($tables as $table) {
    $stmt2 = $conn->query("SELECT COLUMN_NAME, DATA_TYPE FROM INFORMATION_SCHEMA.COLUMNS WHERE TABLE_NAME = '$table'");
    $columns = [];
    while($row2 = $stmt2->fetch(PDO::FETCH_ASSOC)) {
        $columns[] = $row2['COLUMN_NAME'] . " (" . $row2['DATA_TYPE'] . ")";
    }
    $schema[$table] = $columns;
}
file_put_contents('schema_utf8.json', json_encode($schema, JSON_PRETTY_PRINT));
?>
