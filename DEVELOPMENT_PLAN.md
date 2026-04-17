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

### Mejoras planificadas (nuevas)

- **UI/UX de productividad diaria:** dashboard operativo, filtros persistentes, búsqueda contextual, feedback uniforme (toasts/undo).  
- **Colaboración y onboarding:** invitaciones por email y selección de miembros sin depender de `user_id` manual.  
- **Planificación avanzada de trabajo:** fechas límite, prioridad, etiquetas, checklist/subtareas y dependencias entre tareas.  
- **Operativa de tablero:** drag & drop para columnas/tareas sobre el flujo Inertia existente.  
- **Seguimiento y auditoría:** activity log con filtros/export y notificaciones in-app para eventos clave.

### Reglas de ejecución (chunks)

- **Sail** para comandos (`./vendor/bin/sail`).  
- Capas: `Controllers → Services → Repositories → Models`.  
- Tras tocar páginas TSX: manifest Vite (build o dev vía Sail).  
- **No** mezclar backend y frontend en el mismo chunk.

---

## 2. Goals (extensión incremental)

- Consolidar TeamTasker como SaaS Kanban multi-tenant orientado a equipos pequeños/medianos sin romper la arquitectura Laravel + Inertia existente.  
- Reducir fricción operativa en flujos críticos: onboarding de miembros, gestión diaria de tareas y trazabilidad de cambios.  
- Aumentar capacidad de planificación y coordinación (prioridad, fechas, dependencias, subtareas) sin introducir APIs REST ni nuevas tecnologías.  
- Mejorar discoverability y tiempo-a-valor con dashboard útil y navegación/filtros persistentes en vistas principales.  
- Mantener ejecución por chunks de 1–2 interacciones, respetando capas `Controllers → Services → Repositories → Models`.

## 3. Plan por fases y chunks

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

#### Phase 3.A – Backend Foundation ✅ *Completada*

**Objetivo:** Columnas ordenadas por proyecto.

**Tareas:**

- Migración `columns`: `project_id`, nombre, `position` (o equivalente), índices.  
- Modelo `Column`; relación con `Project`.  
- `ColumnRepository` + `ColumnService`: listar ordenado, crear, actualizar, reordenar en transacción si aplica.

**Implementado:** Tabla **`board_columns`** (evita el identificador reservado `columns` en SQL), FK `project_id` → `projects` con `cascadeOnDelete`, `UNIQUE (project_id, position)`; modelo `Column` con `#[Fillable]` y `Project::boardColumns()`; `ColumnRepository` (listado por `position`, crear con posición al final si omite, actualizar nombre, reordenar en `DB::transaction` con fase intermedia de offsets); `ColumnService` como fachada; `ColumnFactory` con `forProject` / `atPosition`; Pest en `ColumnRepositoryTest` y `ColumnServiceTest` (orden, reorden, validación de pertenencia, cascade).

**Criterios de cierre:** Tests de servicio/repositorio para orden y pertenencia a proyecto.

---

#### Phase 3.B – Application Layer ✅ *Completada*

**Objetivo:** Exponer columnas vía Inertia.

**Tareas:**

- Controlador(es) y rutas bajo proyecto (o recurso anidado) con policy en cadena team → project.  
- Acciones: crear/editar/eliminar columna, reordenar (sin JSON para el flujo principal de la SPA).  
- Pest: permisos y consistencia de `project_id`.

**Implementado:** `ColumnController` con `board` (Inertia `teams/projects/board`), `store` / `update` / `destroy` / `reorder` (POST con `column_ids[]`); rutas bajo `teams/{team}/projects/{project}` con `scopeBindings()`; `ColumnPolicy` delegando en `ProjectPolicy::update` (misma regla que gestionar proyectos); `StoreColumnRequest`, `UpdateColumnRequest`, `ReorderColumnsRequest` con `team_id`/`project_id` coherentes; `Project::columns()` como alias de `boardColumns()` para binding anidado `{column}`; `deleteColumn` en repositorio/servicio; página React mínima del tablero; Wayfinder regenerado (`--with-form`); Pest en `ColumnControllerTest` (invitado, miembro, owner, 404 cruzado, validación).

**Criterios de cierre:** Inertia o redirects; tests verdes.

---

#### Phase 3.C – Frontend Integration ✅ *Completada*

**Objetivo:** Vista tablero mínima.

**Tareas:**

- Página tablero: columnas en UI.  
- Formularios Inertia para alta/edición; reordenar solo si MVP lo exige (si no, acciones simples vía POST).

**Implementado:** `teams/projects/board`: columnas en tarjetas horizontales (scroll); `Form` Wayfinder para alta (`ColumnController.store`), renombrar (`update`) y borrar (`destroy` con confirm); reordenación con botones izquierda/derecha vía `router.post` a `reorder` y `column_ids`; miembros sin permiso ven columnas sin acciones; enlace al tablero desde el nombre del proyecto en `teams/projects/index`; copy de archivo en fase posterior para tareas.

**Criterios de cierre:** Usuario gestiona columnas dentro del flujo Inertia.

---

### Phase 4 — Tasks

#### Phase 4.A – Backend Foundation ✅ *Completada*

**Objetivo:** Tarjetas ligadas a columna y proyecto.

**Tareas:**

- Migración `tasks`: FKs, posición, campos MVP (título, descripción opcional, asignado opcional).  
- Modelo `Task`; relaciones con `Column` / `Project`.  
- `TaskRepository` + `TaskService`: crear, actualizar, mover entre columnas **del mismo proyecto**; plan de eager loading para tablero.

**Implementado:** Tabla `tasks` con `project_id` / `column_id` / `assignee_id` (nullable, `nullOnDelete` en `users`), `title`, `description`, `position`, `UNIQUE (column_id, position)`, cascadas al borrar proyecto o columna; modelo `Task` con `project()`, `column()`, `assignee()`; `Column::tasks()`, `Project::tasks()`, `User::assignedTasks()`; `TaskRepository` (`listColumnsWithTasksOrderedForProject` con `with('tasks')` ordenado, `createTask` validando columna del proyecto, `updateTask`, `moveTaskToColumn` en transacción con `InvalidArgumentException` si el proyecto de la columna destino difiere); `TaskService` con `boardColumnsWithTasks`; `TaskFactory` (`forColumn`, `atPosition`); Pest en `TaskRepositoryTest` y `TaskServiceTest`.

**Criterios de cierre:** Tests de mover tarea y rechazo de columnas de otro proyecto.

---

#### Phase 4.B – Application Layer ✅ *Completada*

**Objetivo:** Acciones HTTP para tareas.

**Tareas:**

- `TaskController` + rutas (crear, editar, mover, borrar si aplica) con policies.  
- Props Inertia para tablero: estructura que minimice N+1.  
- Pest: flujos autorizados y límites multi-tenant.

**Implementado:** `TaskPolicy` (`create` sobre columna, `update`/`delete` sobre tarea vía permiso de proyecto); rutas anidadas `POST …/columns/{column}/tasks`, `PATCH …/tasks/{task}`, `POST …/tasks/{task}/move`, `DELETE …/tasks/{task}` con binding `{task}` bajo proyecto; `StoreTaskRequest` / `UpdateTaskRequest` / `MoveTaskRequest` (asignado debe ser dueño o miembro del equipo del proyecto; columna destino del mismo proyecto); `TaskController`; tablero Inertia vía `TaskService::boardColumnsWithTasks` + `tasks.assignee` en eager load; `can.manageTasks`; UI mínima en `board` (alta, mover con `<select>`, borrar; edición vía PATCH cubierta en tests); Wayfinder regenerado; Pest en `TaskControllerTest` y ajustes en `ColumnControllerTest`.

**Criterios de cierre:** Uso normal del tablero sin API JSON obligatoria.

---

#### Phase 4.C – Frontend Integration ✅ *Completada*

**Objetivo:** Tarjetas visibles por columna.

**Tareas:**

- Render de tareas por columna en la vista tablero.  
- Formularios Inertia crear/editar; acción mover según contrato del backend.

**Implementado:** `ColumnController::board` expone `assignableUsers` (owner + miembros del equipo, solo si `manageTasks`); `board.tsx`: tarjetas con vista lectura para miembros; con `manageTasks`, formulario **PATCH** por tarea (título, descripción, asignado con `InputError`), **POST** alta con descripción y asignado opcionales, mover y borrar; estilos alineados con inputs; Pest: `assignableUsers` en Inertia (owner vs miembro), validación `title` en update.

**Criterios de cierre:** Crear/mover/editar tarea desde UI con feedback Inertia.

---

### Phase 5 — Comments

#### Phase 5.A – Backend Foundation ✅ *Completada*

**Objetivo:** Comentarios atados a tarea.

**Tareas:**

- Migración `comments`: `task_id`, `user_id`, cuerpo, timestamps, FKs.  
- Modelo `Comment`; relaciones.  
- `CommentRepository` + `CommentService`: listar por tarea, crear, (editar/borrar si MVP).

**Implementado:** Migración `comments` con `task_id` / `user_id` / `body` + FKs con `cascadeOnDelete`; modelo `Comment` con `task()` y `user()`; relaciones inversas `Task::comments()` y `User::comments()`; `CommentRepository` (listar por tarea con `user`, crear, actualizar, borrar) y `CommentService`; `CommentFactory` con estados `forTask`/`byUser`; tests en `CommentRepositoryTest` y `CommentServiceTest`.

**Criterios de cierre:** Tests de servicio con tarea en proyecto de equipo conocido.

---

#### Phase 5.B – Application Layer ✅ *Completada*

**Objetivo:** Rutas Inertia para comentarios.

**Tareas:**

- Rutas anidadas bajo tarea o proyecto según convención ya usada.  
- Controlador + policy (cadena task → project → team).  
- Pest: usuario ajeno no lista/crea comentarios en tarea de otro equipo.

**Implementado:** `TaskPolicy::view` (listar comentarios vía vista de proyecto); `CommentPolicy` (`create` sobre `Task`, `view`/`update`/`delete` sobre `Comment` con cadena task → project → team); rutas anidadas `GET|POST …/tasks/{task}/comments`, `PATCH|DELETE …/comments/{comment}` con `scopeBindings`; `StoreCommentRequest` / `UpdateCommentRequest`; `CommentController` (index Inertia `teams/projects/tasks/comments`, store/update/destroy con redirects); página Inertia mínima para listado; Wayfinder regenerado; Pest en `CommentControllerTest` (invitado, otro equipo, miembro, validación, 404 por tarea/comentario incoherente, edición autor vs miembro).

**Criterios de cierre:** Comportamiento servidor completo sin depender de UI.

---

#### Phase 5.C – Frontend Integration ✅ *Completada*

**Objetivo:** UX de comentarios en ficha de tarea.

**Tareas:**

- Sección comentarios en página de tarea (o patrón equivalente del proyecto).  
- Formulario crear; lista con autor y fecha.

**Implementado:** `teams/projects/tasks/comments.tsx` evolucionada a ficha de tarea con UX de comentarios (alta con `<Form>`, listado con autor + fecha formateada, estado vacío, edición y borrado cuando hay permiso, errores de validación visibles con `InputError`); enlace **Comments** desde cada tarjeta en `teams/projects/board.tsx`; `CommentController::index` ahora expone permisos (`can.createComments` y `comments[].can.update|delete`) para render condicional seguro en frontend; tests de controlador actualizados para validar nuevos props Inertia de permisos.

**Criterios de cierre:** Flujo feliz + errores de validación visibles.

---

### Phase 6 — Activity Log

#### Phase 6.A – Backend Foundation ✅ *Completada*

**Objetivo:** Registro auditable de eventos clave.

**Tareas:**

- Migración `activity_logs` (polimórfico o FKs explícitas según consultas previstas).  
- Modelo + `ActivityLogRepository`.  
- `ActivityLogService` + llamadas desde **servicios** existentes (crear tarea, mover, comentar, etc.) sin lógica pesada en modelos.

**Implementado:** migración `activity_logs` con `project_id`, `actor_id`, `event`, `subject` polimórfico y `metadata` JSON; modelo `ActivityLog` con casts/relaciones (`project`, `actor`, `subject`) y relaciones inversas en `Project`/`User`; `ActivityLogRepository` (crear y listar por proyecto) y `ActivityLogService` (eventos `task.*` y `comment.*`); integración en `TaskService` y `CommentService` para registrar crear/editar/mover/borrar tarea y crear/editar/borrar comentario; controladores de tareas/comentarios actualizados para pasar actor autenticado; tests en `ActivityLogServiceTest` y ampliación de `TaskServiceTest`/`CommentServiceTest` para verificar persistencia de eventos.

**Criterios de cierre:** Tests de que eventos esperados se persisten en acciones clave.

---

#### Phase 6.B – Application Layer ✅ *Completada*

**Objetivo:** Exponer lectura del log vía Inertia (sin API).

**Tareas:**

- Decidir: props adicionales en páginas existentes vs. ruta dedicada `GET` con `Inertia::render`.  
- Controlador mínimo + policy de lectura acorde al contexto.  
- Pest: solo usuarios autorizados ven entradas del log relevantes.

**Implementado:** ruta dedicada `GET teams/{team}/projects/{project}/activity` (`teams.projects.activity.index`) con `scopeBindings`; `ActivityLogController@index` vía `Inertia::render('teams/projects/activity/index')` y props acotados (`team`, `project`, `activityLogs`); `ActivityLogPolicy` (`viewAny` por proyecto y `view` por registro, delegando en acceso a proyecto/equipo); repositorio de logs con eager-load de `actor`; página Inertia base para consumo del backend; Pest `ActivityLogControllerTest` cubriendo invitado, usuario de otro equipo, miembro autorizado y 404 por mezcla team/proyecto.

**Criterios de cierre:** Datos del log disponibles como props Inertia donde se definió.

---

#### Phase 6.C – Frontend Integration ✅ *Completada*

**Objetivo:** Timeline legible.

**Tareas:**

- Componente de lista/timeline consumiendo props.  
- Integrar en vista de tarea o proyecto según Phase 6.B.

**Implementado:** `teams/projects/activity/index.tsx` ahora renderiza timeline legible de eventos (tipo de evento con icono, actor, fecha formateada, descripción derivada de `metadata`/`subject`, y estado vacío); sin fetch extra, todo consumido desde props Inertia de Phase 6.B; navegación integrada desde `teams/projects/board.tsx` con enlace **View activity** a la ruta del log del proyecto.

**Criterios de cierre:** Usuario ve historial acotado a lo que el backend envía (sin fetch ad hoc).

---

### Phase 7 — Productividad UI/UX (dashboard, búsqueda y feedback)

**Estado:** Completada (7.A–7.C).

#### Phase 7.A – Dashboard Operativo

**Objetivo:** Reemplazar placeholders del dashboard con métricas y listas accionables para trabajo diario por equipo.

**Chunks:**

- **Chunk 7.A.1 (Backend):** agregar consultas en repositorios/servicios para “mis tareas”, “tareas vencidas/próximas”, “actividad reciente” y “proyectos activos” (siempre scope por team membership).  
  - **Áreas:** `app/Repositories`, `app/Services`, `app/Http/Controllers`, `app/Models` (scopes livianos).  
  - **Verificación:** Pest de autorización multi-tenant + pruebas de datos agregados.
- **Chunk 7.A.2 (Frontend):** sustituir `resources/js/pages/dashboard.tsx` por widgets/cards con enlaces profundos a tablero, comentarios y actividad.  
  - **Áreas:** `resources/js/pages/dashboard.tsx`, componentes UI compartidos si aplica.  
  - **Verificación:** render Inertia correcto, navegación funcional y sin errores de tipo/lint.

**Definition of done:**

- Dashboard muestra datos reales del usuario autenticado, con estado vacío claro.  
- Cada widget tiene CTA navegable a una acción real.  
- Cobertura mínima de tests para permisos y shape de props Inertia.

**Riesgos / dependencias:**

- Dependencia de campos de planificación (due dates/prioridad) para enriquecer métricas (si no existen, usar fallback incremental).  
- Riesgo de consultas pesadas; mitigar con índices y límites de registros recientes.

---

#### Phase 7.B – Búsqueda y filtros persistentes

**Objetivo:** Mejorar descubrimiento y foco con filtros persistentes en proyectos, tablero, comentarios y actividad.

**Chunks:**

- **Chunk 7.B.1 (Backend):** extender Form Requests/controladores para aceptar filtros (`assignee`, `column`, `status`, `event`, `date_range`, `query`) y delegar a repositorios.  
  - **Áreas:** controladores de proyectos/tablero/comentarios/actividad, repositorios de `Task`, `Comment`, `ActivityLog`.  
  - **Verificación:** Pest con combinaciones de filtros y garantía de aislamiento por team.
- **Chunk 7.B.2 (Frontend):** UI de filtros + persistencia en query string (patrón Inertia), chips de filtros activos y acción “clear all”.  
  - **Áreas:** `resources/js/pages/teams/projects/index.tsx`, `board.tsx`, `tasks/comments.tsx`, `activity/index.tsx`.  
  - **Verificación:** mantener filtros al navegar/volver y no perder estado por reload.

**Definition of done:**

- Filtros funcionales en todas las vistas objetivo.  
- Estado persistido por URL compartible.  
- Tests cubren filtros principales y casos vacíos/sin resultados.

**Riesgos / dependencias:**

- Complejidad de UX si se mezclan demasiados filtros; priorizar presets útiles.  
- Necesidad de normalizar naming de parámetros para evitar inconsistencia entre páginas.

---

#### Phase 7.C – Feedback UX unificado (toasts + confirmaciones + undo corto)

**Objetivo:** Unificar feedback de acciones con mensajes consistentes, confirmaciones no intrusivas y opción de revertir acciones destructivas de bajo riesgo.

**Chunks:**

- **Chunk 7.C.1 (Aplicación):** estandarizar flashes de éxito/error por controlador y definir contrato de mensajes por acción.  
  - **Áreas:** controladores de teams/projects/columns/tasks/comments, `HandleInertiaRequests` si se comparte flash global.  
  - **Verificación:** tests HTTP/Inertia validando flashes y códigos esperados.
- **Chunk 7.C.2 (Frontend):** implementar capa visual de toasts y reemplazar confirmaciones `window.confirm` por patrón de diálogo consistente; añadir undo temporal en archivado/borrado lógico cuando aplique.  
  - **Áreas:** layout/components UI + páginas con acciones destructivas.  
  - **Verificación:** QA manual de happy/error paths, sin duplicar toasts en navegación.

**Definition of done:**

- Todas las mutaciones principales muestran feedback consistente.  
- Confirmaciones tienen copy claro de impacto.  
- Al menos una acción soporta undo temporal documentado.

**Riesgos / dependencias:**

- Undo requiere definir alcance exacto (hard delete vs archive/restore).  
- Riesgo de regresión visual transversal; requerirá smoke de páginas clave.

---

### Phase 8 — Onboarding y colaboración (invitaciones + miembros)

**Estado:** Completada (8.A–8.D).

**Nota de alcance:** invitaciones con **enlace manual primero** (8.A–8.C), luego **correo automático** (8.D), misma entidad `team_invitations`.

#### Phase 8.A – Backend Foundation (Invitaciones por email)

**Objetivo:** Incorporar modelo de invitaciones a equipo por email, con expiración y estados.

**Chunks:**

- **Chunk 8.A.1:** migración `team_invitations` (team_id, email, role, invited_by, token, expires_at, accepted_at/cancelled_at) + índices/FKs.  
  - **Áreas:** `database/migrations`, modelos nuevos, enum de estado/rol si aplica.  
  - **Verificación:** tests de migración/modelo/relaciones y constraints.
- **Chunk 8.A.2:** repositorio/servicio para crear, reenviar, cancelar y aceptar invitaciones con reglas de owner/admin.  
  - **Áreas:** `app/Repositories`, `app/Services`, policies relacionadas.  
  - **Verificación:** Pest de reglas de negocio y autorización.

**Definition of done:**

- Invitaciones persisten con estado y expiración.  
- Reglas de permisos consistentes con `TeamPolicy::manageMembers`.  
- Cobertura de negocio (duplicados, expiradas, canceladas).

**Riesgos / dependencias:**

- **Decisión:** entrega en dos pasos — (1) persistencia + token + aceptación autenticada + copiar enlace manual; (2) envío automático vía correo en **Phase 8.D** usando Mail de Laravel (sin nuevas tecnologías).  
- Dependencia de política de caducidad y reuso de invitaciones.

---

#### Phase 8.B – Application Layer (Flujos HTTP Inertia)

**Objetivo:** Exponer flujos de invitación/aceptación/cancelación sin API REST.

**Chunks:**

- **Chunk 8.B.1:** rutas web + controladores + Form Requests para invitar y gestionar invitaciones dentro de `teams/{team}`.  
  - **Áreas:** `routes/web.php`, controladores de teams/members/invitations, requests, policies.  
  - **Verificación:** Pest de acceso autorizado/no autorizado y validaciones.
- **Chunk 8.B.2:** flujo de aceptación por usuario autenticado (matching por email) y alta de membresía en transacción.  
  - **Áreas:** services + controller action de aceptación.  
  - **Verificación:** tests de aceptación exitosa, token inválido, expirado y email no coincidente.

**Definition of done:**

- Un admin/owner puede crear y cancelar invitaciones.  
- Usuario invitado puede aceptar desde flujo web autenticado.  
- No se expone data cross-team.

**Riesgos / dependencias:**

- Dependencia de UX de autenticación actual (Fortify) para “join flow”.  
- Riesgo de race conditions al aceptar dos veces; mitigar con locking/transacción.

---

#### Phase 8.C – Frontend Integration (miembros sin `user_id` manual)

**Objetivo:** Sustituir la experiencia de alta de miembros por invitación por email y selector/autocomplete de usuarios existentes.

**Chunks:**

- **Chunk 8.C.1:** actualizar `teams/show.tsx` para formularios de invitación, listado de invitaciones pendientes y acciones de cancel/retry.  
  - **Áreas:** `resources/js/pages/teams/show.tsx` + componentes de formulario/lista.  
  - **Verificación:** errores de validación visibles, estados vacíos y permisos condicionales.
- **Chunk 8.C.2:** autocomplete opcional para usuarios existentes (si el backend expone dataset acotado por permisos) manteniendo fallback por email.  
  - **Áreas:** página `teams/show`, props Inertia y typing TS.  
  - **Verificación:** interacción sin recargas inesperadas y sin exponer usuarios fuera de contexto.

**Definition of done:**

- El flujo principal de agregar miembros no depende de conocer `user_id`.  
- Invitaciones y membresías se gestionan desde una sola vista de equipo.  
- UI respeta permisos owner/admin/member.

**Riesgos / dependencias:**

- Riesgo de fuga de datos de usuarios en autocomplete; limitar resultados y campos.  
- **Decisión:** en la primera iteración de invitaciones, UI incluye **copiar enlace** y flujo de aceptación; el envío automático de correo queda en **Phase 8.D**.

---

#### Phase 8.D – Envío de correo real (segunda iteración)

**Objetivo:** Tras cerrar invitaciones con enlace manual (8.A–8.C), enviar email al invitado usando el stack Laravel existente (Mailable / notificación por mail, cola opcional).

**Chunks:**

- **Chunk 8.D.1 (Backend):** Mailable o notificación con URL firmada/token; disparo al crear/reenviar invitación; respetar `TeamPolicy::manageMembers`.  
  - **Áreas:** `app/Mail` o `app/Notifications`, `TeamInvitationService`, tests con `Mail::fake()`.  
  - **Verificación:** Pest de que el mail se encola o envía con datos correctos y sin fuga cross-team.
- **Chunk 8.D.2 (Frontend):** botón “Reenviar email” y feedback de éxito/error alineado con Phase 7.C.  
  - **Áreas:** `teams/show.tsx` o equivalente.  
  - **Verificación:** flujo manual + reenvío coexisten.

**Definition of done:**

- Crear/re-enviar invitación puede enviar correo real cuando `MAIL_*` esté configurado.  
- Sin correo configurado, el producto sigue siendo usable vía copiar enlace (8.C).

**Riesgos / dependencias:**

- Configuración de entorno (`MAIL_MAILER`, etc.) fuera del repo; documentar en README si aplica.

---

### Phase 9 — Planificación avanzada de tareas

#### Phase 9.A – Fechas límite y prioridad

**Objetivo:** Añadir `due_date` y `priority` a tareas para priorización y métricas operativas.

**Chunks:**

- **Chunk 9.A.1 (Backend):** migración de campos en `tasks`, enum/casts y filtros por vencimiento/prioridad.  
  - **Áreas:** `database/migrations`, `app/Models/Task.php`, repositorio/servicio de tareas.  
  - **Verificación:** tests de validación, ordenación y filtros por rango temporal.
- **Chunk 9.A.2 (Frontend):** formularios de tarea en `board.tsx` y vistas relacionadas con indicadores visuales (overdue/today/upcoming).  
  - **Áreas:** `resources/js/pages/teams/projects/board.tsx`, dashboard widgets.  
  - **Verificación:** creación/edición/movimiento mantienen consistencia visual y funcional.

**Definition of done:**

- Tareas soportan prioridad y fecha límite de forma estable.  
- Se visualizan alertas de vencimiento en tablero/dashboard.  
- Tests cubren autorización + validación de campos nuevos.

**Riesgos / dependencias:**

- Dependencia de timezone y formato de fecha; estandarizar en backend y UI.  
- Riesgo de ruido visual si no se define jerarquía de estados.

---

#### Phase 9.B – Etiquetas (labels) y clasificación

**Objetivo:** Permitir clasificación transversal de tareas con etiquetas por equipo/proyecto.

**Chunks:**

- **Chunk 9.B.1 (Backend):** tablas de labels y pivote task_label (scope por equipo/proyecto), CRUD básico en servicio/repositorio.  
  - **Áreas:** migraciones, modelos, repositorios, servicios, policies.  
  - **Verificación:** tests de pertenencia de label a contexto correcto y filtros.
- **Chunk 9.B.2 (Frontend):** selector de etiquetas en formularios de tarea + filtros por label en board/listados.  
  - **Áreas:** `board.tsx`, posibles componentes reutilizables de labels.  
  - **Verificación:** persistencia en query string y render consistente de badges.

**Definition of done:**

- Se pueden crear/asignar etiquetas y filtrar tareas por etiqueta.  
- No se permiten labels cruzadas entre equipos/proyectos.  
- UX clara para etiquetas vacías y múltiples.

**Riesgos / dependencias:**

- Riesgo de proliferación de labels; considerar límites y convención de nombres.  
- Dependencia de filtros Phase 7.B para experiencia completa.

---

#### Phase 9.C – Checklist / subtareas

**Objetivo:** Descomponer tareas en checklist para seguimiento granular de progreso.

**Decisión de UX:** en el tablero solo **resumen** (`x/y` completadas) + enlace al detalle de tarea; la **edición completa** del checklist (CRUD ítems, reordenar) vive en una **vista dedicada de detalle de tarea** (Inertia), para no saturar `board.tsx`.

**Chunks:**

- **Chunk 9.C.1 (Backend):** entidad `task_checklist_items` con orden, estado y autoría mínima; servicios para CRUD y toggle.  
  - **Áreas:** migraciones, modelos, repositorio/servicio/checklist controller.  
  - **Verificación:** Pest de consistencia de orden y permisos.
- **Chunk 9.C.2 (Frontend — board):** en `board.tsx` mostrar progreso `x/y` y CTA “Detalle” / enlace a la página de detalle de tarea (props mínimos desde `ColumnController::board` o visit dedicada).  
  - **Áreas:** `resources/js/pages/teams/projects/board.tsx`, props Inertia del tablero.  
  - **Verificación:** board no incluye formularios largos de checklist.
- **Chunk 9.C.3 (Frontend — detalle):** página Inertia de detalle de tarea con checklist completo (alta, toggle, reordenar, borrar según permisos).  
  - **Áreas:** nueva página bajo `resources/js/pages/...`, rutas/controlador si se separa de comentarios o se unifica con ficha existente.  
  - **Verificación:** actualización post-submit y errores por ítem visibles.

**Definition of done:**

- Cada tarea puede tener checklist con estado completo/incompleto.  
- El progreso `x/y` es visible en el board; la edición completa ocurre solo en detalle.  
- Cobertura de acciones principales y límites de permiso.

**Riesgos / dependencias:**

- Alinear navegación entre board, comentarios (`comments.tsx`) y detalle de tarea para no duplicar rutas confusas.

---

#### Phase 9.D – Dependencias entre tareas

**Objetivo:** Modelar bloqueos entre tareas (blocking/blocked-by) para evitar avances inconsistentes.

**Decisión de negocio (bloqueo suave):** mientras existan dependencias no satisfechas, la tarea **sí puede moverse entre columnas y editarse**; solo se **impide completar/cerrar** la tarea dependiente hasta que las prerequisitas estén resueltas. El backend debe rechazar la acción de “completar” con error claro; la UI muestra badge/mensaje de bloqueo.

**Chunks:**

- **Chunk 9.D.1 (Backend):** relación de dependencias y validaciones anti-ciclo básicas en servicio.  
  - **Áreas:** migración pivote de dependencias, servicio de tareas, repositorio.  
  - **Verificación:** tests de detección de ciclo simple y pertenencia al mismo proyecto.
- **Chunk 9.D.2 (Backend):** regla de “completar” bloqueado si hay dependencias pendientes (**bloqueo suave**: no aplicar bloqueo al `move` entre columnas).  
  - **Áreas:** `TaskService` / `TaskController` según exista acción explícita de completar o se derive de columna “Done”.  
  - **Verificación:** Pest — mover permitido, completar rechazado con mensaje esperado.
- **Chunk 9.D.3 (Frontend):** selector de dependencias en **detalle de tarea** (preferido) o mínimo en board; indicadores visuales de “blocked until …”.  
  - **Áreas:** vista de detalle de tarea, `board.tsx` solo badges si aplica.  
  - **Verificación:** tareas bloqueadas para completar muestran estado claro; mover sigue permitido.

**Definition of done:**

- Se pueden crear/quitar dependencias dentro del mismo proyecto.  
- El sistema evita ciclos básicos y aplica bloqueo suave al completar.  
- Tests cubren reglas de consistencia y multi-tenant.

**Riesgos / dependencias:**

- Definir con precisión qué significa “completar” en el producto (columna terminal vs campo `completed_at`); alinear UI y servicio.

---

### Phase 10 — Operación avanzada (drag & drop, notificaciones, actividad avanzada)

#### Phase 10.A – Drag & drop de columnas y tareas

**Objetivo:** Reemplazar flujos manuales de mover por interacción directa de arrastrar y soltar.

**Chunks:**

- **Chunk 10.A.1 (Frontend):** habilitar DnD en board para columnas y tareas, reutilizando endpoints `reorder` y `move` ya existentes.  
  - **Áreas:** `resources/js/pages/teams/projects/board.tsx`, componentes auxiliares DnD.  
  - **Verificación:** movimiento visual consistente y fallback accesible por teclado/botones.
- **Chunk 10.A.2 (Aplicación):** ajustar contratos de payload y validaciones para garantizar orden/posición estable en operaciones rápidas.  
  - **Áreas:** requests/controladores/servicios de `Column` y `Task`.  
  - **Verificación:** tests de reorden/movimiento concurrente básico y no regresión.

**Definition of done:**

- Usuario mueve tareas y columnas con DnD sin perder persistencia.  
- Existe fallback accesible para interacción no-pointer.  
- No se rompe compatibilidad con flujo Inertia actual.

**Riesgos / dependencias:**

- Dependencia de librería ya permitida por stack (sin introducir tecnología ajena al proyecto).  
- Riesgo de regresiones de usabilidad en móvil; planear QA responsive.

---

#### Phase 10.B – Notificaciones in-app

**Objetivo:** Alertar a usuarios sobre asignaciones, menciones y actividad relevante en sus equipos/proyectos.

**Chunks:**

- **Chunk 10.B.1 (Backend):** eventos de dominio + persistencia de notificaciones por usuario con estado leído/no leído.  
  - **Áreas:** servicios de tareas/comentarios/miembros, modelos/repositorios de notificación.  
  - **Verificación:** tests de generación y visibilidad de notificaciones por actor/receptor.
- **Chunk 10.B.2 (Frontend):** centro de notificaciones en layout/sidebar con contador y acciones “mark as read”.  
  - **Áreas:** layout principal, componentes de notificación, páginas afectadas por deep links.  
  - **Verificación:** navegación al contexto correcto desde cada notificación.

**Definition of done:**

- Notificaciones se crean en eventos definidos y solo para usuarios autorizados.  
- El usuario puede ver/leer/limpiar notificaciones desde UI.  
- Cobertura de casos de multi-tenant y permisos.

**Riesgos / dependencias:**

- Riesgo de ruido por exceso de eventos; definir matriz mínima de disparadores.  
- **Decisión:** primera iteración de Phase 10.B solo **notificaciones in-app** (sin push ni email como canal de notificación); el correo transaccional de invitaciones es independiente (Phase 8.D).

---

#### Phase 10.C – Activity Log avanzado (filtros + export)

**Objetivo:** Potenciar auditoría con filtros por actor/evento/fecha y export simple.

**Decisión de permisos:** el **export CSV** (y cualquier descarga masiva) solo para **`owner` y `admin` del equipo**; `member` puede seguir **viendo** el log en UI si la policy actual lo permite, pero sin acción de export.

**Chunks:**

- **Chunk 10.C.1 (Backend):** ampliar `ActivityLogRepository` con filtros compuestos y endpoint de export (CSV) bajo autorización de proyecto.  
  - **Áreas:** repositorio/servicio/controlador de actividad, policies, tests de export.  
  - **Verificación:** Pest de filtros y de contenido exportado con scope correcto; miembro sin rol admin/owner recibe 403 en export.
- **Chunk 10.C.2 (Frontend):** filtros UI en `activity/index.tsx` + acción de export conservando parámetros activos.  
  - **Áreas:** `resources/js/pages/teams/projects/activity/index.tsx`.  
  - **Verificación:** resultado de export coincide con la tabla filtrada en UI.

**Definition of done:**

- Usuario autorizado filtra y exporta actividad del proyecto.  
- Export respeta exactamente permisos y filtros aplicados.  
- No se introducen endpoints API fuera del flujo web/Inertia.

**Riesgos / dependencias:**

- Dependencia de volumen de datos para export; considerar límites/paginación.  
- Riesgo de exponer metadata sensible; revisar whitelist de columnas exportables.

---

### Seguimiento incremental (nuevo alcance)

**Recommended next subphase:** `Phase 9.A` (fechas límite y prioridad en tareas), tras cierre de Phase 8.

**Alternative paths:**

- **Path A (UX-first):** `7.A → 7.B → 7.C → 10.A` para acelerar adopción de usuarios actuales.  
- **Path B (Collab-first):** `8.A → 8.B → 8.C → 10.B` para mejorar onboarding y comunicación.  
- **Path C (Planning-first):** `9.A → 9.B → 9.C → 9.D` para equipos que necesitan control operativo avanzado.

**Execution order note:** mantener secuencia `A → B → C` dentro de cada fase; no mezclar backend+frontend del mismo subphase en un único chunk.

**Status tracking (append-only):**

- `Phase 7.A`: ✅ done  
- `Phase 7.B`: ✅ done  
- `Phase 7.C`: ✅ done  
- `Phase 8.A`: ✅ done  
- `Phase 8.B`: ✅ done  
- `Phase 8.C`: ✅ done  
- `Phase 8.D`: ✅ done  
- `Phase 9.A`: ⏳ pending  
- `Phase 9.B`: ⏳ pending  
- `Phase 9.C`: ⏳ pending  
- `Phase 9.D`: ⏳ pending  
- `Phase 10.A`: ⏳ pending  
- `Phase 10.B`: ⏳ pending  
- `Phase 10.C`: ⏳ pending

---

## 4. Refactor continuo

Si la query “equipos del usuario” se repite en más sitios, unificar en **un** lugar (scope de modelo o método de repositorio) en un chunk **0.A** o **1.A** futuro; no duplicar en controladores.

---

## 5. Referencia rápida: entidades (`PROJECT_RULES.md`)

User, Team, TeamMember, TeamInvitation, Project, Column, Task, Comment, ActivityLog.

**Orden de ejecución:** Phase 0 → 1 → 2 → 3 → 4 → 5 → 6 → 7 → 8 (**8.A → 8.B → 8.C → 8.D**) → 9 → 10, respetando **.A antes que .B, .B antes que .C** dentro de cada fase (y subfases adicionales como 8.D, 9.D.2, 9.C.3 en el orden indicado en cada fase).

---

## 6. Out of Scope (actualizado)

- No migrar a microservicios ni introducir arquitectura API REST para el producto (se mantiene Inertia server-driven).  
- No incorporar herramientas/servicios externos no exigidos por el MVP incremental (ej. push realtime complejo) en fases 7–10 iniciales.  
- No rediseñar capas ni romper la convención `Controllers → Services → Repositories → Models`.  
- No reescribir módulos ya completados salvo ajustes puntuales de compatibilidad con nuevas fases.  
- No implementar automatizaciones avanzadas (rules engine, IA predictiva) antes de cerrar alcance funcional de fases 7–10.

---

## 7. Decisiones de producto (cerradas) y supuestos

### Decisiones cerradas

- **Invitaciones (Phase 8):** enfoque **híbrido** — primero persistencia + token + aceptación autenticada + **copiar enlace** en UI (8.A–8.C); después **envío real de correo** en **Phase 8.D** con Mail de Laravel (sin nuevas tecnologías).  
- **Dependencias (Phase 9.D):** **bloqueo suave** — se bloquea solo **completar/cerrar** la tarea dependiente; **mover entre columnas y editar** siguen permitidos mientras haya prerequisitos pendientes.  
- **Checklist (Phase 9.C):** en **board** solo resumen `x/y` + enlace al detalle; **edición completa** del checklist en **vista dedicada de detalle de tarea** (nuevo chunk 9.C.3).  
- **Export activity log (Phase 10.C):** solo **`owner` y `admin`** del equipo; **`member`** sin export (puede ver timeline si policy lo permite).  
- **Notificaciones (Phase 10.B):** primera iteración **solo in-app** (sin push ni email como canal de notificación).

### Supuestos / alcance abierto menor

- **Assumption:** dashboard Phase 7.A inicia con métricas de últimos **7/14 días** para controlar carga y legibilidad.  
- **Nota:** permiso granular futuro (`member` con `export_activity_log`) queda **fuera de alcance** hasta post-MVP si se pide explícitamente.
