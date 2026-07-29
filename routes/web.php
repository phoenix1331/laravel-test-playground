<?php

use App\Http\Controllers\PostController;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\Route;

Route::get('/', function () {
    return view('welcome');
});

// ---------------------------------------------------------------------------
// Minimal auth routes — just enough for login/logout without a full auth kit.
// E2e tests use these to log in as admin or customer before exercising the UI.
// ---------------------------------------------------------------------------

Route::get('/login', fn () => view('auth.login'))->name('login');

Route::post('/login', function (Request $request) {
    $credentials = $request->only('email', 'password');

    if (Auth::attempt($credentials, remember: false)) {
        $request->session()->regenerate();
        return redirect()->intended(route('posts.index'));
    }

    return back()->withErrors(['email' => 'Invalid credentials.'])->onlyInput('email');
});

Route::post('/logout', function (Request $request) {
    Auth::logout();
    $request->session()->invalidate();
    $request->session()->regenerateToken();
    return redirect('/');
})->name('logout');

/*
|--------------------------------------------------------------------------
| Post web routes
|--------------------------------------------------------------------------
|
| These routes return Blade views and redirects — the traditional full-page
| request/response cycle. The auth middleware ensures only logged-in users
| can create, publish, or delete posts. The index is public.
|
*/

Route::get('/posts', [PostController::class, 'index'])->name('posts.index');

Route::middleware('auth')->group(function () {
    Route::get('/posts/create', [PostController::class, 'create'])->name('posts.create');
    Route::post('/posts', [PostController::class, 'store'])->name('posts.store');
    Route::patch('/posts/{post}/publish', [PostController::class, 'publish'])->name('posts.publish');
    Route::delete('/posts/{post}', [PostController::class, 'destroy'])->name('posts.destroy');
});
