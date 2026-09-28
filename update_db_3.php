<?php
require __DIR__ . '/setup_guard.php';
$db_path = __DIR__ . '/db/database.sqlite';

try {
    $pdo = new PDO("sqlite:" . $db_path);
    $pdo->setAttribute(PDO::ATTR_ERRMODE, PDO::ERRMODE_EXCEPTION);

    // Crear tabla de usuarios
    $pdo->exec("CREATE TABLE IF NOT EXISTS users (
        id INTEGER PRIMARY KEY AUTOINCREMENT,
        username TEXT UNIQUE NOT NULL,
        password_hash TEXT NOT NULL,
        role TEXT NOT NULL CHECK(role IN ('admin', 'gerente', 'vendedor')),
        created_at DATETIME DEFAULT CURRENT_TIMESTAMP
    )");
    echo "Tabla 'users' creada o ya existe.\n";

    // Insertar usuarios por defecto si la tabla esta vacia
    $stmt = $pdo->query("SELECT COUNT(*) FROM users");
    if ($stmt->fetchColumn() == 0) {
        $insert = $pdo->prepare("INSERT INTO users (username, password_hash, role) VALUES (?, ?, ?)");
        $insert->execute(['admin',    password_hash('admin123',    PASSWORD_BCRYPT), 'admin']);
        $insert->execute(['gerente',  password_hash('gerente123',  PASSWORD_BCRYPT), 'gerente']);
        $insert->execute(['vendedor', password_hash('vendedor123', PASSWORD_BCRYPT), 'vendedor']);
        echo "Usuarios por defecto creados: admin, gerente, vendedor.\n";
    } else {
        echo "Los usuarios ya existen en la base de datos.\n";
    }

    echo "Actualizacion de BD completa (Fase 4 - Login).\n";
} catch (PDOException $e) {
    echo "Error: " . $e->getMessage() . "\n";
}
?>
