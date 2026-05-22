# ⚙️ Autorepuestos Sildemar - Gestión de Inventario

Este sistema fue desarrollado como proyecto central para el cumplimiento de las **120 horas de Servicio Comunitario**, orientado a la digitalización y optimización de los procesos de inventario en el establecimiento **Autorepuestos Sildemar**.

## 📋 Descripción del Proyecto

El objetivo principal fue sustituir los registros manuales por una herramienta digital robusta que permita un control preciso sobre el stock de repuestos automotrices. El sistema facilita la administración de productos, proveedores y ventas diarias.

## 🚀 Funcionalidades

- **Control de Inventario**: Registro, edición y eliminación de repuestos con especificaciones técnicas.
- **Gestión de Stock**: Actualización automática de cantidades tras cada operación.
- **Historial de Movimientos**: Registro de entradas y salidas de mercancía.
- **Interfaz Administrativa**: Panel diseñado para una navegación rápida y eficiente en entornos de alta demanda.
- **Seguridad**: Control de acceso mediante autenticación de usuarios.

## 🛠️ Stack Tecnológico

- **Backend**: PHP 8.x
- **Base de Datos**: MySQL
- **Frontend**: HTML5, CSS3, JavaScript y Bootstrap 5 para el diseño responsivo.
- **Herramientas de Desarrollo**: Git/GitHub para el control de versiones.

## 🐳 Docker

El proyecto incluye `Dockerfile`, `docker-compose.yml` y una configuración de `nginx` para levantar el sistema completo con PHP-FPM y MySQL.

### Requisitos

- Docker Desktop instalado y funcionando.
- Virtualización habilitada en BIOS/UEFI.

### Levantar el proyecto

Desde la carpeta raíz del proyecto (`C:\xampp\htdocs\Sildemar`):

```powershell
docker compose up --build -d
```

Si haces cambios en dependencias o en `composer.json`, reconstruye la imagen con:

```powershell
docker compose build --no-cache
```

### Verificar servicios

```powershell
docker compose ps
```

### Acceder al sistema

Abre en el navegador:

```text
http://localhost:8080
```

### Variables de entorno

El proyecto ahora usa un archivo de entorno para que las credenciales no queden incrustadas en el repositorio.

- Crea un archivo `.env` en la raíz del proyecto.
- Usa `.env.example` como referencia.
- No subas `.env` al repositorio.

Ejemplo de `.env`:

```text
DB_USER=sildemar
DB_PASS=sildemar_pass
DB_NAME=sildemar
MYSQL_ROOT_PASSWORD=rootpass
```

### phpMyAdmin

Para seguridad, `phpMyAdmin` está configurado como servicio de desarrollo.

Inicia todo el stack sin phpMyAdmin con:

```powershell
docker compose up --build -d
```

Si necesitas phpMyAdmin durante desarrollo:

```powershell
docker compose --profile dev up -d phpmyadmin
```

Accede a phpMyAdmin en:

```text
http://localhost:8081
```

### Recuperación de contraseña

En desarrollo, puedes activar la depuración de correo para que el código de recuperación aparezca en la respuesta JSON del backend. Esto es útil cuando PHP no tiene SMTP configurado.

- Ajusta `MAIL_DEBUG=1` en el archivo `.env`.

### Credenciales de base de datos dentro de Docker

- Host: `db`
- Base: `sildemar`
- Usuario: `${DB_USER}`
- Contraseña: `${DB_PASS}`

### Seguridad y recomendaciones

- No expongas el puerto de MySQL (`3306`) en producción.
- Usa HTTPS en producción.
- Filtra y valida siempre datos en backend.
- No dejes `phpMyAdmin` accesible públicamente en un servidor en vivo.

## 🌐 Despliegue en Producción (Checklist)

1. **SSL/HTTPS**: Es obligatorio instalar un certificado SSL (Let's Encrypt recomendado).
2. **Configuración PHP**:
   - `display_errors = Off`
   - `log_errors = On`
   - `session.cookie_secure = On` (Solo si usas HTTPS)
   - `session.cookie_httponly = On`
3. **Base de Datos**: Crear un usuario de base de datos específico para la aplicación con permisos limitados (solo SELECT, INSERT, UPDATE, DELETE).
4. **Correo**: Configurar las variables `SMTP_HOST`, `SMTP_USER` y `SMTP_PASS` en el archivo `.env` una vez se tenga el dominio institucional.
5. **Tareas Programadas (Cron)**: Se recomienda configurar un Cron Job para ejecutar una limpieza de solicitudes pendientes de más de 48 horas si no se desea depender de la ejecución al listar.


### Detener el proyecto

```powershell
docker compose down
```

> Si prefieres ejecutar el proyecto con XAMPP en lugar de Docker, coloca los archivos en `htdocs` y configura la base de datos local. Para este proyecto, Docker es la forma recomendada y más segura.
