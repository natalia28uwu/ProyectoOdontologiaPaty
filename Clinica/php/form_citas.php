<?php
include "conexion.php"; 

if ($_SERVER["REQUEST_METHOD"] == "POST") {
    $cedula      = $_POST['cedula'];
    $id_servicio = $_POST['id_servicio'];
    $id_doctor   = $_POST['id_doctor'];
    $fecha_input = $_POST['fecha_cita']; 
    $motivo      = $_POST['motivo'];
    $estado      = "Pendiente"; 

    // SOLUCIÓN AL ERROR DE CONVERSIÓN:
    // Cambiamos la 'T' que envía el navegador por un espacio para que SQL Server lo acepte
    $fecha_cita = str_replace("T", " ", $fecha_input);

    try {
        // 1. Validar si el paciente existe
        $check = $conn->prepare("SELECT COUNT(*) FROM Pacientes WHERE Cedula_paciente = :ced");
        $check->execute([':ced' => $cedula]);
        
        if ($check->fetchColumn() == 0) {
            echo "<script>
                alert('ERROR: La cédula $cedula no está registrada.');
                window.history.back();
            </script>";
            exit();
        }

        // 2. Insertar con la fecha ya formateada
        $sql = "INSERT INTO Citas (Fecha_citas, Estado_citas, Motivo_Cita, IdServicio, Cedula_paciente, IdDoctor) 
                VALUES (:fec, :est, :mot, :ser, :ced, :doc)";
        
        $stmt = $conn->prepare($sql);
        $stmt->execute([
            ':fec' => $fecha_cita, // Ahora lleva el formato 'YYYY-MM-DD HH:MM'
            ':est' => $estado,
            ':mot' => $motivo,
            ':ser' => $id_servicio,
            ':ced' => $cedula,
            ':doc' => $id_doctor
        ]);

        echo "<script>
                alert('¡Cita registrada con éxito!');
                window.location.href='../pags/solicitar_cita.php';
              </script>";

    } catch (PDOException $e) {
        echo "<div style='color:red; border:1px solid red; padding:20px; font-family:sans-serif;'>";
        echo "<strong>Error en la base de datos:</strong><br>" . $e->getMessage();
        echo "</div>";
    }
}
?>