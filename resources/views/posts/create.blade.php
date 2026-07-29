@extends('layouts.app')

@section('title', 'New Post')

@section('content')

    <div class="mb-6">
        <h1 class="text-2xl font-bold">New Post</h1>
        <p class="text-sm text-gray-500 mt-1">
            Posts are saved as drafts. Publish them from the posts list.
        </p>
    </div>

    @if ($errors->any())
        <ul class="errors mb-4 p-4 bg-red-50 border border-red-200 rounded text-red-700 text-sm space-y-1">
            @foreach ($errors->all() as $error)
                <li>{{ $error }}</li>
            @endforeach
        </ul>
    @endif

    <form method="POST" action="{{ route('posts.store') }}"
          class="bg-white p-6 rounded-lg border border-gray-200 shadow-sm space-y-5">
        @csrf

        <div>
            <label for="title" class="block text-sm font-medium text-gray-700 mb-1">
                Title
            </label>
            <input id="title"
                   name="title"
                   type="text"
                   value="{{ old('title') }}"
                   placeholder="Give your post a title..."
                   class="w-full border border-gray-300 rounded px-3 py-2 text-sm
                          focus:outline-none focus:ring-2 focus:ring-indigo-400
                          @error('title') border-red-400 @enderror">
        </div>

        <div>
            <label for="body" class="block text-sm font-medium text-gray-700 mb-1">
                Body
            </label>
            <textarea id="body"
                      name="body"
                      rows="8"
                      placeholder="Write your post..."
                      class="w-full border border-gray-300 rounded px-3 py-2 text-sm
                             focus:outline-none focus:ring-2 focus:ring-indigo-400
                             @error('body') border-red-400 @enderror">{{ old('body') }}</textarea>
        </div>

        <div class="flex items-center gap-4">
            <button type="submit"
                    class="save-draft-btn px-4 py-2 bg-indigo-600 text-white text-sm rounded hover:bg-indigo-700 transition">
                Save Draft
            </button>
            <a href="{{ route('posts.index') }}" class="text-sm text-gray-500 hover:underline">
                Cancel
            </a>
        </div>

    </form>

@endsection
