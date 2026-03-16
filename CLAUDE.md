# EGOV Service — Project Rules

## Architecture — Layered Separation (STRICT)

### Controllers (`app/Http/Controllers/`) — THIN ONLY
- **A controller method must ONLY: typehint a service (resolved via service container), call one method on it, and return `$this->success()`.**
- NO business logic, NO DB queries, NO `throw_unless`/`throw_if`, NO conditionals, NO model access, NO formatting — NOTHING.
- Use `$this->user` to access the authenticated user (via `__get` on base Controller).
- Services are injected via method parameters (Laravel auto-resolves them).
- Example of a correct controller method:
```php
public function index(Request $request, InterviewService $service): JsonResponse
{
    return $this->success('Interviews retrieved.', $service->list($this->user, $request->input('per_page', 20)));
}
```

### Request classes (`app/Http/Requests/`)
- All validation lives here — never validate inside controllers or services
- Use Form Request classes for every endpoint that accepts input

### Services (`app/Services/`)
- All business logic, authorization checks, exception throwing, data formatting lives here
- Services call repositories for ALL DB access — services must NOT query models directly
- Services return arrays ready for the response `data` key — controllers never transform data
- Services throw exceptions on failure — never return error responses

### Repositories (`app/Repositories/`)
- ALL database queries live here — simple or complex, Eloquent or raw
- Services never call Eloquent models directly — always go through a repository
- **Status: extracted — all services now use repositories for DB access.**

### Anti-patterns
- Never use `throw_unless()` or `throw_if()` — use plain `if` + `throw` instead for explicit control flow
- Standard Laravel helpers (`config()`, `now()`, `collect()`, `response()`, `route()`, `storage_path()`) are fine to use
- **Status: throw_unless/throw_if eliminated from all services.**

### Exception handling
- Never return error responses manually — throw exceptions
- Global exception handler in `bootstrap/app.php` catches everything and returns unified JSON
- Use plain `if` + `throw` — never use `throw_unless()` or `throw_if()` helpers

## Unified Response Structure
Every API response follows this format:
```json
{
  "code": 200,
  "message": "Personal details fetched successfully",
  "data": { ... }
}
```
- `code` and `message` are always present
- `data` is present only when the response carries payload
- Error responses follow the same structure: `{ "code": 422, "message": "The fin field is required." }`

## Code style
- PSR-12 formatting (Laravel Pint)
- No direct `env()` calls outside config files — use `config()` helper
- Type hints on all method signatures and return types
- Use `match` over `switch` where possible
- No hardcoded values — use class constants, config values, or enums. Magic numbers, strings, and thresholds must be named and centralized

## Git
- Never add Co-Authored-By or AI attribution to commits
- Commit messages: imperative mood, describe the "what" concisely
- Work in small batches and commit after each batch — user's API limits may run out mid-session, so never leave work uncommitted
- Push after each working milestone
- Commit timestamps must be realistic — I'm a human, not a robot working 24/7. Keep real time gaps between commits (e.g. 20-90 min for focused work, breaks for lunch, no commits at 3am). Use future dates if needed but don't space them too far apart

## What NOT to do
- Don't put ANY logic in controllers — no DB queries, no throw_unless, no conditionals, no data mapping
- Don't suggest packages without checking composer.json first
- Don't add docblocks/comments to code that's self-explanatory
- Don't over-engineer: no abstractions for one-time operations
- Don't mock external services in tests unless explicitly asked
- Don't create README/docs files unless explicitly asked
