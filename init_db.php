<?php
require __DIR__ . '/setup_guard.php';
$db_path = __DIR__ . '/db/database.sqlite';

// Create db directory if it doesn't exist
if (!is_dir(__DIR__ . '/db')) {
    mkdir(__DIR__ . '/db', 0777, true);
}

try {
    $pdo = new PDO("sqlite:" . $db_path);
    $pdo->setAttribute(PDO::ATTR_ERRMODE, PDO::ERRMODE_EXCEPTION);

    // Create Products Table
    $pdo->exec("CREATE TABLE IF NOT EXISTS products (
        id INTEGER PRIMARY KEY AUTOINCREMENT,
        name TEXT NOT NULL,
        category TEXT NOT NULL,
        price REAL NOT NULL,
        stock INTEGER NOT NULL DEFAULT 0,
        image_color TEXT DEFAULT '#fbcfe8'
    )");

    // Create Sales Table
    $pdo->exec("CREATE TABLE IF NOT EXISTS sales (
        id INTEGER PRIMARY KEY AUTOINCREMENT,
        total_amount REAL NOT NULL,
        created_at DATETIME DEFAULT CURRENT_TIMESTAMP
    )");

    // Create Sale Items Table
    $pdo->exec("CREATE TABLE IF NOT EXISTS sale_items (
        id INTEGER PRIMARY KEY AUTOINCREMENT,
        sale_id INTEGER NOT NULL,
        product_id INTEGER NOT NULL,
        quantity INTEGER NOT NULL,
        subtotal REAL NOT NULL,
        FOREIGN KEY (sale_id) REFERENCES sales(id),
        FOREIGN KEY (product_id) REFERENCES products(id)
    )");

    // Insert initial dummy data if products table is empty
    $stmt = $pdo->query("SELECT COUNT(*) FROM products");
    if ($stmt->fetchColumn() == 0) {
        $pdo->exec("INSERT INTO products (name, category, price, stock, image_color) VALUES 
            ('Helado Vainilla', 'Helados', 2.50, 50, '#fef3c7'),
            ('Helado Chocolate', 'Helados', 3.00, 40, '#fed7aa'),
            ('Helado Fresa', 'Helados', 2.50, 60, '#fbcfe8'),
            ('Cheesecake', 'Postres', 4.50, 20, '#e9d5ff'),
            ('Brownie', 'Postres', 3.00, 30, '#d8b4fe'),
            ('Café Americano', 'Varios', 1.50, 100, '#d6d3d1')
        ");
        echo "Base de datos creada y poblada con datos de prueba.\n";
    } else {
        echo "La base de datos ya existe y tiene datos.\n";
    }

    // Crear tabla de usuarios
    $pdo->exec("CREATE TABLE IF NOT EXISTS users (
        id INTEGER PRIMARY KEY AUTOINCREMENT,
        username TEXT UNIQUE NOT NULL,
        password_hash TEXT NOT NULL,
        role TEXT NOT NULL CHECK(role IN ('admin', 'gerente', 'vendedor')),
        created_at DATETIME DEFAULT CURRENT_TIMESTAMP
    )");

    // Insertar usuarios por defecto si la tabla esta vacia
    $stmt = $pdo->query("SELECT COUNT(*) FROM users");
    if ($stmt->fetchColumn() == 0) {
        $insert = $pdo->prepare("INSERT INTO users (username, password_hash, role) VALUES (?, ?, ?)");
        $insert->execute(['admin',    password_hash('admin123',    PASSWORD_BCRYPT), 'admin']);
        $insert->execute(['gerente',  password_hash('gerente123',  PASSWORD_BCRYPT), 'gerente']);
        $insert->execute(['vendedor', password_hash('vendedor123', PASSWORD_BCRYPT), 'vendedor']);
        echo "Usuarios por defecto creados: admin, gerente, vendedor.\n";
    }

} catch (PDOException $e) {
    echo "Error: " . $e->getMessage() . "\n";
}
?>
