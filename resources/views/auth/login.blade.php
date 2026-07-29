@extends('layouts.app')

@section('title', 'Login')

@section('content')

    <div class="max-w-sm mx-auto mt-10">
        <h1 class="text-2xl font-bold mb-6 text-center">Login</h1>

        @if ($errors->any())
            <ul class="errors mb-4 p-4 bg-red-50 border border-red-200 rounded text-red-700 text-sm space-y-1">
                @foreach ($errors->all() as $error)
                    <li>{{ $error }}</li>
                @endforeach
            </ul>
        @endif

        <form method="POST" action="/login"
              class="bg-white p-6 rounded-lg border border-gray-200 shadow-sm space-y-4">
            @csrf

            <div>
                <label for="email" class="block text-sm font-medium text-gray-700 mb-1">Email</label>
                <input id="email" name="email" type="email" value="{{ old('email') }}"
                       class="w-full border border-gray-300 rounded px-3 py-2 text-sm
                              focus:outline-none focus:ring-2 focus:ring-indigo-400">
            </div>

            <div>
                <label for="password" class="block text-sm font-medium text-gray-700 mb-1">Password</label>
                <input id="password" name="password" type="password"
                       class="w-full border border-gray-300 rounded px-3 py-2 text-sm
                              focus:outline-none focus:ring-2 focus:ring-indigo-400">
            </div>

            <button type="submit"
                    class="w-full px-4 py-2 bg-indigo-600 text-white text-sm rounded hover:bg-indigo-700 transition">
                Login
            </button>
        </form>
    </div>

@endsection
