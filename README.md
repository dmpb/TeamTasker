# TeamTasker

Aplicacion web para gestion colaborativa de equipos, proyectos y tareas tipo tablero (kanban), construida con Laravel + Inertia + React.

## Stack tecnico

- Backend: Laravel 13, PHP 8.4+, PostgreSQL, Redis, Memcached
- Frontend: React 19, Inertia.js v2, TypeScript, Tailwind CSS v4, Vite
- Autenticacion: Laravel Fortify (incluye 2FA)
- Desarrollo local con contenedores: Laravel Sail (Docker)
- Testing local: Pest / PHPUnit

## Funcionalidades principales

- Gestion de equipos y miembros con roles
- Gestion de proyectos por equipo (incluyendo archivado)
- Tablero por columnas para organizar tareas
- CRUD de tareas y movimiento entre columnas
- Comentarios por tarea
- Registro de actividad por proyecto
- Invitaciones a equipo por email (enlace manual + correo si `MAIL_*` esta configurado)
- Area de configuracion de perfil, seguridad y apariencia

### Invitaciones por correo (Phase 8)

Las invitaciones encolan `TeamInvitationMail` (cola por defecto). Para envio real en local, configura en `.env` al menos `MAIL_MAILER`, `MAIL_HOST`, etc. (por ejemplo `log` o `Mailpit` con Sail). Sin correo configurado, el flujo sigue siendo usable copiando el enlace desde la pagina del equipo.

## Requisitos

- Docker Desktop (o Docker Engine + Compose v2)
- WSL2 (si usas Windows)
- Git

> Nota: para ejecutar con Sail no necesitas instalar PHP ni Node en host, porque se ejecutan dentro de contenedores.

## Inicio rapido con Docker (Sail)

1) Clona el repositorio y entra al proyecto:

```bash
git clone <repo-url>
cd TeamTasker
```

2) Instala dependencias PHP (esto tambien instala Sail en `vendor/bin/sail`):

```bash
composer install
```

3) Crea variables de entorno:

```bash
cp .env.example .env
```

4) Levanta los servicios:

```bash
./vendor/bin/sail up -d
```

5) Instala dependencias frontend dentro del contenedor:

```bash
./vendor/bin/sail npm install
```

6) Genera clave de app y ejecuta migraciones:

```bash
./vendor/bin/sail artisan key:generate
./vendor/bin/sail artisan migrate
```

7) Inicia Vite en modo desarrollo (recomendado):

```bash
./vendor/bin/sail npm run dev -- --host 0.0.0.0 --port 5173
```

8) Abre la app:

- App Laravel: [http://localhost](http://localhost)
- Vite dev server: [http://localhost:5173](http://localhost:5173)

## Comandos utiles

- Ver estado de contenedores:
  - `./vendor/bin/sail ps`
- Ver logs:
  - `./vendor/bin/sail logs -f`
- Detener servicios:
  - `./vendor/bin/sail down`
- Ejecutar comandos artisan:
  - `./vendor/bin/sail artisan <comando>`

## Testing (solo local)

Los tests estan configurados para ejecutarse localmente.

- Ejecutar todo:
  - `./vendor/bin/sail artisan test --compact`
- Ejecutar por archivo:
  - `./vendor/bin/sail artisan test --compact tests/Feature/ExampleTest.php`
- Ejecutar con Composer:
  - `composer test`

## Calidad de codigo

- Formateo PHP (Pint):
  - `./vendor/bin/sail composer lint`
- Lint frontend:
  - `./vendor/bin/sail npm run lint`
- Type check:
  - `./vendor/bin/sail npm run types:check`

## Estructura principal

- `app/Http/Controllers`: logica HTTP de equipos, proyectos, columnas, tareas y comentarios
- `app/Models`: modelos de dominio (`Team`, `Project`, `Column`, `Task`, `Comment`, `ActivityLog`, etc.)
- `database/migrations`: esquema de base de datos
- `resources/js/pages`: paginas Inertia/React
- `routes/web.php`: rutas web y modulos principales

## Solucion de problemas

### Error: Vite manifest not found

Si ves `Vite manifest not found at: public/build/manifest.json`, significa que no esta corriendo Vite o no hay build de assets.

Opciones:

1. Modo desarrollo (recomendado):

```bash
./vendor/bin/sail npm run dev -- --host 0.0.0.0 --port 5173
```

2. Build de produccion:

```bash
./vendor/bin/sail npm run build
```

### Vite se cierra con codigo 137

Suele ser falta de memoria en Docker/WSL. Aumenta RAM para Docker Desktop o WSL y vuelve a iniciar Vite.
