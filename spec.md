# Internship Logbook System Spec (AI Implementation Guide)

## 1. Project Overview & Tech Stack
- **Framework:** Laravel 12 (backward compatible with 11).
- **Database:** MySQL (Production/Dev), SQLite (Testing).
- **Authentication:** Laravel Session (Web/Blade for Login/Logout) + Sanctum (API Modules).
- **Authorization:** `spatie/laravel-permission` (Roles: `Admin`, `Supervisor`, `Intern`).
- **Media Management:** `spatie/laravel-medialibrary` (Collection: `appendix`).
- **Activity Tracking:** `spatie/laravel-activitylog` (Target: Project status changes).
- **Frontend Scaffold:** Vue 3 (Composition API) + Tailwind CSS (Inertia.js or standalone API-driven UI).

## 2. Database Architecture & Relationships
Create migrations and eloquent models for the following entities with strict foreign key constraints.

1. **Institutions**
   - Fields: `name`, `type` (enum: university, vocational).
2. **Periods**
   - Fields: `name`, `start_date`, `end_date`, `holidays` (JSON array of dates).
3. **Users & RBAC**
   - Standard Laravel Auth fields.
   - Spatie Roles: Admin (full access), Supervisor (manages assigned interns), Intern.
4. **Intern Profiles (Pivot/Junction for Multi-Tenancy)**
   - Fields: `user_id`, `institution_id`, `period_id`.
   - *Rationale: Allows an intern to be tracked under specific periods and institutions concurrently.*
5. **Logbooks**
   - Fields: `intern_profile_id`, `report_date` (date), `done_tasks` (text), `next_tasks` (text), `status` (enum: draft, submitted, approved).
   - Relations: Has attached media (Spatie Media Library) for 'appendix'.
6. **Projects / Assigned Tasks**
   - Fields: `title`, `description`, `assignee_id` (User/Intern), `assigner_id` (User/Supervisor), `status` (enum: todo, doing, done), `target_week` (integer/string, e.g., "Week 1").
   - Integrations: Spatie Activitylog to track `status` changes.
7. **Comments**
   - Fields: `body`, `user_id`, `commentable_id`, `commentable_type` (Polymorphic, attached to Project).

## 3. Core Business Logic & API Endpoints

### A. Core Management (CRUD)
- `POST /api/register` : User registration via email (default role: Intern).
- `GET/POST /api/users`, `/api/roles`, `/api/institutions`, `/api/periods` : Accessible only by Admin.

### B. Logbook Module
- **Rule 1:** Interns can only submit logs for their active `period_id`.
- **Rule 2 (Holiday Validation):** System must reject submissions if `report_date` matches any date in the active Period's `holidays` JSON array. Returns 422 Unprocessable Entity.
- **Rule 3:** Required fields for submission: `done_tasks` and `next_tasks`. `appendix` is optional but must be handled via Spatie Media Library if provided (image/pdf).

### C. Project / Kanban Module (Mini Trello)
- **Rule 1 (Assignments):** Supervisors can assign predefined curriculum projects to interns (e.g., "Learn Laravel", "Build E-Budgeting with Docker").
- **Rule 2 (Status):** Interns can update status (`todo` -> `doing` -> `done`). This action MUST trigger Spatie Activitylog.
- **Rule 3 (Comments):** Supervisors and Interns can leave polymorphic comments on the Project for history/tracking.

### D. Reporting Module
- `GET /api/reports/summary` : Generate aggregated data (total approved logbooks, completed projects) filtered by `institution_id` and `period_id` as proof of completion for the institution.

## 4. Frontend Scaffold Requirements
Provide the Vue 3 `<script setup>` code for:
1. **`LogbookForm.vue`**: Form with date picker, `done_tasks` textarea, `next_tasks` textarea, and file upload for appendix. Handle 422 holiday validation errors gracefully.
2. **`ProjectBoard.vue`**: Kanban board showing 3 columns (Todo, Doing, Done). Click on a card opens a modal showing Activitylog history and a comment submission form.

## 5. Testing Protocol (Strict Criteria)
Use Pest or PHPUnit. Tests must use `RefreshDatabase` with SQLite memory.
Mandatory Test Cases to implement:
1. **Web Auth:** `test_login_page_renders_correctly`, `test_user_can_login_with_valid_credentials`, `test_user_cannot_login_with_invalid_credentials`.
2. **Route Protection:** `test_unauthenticated_user_cannot_access_dashboard`.
3. **Logbook API:** `test_intern_can_submit_logbook`, `test_intern_cannot_submit_logbook_on_holiday` (Crucial logic test).
4. **Project API:** `test_project_status_update_creates_activity_log`.
5. **RBAC:** `test_intern_cannot_access_admin_endpoints`.

All tests MUST pass. Ensure factories are created for User, Institution, Period, and Project.