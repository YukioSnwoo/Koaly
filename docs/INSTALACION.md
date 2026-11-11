# Guía de instalación de Koaly

[← Volver al README](../README.md)

Koaly ya funciona en línea en [koalicius.com](https://koalicius.com/) y para usarlo no hace falta instalar nada. Esta guía es para quien quiera **correrlo en su computadora** para desarrollar, o **instalarlo en su propio hosting**.

## Contenido

- [Requisitos](#requisitos)
- [Instalación local](#instalación-local)
- [Primeros datos](#primeros-datos)
- [Despliegue en Hostinger](#despliegue-en-hostinger)
- [Cambios a la base de datos](#cambios-a-la-base-de-datos)
- [Problemas comunes](#problemas-comunes)

---

## Requisitos

- PHP 8.1 o superior con las extensiones `pdo_mysql`, `mbstring` y `openssl` (`curl` es opcional, para el filtro de lenguaje en línea).
- MariaDB 10.6+ o MySQL 8.
- Un servidor web local (XAMPP, MAMP, Laragon) o el servidor integrado de PHP.
- Una cuenta de correo con SMTP (Hostinger o Gmail) para dar de alta cajeros y recuperar contraseñas.

---

## Instalación local

### 1. Clonar el repositorio

```bash
git clone https://github.com/YukioSnwoo/Koaly.git
cd Koaly
```

### 2. Crear la base de datos e importar el esquema

Crea una base vacía con `utf8mb4` e importa [`sql/esquema.sql`](../sql/esquema.sql), desde phpMyAdmin o desde la terminal:

```bash
mysql -u root -p -e "CREATE DATABASE koaly CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci;"
mysql -u root -p koaly < sql/esquema.sql
```

### 3. Cargar los datos base y el primer administrador

Genera el hash de una contraseña inicial:

```bash
php -r "echo password_hash('CambiaEsta123', PASSWORD_DEFAULT), PHP_EOL;"
```

Y corre esto en la base, pegando el hash donde dice `HASH_GENERADO`:

```sql
-- Los id de los roles importan: el login manda 1 → admin/, 2 → gerente/, 3 → cajero/
INSERT INTO Roles (id_rol, nombre_rol) VALUES (1, 'Administrador'), (2, 'Gerente'), (3, 'Cajero');

-- La caja busca los métodos por nombre
INSERT INTO Metodos_Pago (nombre_metodo) VALUES ('Efectivo'), ('Tarjeta Credito'), ('Transferencia'), ('Varios');

-- Los paneles traducen las categorías por este nombre exacto
INSERT INTO Categorias (nombre_categoria)
VALUES ('Abarrotes'), ('Bebidas'), ('Cuidado Personal'), ('Electronica'), ('Hogar'), ('Ropa');

-- Primer administrador: no lleva sucursal y debe cambiar la contraseña al entrar
INSERT INTO Usuarios (nombre, email, contrasena_hash, id_rol, id_sucursal, estado,
                      fecha_inicio_estado, fecha_contratacion, debe_cambiar)
VALUES ('Administrador', 'admin@ejemplo.com', 'HASH_GENERADO', 1, NULL, 'Activo',
        CURDATE(), CURDATE(), 1);
```

### 4. Configurar la conexión

Crea `admin/database.php`. Este archivo está en `.gitignore`, así que nunca se sube al repositorio:

```php
<?php
$pdo = new PDO(
    'mysql:host=localhost;dbname=koaly;charset=utf8mb4',
    'root',
    'tu_contraseña',
    [
        PDO::ATTR_ERRMODE            => PDO::ERRMODE_EXCEPTION,
        PDO::ATTR_DEFAULT_FETCH_MODE => PDO::FETCH_ASSOC,
    ]
);
```

### 5. Configurar el correo

Copia [`login/config_correo.ejemplo.php`](../login/config_correo.ejemplo.php) como `login/config_correo.php` y llena los datos del buzón. El archivo de ejemplo explica cómo hacerlo con Hostinger o con Gmail.

Sin este archivo se usa `mail()` del servidor, que en una computadora local casi nunca funciona.

### 6. (Opcional) Filtro de lenguaje en línea

El filtro de lenguaje ofensivo funciona sin internet. Para sumar la revisión de APIVerve, crea `private/private-API-frofanity.php` **una carpeta arriba del proyecto**, fuera de la carpeta pública:

```php
<?php
return ['APIVERVE_API_KEY' => 'tu_llave'];
```

### 7. Iniciar el servidor

Desde la carpeta del proyecto:

```bash
php -S localhost:8000
```

Abre <http://localhost:8000>, entra con `admin@ejemplo.com` y la contraseña del paso 3, y elige tu contraseña definitiva.

> [!TIP]
> Para que funcionen las fotos de perfil y las imágenes de productos, el servidor necesita permiso de escritura en `Imagenes/`.

---

## Primeros datos

La base empieza vacía. Para tener una sucursal lista para vender:

1. **Administrador:** crea una sucursal y registra a su gerente. Al asignárselo, la sucursal queda activa.
2. **Gerente:** solicita los productos del catálogo desde *Productos*. El administrador los aprueba en *Inventario global → Solicitudes*.
3. **Gerente:** da de alta las cajas y los cajeros. Cada cajero recibe por correo su contraseña temporal y el enlace del aviso de privacidad, por eso este paso necesita el correo del paso 5.
4. **Cajero:** acepta el aviso, entra con la contraseña temporal, elige la suya y abre su caja.

---

## Despliegue en Hostinger

El sitio en producción, [koalicius.com](https://koalicius.com/), está en Hostinger.

1. Sube el proyecto a `public_html/`, por Git desde hPanel o por FTP.
2. Crea la base en hPanel → *Bases de datos* e importa `sql/esquema.sql` con phpMyAdmin.
3. Crea `admin/database.php` y `login/config_correo.php` directamente en el servidor.
4. Si usas la API del filtro, pon `private/` al mismo nivel que `public_html/`, no adentro.

Las hojas de estilo y los scripts llevan la fecha del archivo en la URL (`?v=…`). Así el navegador descarga la versión nueva aunque Hostinger guarde la anterior en caché.

---

## Cambios a la base de datos

- [`sql/esquema.sql`](../sql/esquema.sql) crea la base desde cero, sin datos. Ya incluye las migraciones 001 a 013 y el costo de la 015.
- Cada cambio a una base que ya existe va en una migración numerada (`sql/001_…`, `sql/002_…`) que se corre en orden desde phpMyAdmin.
- Corre la migración nueva **antes** de subir el código que la usa.
- Los volcados completos traen datos personales, por eso se guardan fuera de git (`sql/respaldos/` está en `.gitignore`).

---

## Problemas comunes

| Lo que ves | Qué revisar |
| :-- | :-- |
| "Error de conexión a la base de datos" al iniciar sesión | Los datos de `admin/database.php` y que la base esté encendida. |
| "No se pudo enviar el correo" | `login/config_correo.php`: servidor, puerto, usuario y contraseña del buzón. |
| "La cuenta no tiene una sucursal asignada" | El gerente no tiene sucursal: asígnasela en *Admin → Gerentes*. |
| Un panel avisa "Falta correr sql/0XX_….sql" | Corre esa migración en phpMyAdmin. |
| Los estilos no cambian después de subir algo | Recarga sin caché (Cmd + Shift + R o Ctrl + F5). |

---

[← Volver al README](../README.md)
