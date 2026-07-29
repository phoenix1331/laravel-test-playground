# Laravel Testing Playground

A hands-on reference showing how unit, feature, and end-to-end tests differ — built on Laravel 13, Pest, and Playwright with real models, services, and UI to test against.

---

## What this repo demonstrates

| Layer | Tool | What it tests | Typical speed |
|---|---|---|---|
| Unit | Pest | PHP logic in isolation — no DB, no HTTP | ~80 ms total |
| Feature | Pest + Laravel TestCase | Full server stack — routing, controllers, DB | ~1–2 s total |
| E2e | Playwright (TypeScript) | Real browser against a running server | ~15 s total |

The domain is a simple **Post** system with role-based access (admin / customer). Every layer has something real to test:

- **Unit** — does `PostService::publish()` throw when the actor isn't the author or an admin?
- **Feature** — does `PATCH /api/posts/{id}/publish` return 403 and leave the DB unchanged when called by the wrong user?
- **E2e** — can a logged-in customer fill in the create form, save a draft, and see the flash message?

---

## Project structure

```
app/
  Enums/
    Role.php              # Admin | Customer — cast automatically on User
    PostStatus.php        # Draft | Published — cast automatically on Post
  Models/
    User.php              # isAdmin(), isCustomer(), posts() relationship
    Post.php              # isPublished(), isDraft(), isOwnedBy() helpers
  Services/
    PostService.php       # all business logic lives here, not in the controller
  Http/
    Controllers/
      PostController.php  # thin — translates HTTP ↔ service calls
    Requests/
      StorePostRequest.php

tests/
  Unit/
    PostServiceTest.php   # pure PHP, no DB, no HTTP — tests rules in isolation
  Feature/
    PostApiTest.php       # JSON API endpoints — status codes, JSON shape, DB state
    PostWebTest.php       # Blade web routes — redirects, flash messages, DB state

e2e/
  posts.spec.ts           # Playwright — real browser, real server, real user flows

database/
  factories/
    UserFactory.php       # ->admin() and ->customer() states
    PostFactory.php       # ->published() and ->draft() states
  seeders/
    DatabaseSeeder.php    # seeds known admin + customer accounts with posts
```

---

## Requirements

- PHP 8.2+
- Composer
- Node 18+
- npm

---

## Setup

```bash
composer install
npm install
cp .env.example .env
php artisan key:generate
php artisan migrate --seed
npx playwright install chromium
```

The seeder creates two known accounts you can use in the browser or e2e tests:

| Email | Password | Role |
|---|---|---|
| admin@example.com | password | admin |
| customer@example.com | password | customer |

---

## Running the tests

### Unit tests

```bash
npm run test:unit
```

No infrastructure required. No database, no HTTP, no framework boot. Tests run
in under 100 ms. When one fails you know exactly which PHP class is broken.

### Feature tests

```bash
npm run test:feature
```

Boots the full Laravel application against an in-memory SQLite database, reset
between tests via `RefreshDatabase`. Tests the complete server-side stack:
route → middleware → controller → service → database.

### E2e tests

```bash
# Run once before e2e tests — Playwright hits the real server which needs compiled assets
npm run build
php artisan migrate:fresh --seed

# Then run in whichever mode you prefer:
npm run test:e2e          # headless — fastest, good for CI
npm run test:e2e:headed   # visible Chrome window with 300 ms slowdown between actions
npm run test:e2e:ui       # Playwright's own UI — pick tests, scrub the step timeline
```

Playwright starts `php artisan serve` automatically before tests run.

> **Tip for learning:** `npm run test:e2e:ui` opens a GUI where you can select
> individual tests and watch a frame-by-frame replay of every action, including
> before/after screenshots and a DOM snapshot at each step. It's the best way
> to understand what e2e tests actually do.

### All tests (unit + feature + e2e)

```bash
npm test
```

---

## The three layers explained

### Unit tests — `tests/Unit/`

**Run when:** you change a service method, a model helper, or an enum.

**What they prove:** the business rule is correct in PHP.

**What they don't prove:** that the rule is enforced at the HTTP boundary, or
that the database is actually updated.

Unit tests construct models with `new Model()` and set attributes directly —
no factories, no database, no framework boot. This keeps them fast and focused.
If a test needs the database, it belongs in Feature.

```php
// No DB, no HTTP — just PHP objects and assertions
it('prevents a customer from publishing another users post', function () {
    $actor = makeUser(Role::Customer, id: 2);
    $post  = makePost(PostStatus::Draft, userId: 1);

    expect(fn () => (new PostService)->publish($actor, $post))
        ->toThrow(AuthorizationException::class);
});
```

Datasets (Pest's data providers) drive the same test with multiple scenarios
without copy-pasting the test body:

```php
dataset('publish scenarios', [
    'admin can publish any post'        => [Role::Admin,    false, true],
    'author can publish their own post' => [Role::Customer, true,  true],
    'customer cannot publish others'    => [Role::Customer, false, false],
]);

it('enforces publish rules', function (Role $role, bool $isAuthor, bool $allowed) {
    // one test body, three runs
})->with('publish scenarios');
```

### Feature tests — `tests/Feature/`

**Run when:** you change a route, middleware, controller, or form request.

**What they prove:** the HTTP contract is correct — status codes, JSON shape,
redirects, session flash, database state.

**What they don't prove:** that the browser renders the response correctly, or
that client-side JavaScript works.

Feature tests use `actingAs()`, `postJson()`, `assertDatabaseHas()`, and
`assertSessionHas()` — Laravel-specific helpers that only work because
`TestCase` boots the framework. There is no browser involved.

```php
// Real HTTP, real DB — no browser
it('returns 403 when a customer publishes another users post', function () {
    $attacker = User::factory()->customer()->create();
    $post     = Post::factory()->draft()->create();

    $this->actingAs($attacker)
        ->patchJson("/api/posts/{$post->id}/publish")
        ->assertForbidden()
        ->assertJsonPath('message', 'You can only publish your own posts.');

    // The DB must be unchanged — the feature test can verify this,
    // the unit test cannot.
    $this->assertDatabaseHas('posts', ['id' => $post->id, 'status' => 'draft']);
});
```

### E2e tests — `e2e/`

**Run when:** you change a Blade view, add JavaScript, or modify any user-facing flow.

**What they prove:** the whole system works together from a real user's perspective
— login, form submission, flash messages, page navigation.

**What they don't prove:** exactly which layer broke. That's the job of unit and
feature tests. E2e tests tell you *something* is broken; the other layers tell
you *what*.

```typescript
// Real browser, real server, real rendering
test('customer can fill the form and save a draft', async ({ page }) => {
    await loginAs(page, 'customer@example.com');
    await page.goto('/posts/create');
    await page.fill('#title', 'My Playwright Post');
    await page.fill('#body', 'This post was created by a Playwright e2e test.');
    await page.click('.save-draft-btn');

    await expect(page).toHaveURL(/\/posts/);
    await expect(page.locator('.flash-success')).toContainText('Post created as a draft');
});
```

---

## Key design decisions

**Service layer** — all business logic (who can publish, who can delete) lives in
`PostService`, not in the controller. This means unit tests can verify the rules
with plain PHP objects, and feature tests can verify the HTTP contract without
duplicating the rule assertions.

**Enum casts** — `User::$role` and `Post::$status` are cast to PHP enums
automatically by Eloquent. You can never accidentally write an invalid status to
the database from application code, and helpers like `isAdmin()` and
`isPublished()` read like plain English.

**Factory states** — `User::factory()->admin()`, `Post::factory()->published()`.
Named states make test setup read like a sentence and keep the database-level
details out of the test body.

**Datasets** — instead of writing five near-identical tests, one test is declared
and a dataset supplies the varying inputs. Pest runs the test once per row and
labels each run with the dataset key, making failure messages self-explanatory.
See `tests/Unit/PostServiceTest.php` and `tests/Feature/PostApiTest.php`.

**`withoutVite()`** — feature tests call `withoutVite()` in the base `TestCase`
so that Blade views render without a compiled Vite manifest. Playwright e2e tests
hit the real server and need `npm run build` to have been run first.

---

## Test environment configuration (`phpunit.xml`)

Unit and feature tests use their own environment, defined in `phpunit.xml` at the
root of the project. Nothing in `.env` affects them. The key settings:

```xml
<env name="DB_CONNECTION" value="sqlite"/>
<env name="DB_DATABASE" value=":memory:"/>
```

`DB_DATABASE=:memory:` means SQLite runs entirely in RAM — no file is created on
disk and the database is discarded after each test run. Combined with
`RefreshDatabase`, each individual test also gets a clean slate via transaction
rollback.

**To switch the test database**, edit those two lines in `phpunit.xml`:

| Target | `DB_CONNECTION` | `DB_DATABASE` |
|---|---|---|
| SQLite in-memory (default) | `sqlite` | `:memory:` |
| SQLite file on disk | `sqlite` | `/absolute/path/to/test.sqlite` |
| MySQL | `mysql` | your test DB name (add host/user/pass envs too) |
| PostgreSQL | `pgsql` | your test DB name (add host/user/pass envs too) |

The e2e tests are **not** affected by `phpunit.xml` — Playwright drives the real
running server, which reads from `.env`. That database is populated by
`php artisan migrate:fresh --seed`.
