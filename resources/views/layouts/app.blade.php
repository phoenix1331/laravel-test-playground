<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>@yield('title', 'Test Playground')</title>
    @vite(['resources/css/app.css', 'resources/js/app.js'])
</head>
<body class="min-h-screen bg-gray-50 text-gray-900">

    {{-- Navigation --}}
    <nav class="bg-white border-b border-gray-200 px-6 py-3 flex items-center justify-between">
        <a href="{{ route('posts.index') }}" class="font-semibold text-lg text-indigo-600">
            Test Playground
        </a>

        <div class="flex items-center gap-4 text-sm">
            @auth
                <span class="text-gray-500">
                    {{ auth()->user()->name }}
                    <span class="ml-1 px-1.5 py-0.5 rounded text-xs font-medium
                        {{ auth()->user()->isAdmin() ? 'bg-red-100 text-red-700' : 'bg-blue-100 text-blue-700' }}">
                        {{ auth()->user()->role->value }}
                    </span>
                </span>
                <a href="{{ route('posts.create') }}"
                   class="px-3 py-1.5 bg-indigo-600 text-white rounded hover:bg-indigo-700 transition">
                    New Post
                </a>
            @else
                <a href="/login" class="text-indigo-600 hover:underline">Login</a>
            @endauth
        </div>
    </nav>

    {{-- Flash messages --}}
    @if (session('success'))
        <div class="flash-success max-w-3xl mx-auto mt-4 px-4 py-3 bg-green-50 border border-green-200 text-green-800 rounded">
            {{ session('success') }}
        </div>
    @endif

    @if (session('error'))
        <div class="flash-error max-w-3xl mx-auto mt-4 px-4 py-3 bg-red-50 border border-red-200 text-red-800 rounded">
            {{ session('error') }}
        </div>
    @endif

    {{-- Page content --}}
    <main class="max-w-3xl mx-auto px-4 py-8">
        @yield('content')
    </main>

</body>
</html>
