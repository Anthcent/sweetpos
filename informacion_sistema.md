# Información del Sistema: Sweet POS

Aquí tienes la información detallada sobre tu sistema, incluyendo sus módulos, arquitectura y el modelo de base de datos.

---

## Resumen del Sistema

| Información | Detalle |
| :--- | :--- |
| **Nombre del sistema** | **Sweet POS** |
| **Objetivo** | Administrar ventas (punto de venta / POS), consumo en mesas, registro de clientes, control de inventario (stock) e historial de transacciones en una heladería o cafetería. |
| **Tipo** | Aplicación Web (con diseño responsivo y moderno, usando Tailwind CSS y Lucide Icons). |
| **Lenguaje** | **PHP (Nativo)** + HTML5 + JavaScript (Vanilla / ES6) + Tailwind CSS para el frontend. |
| **Base de datos** | **SQLite** (a través de PHP PDO, almacenada localmente en `db/database.sqlite`). |
| **Usuarios / Roles** | `admin` (Administrador), `gerente` (Gerente) y `vendedor` (Vendedor / Cajero). |
| **Patrón usado** | **Controlador de Página (Page Controller) + API REST Backend**: Scripts de PHP que actúan como páginas principales (`index.php`, `mesas.php`, etc.) que renderizan la interfaz y delegan la lógica de datos a endpoints de API desacoplados en la carpeta `api/` (`products.php`, `sales.php`, `clients.php`, `history.php`), consumidos vía AJAX/`fetch`. |

---

## 🛠️ Listado de Módulos

El sistema cuenta con un control de accesos basado en roles (`canAccess($module)` definido en [auth.php](file:///c:/Users/DELL%203380/Downloads/elianny/1/sistema/auth.php)), que habilita o restringe los siguientes módulos:

1. **Autenticación y Gestión de Sesiones (`login.php`, `logout.php`, `auth.php`, `api/auth_api.php`)**
   - Control de sesiones seguro.
   - Encriptación de contraseñas mediante `PASSWORD_BCRYPT`.
   - Redirección automática y protección de rutas según roles.
   - *Acceso:* Todos los roles para iniciar/cerrar sesión.

2. **Terminal de Punto de Venta / POS (`index.php`, `api/products.php`, `api/sales.php`)**
   - Interfaz táctil e interactiva para registrar compras.
   - Filtrado rápido de productos por categorías (`Helados`, `Postres`, `Varios`).
   - Carrito de compras reactivo con sumatoria de totales.
   - Selección de número de mesa (`table_number`), método de pago (`Efectivo`, `Transferencia`, etc.) y número de referencia.
   - Asociación de clientes a la venta.
   - *Acceso:* `admin`, `gerente`, `vendedor`.

3. **Gestión de Mesas (`mesas.php`)**
   - Vista de estado de las mesas activas en el salón.
   - Visualización de comandas/ventas pendientes vinculadas a cada mesa.
   - *Acceso:* `admin`, `gerente`, `vendedor`.

4. **Inventario / Control de Stock (`inventario.php`, `api/products.php`)**
   - Listado de productos en stock.
   - Edición y actualización rápida de información del producto (nombre, precio, cantidad de stock, categoría y color visual de la tarjeta).
   - Agregar nuevos productos y eliminar productos (eliminación exclusiva para administrador).
   - *Acceso:* `admin`, `gerente` (eliminación solo `admin`).

5. **Historial de Ventas y Reportes (`historial.php`, `api/history.php`)**
   - Panel de control con métricas rápidas: Total vendido hoy, total de transacciones y promedio por ticket.
   - Listado histórico de ventas filtrable por rango de fechas y métodos de pago.
   - Desglose detallado de los artículos vendidos dentro de cada transacción.
   - Opción para anular/eliminar transacciones del historial.
   - *Acceso:* `admin`, `gerente`.

6. **Gestión de Clientes (`api/clients.php`, modal en POS)**
   - Formulario para registrar nuevos clientes (Cédula/DNI, Nombre, Teléfono y Dirección).
   - Buscador predictivo en el POS para vincular el cliente a la venta actual por su cédula o nombre.
   - *Acceso:* `admin`, `gerente`.

---

## 🗄️ Modelo de Base de Datos (Tablas)

La base de datos utiliza **SQLite** y consta de 5 tablas principales relacionadas entre sí:

### 1. Tabla `users` (Usuarios del sistema)
Almacena las credenciales y el rol asignado a cada usuario.
* **`id`** `INTEGER PRIMARY KEY AUTOINCREMENT`: Identificador único.
* **`username`** `TEXT UNIQUE NOT NULL`: Nombre de usuario para iniciar sesión.
* **`password_hash`** `TEXT NOT NULL`: Contraseña encriptada con Bcrypt.
* **`role`** `TEXT NOT NULL`: Rol del usuario. Restringido por check a: `admin`, `gerente`, `vendedor`.
* **`created_at`** `DATETIME DEFAULT CURRENT_TIMESTAMP`: Fecha de registro.

### 2. Tabla `products` (Catálogo de Productos)
Registra los helados, postres y artículos disponibles en el negocio.
* **`id`** `INTEGER PRIMARY KEY AUTOINCREMENT`: Identificador único.
* **`name`** `TEXT NOT NULL`: Nombre del producto (ej. *Helado Fresa*).
* **`category`** `TEXT NOT NULL`: Categoría (ej. *Helados*, *Postres*, *Varios*).
* **`price`** `REAL NOT NULL`: Precio de venta.
* **`stock`** `INTEGER NOT NULL DEFAULT 0`: Unidades físicas disponibles.
* **`image_color`** `TEXT DEFAULT '#fbcfe8'`: Código hexadecimal para personalizar el color de la tarjeta en la interfaz visual.

### 3. Tabla `clients` (Clientes registrados)
Información de contacto de clientes para facturación o asignación de pedidos.
* **`id`** `INTEGER PRIMARY KEY AUTOINCREMENT`: Identificador único.
* **`cedula`** `TEXT UNIQUE NOT NULL`: Cédula, DNI o número de identificación fiscal.
* **`name`** `TEXT NOT NULL`: Nombre completo o razón social.
* **`phone`** `TEXT`: Número de teléfono de contacto.
* **`address`** `TEXT`: Dirección física o de entrega.
* **`created_at`** `DATETIME DEFAULT CURRENT_TIMESTAMP`: Fecha de registro del cliente.

### 4. Tabla `sales` (Cabecera de Ventas)
Registra el total y los metadatos de cada transacción realizada.
* **`id`** `INTEGER PRIMARY KEY AUTOINCREMENT`: Identificador único de venta.
* **`total_amount`** `REAL NOT NULL`: Monto total cobrado en la venta.
* **`created_at`** `DATETIME DEFAULT CURRENT_TIMESTAMP`: Fecha y hora de la venta.
* **`status`** `TEXT DEFAULT 'paid'`: Estado de la venta (ej. *paid*, *pending*).
* **`table_number`** `INTEGER DEFAULT NULL`: Número de mesa si el consumo fue en local.
* **`payment_method`** `TEXT DEFAULT NULL`: Método de pago (ej. *Efectivo*, *Transferencia*, *Pago Móvil*).
* **`client_id`** `INTEGER DEFAULT NULL`: Relación con la tabla `clients(id)`.
* **`reference_number`** `TEXT DEFAULT NULL`: Código de referencia de pagos electrónicos (pago móvil, transferencia, etc.).

### 5. Tabla `sale_items` (Detalle de la Venta)
Almacena los artículos específicos que conforman cada ticket de venta.
* **`id`** `INTEGER PRIMARY KEY AUTOINCREMENT`: Identificador único de línea de venta.
* **`sale_id`** `INTEGER NOT NULL`: Relación con la venta padre `sales(id)`.
* **`product_id`** `INTEGER NOT NULL`: Relación con el producto vendido `products(id)`.
* **`quantity`** `INTEGER NOT NULL`: Cantidad de unidades vendidas.
* **`subtotal`** `REAL NOT NULL`: Subtotal calculado (`quantity` × precio del producto).
