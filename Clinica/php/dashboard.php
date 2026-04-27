<?php
session_start();
require_once __DIR__ . '/../php/conexion.php';

// Verificar si el paciente está logueado
if (!isset($_SESSION['cedula_paciente'])) {
    header("Location: ../pags/login.html");
    exit();
}

$cedula_paciente = $_SESSION['cedula_paciente'];

// 1. Obtener datos del paciente
$stmt = $conn->prepare("SELECT Nombre_paciente, Apellido_paciente, TipoPaciente FROM Pacientes WHERE Cedula_paciente = :cedula");
$stmt->execute(['cedula' => $cedula_paciente]);
$paciente = $stmt->fetch(PDO::FETCH_ASSOC);

$nombre_completo = $paciente ? $paciente['Nombre_paciente'] . ' ' . $paciente['Apellido_paciente'] : 'Paciente no encontrado';
$iniciales = $paciente ? substr($paciente['Nombre_paciente'], 0, 1) . substr($paciente['Apellido_paciente'], 0, 1) : 'NP';
$tipo_paciente = $paciente ? $paciente['TipoPaciente'] : '';

// 2. Próximo Pago (Total pendiente en cotizaciones o simplemente un valor simulado si no hay datos de pago)
// Aquí asumimos que balance está en cotizaciones asociadas a diagnostico->evaluacion->paciente
$stmtPago = $conn->prepare("
    SELECT ISNULL(SUM(c.Balance), 0) as TotalPendiente 
    FROM Cotizaciones c
    JOIN Diagnostico d ON c.IdDiagnostico = d.IdDiagnostico
    JOIN Evaluaciones e ON d.IdEvaluacion = e.IdEvaluacion
    WHERE e.Cedula_evaluacion = :cedula
");
$stmtPago->execute(['cedula' => $cedula_paciente]);
$pagoRow = $stmtPago->fetch(PDO::FETCH_ASSOC);
$proximo_pago = $pagoRow ? number_format($pagoRow['TotalPendiente'], 2) : '0.00';

// 3. Cantidad de Tratamientos
$stmtTrat = $conn->prepare("
    SELECT COUNT(*) as Total
    FROM Tratamientos t
    JOIN Citas c ON t.IdCita = c.IdCita
    WHERE c.Cedula_paciente = :cedula
");
$stmtTrat->execute(['cedula' => $cedula_paciente]);
$tratRow = $stmtTrat->fetch(PDO::FETCH_ASSOC);
$cantidad_tratamientos = $tratRow ? $tratRow['Total'] : 0;

// 4. Última Visita (mayor fecha en RecordClinico o Citas pasada)
$stmtVisita = $conn->prepare("
    SELECT TOP 1 Fecha_citas as Fecha 
    FROM Citas 
    WHERE Cedula_paciente = :cedula AND Fecha_citas <= GETDATE()
    ORDER BY Fecha_citas DESC
");
$stmtVisita->execute(['cedula' => $cedula_paciente]);
$visitaRow = $stmtVisita->fetch(PDO::FETCH_ASSOC);
$ultima_visita = 'N/A';
if ($visitaRow && $visitaRow['Fecha']) {
    $fechaObj = new DateTime($visitaRow['Fecha']);
    $ultima_visita = $fechaObj->format('d M, Y');
}

// 5. Historial de Tratamientos (Con Búsqueda)
$search = isset($_GET['search']) ? trim($_GET['search']) : '';
$historySql = "
    SELECT t.Nombre_tratamiento, c.Fecha_citas, d.Nombre_doctor, d.Apellido_doctor, c.Estado_citas
    FROM Tratamientos t
    JOIN Citas c ON t.IdCita = c.IdCita
    JOIN Doctores d ON t.IdDoctor = d.IdDoctor
    WHERE c.Cedula_paciente = :cedula
";
$params = ['cedula' => $cedula_paciente];

if ($search !== '') {
    $historySql .= " AND (t.Nombre_tratamiento LIKE :search OR CONVERT(VARCHAR, c.Fecha_citas, 103) LIKE :search)";
    $params['search'] = '%' . $search . '%';
}
$historySql .= " ORDER BY c.Fecha_citas DESC";

$stmtHistorial = $conn->prepare($historySql);
$stmtHistorial->execute($params);
$historial = $stmtHistorial->fetchAll(PDO::FETCH_ASSOC);

// Por si no hay historial porque no hay tratamientos registrados:
// Haremos un fallback a mostrar citas que ya pasaron
if (empty($historial) && empty($search)) {
    $stmtCitasPasadas = $conn->prepare("
        SELECT c.Motivo_Cita as Nombre_tratamiento, c.Fecha_citas, d.Nombre_doctor, d.Apellido_doctor, c.Estado_citas
        FROM Citas c
        JOIN Doctores d ON c.IdDoctor = d.IdDoctor
        WHERE c.Cedula_paciente = :cedula AND c.Fecha_citas <= GETDATE()
        ORDER BY c.Fecha_citas DESC
    ");
    $stmtCitasPasadas->execute(['cedula' => $cedula_paciente]);
    $historial = $stmtCitasPasadas->fetchAll(PDO::FETCH_ASSOC);
}

// 6. Próxima Cita
$stmtNextCita = $conn->prepare("
    SELECT TOP 1 c.Fecha_citas, c.Motivo_Cita, d.Nombre_doctor, d.Apellido_doctor
    FROM Citas c
    JOIN Doctores d ON c.IdDoctor = d.IdDoctor
    WHERE c.Cedula_paciente = :cedula AND c.Fecha_citas > GETDATE()
    ORDER BY c.Fecha_citas ASC
");
$stmtNextCita->execute(['cedula' => $cedula_paciente]);
$proxima_cita = $stmtNextCita->fetch(PDO::FETCH_ASSOC);

// 7. Información para el panel lateral (Último Récord Clínico)
$stmtRecord = $conn->prepare("
    SELECT TOP 1 Observaciones, Fecha
    FROM RecordClinico
    WHERE Cedula_paciente = :cedula
    ORDER BY Fecha DESC
");
$stmtRecord->execute(['cedula' => $cedula_paciente]);
$ultimo_record = $stmtRecord->fetch(PDO::FETCH_ASSOC);

// 8. Información para el panel lateral (Última Evaluación)
$stmtEval = $conn->prepare("
    SELECT TOP 1 Descripcion_evaluacion, Observacion_evaluacion, FechaEvaluacion
    FROM Evaluaciones
    WHERE Cedula_evaluacion = :cedula
    ORDER BY FechaEvaluacion DESC
");
$stmtEval->execute(['cedula' => $cedula_paciente]);
$ultima_eval = $stmtEval->fetch(PDO::FETCH_ASSOC);

// Control de navegación
$vista_actual = isset($_GET['view']) ? $_GET['view'] : 'inicio';

// Establecemos configuración de idioma para fechas
setlocale(LC_TIME, 'es_ES.UTF-8', 'es_ES', 'esp');
?>
<!DOCTYPE html>
<html lang="es">

<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title id="page-title">Portal del Paciente - Los Prados</title>
    <link
        href="https://fonts.googleapis.com/css2?family=Montserrat:wght@300;400;500;600;700&family=Playfair+Display:ital,wght@0,400;0,700;1,400&display=swap"
        rel="stylesheet">
    <style>
        :root {
            --bg-color: #F4F7F9;
            --sidebar-bg: #FFFFFF;
            --oro: #37657a;
            --oro-light: #5a9cb8;
            --oro-fade: rgba(55, 101, 122, 0.1);
            --black: #1A1C1E;
            --text-main: #2C3539;
            --text-muted: #7A8B94;
            --white: #FFFFFF;
            --card-shadow: 0 10px 30px rgba(0, 0, 0, 0.03);
            --transition: all 0.3s cubic-bezier(0.4, 0, 0.2, 1);
            --font-main: 'Montserrat', sans-serif;
            --font-alt: 'Playfair Display', serif;
            --radius-lg: 20px;
            --radius-md: 12px;
            --radius-sm: 8px;
        }

        * {
            margin: 0;
            padding: 0;
            box-sizing: border-box;
        }

        body {
            font-family: var(--font-main);
            background-color: var(--bg-color);
            color: var(--text-main);
            display: flex;
            min-height: 100vh;
            overflow-x: hidden;
            -webkit-font-smoothing: antialiased;
        }

        /* --- Sidebar --- */
        .sidebar {
            width: 280px;
            background: var(--sidebar-bg);
            border-right: 1px solid rgba(0, 0, 0, 0.04);
            display: flex;
            flex-direction: column;
            position: fixed;
            height: 100vh;
            z-index: 100;
            transition: var(--transition);
        }

        .brand {
            padding: 40px 30px;
            display: flex;
            align-items: center;
            gap: 12px;
        }

        .brand-icon {
            width: 40px;
            height: 40px;
            border-radius: 10px;
            background: linear-gradient(135deg, var(--oro), var(--oro-light));
            display: flex;
            align-items: center;
            justify-content: center;
            color: white;
            box-shadow: 0 4px 15px rgba(55, 101, 122, 0.3);
        }

        .brand-text {
            display: flex;
            flex-direction: column;
        }

        .brand-main {
            font-size: 0.75rem;
            font-weight: 700;
            letter-spacing: 1px;
            color: var(--text-main);
            text-transform: uppercase;
        }

        .brand-accent {
            font-family: var(--font-alt);
            font-size: 1.1rem;
            font-weight: 700;
            color: var(--oro);
            letter-spacing: 1px;
        }

        .nav-menu {
            flex: 1;
            padding: 0 20px;
            display: flex;
            flex-direction: column;
            gap: 8px;
        }

        .nav-item {
            display: flex;
            align-items: center;
            gap: 14px;
            padding: 14px 20px;
            color: var(--text-muted);
            text-decoration: none;
            font-size: 0.9rem;
            font-weight: 500;
            border-radius: var(--radius-md);
            transition: var(--transition);
        }

        .nav-item svg {
            width: 20px;
            height: 20px;
            stroke: currentColor;
            fill: none;
            stroke-width: 2;
            stroke-linecap: round;
            stroke-linejoin: round;
            transition: var(--transition);
        }

        .nav-item:hover {
            background: var(--oro-fade);
            color: var(--oro);
            transform: translateX(4px);
        }

        .nav-item.active {
            background: linear-gradient(135deg, var(--oro), var(--oro-light));
            color: var(--white);
            box-shadow: 0 4px 15px rgba(55, 101, 122, 0.25);
        }

        .sidebar-footer {
            padding: 30px 20px;
            border-top: 1px solid rgba(0, 0, 0, 0.04);
        }

        .logout-btn {
            display: flex;
            align-items: center;
            gap: 12px;
            width: 100%;
            padding: 12px 20px;
            background: rgba(231, 76, 60, 0.08);
            color: #e74c3c;
            border: none;
            border-radius: var(--radius-md);
            font-family: var(--font-main);
            font-size: 0.9rem;
            font-weight: 600;
            cursor: pointer;
            transition: var(--transition);
        }

        .logout-btn:hover {
            background: rgba(231, 76, 60, 0.15);
        }

        /* --- Main Content --- */
        .main-content {
            flex: 1;
            margin-left: 280px;
            display: flex;
            flex-direction: column;
            min-height: 100vh;
        }

        .top-nav {
            height: 80px;
            background: var(--white);
            border-bottom: 1px solid rgba(0, 0, 0, 0.04);
            display: flex;
            align-items: center;
            justify-content: space-between;
            padding: 0 40px;
            position: sticky;
            top: 0;
            z-index: 90;
        }

        .search-bar {
            position: relative;
            width: 300px;
        }

        .search-bar input {
            width: 100%;
            padding: 12px 20px 12px 45px;
            border: 1px solid rgba(0, 0, 0, 0.08);
            border-radius: 50px;
            background: var(--bg-color);
            font-family: var(--font-main);
            font-size: 0.9rem;
            color: var(--text-main);
            transition: var(--transition);
        }

        .search-bar input:focus {
            outline: none;
            border-color: var(--oro-light);
            background: var(--white);
            box-shadow: 0 0 0 3px var(--oro-fade);
        }

        .search-bar svg {
            position: absolute;
            left: 16px;
            top: 50%;
            transform: translateY(-50%);
            color: var(--text-muted);
            width: 18px;
            height: 18px;
        }

        .user-profile {
            display: flex;
            align-items: center;
            gap: 20px;
        }

        .notification {
            position: relative;
            cursor: pointer;
            color: var(--text-muted);
            transition: var(--transition);
        }

        .notification:hover {
            color: var(--oro);
        }

        .notification .badge {
            position: absolute;
            top: -4px;
            right: -4px;
            width: 8px;
            height: 8px;
            background: #e74c3c;
            border-radius: 50%;
            border: 2px solid var(--white);
        }

        .profile-btn {
            display: flex;
            align-items: center;
            gap: 12px;
            cursor: pointer;
        }

        .avatar {
            width: 44px;
            height: 44px;
            border-radius: 50%;
            background: var(--oro-fade);
            display: flex;
            align-items: center;
            justify-content: center;
            color: var(--oro);
            font-weight: 700;
            font-size: 1.1rem;
            font-family: var(--font-alt);
        }

        .profile-info {
            display: flex;
            flex-direction: column;
        }

        .profile-name {
            font-size: 0.9rem;
            font-weight: 600;
            color: var(--text-main);
        }

        .profile-role {
            font-size: 0.75rem;
            color: var(--text-muted);
        }

        /* --- Dashboard Area --- */
        .dashboard-container {
            padding: 40px;
            max-width: 1400px;
            margin: 0 auto;
            width: 100%;
        }

        .welcome-banner {
            background: linear-gradient(135deg, var(--oro), var(--oro-light));
            border-radius: var(--radius-lg);
            padding: 40px;
            color: var(--white);
            display: flex;
            justify-content: space-between;
            align-items: center;
            margin-bottom: 40px;
            box-shadow: 0 15px 40px rgba(55, 101, 122, 0.2);
            position: relative;
            overflow: hidden;
        }

        .welcome-banner::after {
            content: '';
            position: absolute;
            right: -50px;
            top: -50px;
            width: 300px;
            height: 300px;
            background: rgba(255, 255, 255, 0.1);
            border-radius: 50%;
            filter: blur(20px);
        }

        .welcome-banner::before {
            content: '';
            position: absolute;
            right: 150px;
            bottom: -50px;
            width: 200px;
            height: 200px;
            background: rgba(255, 255, 255, 0.05);
            border-radius: 50%;
            filter: blur(10px);
        }

        .welcome-text {
            position: relative;
            z-index: 2;
        }

        .welcome-text h1 {
            font-family: var(--font-alt);
            font-size: 2.2rem;
            margin-bottom: 8px;
            font-weight: 600;
        }

        .welcome-text p {
            font-size: 1rem;
            opacity: 0.9;
            max-width: 500px;
            line-height: 1.5;
        }

        .welcome-image svg {
            width: 160px;
            height: auto;
            position: relative;
            z-index: 2;
            opacity: 0.9;
        }

        /* --- Stats Grid --- */
        .stats-grid {
            display: grid;
            grid-template-columns: repeat(3, 1fr);
            gap: 24px;
            margin-bottom: 40px;
        }

        .stat-card {
            background: var(--white);
            border-radius: var(--radius-lg);
            padding: 30px;
            display: flex;
            align-items: flex-start;
            gap: 20px;
            box-shadow: var(--card-shadow);
            transition: var(--transition);
            border: 1px solid rgba(0, 0, 0, 0.02);
        }

        .stat-card:hover {
            transform: translateY(-5px);
            box-shadow: 0 15px 35px rgba(0, 0, 0, 0.06);
            border-color: var(--oro-fade);
        }

        .stat-icon {
            width: 54px;
            height: 54px;
            border-radius: 14px;
            display: flex;
            align-items: center;
            justify-content: center;
            flex-shrink: 0;
        }

        .stat-icon.blue {
            background: rgba(55, 101, 122, 0.1);
            color: var(--oro);
        }

        .stat-icon.green {
            background: rgba(46, 204, 113, 0.1);
            color: #2ecc71;
        }

        .stat-icon.purple {
            background: rgba(155, 89, 182, 0.1);
            color: #9b59b6;
        }

        .stat-info h3 {
            font-size: 0.9rem;
            color: var(--text-muted);
            font-weight: 500;
            margin-bottom: 8px;
        }

        .stat-info .value {
            font-family: var(--font-alt);
            font-size: 2rem;
            font-weight: 700;
            color: var(--text-main);
            line-height: 1;
        }

        /* --- Main Dashboard Sections --- */
        .content-grid {
            display: grid;
            grid-template-columns: 1fr 400px;
            gap: 30px;
        }

        .card {
            background: var(--white);
            border-radius: var(--radius-lg);
            box-shadow: var(--card-shadow);
            border: 1px solid rgba(0, 0, 0, 0.02);
            overflow: hidden;
        }

        .card-header {
            padding: 24px 30px;
            border-bottom: 1px solid rgba(0, 0, 0, 0.04);
            display: flex;
            justify-content: space-between;
            align-items: center;
        }

        .card-header h2 {
            font-family: var(--font-alt);
            font-size: 1.4rem;
            color: var(--text-main);
            font-weight: 600;
        }

        .btn-ghost {
            color: var(--oro);
            text-decoration: none;
            font-size: 0.85rem;
            font-weight: 600;
            transition: var(--transition);
        }

        .btn-ghost:hover {
            color: var(--oro-light);
            text-decoration: underline;
        }

        .card-body {
            padding: 30px;
        }

        /* History Table */
        .history-table {
            width: 100%;
            border-collapse: collapse;
        }

        .history-table th {
            text-align: left;
            padding-bottom: 15px;
            font-size: 0.8rem;
            text-transform: uppercase;
            color: var(--text-muted);
            letter-spacing: 1px;
            border-bottom: 1px solid rgba(0, 0, 0, 0.06);
            font-weight: 600;
        }

        .history-table td {
            padding: 20px 0;
            border-bottom: 1px solid rgba(0, 0, 0, 0.04);
            vertical-align: middle;
        }

        .history-table tr:last-child td {
            border-bottom: none;
            padding-bottom: 0;
        }

        .treatment-name {
            font-weight: 600;
            color: var(--text-main);
            margin-bottom: 4px;
            display: block;
        }

        .doctor-name {
            font-size: 0.8rem;
            color: var(--text-muted);
        }

        .status-badge {
            display: inline-block;
            padding: 6px 12px;
            border-radius: 50px;
            font-size: 0.75rem;
            font-weight: 600;
        }

        .status-badge.completed {
            background: rgba(46, 204, 113, 0.1);
            color: #27ae60;
        }

        .status-badge.pending {
            background: rgba(241, 196, 15, 0.1);
            color: #d35400;
        }

        /* Next Appointment Card */
        .appointment-card {
            background: linear-gradient(to bottom right, var(--white), #fdfdfd);
            border: 1px solid rgba(55, 101, 122, 0.1);
            border-radius: var(--radius-md);
            padding: 25px;
            margin-top: 10px;
            text-align: center;
            position: relative;
            overflow: hidden;
        }

        .appointment-card::before {
            content: '';
            position: absolute;
            top: 0;
            left: 0;
            width: 4px;
            height: 100%;
            background: var(--oro);
        }

        .appt-date {
            margin-bottom: 20px;
        }

        .appt-day {
            font-size: 0.9rem;
            color: var(--oro);
            font-weight: 600;
            text-transform: uppercase;
            letter-spacing: 2px;
            margin-bottom: 5px;
            display: block;
        }

        .appt-time {
            font-family: var(--font-alt);
            font-size: 2.2rem;
            color: var(--text-main);
            font-weight: 700;
        }

        .appt-details {
            border-top: 1px dashed rgba(0, 0, 0, 0.1);
            padding-top: 20px;
            margin-bottom: 25px;
        }

        .appt-details p {
            font-size: 0.95rem;
            color: var(--text-main);
            font-weight: 500;
            margin-bottom: 5px;
        }

        .appt-details span {
            font-size: 0.85rem;
            color: var(--text-muted);
        }

        .btn-primary {
            display: inline-block;
            padding: 14px 28px;
            background: var(--oro);
            color: var(--white);
            text-decoration: none;
            border-radius: 50px;
            font-size: 0.85rem;
            font-weight: 600;
            text-transform: uppercase;
            letter-spacing: 1px;
            transition: var(--transition);
            border: none;
            cursor: pointer;
            width: 100%;
        }

        .btn-primary:hover {
            background: var(--oro-light);
            transform: translateY(-2px);
            box-shadow: 0 6px 20px rgba(55, 101, 122, 0.3);
        }

        /* Info Item */
        .info-list {
            display: flex;
            flex-direction: column;
            gap: 20px;
        }

        .info-item {
            display: flex;
            align-items: flex-start;
            gap: 15px;
        }

        .info-icon {
            width: 40px;
            height: 40px;
            border-radius: 10px;
            background: var(--bg-color);
            display: flex;
            align-items: center;
            justify-content: center;
            color: var(--oro);
            flex-shrink: 0;
        }

        .info-text h4 {
            font-size: 0.9rem;
            color: var(--text-main);
            font-weight: 600;
            margin-bottom: 4px;
        }

        .info-text p {
            font-size: 0.85rem;
            color: var(--text-muted);
            line-height: 1.5;
        }

        @media (max-width: 1024px) {
            .content-grid {
                grid-template-columns: 1fr;
            }
        }
    </style>
</head>

<body>

    <!-- Sidebar -->
    <aside class="sidebar">
        <div class="brand">
            <div class="brand-icon">
                <svg width="24" height="24" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"
                    stroke-linecap="round" stroke-linejoin="round">
                    <path
                        d="M12 2C8 2 5 4 5 7.2C5 10.3 6.8 11.8 6.8 15C6.8 18.5 5 22.5 5.8 26C6.6 29.5 8.2 29.5 9.4 29.5C10.6 29.5 11 28 12 24.5C13 28 13.4 29.5 14.6 29.5C15.8 29.5 17.4 29.5 18.2 26C19 22.5 17.2 18.5 17.2 15C17.2 11.8 19 10.3 19 7.2C19 4 16 2 12 2Z"
                        transform="scale(0.8) translate(3, -2)" />
                </svg>
            </div>
            <div class="brand-text">
                <span class="brand-main">Centro Odontológico</span>
                <span class="brand-accent">Los Prados</span>
            </div>
        </div>

        <nav class="nav-menu">
            <a href="?view=inicio" class="nav-item <?php echo $vista_actual === 'inicio' ? 'active' : ''; ?>">
                <svg viewBox="0 0 24 24">
                    <path d="M3 9l9-7 9 7v11a2 2 0 0 1-2 2H5a2 2 0 0 1-2-2z"></path>
                    <polyline points="9 22 9 12 15 12 15 22"></polyline>
                </svg>
                Inicio
            </a>
            <a href="?view=citas" class="nav-item <?php echo $vista_actual === 'citas' ? 'active' : ''; ?>">
                <svg viewBox="0 0 24 24">
                    <rect x="3" y="4" width="18" height="18" rx="2" ry="2"></rect>
                    <line x1="16" y1="2" x2="16" y2="6"></line>
                    <line x1="8" y1="2" x2="8" y2="6"></line>
                    <line x1="3" y1="10" x2="21" y2="10"></line>
                </svg>
                Mis Citas
            </a>
            <a href="?view=historial" class="nav-item <?php echo $vista_actual === 'historial' ? 'active' : ''; ?>">
                <svg viewBox="0 0 24 24">
                    <path d="M14 2H6a2 2 0 0 0-2 2v16a2 2 0 0 0 2 2h12a2 2 0 0 0 2-2V8z"></path>
                    <polyline points="14 2 14 8 20 8"></polyline>
                    <line x1="16" y1="13" x2="8" y2="13"></line>
                    <line x1="16" y1="17" x2="8" y2="17"></line>
                    <polyline points="10 9 9 9 8 9"></polyline>
                </svg>
                Historial
            </a>
            <a href="?view=facturacion" class="nav-item <?php echo $vista_actual === 'facturacion' ? 'active' : ''; ?>">
                <svg viewBox="0 0 24 24">
                    <rect x="2" y="5" width="20" height="14" rx="2"></rect>
                    <line x1="2" y1="10" x2="22" y2="10"></line>
                </svg>
                Facturación
            </a>
        </nav>

        <div class="sidebar-footer">
            <button class="logout-btn" onclick="window.location.href='logout.php';">
                <svg viewBox="0 0 24 24" width="18" height="18" fill="none" stroke="currentColor" stroke-width="2"
                    stroke-linecap="round" stroke-linejoin="round">
                    <path d="M9 21H5a2 2 0 0 1-2-2V5a2 2 0 0 1 2-2h4"></path>
                    <polyline points="16 17 21 12 16 7"></polyline>
                    <line x1="21" y1="12" x2="9" y2="12"></line>
                </svg>
                Cerrar sesión
            </button>
        </div>
    </aside>

    <!-- Main Content -->
    <main class="main-content">
        <!-- Top Nav -->
        <header class="top-nav">
            <form method="GET" class="search-bar" action="dashboard.php">
                <button type="submit" style="background: none; border: none; padding: 0; margin: 0; outline: none; cursor: pointer; position: absolute; left: 16px; top: 50%; transform: translateY(-50%); color: var(--text-muted); display: flex;">
                    <svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round" width="18" height="18">
                        <circle cx="11" cy="11" r="8"></circle>
                        <line x1="21" y1="21" x2="16.65" y2="16.65"></line>
                    </svg>
                </button>
                <input type="text" name="search" value="<?php echo htmlspecialchars($search); ?>" placeholder="Buscar tratamientos, fechas...">
            </form>

            <div class="user-profile">
                <!-- Se eliminó el botón de notificaciones y ajustes según solicitud -->
                <div class="profile-btn">
                    <div class="avatar"><?php echo $iniciales; ?></div>
                    <div class="profile-info">
                        <span class="profile-name" id="user-profile-name"><?php echo $nombre_completo; ?></span>
                        <span class="profile-role" id="user-profile-role">Paciente</span>
                    </div>
                </div>
            </div>
        </header>

        <!-- Dashboard Dashboard -->
        <div class="dashboard-container">

            <?php if ($vista_actual === 'inicio' || $vista_actual === ''): ?>
            <!-- Welcome Banner -->
            <div class="welcome-banner">
                <div class="welcome-text">
                    <h1>¡Hola, <?php echo $paciente['Nombre_paciente']; ?>!</h1>
                    <p>Bienvenido/a a tu portal de paciente de Los Prados. Aquí puedes gestionar tus citas, revisar tu
                        historial clínico y ver tus planes de tratamiento.</p>
                </div>
                <!-- Abstract decorative illustration -->
                <div class="welcome-image">
                    <svg viewBox="0 0 200 200" fill="none" xmlns="http://www.w3.org/2000/svg">
                        <path
                            d="M100 0C44.7715 0 0 44.7715 0 100C0 155.228 44.7715 200 100 200C155.228 200 200 155.228 200 100C200 44.7715 155.228 0 100 0Z"
                            fill="white" fill-opacity="0.1" />
                        <path
                            d="M80 50C52.3858 50 30 72.3858 30 100C30 127.614 52.3858 150 80 150C107.614 150 130 127.614 130 100C130 72.3858 107.614 50 80 50Z"
                            stroke="white" stroke-opacity="0.3" stroke-width="4" />
                        <circle cx="140" cy="70" r="15" fill="white" fill-opacity="0.4" />
                        <circle cx="60" cy="140" r="8" fill="white" fill-opacity="0.6" />
                    </svg>
                </div>
            </div>

            <!-- Stats -->
            <div class="stats-grid">
                <div class="stat-card">
                    <div class="stat-icon blue">
                        <svg viewBox="0 0 24 24" width="28" height="28" fill="none" stroke="currentColor"
                            stroke-width="2">
                            <path d="M12 2v20M17 5H9.5a3.5 3.5 0 0 0 0 7h5a3.5 3.5 0 0 1 0 7H6" />
                        </svg>
                    </div>
                    <div class="stat-info">
                        <h3 id="stat-payment-label">Próximo Pago</h3>
                        <div class="value" id="stat-payment-value">RD$ <?php echo $proximo_pago; ?></div>
                    </div>
                </div>
                <div class="stat-card">
                    <div class="stat-icon green">
                        <svg viewBox="0 0 24 24" width="28" height="28" fill="none" stroke="currentColor"
                            stroke-width="2">
                            <path d="M22 11.08V12a10 10 0 1 1-5.93-9.14" />
                            <polyline points="22 4 12 14.01 9 11.01" />
                        </svg>
                    </div>
                    <div class="stat-info">
                        <h3 id="stat-treatments-label">Tratamientos</h3>
                        <div class="value" id="stat-treatments-value"><?php echo $cantidad_tratamientos; ?></div>
                    </div>
                </div>
                <div class="stat-card">
                    <div class="stat-icon purple">
                        <svg viewBox="0 0 24 24" width="28" height="28" fill="none" stroke="currentColor"
                            stroke-width="2">
                            <circle cx="12" cy="12" r="10" />
                            <polyline points="12 6 12 12 16 14" />
                        </svg>
                    </div>
                    <div class="stat-info">
                        <h3 id="stat-visit-label">Última Visita</h3>
                        <div class="value" style="font-size:1.4rem; padding-top:8px;" id="stat-visit-value"><?php echo htmlspecialchars($ultima_visita); ?></div>
                    </div>
                </div>
            </div>

            <!-- Main Content Area -->
            <div class="content-grid">

                <!-- History Table -->
                <div class="card">
                    <div class="card-header">
                        <h2>Historial de Tratamientos</h2>
                        <a href="?view=historial" class="btn-ghost">Ver todo</a>
                    </div>
                    <div class="card-body">
                        <table class="history-table">
                            <thead>
                                <tr>
                                    <th>Tratamiento</th>
                                    <th>Fecha</th>
                                    <th>Estado</th>
                                </tr>
                            </thead>
                            <tbody id="treatment-history-body">
<?php if (!empty($historial)): ?>
    <?php foreach ($historial as $row): 
        $fechaFormateada = date("d C M, Y", strtotime($row['Fecha_citas']));
        $fechaFormateada = str_replace([' C ', 'Jan', 'Feb', 'Mar', 'Apr', 'May', 'Jun', 'Jul', 'Aug', 'Sep', 'Oct', 'Nov', 'Dec'], [' ', 'Ene', 'Feb', 'Mar', 'Abr', 'May', 'Jun', 'Jul', 'Ago', 'Sep', 'Oct', 'Nov', 'Dic'], $fechaFormateada);
        // Asignar clase de estado según valor
        $estado = strtolower($row['Estado_citas']);
        $estadoClass = strpos($estado, 'completado') !== false ? 'completed' : 'pending';
    ?>
    <tr>
        <td>
            <span class="treatment-name"><?php echo htmlspecialchars($row['Nombre_tratamiento']); ?></span>
            <span class="doctor-name">Dr/a. <?php echo htmlspecialchars($row['Nombre_doctor'] . ' ' . $row['Apellido_doctor']); ?></span>
        </td>
        <td><?php echo htmlspecialchars($fechaFormateada); ?></td>
        <td><span class="status-badge <?php echo $estadoClass; ?>"><?php echo htmlspecialchars(ucfirst($row['Estado_citas'])); ?></span></td>
    </tr>
    <?php endforeach; ?>
<?php else: ?>
    <tr>
        <td colspan="3" style="text-align: center; color: var(--text-muted);">No se encontraron tratamientos.</td>
    </tr>
<?php endif; ?>
</tbody>
                        </table>
                    </div>
                </div>

                <!-- Right Side panel -->
                <div style="display:flex; flex-direction:column; gap:30px;">
                    <!-- Next Appointment -->
                    <div class="card">
                        <div class="card-header">
                            <h2>Próxima Cita</h2>
                        </div>
                        <div class="card-body">
                            <?php if ($proxima_cita): 
    $fechaCita = new DateTime($proxima_cita['Fecha_citas']);
    // Días y meses en español
    $dias = ['Domingo', 'Lunes', 'Martes', 'Miércoles', 'Jueves', 'Viernes', 'Sábado'];
    $meses = ['Ene', 'Feb', 'Mar', 'Abr', 'May', 'Jun', 'Jul', 'Ago', 'Sep', 'Oct', 'Nov', 'Dic'];
    $dia = $dias[(int)$fechaCita->format('w')];
    $mes = $meses[(int)$fechaCita->format('n') - 1];
    $fechaTxt = $fechaCita->format('d ') . $mes;
    $horaTxT = $fechaCita->format('h:i A');
?>
<div class="appointment-card">
    <div class="appt-date">
        <span class="appt-day" id="next-appt-day"><?php echo htmlspecialchars($dia . ', ' . $fechaTxt); ?></span>
        <div class="appt-time" id="next-appt-time"><?php echo htmlspecialchars($horaTxT); ?></div>
    </div>
    <div class="appt-details">
        <p id="next-appt-desc"><?php echo htmlspecialchars($proxima_cita['Motivo_Cita']); ?></p>
        <span id="next-appt-doctor">Con Dr/a. <?php echo htmlspecialchars($proxima_cita['Nombre_doctor'] . ' ' . $proxima_cita['Apellido_doctor']); ?></span>
    </div>
    <!-- <button class="btn-primary">Reagendar</button> -->
</div>
<?php else: ?>
<div class="appointment-card">
    <div class="appt-date">
        <span class="appt-day">Sin cita pendiente</span>
        <div class="appt-time">--:--</div>
    </div>
    <div class="appt-details">
        <p>No tienes próximas citas programadas.</p>
    </div>
</div>
<?php endif; ?>
                        </div>
                    </div>

                    <!-- Informational Box -->
                    <div class="card">
                        <div class="card-body">
                            <div class="info-list">
                                <div class="info-item">
                                    <div class="info-icon">
                                        <svg viewBox="0 0 24 24" width="20" height="20" fill="none"
                                            stroke="currentColor" stroke-width="2">
                                            <path d="M21 15v4a2 2 0 0 1-2 2H5a2 2 0 0 1-2-2v-4" />
                                            <polyline points="17 8 12 3 7 8" />
                                            <line x1="12" y1="3" x2="12" y2="15" />
                                        </svg>
                                    </div>
                                    <div class="info-text">
                                        <h4>Último Récord Clínico</h4>
                                        <p><?php echo $ultimo_record ? htmlspecialchars($ultimo_record['Observaciones']) : 'No hay observaciones registradas aún.'; ?></p>
                                    </div>
                                </div>
                                <div class="info-item">
                                    <div class="info-icon">
                                        <svg viewBox="0 0 24 24" width="20" height="20" fill="none"
                                            stroke="currentColor" stroke-width="2">
                                            <path
                                                d="M10.29 3.86L1.82 18a2 2 0 0 0 1.71 3h16.94a2 2 0 0 0 1.71-3L13.71 3.86a2 2 0 0 0-3.42 0z" />
                                            <line x1="12" y1="9" x2="12" y2="13" />
                                            <line x1="12" y1="17" x2="12.01" y2="17" />
                                        </svg>
                                    </div>
                                    <div class="info-text">
                                        <h4>Última Evaluación</h4>
                                        <p><?php echo $ultima_eval ? htmlspecialchars($ultima_eval['Descripcion_evaluacion']) . ' - ' . htmlspecialchars($ultima_eval['Observacion_evaluacion']) : 'Sin evaluación reciente.'; ?></p>
                                    </div>
                                </div>
                            </div>
                        </div>
                    </div>
                </div>

            </div>

            <?php elseif ($vista_actual === 'citas'): 
                $stmtTodasCitas = $conn->prepare("
                    SELECT c.Fecha_citas, c.Motivo_Cita, d.Nombre_doctor, d.Apellido_doctor, c.Estado_citas
                    FROM Citas c
                    JOIN Doctores d ON c.IdDoctor = d.IdDoctor
                    WHERE c.Cedula_paciente = :cedula
                    ORDER BY c.Fecha_citas DESC
                ");
                $stmtTodasCitas->execute(['cedula' => $cedula_paciente]);
                $todas_citas = $stmtTodasCitas->fetchAll(PDO::FETCH_ASSOC);
            ?>
            <div class="card">
                <div class="card-header">
                    <h2>Mis Citas</h2>
                </div>
                <div class="card-body">
                    <table class="history-table">
                        <thead>
                            <tr>
                                <th>Fecha y Hora</th>
                                <th>Motivo de Cita</th>
                                <th>Doctor</th>
                                <th>Estado</th>
                            </tr>
                        </thead>
                        <tbody>
                            <?php if (!empty($todas_citas)): ?>
                                <?php foreach ($todas_citas as $cita): 
                                    $fechaCita = new DateTime($cita['Fecha_citas']);
                                    $estado = strtolower($cita['Estado_citas']);
                                    $estadoClass = strpos($estado, 'completado') !== false ? 'completed' : 'pending';
                                ?>
                                <tr>
                                    <td><?php echo htmlspecialchars($fechaCita->format('d/m/Y h:i A')); ?></td>
                                    <td><span class="treatment-name"><?php echo htmlspecialchars($cita['Motivo_Cita']); ?></span></td>
                                    <td>Dr/a. <?php echo htmlspecialchars($cita['Nombre_doctor'] . ' ' . $cita['Apellido_doctor']); ?></td>
                                    <td><span class="status-badge <?php echo $estadoClass; ?>"><?php echo htmlspecialchars(ucfirst($cita['Estado_citas'])); ?></span></td>
                                </tr>
                                <?php endforeach; ?>
                            <?php else: ?>
                                <tr><td colspan="4" style="text-align: center; color: var(--text-muted); padding: 30px;">No tienes citas registradas en el sistema.</td></tr>
                            <?php endif; ?>
                        </tbody>
                    </table>
                </div>
            </div>

            <?php elseif ($vista_actual === 'historial'): 
                $stmtRecordHis = $conn->prepare("
                    SELECT Fecha, Observaciones
                    FROM RecordClinico
                    WHERE Cedula_paciente = :cedula
                    ORDER BY Fecha DESC
                ");
                $stmtRecordHis->execute(['cedula' => $cedula_paciente]);
                $records_historial = $stmtRecordHis->fetchAll(PDO::FETCH_ASSOC);
            ?>
            <div class="card">
                <div class="card-header">
                    <h2>Historial Clínico (Récord)</h2>
                </div>
                <div class="card-body">
                    <table class="history-table">
                        <thead>
                            <tr>
                                <th style="width: 20%;">Fecha del Récord</th>
                                <th>Observaciones Clínicas</th>
                            </tr>
                        </thead>
                        <tbody>
                            <?php if (!empty($records_historial)): ?>
                                <?php foreach ($records_historial as $rec): 
                                    $fechaRec = new DateTime($rec['Fecha']);
                                ?>
                                <tr>
                                    <td><?php echo htmlspecialchars($fechaRec->format('d M, Y')); ?></td>
                                    <td><?php echo nl2br(htmlspecialchars($rec['Observaciones'])); ?></td>
                                </tr>
                                <?php endforeach; ?>
                            <?php else: ?>
                                <tr><td colspan="2" style="text-align: center; color: var(--text-muted); padding: 30px;">No se encontraron récords clínicos en tu historial.</td></tr>
                            <?php endif; ?>
                        </tbody>
                    </table>
                </div>
            </div>

            <?php elseif ($vista_actual === 'facturacion'): 
                // Selecciona desde Cotizaciones para los datos de facturación
                $stmtFacturas = $conn->prepare("
                    SELECT c.* 
                    FROM Cotizaciones c
                    JOIN Diagnostico d ON c.IdDiagnostico = d.IdDiagnostico
                    JOIN Evaluaciones e ON d.IdEvaluacion = e.IdEvaluacion
                    WHERE e.Cedula_evaluacion = :cedula
                ");
                $stmtFacturas->execute(['cedula' => $cedula_paciente]);
                $facturas = $stmtFacturas->fetchAll(PDO::FETCH_ASSOC);
            ?>
            <div class="card">
                <div class="card-header">
                    <h2>Facturación y Cotizaciones</h2>
                </div>
                <div class="card-body">
                    <div style="overflow-x: auto;">
                        <table class="history-table" style="min-width: 800px;">
                            <thead>
                                <tr>
                                    <?php if(!empty($facturas)): ?>
                                        <?php foreach(array_keys($facturas[0]) as $key): ?>
                                            <th><?php echo htmlspecialchars(ucfirst(str_replace('_', ' ', $key))); ?></th>
                                        <?php endforeach; ?>
                                    <?php else: ?>
                                        <th>Información de Facturación</th>
                                    <?php endif; ?>
                                </tr>
                            </thead>
                            <tbody>
                                <?php if (!empty($facturas)): ?>
                                    <?php foreach ($facturas as $fila): ?>
                                    <tr>
                                        <?php foreach ($fila as $k => $val): ?>
                                            <td>
                                                <?php 
                                                    if (is_numeric($val) && strpos(strtolower($k), 'id') === false && strpos(strtolower($k), 'telefono') === false && strpos(strtolower($k), 'cedula') === false) {
                                                        echo 'RD$ ' . number_format((float)$val, 2);
                                                    } else {
                                                        if (!is_null($val) && preg_match('/^\d{4}-\d{2}-\d{2}/', $val)) {
                                                            $tdFecha = new DateTime($val);
                                                            echo htmlspecialchars($tdFecha->format('d M, Y'));
                                                        } else {
                                                            echo htmlspecialchars((string)$val); 
                                                        }
                                                    }
                                                ?>
                                            </td>
                                        <?php endforeach; ?>
                                    </tr>
                                    <?php endforeach; ?>
                                <?php else: ?>
                                    <tr><td style="text-align: center; color: var(--text-muted); padding:30px;">No hay registros de facturación o cotizaciones pendientes.</td></tr>
                                <?php endif; ?>
                            </tbody>
                        </table>
                    </div>
                </div>
            </div>
            
            <?php else: ?>
            <div class="card">
                <div class="card-body">
                    <p>Vista no encontrada.</p>
                </div>
            </div>
            <?php endif; ?>

        </div>
    </main>

</body>

</html>