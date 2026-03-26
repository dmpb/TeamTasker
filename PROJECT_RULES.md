# 🧠 TeamTasker – AI Development Rules

---

# 🎯 Project Overview

TeamTasker is a SaaS multi-tenant application that allows teams to manage projects using a Kanban system.

This project is built as a **fullstack monolith using Laravel + React**, fully integrated within the Laravel ecosystem.

---

# 🧱 Tech Stack

## Backend
- Laravel 13
- PostgreSQL
- Laravel Fortify (authentication)
- Eloquent ORM
- Service Layer Architecture

## Frontend
- React
- TypeScript
- TailwindCSS

## Environment
- Laravel Sail (Docker)

---

# 🏗️ Architecture Rules (STRICT)

Follow this layering:

Controllers → Services → Repositories → Models

## Responsibilities

### Controllers
- Handle HTTP requests
- Validate input
- Return JSON responses
- Do NOT contain business logic

### Services
- Contain all business logic
- Orchestrate operations
- Call repositories

### Repositories
- Handle database queries only
- No business logic

### Models
- Define relationships
- Define scopes if needed
- No heavy logic

---

# 📁 Frontend Architecture

pages → features → components → api

## Rules

- pages: route-level views
- features: domain logic
- components: reusable UI
- api: HTTP communication layer

---

# 🧩 Core Entities

- User
- Team
- TeamMember
- Project
- Column
- Task
- Comment
- ActivityLog

---

# 🔐 Authentication System (CRITICAL)

Authentication is already implemented using Laravel Fortify.

## Rules

- DO NOT recreate authentication logic
- DO NOT generate login/register controllers
- DO NOT duplicate Fortify flows
- USE Fortify as the authentication backend

## Allowed Actions

- Extend Fortify actions (CreateNewUser, etc.)
- Add fields to users table if needed
- Use authenticated user via:
  - auth()->user()
  - request()->user()

## API Authentication

- Use Sanctum (if needed for API tokens)
- Protect routes with auth middleware

---

# 🐳 Environment Rules

The project uses Laravel Sail.

## Rules

- Assume Docker environment is running
- Do NOT generate non-Docker setup steps
- Use environment variables properly (.env)
- Do NOT hardcode credentials

---

# 🧱 Database Design Rules

- Use foreign keys in all relationships
- Use cascading deletes where appropriate
- Use indexes for foreign keys
- Use snake_case naming

---

# 🧩 Multi-Tenancy Rule (IMPORTANT)

This is a team-based system.

## Rules

- A user can belong to multiple teams
- A team can have multiple users
- All data must be scoped to a team

👉 NEVER expose data across teams

---

# ⚙️ Development Rules

- Do NOT generate unnecessary code
- Keep functions small and focused
- Prefer readability over cleverness
- Avoid duplication
- Follow Laravel conventions
- Use clear naming

---

# 🧪 Output Rules (VERY IMPORTANT)

When generating code:

- ONLY generate what is requested
- DO NOT explain unless asked
- DO NOT generate extra files
- KEEP responses minimal
- FOLLOW existing architecture

---

# 🚀 Development Strategy

Build the system in phases:

1. Teams (since auth already exists)
2. Projects
3. Kanban Board (Columns)
4. Tasks
5. Comments
6. Activity Log

---

# 📌 Current Phase

If not specified, assume:

👉 Phase 1: Teams

---

# 🧩 Teams Module (FIRST PRIORITY)

## Tables

### teams
- id
- name
- owner_id (user_id)
- timestamps

### team_members
- id
- user_id
- team_id
- role (owner, admin, member)
- timestamps

---

# 🧠 Backend Guidelines

- Use Service classes for logic
- Use Repository classes for DB access
- Controllers should be thin

---

# 🧠 Frontend Guidelines

- Use feature-based structure
- Keep UI components reusable
- Separate API calls

---

# ⚠️ Constraints

- This is an MVP
- No overengineering
- No microservices
- No unnecessary abstractions

---

# 🧠 Mindset

This is a production-level SaaS system.

Act as a senior Laravel + React engineer.

Focus on:
- scalability
- clean architecture
- maintainability

---

# 🐳 Docker Execution Rules (CRITICAL)

The project uses Laravel Sail.

## 🚨 MANDATORY RULE

ALL commands MUST be executed inside the Docker container using Sail.

## ✅ Correct Command Usage

Always prefix commands with:

./vendor/bin/sail

### Examples

- PHP commands:
  ./vendor/bin/sail artisan migrate
  ./vendor/bin/sail artisan make:model Team

- Composer:
  ./vendor/bin/sail composer install

- NPM:
  ./vendor/bin/sail npm install
  ./vendor/bin/sail npm run dev

- Tests:
  ./vendor/bin/sail artisan test

---

## ❌ FORBIDDEN

- Running commands directly on host machine
- Using:
  - php artisan ...
  - composer ...
  - npm ...
  - node ...

WITHOUT Sail prefix

---

## 🧠 Behavior Rule for AI

When suggesting or generating commands:

- ALWAYS use Sail
- NEVER assume host environment
- NEVER omit the Sail prefix

---

## ⚠️ Reason

Host machine and Docker container have different:

- PHP versions
- Node versions
- Dependencies
- Database connections

Running commands outside Docker will break the system.

---

## 🧪 Command Output Rule

When generating commands:

- Output ONLY the command
- Do not explain
- Do not wrap in paragraphs

## 🛑 Safety Rule

If a command is about to be executed without Sail:

👉 STOP and correct it before responding
