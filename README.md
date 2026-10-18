# CollabSpace REST API

[![PHP Version](https://img.shields.io/badge/PHP-8.2%20%7C%208.5-777BB4?style=flat-square&logo=php&logoColor=white)](https://php.net)
[![Laravel Framework](https://img.shields.io/badge/Laravel-12.x-FF2D20?style=flat-square&logo=laravel&logoColor=white)](https://laravel.com)
[![Test Suite](https://img.shields.io/badge/Tests-148%20Passed-10B981?style=flat-square&logo=githubactions&logoColor=white)](https://phpunit.de)
[![Code Style](https://img.shields.io/badge/Code%20Style-Laravel%20Pint-F59E0B?style=flat-square)](https://laravel.com/docs/pint)
[![WebSockets](https://img.shields.io/badge/WebSockets-Laravel%20Reverb-8B5CF6?style=flat-square)](https://reverb.laravel.com)
[![Database](https://img.shields.io/badge/Database-MySQL-00758F?style=flat-square&logo=mysql&logoColor=white)](https://mysql.com)

**CollabSpace** is a high-performance, modular enterprise collaboration and project management backend built with Laravel 12 and PHP 8.5. It delivers robust real-time communication, agile project workflows, intelligent video meeting management via Google Meet REST API v2, multi-channel notifications, and context-aware workspace analytics.

---

## Architecture & System Design

The application follows clean architecture and separation of concerns:

```
app/
├── DTOs/                     # Strongly-typed data transfer objects
├── Enums/                    # Typed status, priority, and role definitions
├── Events/                   # Broadcast events for real-time WebSockets
├── Exceptions/               # Domain-specific typed exceptions
├── Http/
│   ├── Controllers/Api/      # REST API endpoints with standardized JSON envelopes
│   ├── Middleware/           # JWT authentication & rate limiting
│   ├── Requests/             # Form request validation with custom rule sets
│   └── Resources/            # Eloquent API resources for predictable responses
├── Models/                   # Eloquent models, relationships, casts, and scopes
├── Notifications/            # Multi-channel notifications (Database + Mail)
├── Policies/                 # Role-based access control (Admin, PM, Team Member)
├── Services/                 # Core business logic and third-party integrations
└── Traits/                   # Reusable API response and pagination traits
```

### Key Architectural Highlights
- **Layered Controller-Service Pattern**: Controllers handle HTTP concerns and return standardized `ApiResponse` envelopes, delegating domain logic to dedicated service classes.
- **Role-Based Access Control (RBAC)**: Fine-grained permissions across `Admin`, `ProjectManager`, and `TeamMember` enforced with Laravel Gates and Policies.
- **Stateless Authentication**: JWT bearer token authentication with refresh token rotation.
- **Real-Time WebSockets**: Powered by Laravel Reverb for instant messaging, live typing indicators, and meeting transcript streaming.
- **Third-Party Integrations**:
  - **Google Meet API v2**: Automatic meeting space generation with OAuth token auto-refresh and resilient RFC link fallbacks.
  - **Google Gemini API**: Workspace-aware meeting synthesis, discussion extraction, and context-informed assistant queries.
  - **Google OAuth 2.0**: Native Socialite credential verification for seamless single-sign-on.

---

## Core Modules & Features

| Module | Endpoints | Description |
| :--- | :--- | :--- |
| **01. Authentication** | `POST /auth/*` | Registration, login, JWT refresh token rotation, password reset workflow, and Google OAuth SSO. |
| **02. User Profile** | `GET/PUT /profile/*` | Profile updates, avatar uploads, task aggregations, uploaded documents, and activity feeds. |
| **03. Dashboard** | `GET /dashboard/*` | Executive overview: project progress metrics, urgent tasks, team workload, upcoming meetings, and recent files. |
| **04. Projects** | `GET/POST/PUT/DELETE /projects/*` | Complete project lifecycle: teams assignment, member roles, overview KPIs, folders, and documents. |
| **05. Tasks & Kanban** | `GET/POST/PATCH /tasks/*` | 4-stage Kanban workflow (`todo`, `in_progress`, `review`, `completed`), drag-and-drop ordering, reviewers, and task comments. |
| **06. Real-Time Chat** | `GET/POST /chat/*` | 1-on-1 direct messaging, project channels, threaded discussions, read receipts, and mute toggles via WebSockets. |
| **07. Meetings** | `GET/POST/PATCH /meetings/*` | Scheduling, calendar filters, Google Meet room provisioning, RSVPs, live audio transcripts, and automated meeting summaries. |
| **08. Files & Storage** | `GET/DELETE /files/*` | Document management with folder hierarchy, preview links, and download counter telemetry. |
| **09. Reports & KPIs** | `GET/POST /reports/*` | Executive KPI summaries, deadline adherence charts, worker productivity metrics, and custom report exports. |
| **10. Intelligent Assistant** | `GET/POST /ai/*` | Workspace-aware assistant with contextual understanding of tasks, deadlines, and project data. |
| **11. Notifications** | `GET/POST/PATCH /notifications/*` | Dual-channel notifications (in-app bell + email alerts) with unread badge counters. |

---

## Getting Started

### Prerequisites
- **PHP** >= 8.2 (PHP 8.5 recommended)
- **Composer** 2.x
- **MySQL** 8.x or MariaDB
- **Node.js** & NPM (for frontend integration and build tools)
- **Laravel Herd** (recommended) or standard PHP CLI

### Installation

1. **Clone the repository:**
   ```bash
   git clone https://github.com/manoxrd/CollabSpace-AI-API.git
   cd CollabSpace-AI-API
   ```

2. **Install PHP dependencies:**
   ```bash
   composer install
   ```

3. **Configure environment:**
   ```bash
   cp .env.example .env
   php artisan key:generate
   php artisan jwt:secret
   ```

4. **Set up database credentials in `.env`:**
   ```env
   DB_CONNECTION=mysql
   DB_HOST=127.0.0.1
   DB_PORT=3306
   DB_DATABASE=collabspace_db
   DB_USERNAME=root
   DB_PASSWORD=
   ```

5. **Run migrations and seed the database:**
   ```bash
   php artisan migrate:fresh --seed
   ```

6. **Create public storage symlink:**
   ```bash
   php artisan storage:link
   ```

7. **Start the development server:**
   ```bash
   php artisan serve
   ```
   The API will be available at `http://127.0.0.1:8000/api/v1`.

---

## Seeded Default Test Accounts

The seeder initializes realistic enterprise accounts with pre-configured roles:

| Role | Email | Password | Access Scope |
| :--- | :--- | :--- | :--- |
| **Admin** | `admin@collabspace.ai` | `password` | Full system access, all projects, reports, and administrative controls. |
| **Project Manager** | `mohanad.wahib@collabspace.ai` | `password` | Project creation, team assignment, task review, report generation. |
| **Team Member** | `mohamed.salah@collabspace.ai` | `password` | Assigned project views, task kanban execution, chat, meetings, and file uploads. |

---

## Postman API Collection

A fully documented, import-ready Postman collection is included in the project root:

📄 **[CollabSpace_AI_API.postman_collection.json](./CollabSpace_AI_API.postman_collection.json)**

### Collection Highlights:
- **92 Endpoints** organized into 11 logical modules matching the architecture.
- **Pre-Configured Authentication**: Includes a pre-configured development JWT Bearer Token (`{{token}}`). All protected requests authenticate out of the box.
- **Automated Token Capture**: Running any login request automatically stores `access_token` and `refresh_token` in collection variables for subsequent requests.
- **Pre-filled Payload Examples**: Every request contains realistic, schema-valid JSON bodies and query parameters.

### Quick Import:
1. Open Postman.
2. Click **Import** → select `CollabSpace_AI_API.postman_collection.json`.
3. Set your `base_url` variable (defaults to `http://127.0.0.1:8000/api/v1` or `http://collabspace-ai-api.test/api/v1`).
4. Execute any request!

---

## Third-Party Integrations

### Google Meet REST API v2
- Configured in `config/services.php` and `.env` (`GOOGLE_CLIENT_ID`, `GOOGLE_CLIENT_SECRET`, `GOOGLE_REFRESH_TOKEN`).
- When a meeting is scheduled without a meeting link, the backend automatically provisions an official Google Meet room space (`https://meet.google.com/xxx-yyyy-zzz`).
- Automated token management continuously refreshes expired access tokens using the refresh token flow and caches tokens for 55 minutes.

### Real-Time WebSockets (Laravel Reverb)
- Private and presence channels for project channels and 1-on-1 direct conversations.
- Meeting transcript broadcasting for live captions and meeting updates.
- Start the WebSocket daemon:
  ```bash
  php artisan reverb:start
  ```

---

## Automated Test Suite

CollabSpace includes a comprehensive automated test suite covering authentication, role gates, project workflows, task state transitions, Google Meet integration, real-time broadcasts, and report calculations.

```bash
# Run all feature and unit tests
php artisan test --compact

# Run specific test suites
vendor/bin/phpunit tests/Feature/AuthTest.php
vendor/bin/phpunit tests/Feature/MeetingTest.php
vendor/bin/phpunit tests/Feature/FullSystemIntegrationTest.php
```

### Code Style Enforcement
The codebase adheres strictly to PSR-12 and Laravel Pint style standards:
```bash
vendor/bin/pint --dirty --format agent
```

---

## License

This project is licensed under the [MIT License](LICENSE).
