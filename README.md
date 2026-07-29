# Laravel Testing Playground

A hands-on reference showing how unit, feature, and end-to-end tests differ — built on Laravel 13, Pest, and Playwright with real models, services, and UI to test against.

---

## What this repo demonstrates

| Layer | Tool | What it tests | Speed |
|---|---|---|---|
| Unit | Pest | PHP logic in isolation — no DB, no HTTP | ~80 ms for the whole suite |
| Feature | Pest + Laravel TestCase | Full server stack — routing, controllers, DB | ~1–2 s |
| E2e | Playwright (TypeScript) | Real browser against a running server | ~10–30 s |

The domain is a simple **Post** system with role-based access (admin / customer). That gives every layer something real to test:

- **Unit** — does `PostService::publish()` throw when the actor isn't the author or an admin?
- **Feature** — does `PATCH /api/posts/{id}/publish` return 403 and leave the DB unchanged when called by the wrong user?
- **E2e** — does a logged-in customer see the Publish button, click it, and see the post appear in the list?

---

## Project structure

```
app/
  Enums/
    Role.php              # Admin | Customer
    PostStatus.php        # Draft | Published
  Models/
    User.php              # role cast to Role enum
    Post.php              # status cast to PostStatus enum
  Services/
    PostService.php       # all business logic lives here
  Http/
    Controllers/
      PostController.php  # thin — delegates to PostService
    Requests/
      StorePostRequest.php

tests/
  Unit/
    PostServiceTest.php   # pure PHP, no DB, no HTTP
  Feature/
    PostApiTest.php       # JSON API endpoints
    PostWebTest.php       # Blade web routes

e2e/
  posts.spec.ts           # Playwright browser tests

database/
  factories/
    UserFactory.php       # ->admin() and ->customer() states
    PostFactory.php       # ->published() and ->draft() states
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

The seeder creates two known accounts:

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

No infrastructure required. Tests run in under 100 ms and never touch a database
or make HTTP requests. When one fails you know exactly which PHP class is broken.

### Feature tests
```bash
npm run test:feature
```

Boots the full Laravel application against an in-memory SQLite database
(reset between tests via `RefreshDatabase`). Tests the complete server-side
stack: route → middleware → controller → service → database.

### E2e tests
```bash
npm run test:e2e
```

Launches Chromium, starts `php artisan serve` automatically, and drives the UI
the same way a real user would. These catch bugs that only appear in the
browser — rendering, JavaScript, form submissions with CSRF tokens.

### All tests
```bash
npm test
```

---

## The three layers explained

### Unit tests — `tests/Unit/`

**Run when:** you change a service method, a model helper, or an enum.

**What they prove:** the business rule is implemented correctly in PHP.

**What they don't prove:** that the rule is enforced at the HTTP boundary, or
that the database is updated correctly.

Unit tests use plain `new Model()` and set attributes directly — no factories,
no database, no framework boot. This is intentional. If a test needs the
database, it belongs in Feature.

```php
// No DB, no HTTP — just PHP objects and assertions
it('prevents a customer from publishing another users post', function () {
    $actor = makeUser(Role::Customer, id: 2);
    $post  = makePost(PostStatus::Draft, userId: 1);

    expect(fn () => (new PostService)->publish($actor, $post))
        ->toThrow(AuthorizationException::class);
});
```

### Feature tests — `tests/Feature/`

**Run when:** you change a route, middleware, controller, or form request.

**What they prove:** the HTTP contract is correct end-to-end on the server side.

**What they don't prove:** that the UI renders correctly, or that JavaScript works.

Feature tests use `actingAs()`, `postJson()`, `assertDatabaseHas()`, and
`assertSessionHas()` — helpers that only exist because Laravel's TestCase is
active. There is no browser.

```php
// Real HTTP, real DB, no browser
it('returns 403 when a customer publishes another users post', function () {
    $attacker = User::factory()->customer()->create();
    $post     = Post::factory()->draft()->create();

    $this->actingAs($attacker)
        ->patchJson("/api/posts/{$post->id}/publish")
        ->assertForbidden();

    $this->assertDatabaseHas('posts', ['id' => $post->id, 'status' => 'draft']);
});
```

### E2e tests — `e2e/`

**Run when:** you change a Blade view, add JavaScript, or touch any user-facing flow.

**What they prove:** the whole system works together from the user's perspective.

**What they don't prove:** exactly which layer broke when something fails — that's
the job of unit and feature tests.

E2e tests are the slowest and the most fragile (a CSS class rename can break a
selector), but they give the highest confidence because they run in a real browser
against a real server.

```typescript
// Real browser, real server, real HTTP, real rendering
test('customer can create a post and see it listed after publishing', async ({ page }) => {
    await loginAs(page, 'customer@example.com');
    await page.goto('/posts/create');
    await page.fill('#title', 'My E2e Post');
    await page.fill('#body', 'Written by Playwright.');
    await page.click('.save-draft-btn');
    // ...
});
```

---

## Key design decisions

**Service layer** — all business logic (who can publish, who can delete) lives in
`PostService`, not in the controller. Unit tests can test the rules directly
without HTTP; feature tests can test the HTTP contract without duplicating the
rule assertions.

**Enum casts** — `User::$role` and `Post::$status` are cast to PHP enums. You
can never write an invalid status from application code, and the helpers
(`isAdmin()`, `isPublished()`) read like plain English.

**Factory states** — `User::factory()->admin()`, `Post::factory()->published()`.
Named states make test setup read like a sentence and hide the database-level
details of what "an admin" means.

**Datasets (data providers)** — instead of five near-identical tests, one test
is declared and a dataset drives it with different inputs. See
`tests/Unit/PostServiceTest.php` and `tests/Feature/PostApiTest.php` for
examples.
