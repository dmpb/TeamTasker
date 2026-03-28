# 🧠 TeamTasker – AI Development Rules

---

# 🎯 Project Overview

TeamTasker is a SaaS multi-tenant application that allows teams to manage projects using a Kanban system.

This project is built as a **fullstack monolith using Laravel + React with Inertia.js**, fully integrated within the Laravel ecosystem.

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
- Inertia.js

## Environment
- Laravel Sail (Docker)

---

# 🏗️ Architecture Rules (STRICT)

Follow this layering:

Controllers → Services → Repositories → Models

---

## Responsibilities

### Controllers
- Handle HTTP requests
- Validate input
- Return Inertia responses
- Handle redirects
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

# ⚡ Inertia.js Rules (CRITICAL)

This project uses Laravel with Inertia.js and React.

---

## Architecture Style

This is NOT a REST API system.

This is a server-driven SPA using Inertia.

---

## Routing Rules

- Use routes in: web.php
- Do NOT use /api routes
- Do NOT create API controllers

---

## Controller Rules

Controllers must:

- Return Inertia::render()
- Pass data as props
- Handle redirects after actions

---

## Response Rules

- Do NOT return JSON
- Always use:
  return Inertia::render('PageName', [...])

---

## Frontend Communication

- Use Inertia forms or router
- Do NOT use fetch/axios manually unless necessary

---

## Validation

- Use Laravel validation
- Errors are automatically handled by Inertia

---

## Auth Usage

- Use auth()->user()
- Share user globally if needed

---

# 🔐 Authentication System (CRITICAL)

Authentication is already implemented using Laravel Fortify.

---

## Rules

- DO NOT recreate authentication logic
- DO NOT generate login/register controllers
- DO NOT duplicate Fortify flows
- USE Fortify as the authentication backend

---

## Allowed Actions

- Extend Fortify actions (CreateNewUser, etc.)
- Add fields to users table if needed
- Use:
  - auth()->user()
  - request()->user()

---

# 🐳 Docker Execution Rules (CRITICAL)

The project uses Laravel Sail.

---

## 🚨 MANDATORY RULE

ALL commands MUST be executed using:

./vendor/bin/sail

---

## ✅ Correct Command Usage

### PHP
./vendor/bin/sail artisan migrate
./vendor/bin/sail artisan make:model Team

### Composer
./vendor/bin/sail composer install

### NPM
./vendor/bin/sail npm install
./vendor/bin/sail npm run dev

### Tests
./vendor/bin/sail artisan test

---

## ❌ FORBIDDEN

- php artisan ...
- composer ...
- npm ...
- node ...

WITHOUT Sail prefix

---

## 🧠 Behavior Rule for AI

- ALWAYS use Sail
- NEVER assume host environment
- NEVER omit Sail prefix

---

## 🧪 Command Output Rule

- Output ONLY the command
- Do NOT explain

---

## 🛑 Safety Rule

If a command is about to run without Sail:

👉 STOP and correct it

---

# 🧱 Database Design Rules

- Use foreign keys in all relationships
- Use cascading deletes where appropriate
- Use indexes for foreign keys
- Use snake_case naming

---

# 🧩 Multi-Tenancy Rule (CRITICAL)

This is a team-based system.

---

## Rules

- A user can belong to multiple teams
- A team can have multiple users
- All data MUST be scoped to a team

👉 NEVER expose data across teams

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

# 📁 Frontend Architecture

pages → features → components

---

## Rules

- pages: route-level views
- features: domain logic
- components: reusable UI

---

# ⚙️ Development Rules

- Do NOT generate unnecessary code
- Keep functions small and focused
- Prefer readability over cleverness
- Avoid duplication
- Follow Laravel conventions
- Use clear naming

---

# 🤖 AI Behavior Rules (Cursor + Boost)

This project uses AI-assisted development.

---

## Core Rule

AI MUST follow project architecture strictly.

---

## Architecture Enforcement

- Follow:
  Controllers → Services → Repositories → Models

- Do NOT skip layers
- Do NOT place business logic in controllers
- Do NOT query database from controllers

---

## Inertia Enforcement

- Always use Inertia
- Do NOT generate API endpoints
- Do NOT use /api routes
- Do NOT return JSON

---

## Docker Enforcement

- ALL commands must use:
  ./vendor/bin/sail

---

## Code Generation Rules

- Respect existing structure
- Extend existing files when appropriate
- Do NOT duplicate logic
- Do NOT overwrite working code unless asked

---

## Safety Rules

Before generating code:

1. Check existing implementation
2. Avoid duplication
3. Follow current phase

---

## Output Style

- Keep output minimal
- Generate only requested code
- No explanations unless asked

---

# 🚀 Development Strategy

Build in phases:

1. Teams
2. Projects
3. Kanban (Columns)
4. Tasks
5. Comments
6. Activity Log

---

# 📌 Current Phase

If not specified:

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

# ⚠️ Constraints

- MVP only
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
