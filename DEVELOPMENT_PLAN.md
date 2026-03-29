# TeamTasker — Plan de desarrollo (por chunks de ejecución)

Documento basado en el estado del repositorio y `PROJECT_RULES.md`. Cada **chunk** está pensado para **1–2 prompts**: capas relacionadas en backend pueden ir juntas (p. ej. Repository + Service); **frontend siempre en chunk aparte**. Sin API REST para el producto; Inertia + Sail.

**Actualizar** este archivo cuando cambie el alcance o se cierren chunks.

---

## 1. Resumen del estado del proyecto

### Módulos ya implementados

- **Infra / rutas base**  
  - `bootstrap/app.php`: `web`, `console`, `health` (sin grupo API en el bootstrap actual).  
  - `routes/web.php`: home Inertia (`welcome`), `dashboard` con `auth` + `verified`, inclusión de `routes/settings.php`.  
  - `routes/settings.php`: perfil, seguridad, apariencia (Inertia / controladores).

- **Autenticación**  
  - Fortify + páginas Inertia en `resources/js/pages/auth/*`.  
  - Usuarios: migración base + columnas 2FA.  
  - `User` con `ownedTeams()` y `teamMemberships()`.

- **Teams (dominio parcial)**  
  - Migraciones: `teams` (`name`, `owner_id`, FK a `users`, cascade), `team_members` (`team_id`, `user_id`, `role`, unicidad, índices, FKs con cascade).  
  - Modelos: `Team`, `TeamMember` con relaciones; `TeamMember::role` como `TeamMemberRole` (enum).  
  - `TeamRepository` (…, `deleteMembership`), `TeamService` (crear equipo, miembros, quitar/cambiar rol con reglas de dueño/admin), `TeamPolicy`, `TeamController` (`index`, `store`, `show`, miembros store/update/destroy).  
  - Frontend: `teams/index` (crear equipo, listado con enlace a detalle), `teams/show` (miembros, alta por user ID, cambiar rol, quitar con confirmación); **Teams** en `app-sidebar`.  
  - Tests: relaciones, repositorio, servicio, controlador.

- **Inertia compartido**  
  - `HandleInertiaRequests`: `auth.user`, nombre de app, estado del sidebar.

- **Projects (Phase 2 — MVP por equipo)**  
  - Persistencia: migración `projects`, modelo `Project`, `Team::projects()`, `ProjectRepository`, `ProjectFactory`, tests de repositorio y relaciones.  
  - HTTP: `ProjectService`, `ProjectController`, rutas anidadas `teams/{team}/projects` con `scopeBindings`, form requests.  
  - Autorización: `TeamPolicy::manageProjects`; `ProjectPolicy` (vista/creación/mutación en contexto de equipo).  
  - Inertia `teams/projects/index` + navegación: enlace desde `teams/show`, enlace **Projects** por equipo en `teams/index`, atajos en sidebar (`teamsForNav` en `HandleInertiaRequests`).  
  - Tests: `ProjectControllerTest`, `DashboardTest` (`teamsForNav`).

### Módulos incompletos / riesgos

- **Projects (Kanban tablero, columnas, tarjetas):** Phase 3+.  
- `team_members.role`: enum en modelo + CHECK en PostgreSQL/MySQL; SQLite en tests sin CHECK (solo capa PHP).  
- `getTeamsByUser`: posible refactor de precedencia (`where(function …)`) al añadir filtros.

### Reglas de ejecución (chunks)

- **Sail** para comandos (`./vendor/bin/sail`).  
- Capas: `Controllers → Services → Repositories → Models`.  
- Tras tocar páginas TSX: manifest Vite (build o dev vía Sail).  
- **No** mezclar backend y frontend en el mismo chunk.

---

## 2. Plan por fases y chunks

### Phase 0 — Cableado y calidad Teams

#### Phase 0.A – Backend Foundation ✅ *Completada*

**Objetivo:** Endurecer dominio y datos de Teams sin tocar aún rutas públicas ni UI nueva.

**Tareas:**

- Opcional: scope en `Team` para “equipos accesibles por usuario” si centraliza la query.  
- `TeamRepository::getTeamsByUser`: agrupar condiciones en `where(function …)` para precedencia segura.  
- `TeamService`: validar `role` permitido (`owner` / `admin` / `member`) donde se escriba rol.  
- `TeamFactory` / `TeamMemberFactory` (y estados útiles) para tests.

**Implementado:** `Team::scopeAccessibleByUser`, repositorio delegando en el scope, `TeamService::addUserToTeam` con `InvalidArgumentException` si el rol no es `owner|admin|member`, factories con estados `forOwner`, `forTeam`, `forUser`, `owner()`, `admin()`, tests actualizados y cobertura de scope + rol inválido.

**Criterios de cierre:** Tests verdes; factories usables donde reduzcan ruido en Pest.

---

#### Phase 0.B – Application Layer (HTTP) ✅ *Completada*

**Objetivo:** Exponer Teams en la app con el mismo estándar que dashboard/settings.

**Tareas:**

- `routes/web.php`: `GET /teams` → `TeamController@index`, `POST /teams` → `TeamController@store`, nombres `teams.index` / `teams.store`, middleware `auth` y **decisión explícita** de `verified` alineada al resto.  
- Evitar redundancia innecesaria entre middleware del controlador y grupo de rutas.  
- `StoreTeamRequest` (o equivalente): extraer validación del controlador.  
- Pest: aserciones contra **rutas nombradas** (sustituir o complementar rutas inline de prueba).

**Implementado:** Rutas registradas en el mismo grupo `auth` + `verified` que `dashboard`; eliminado `HasMiddleware` en `TeamController`; `StoreTeamRequest` con reglas de `name`; tests con `route('teams.index')` / `route('teams.store')` y caso invitado → `login`.

**Criterios de cierre:** Navegador: listar y crear equipo vía rutas reales; tests contra rutas nombradas.

---

#### Phase 0.C – Frontend Integration ✅ *Completada*

**Objetivo:** Flujo crear-equipo desde UI y descubribilidad.

**Tareas:**

- `teams/index.tsx`: formulario Inertia (`Form`) hacia `teams.store`; errores de validación visibles.  
- Regenerar/actualizar acciones Wayfinder si el proyecto las usa para forms (patrón como settings).  
- Enlace “Teams” en sidebar / navegación hacia `teams.index`.  
- Verificar manifest Vite (build o dev con Sail).

**Implementado:** `Form` con `TeamController.store.form()` (`@/actions/.../TeamController`), `InputError` para `name`, `preserveScroll` y `resetOnSuccess`; breadcrumbs con `index` de `@/routes/teams`; ítem **Teams** en `app-sidebar` (`Users`, `teamsIndex()`); manifest verificado con `sail npm run build`.

**Criterios de cierre:** Usuario autenticado crea equipo desde la página y lo ve en el listado sin errores de manifest/consola.

---

### Phase 1 — Teams: autorización, roles, miembros

#### Phase 1.A – Backend Foundation ✅ *Completada*

**Objetivo:** Modelo de datos y consultas listos para miembros y roles.

**Tareas:**

- Opcional: migración o constraint en BD para `role` (si el MVP lo exige).  
- Modelo: cast/enum para `TeamMember::role` si encaja con la versión de Laravel del proyecto.  
- Repositorio (extensión o clase dedicada): miembros por `team_id`, membresía `(user_id, team_id)`.

**Implementado:** Enum respaldado `App\Enums\TeamMemberRole` (`owner` / `admin` / `member`); cast en `TeamMember`; migración `team_members_role_check` en PostgreSQL y MySQL (omitida en SQLite de tests); `TeamRepository::getMembersForTeam`, `findMembership`; `attachUserToTeam` acepta `TeamMemberRole|string`; `TeamService` valida roles vía `TeamMemberRole::tryFrom`; tests de repositorio, servicio, modelo y controlador actualizados.

**Criterios de cierre:** Consultas cubiertas por tests de repositorio o smoke mínimo.

---

#### Phase 1.B – Application Layer ✅ *Completada*

**Objetivo:** Reglas de negocio y HTTP con policy, sin UI.

**Tareas:**

- `TeamService`: añadir/quitar miembro, cambiar rol; reglas owner/admin/member centralizadas.  
- `TeamPolicy` (u otras policies necesarias): ver equipo, gestionar miembros; registro en provider si aplica.  
- `TeamController` + rutas: `show` y acciones de miembros (POST/PATCH/DELETE según diseño), siempre `Inertia::render` o `redirect` / `back`.  
- Pest: usuario sin acceso **no** obtiene datos de otro equipo (403 / abort coherente con el proyecto).

**Implementado:** `TeamPolicy` (`view`, `manageMembers`); `Team::membershipFor`; `TeamService::addMemberToTeam` / `updateMemberRoleInTeam` / `removeMemberFromTeam` (solo roles `admin`/`member` vía HTTP; dueño intocable); `TeamRepository::deleteMembership`; rutas `teams.show`, `teams.members.store|update|destroy`; `StoreTeamMemberRequest` / `UpdateTeamMemberRequest`; controlador con Inertia `teams/show` y redirects con errores de negocio; `AuthorizesRequests` en `Controller`; tests de 403 y flujos admin. Wayfinder: usar `php artisan wayfinder:generate --with-form` para tipos `.form` en acciones.

**Criterios de cierre:** Rutas nuevas protegidas por policy; tests de autorización verdes.

---

#### Phase 1.C – Frontend Integration ✅ *Completada*

**Objetivo:** Pantalla de equipo y gestión de miembros vía Inertia.

**Tareas:**

- Página detalle equipo (props: equipo, miembros, permisos que el backend exponga si aplica).  
- Formularios Inertia para invitar/añadir, cambiar rol, quitar (solo lo que el backend permita).  
- Enlaces desde listado de equipos al detalle.

**Implementado:** `teams/show` con props `members[].can_update_role` / `can_remove` (dueño no editable ni eliminable vía UI); `<Form>` Wayfinder para `storeMember`, `updateMember`, `destroyMember`; alta por `user_id` + rol admin/member; filas con rol/acciones; confirmación antes de quitar; errores de validación y de negocio visibles (`InputError`, alerta para `user`/`role` globales). Listado `teams/index` enlaza al detalle. Tests: validación alta miembro y flags Inertia del dueño.

**Criterios de cierre:** Flujos felices y errores de validación/autorización visibles sin `fetch`/axios manual.

---

### Phase 2 — Projects (por equipo)

#### Phase 2.A – Backend Foundation ✅ *Completada*

**Objetivo:** Persistencia y relaciones Project ↔ Team.

**Tareas:**

- Migración `projects`: `team_id`, nombre, timestamps, FK + índices, cascade según negocio.  
- Modelo `Project`; relaciones en `Team` y `Project`.  
- `ProjectRepository`: listar/crear/actualizar/archivar(o eliminar) filtrado por `team_id`.

**Implementado:** Tabla `projects` con `archived_at` para archivar sin borrar; `cascadeOnDelete` al eliminar equipo; `ProjectRepository::listProjectsForTeam` (flag `includeArchived`), `createProject`, `updateProject`, `archiveProject`, `restoreProject`, `deleteProject`; factory y tests de repositorio, relaciones Eloquent y cascade.

**Criterios de cierre:** Migración reversible; tests de repositorio o smoke de modelo.

---

#### Phase 2.B – Application Layer ✅ *Completada*

**Objetivo:** Casos de uso y rutas con contexto de equipo.

**Tareas:**

- `ProjectService`: operaciones solo si el usuario pertenece al equipo y con rol suficiente (reutilizar policy/patrón de Phase 1).  
- `ProjectController` + rutas anidadas o prefijadas por equipo (convención única).  
- Policies para proyecto en contexto de equipo.  
- Pest: CRUD autorizado / no autorizado.

**Implementado:** `ProjectService` delegando en `ProjectRepository`; rutas `teams.projects.*` con `Route::scopeBindings()`; autorización en Form Requests y controlador (`view` de equipo para índice; `manageProjects` para mutaciones); listado con `include_archived` solo si el usuario puede gestionar; Inertia `teams/projects/index` con formularios Wayfinder para store/update/archive/unarchive/destroy; enlace desde `teams/show`. Wayfinder: `php artisan wayfinder:generate --with-form` tras cambiar rutas.

**Criterios de cierre:** Flujo principal sin JSON; Inertia + redirects; tests verdes.

---

#### Phase 2.C – Frontend Integration ✅ *Completada*

**Objetivo:** Listar y crear proyectos dentro de un equipo.

**Tareas:**

- Página listado proyectos (props desde `index`).  
- Formulario crear/editar proyecto con Inertia.  
- Navegación desde vista de equipo hacia proyectos.

**Implementado:** Enlace explícito **Projects** en cada fila de `teams/index`; grupo **Team projects** en sidebar (`teamsForNav` compartido vía `HandleInertiaRequests`, hasta 8 equipos del usuario, enlaces a `teams/{id}/projects` con icono y estado activo); empty state en `teams/projects/index` (icono, copy según `can.manageProjects`). Tests Inertia en `DashboardTest` para `teamsForNav`.

**Criterios de cierre:** Usuario con permiso gestiona proyectos solo de su equipo.

---

### Phase 3 — Kanban: columnas

#### Phase 3.A – Backend Foundation

**Objetivo:** Columnas ordenadas por proyecto.

**Tareas:**

- Migración `columns`: `project_id`, nombre, `position` (o equivalente), índices.  
- Modelo `Column`; relación con `Project`.  
- `ColumnRepository` + `ColumnService`: listar ordenado, crear, actualizar, reordenar en transacción si aplica.

**Criterios de cierre:** Tests de servicio/repositorio para orden y pertenencia a proyecto.

---

#### Phase 3.B – Application Layer

**Objetivo:** Exponer columnas vía Inertia.

**Tareas:**

- Controlador(es) y rutas bajo proyecto (o recurso anidado) con policy en cadena team → project.  
- Acciones: crear/editar/eliminar columna, reordenar (sin JSON para el flujo principal de la SPA).  
- Pest: permisos y consistencia de `project_id`.

**Criterios de cierre:** Inertia o redirects; tests verdes.

---

#### Phase 3.C – Frontend Integration

**Objetivo:** Vista tablero mínima.

**Tareas:**

- Página tablero: columnas en UI.  
- Formularios Inertia para alta/edición; reordenar solo si MVP lo exige (si no, acciones simples vía POST).

**Criterios de cierre:** Usuario gestiona columnas dentro del flujo Inertia.

---

### Phase 4 — Tasks

#### Phase 4.A – Backend Foundation

**Objetivo:** Tarjetas ligadas a columna y proyecto.

**Tareas:**

- Migración `tasks`: FKs, posición, campos MVP (título, descripción opcional, asignado opcional).  
- Modelo `Task`; relaciones con `Column` / `Project`.  
- `TaskRepository` + `TaskService`: crear, actualizar, mover entre columnas **del mismo proyecto**; plan de eager loading para tablero.

**Criterios de cierre:** Tests de mover tarea y rechazo de columnas de otro proyecto.

---

#### Phase 4.B – Application Layer

**Objetivo:** Acciones HTTP para tareas.

**Tareas:**

- `TaskController` + rutas (crear, editar, mover, borrar si aplica) con policies.  
- Props Inertia para tablero: estructura que minimice N+1.  
- Pest: flujos autorizados y límites multi-tenant.

**Criterios de cierre:** Uso normal del tablero sin API JSON obligatoria.

---

#### Phase 4.C – Frontend Integration

**Objetivo:** Tarjetas visibles por columna.

**Tareas:**

- Render de tareas por columna en la vista tablero.  
- Formularios Inertia crear/editar; acción mover según contrato del backend.

**Criterios de cierre:** Crear/mover/editar tarea desde UI con feedback Inertia.

---

### Phase 5 — Comments

#### Phase 5.A – Backend Foundation

**Objetivo:** Comentarios atados a tarea.

**Tareas:**

- Migración `comments`: `task_id`, `user_id`, cuerpo, timestamps, FKs.  
- Modelo `Comment`; relaciones.  
- `CommentRepository` + `CommentService`: listar por tarea, crear, (editar/borrar si MVP).

**Criterios de cierre:** Tests de servicio con tarea en proyecto de equipo conocido.

---

#### Phase 5.B – Application Layer

**Objetivo:** Rutas Inertia para comentarios.

**Tareas:**

- Rutas anidadas bajo tarea o proyecto según convención ya usada.  
- Controlador + policy (cadena task → project → team).  
- Pest: usuario ajeno no lista/crea comentarios en tarea de otro equipo.

**Criterios de cierre:** Comportamiento servidor completo sin depender de UI.

---

#### Phase 5.C – Frontend Integration

**Objetivo:** UX de comentarios en ficha de tarea.

**Tareas:**

- Sección comentarios en página de tarea (o patrón equivalente del proyecto).  
- Formulario crear; lista con autor y fecha.

**Criterios de cierre:** Flujo feliz + errores de validación visibles.

---

### Phase 6 — Activity Log

#### Phase 6.A – Backend Foundation

**Objetivo:** Registro auditable de eventos clave.

**Tareas:**

- Migración `activity_logs` (polimórfico o FKs explícitas según consultas previstas).  
- Modelo + `ActivityLogRepository`.  
- `ActivityLogService` + llamadas desde **servicios** existentes (crear tarea, mover, comentar, etc.) sin lógica pesada en modelos.

**Criterios de cierre:** Tests de que eventos esperados se persisten en acciones clave.

---

#### Phase 6.B – Application Layer

**Objetivo:** Exponer lectura del log vía Inertia (sin API).

**Tareas:**

- Decidir: props adicionales en páginas existentes vs. ruta dedicada `GET` con `Inertia::render`.  
- Controlador mínimo + policy de lectura acorde al contexto.  
- Pest: solo usuarios autorizados ven entradas del log relevantes.

**Criterios de cierre:** Datos del log disponibles como props Inertia donde se definió.

---

#### Phase 6.C – Frontend Integration

**Objetivo:** Timeline legible.

**Tareas:**

- Componente de lista/timeline consumiendo props.  
- Integrar en vista de tarea o proyecto según Phase 6.B.

**Criterios de cierre:** Usuario ve historial acotado a lo que el backend envía (sin fetch ad hoc).

---

## 3. Refactor continuo

Si la query “equipos del usuario” se repite en más sitios, unificar en **un** lugar (scope de modelo o método de repositorio) en un chunk **0.A** o **1.A** futuro; no duplicar en controladores.

---

## 4. Referencia rápida: entidades (`PROJECT_RULES.md`)

User, Team, TeamMember, Project, Column, Task, Comment, ActivityLog.

**Orden de ejecución:** Phase 0 → 1 → 2 → 3 → 4 → 5 → 6, respetando **.A antes que .B, .B antes que .C** dentro de cada fase.
