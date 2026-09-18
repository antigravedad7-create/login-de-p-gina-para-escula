<?php
session_start();

// Autenticación usando sólo la base de datos
require_once __DIR__ . '/../config/db.php';

if (!isset($pdo) || !$pdo) {
    // No hay conexión BD: informar al usuario
    header('Location: ../courses/index.php?error=db');
    exit();
}

// Asegurar la existencia de la tabla y columnas necesarias
try {
    $pdo->exec("CREATE TABLE IF NOT EXISTS users (
        id INT AUTO_INCREMENT PRIMARY KEY,
        username VARCHAR(100) UNIQUE,
        name VARCHAR(255) DEFAULT NULL,
        email VARCHAR(255) DEFAULT NULL,
        password VARCHAR(255) DEFAULT NULL,
        role VARCHAR(50) DEFAULT 'estudiante',
        cedula VARCHAR(50) DEFAULT NULL,
        telefono VARCHAR(50) DEFAULT NULL,
        grado VARCHAR(50) DEFAULT NULL,
        seccion VARCHAR(50) DEFAULT NULL,
        recovery_question TEXT DEFAULT NULL,
        status VARCHAR(20) DEFAULT 'pending'
    ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4");
} catch (Exception $e) {
    // ignorar
}

// Asegurar existencia de admin
try {
    $stmt = $pdo->prepare('SELECT COUNT(*) FROM users WHERE username = :u');
    $stmt->execute([':u' => 'admin']);
    if ($stmt->fetchColumn() == 0) {
        $hash = password_hash('Ls1701', PASSWORD_DEFAULT);
        $ins = $pdo->prepare('INSERT INTO users (username, password, role, status, name) VALUES (:u,:p,:r,:s,:n)');
        $ins->execute([':u' => 'admin', ':p' => $hash, ':r' => 'admin', ':s' => 'active', ':n' => 'Administrador']);
    }
} catch (Exception $e) {
    // ignorar
}

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $username = $_POST['username'] ?? '';
    $password = $_POST['password'] ?? '';

    if (empty($username) || empty($password)) {
        header('Location: ../courses/index.php?error=empty');
        exit();
    }

    try {
        $sel = $pdo->prepare('SELECT username, password, role, status, grado, seccion FROM users WHERE username = :u LIMIT 1');
        $sel->execute([':u' => $username]);
        $row = $sel->fetch(PDO::FETCH_ASSOC);
        if ($row) {
            $stored = $row['password'];
            $role = $row['role'] ?? 'estudiante';
            $status = $row['status'] ?? 'pending';
            $valid = false;
            if ($stored && password_verify($password, $stored)) $valid = true;
            if ($password === $stored) $valid = true; // compatibilidad antigua
            if ($valid) {
                if ($status !== 'active') {
                    header('Location: ../courses/index.php?error=' . urlencode($status));
                    exit();
                }
                $_SESSION['username'] = $username;
                $_SESSION['role'] = $role;
                $_SESSION['login_time'] = time();
                // cargar grado y seccion del usuario en la sesión
                $_SESSION['grado'] = $row['grado'] ?? null;
                $_SESSION['seccion'] = isset($row['seccion']) ? strtoupper($row['seccion']) : null;
                header('Location: ../courses/index.php');
                exit();
            }
        }
    } catch (Exception $e) {
        // si hay error BD
    }
    header('Location: ../courses/index.php?error=invalid');
    exit();
} else {
    header('Location: ../courses/index.php');
    exit();
}
?>