# Laravel Testing Playground

A hands-on reference showing how unit, feature, and end-to-end tests differ — built on Laravel 13, Pest, and Playwright with real models, services, and UI to test against.

---

## What this repo demonstrates

| Layer | Tool | What it tests | Typical speed |
|---|---|---|---|
| Unit | Pest | PHP logic in isolation — no DB, no HTTP | ~80 ms total |
| Feature | Pest + Laravel TestCase | Full server stack — routing, controllers, DB | ~1–2 s total |
| E2e (Playwright) | Playwright (TypeScript) | Real browser against a running server | ~15 s total |
| E2e (Dusk) | Laravel Dusk (PHP) | Real browser against a running server | ~15 s total |

> **Playwright vs Dusk:** Both suites run identical scenarios against a real browser. Playwright is TypeScript-native and runs via Node; Dusk is PHP-native and runs via `php artisan dusk`. They exist side-by-side in this repo so you can compare the two APIs directly.

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
    PostServiceTest.php                     # pure PHP, no DB, no HTTP — tests rules in isolation
  Feature/
    PostApiTest.php                         # JSON API endpoints — status codes, JSON shape, DB state
    PostWebTest.php                         # Blade web routes — redirects, flash messages, DB state
  Browser/                                  # Laravel Dusk — PHP browser tests (mirrors e2e/)
    Concerns/
      InteractsWithAuth.php                 # loginAs() helper — PHP equivalent of e2e/posts.spec.ts loginAs()
    PublicPostsTest.php                     # /posts page — guest view
    LoginTest.php                           # login form — valid, invalid, post-login UI
    CreatePostTest.php                      # create post form — happy path, validation, guest redirect
    PublishDeletePostTest.php               # admin publish/delete actions, role badges
  DuskTestCase.php                          # base class for all Dusk tests — wires up ChromeDriver

e2e/
  posts.spec.ts           # Playwright — real browser, real server, real user flows (mirrors tests/Browser/)

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
php artisan dusk:chrome-driver  # installs the ChromeDriver binary for Dusk
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

### Browser tests (Dusk)

```bash
# 1. Seed the real SQLite database — Dusk cannot use :memory:
npm run dusk:fresh

# 2. Run in whichever mode you prefer:
npm run test:dusk          # headless — fastest, good for CI
npm run test:dusk:headed   # visible Chrome window so you can watch the browser
```

Unlike Playwright, Dusk does **not** start the server automatically. If the
server isn't already running, `php artisan dusk` will start one for you via
the `APP_URL` in `.env.dusk.local`. You can also start it manually first:

```bash
php artisan serve &
npm run test:dusk
```

> **Tip for learning:** Run `npm run test:dusk:headed` to watch Chrome open and
> perform each test step in real time. This is the best way to understand what
> Dusk is doing — you can see the form fill, the redirect, and the flash message
> appear exactly as a user would see them.

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

### Browser tests (Dusk) — `tests/Browser/`

**Run when:** the same situations as Playwright e2e — you change a Blade view,
add JavaScript, or modify a user-facing flow.

**What they prove:** identical to Playwright — the whole system works together
from a real user's perspective, written in PHP instead of TypeScript.

**What they don't prove:** exactly which layer broke (same caveat as Playwright).

Dusk wraps ChromeDriver in a fluent PHP API that will feel familiar if you've
used Laravel's HTTP testing helpers. The `$browser` object chains assertions
directly instead of `expect()`:

```php
// Real browser, real server, real rendering — PHP API
test('customer can fill the form and save a draft', function () {
    $this->browse(function (Browser $browser) {
        $this->loginAs($browser, 'customer@example.com');

        $browser->visit('/posts/create')
                ->type('#title', 'My Dusk Post')
                ->type('#body', 'This post was created by a Dusk browser test.')
                ->click('.save-draft-btn')
                ->assertPathIs('/posts')
                ->assertSee('Post created as a draft');
    });
})->uses(InteractsWithAuth::class);
```

#### Playwright vs Dusk — API comparison

The test scenarios in `tests/Browser/` deliberately mirror those in `e2e/posts.spec.ts`.
Here is the full API mapping:

| Action | Playwright (TypeScript) | Dusk (PHP) |
|---|---|---|
| Navigate | `page.goto('/path')` | `$browser->visit('/path')` |
| Fill input | `page.fill('#id', 'value')` | `->type('#id', 'value')` |
| Click element | `page.click('.selector')` | `->click('.selector')` |
| Assert URL | `expect(page).toHaveURL(/path/)` | `->assertPathIs('/path')` |
| Assert title | `expect(page).toHaveTitle(/text/)` | `->assertTitleContains('text')` |
| Assert visible text | `expect(locator).toContainText('…')` | `->assertSee('…')` |
| Assert text in element | `expect(locator('h1')).toContainText` | `->assertSeeIn('h1', '…')` |
| Assert text absent | `expect(locator).not.toBeVisible()` | `->assertDontSee('…')` |
| Assert element present | `expect(locator).toBeVisible()` | `->assertPresent('.selector')` |
| Assert link present | `locator('a', {hasText: '…'})` | `->assertSeeLink('…')` |
| Count elements | `locator('…').count()` | `count($browser->elements('…'))` |
| Accept JS dialog | `page.once('dialog', d => d.accept())` | `->acceptDialog()` |
| Shared login helper | `async function loginAs(page, email)` | `trait InteractsWithAuth` |
| Wait for navigation | `page.waitForURL('**/posts')` | `->waitForLocation('/posts')` |

#### Key configuration differences from Playwright

| Concern | Playwright | Dusk |
|---|---|---|
| Language | TypeScript | PHP |
| Config file | `playwright.config.ts` | `phpunit.dusk.xml` + `.env.dusk.local` |
| Server startup | Automatic (`webServer` in config) | Manual or automatic via `APP_URL` |
| Database | File-based SQLite (seeded manually) | File-based SQLite (seeded manually) |
| Session handling | Isolated context per test | Shared browser; form-based login per test |
| Headed mode | `--headed` flag | `--without-headless` flag |
| npm script | `npm run test:e2e` | `npm run test:dusk` |

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

## Test environment configuration

### Unit and feature tests (`phpunit.xml`)

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

### Browser tests — Playwright and Dusk

Neither Playwright nor Dusk is affected by `phpunit.xml`. Both drive a real
running server, which reads from `.env` (or `.env.dusk.local` for Dusk).

**Playwright** reads `playwright.config.ts` and uses whatever `.env` the
running server has loaded.

**Dusk** reads `phpunit.dusk.xml` for PHPUnit settings and `.env.dusk.local`
for environment overrides (merged on top of `.env` when `APP_ENV=dusk`). The
critical overrides are:

```bash
SESSION_DRIVER=cookie   # sessions must survive across real HTTP requests
DB_DATABASE=/path/to/database.sqlite  # file-based, not :memory:
```

Both suites require the database to be seeded before running:

```bash
php artisan migrate:fresh --seed          # for Playwright
npm run dusk:fresh                        # for Dusk (sets --env=dusk)
```
