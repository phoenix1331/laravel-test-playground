@extends('layouts.app')

@section('title', 'Posts')

@section('content')

    <div class="flex items-center justify-between mb-6">
        <h1 class="text-2xl font-bold">Published Posts</h1>
    </div>

    @forelse ($posts as $post)
        <article data-post-id="{{ $post->id }}"
                 class="mb-6 p-5 bg-white rounded-lg border border-gray-200 shadow-sm">

            <header class="mb-2">
                <h2 class="text-xl font-semibold">{{ $post->title }}</h2>
                <p class="text-sm text-gray-500">
                    By {{ $post->author->name }} &middot;
                    {{ $post->published_at->diffForHumans() }}
                </p>
            </header>

            <p class="text-gray-700 leading-relaxed">{{ $post->body }}</p>

            {{-- Actions only visible to the post author or an admin --}}
            @auth
                @if (auth()->user()->isAdmin() || $post->isOwnedBy(auth()->user()))
                    <footer class="mt-4 flex gap-3">

                        {{-- Publish button — only shown on drafts --}}
                        @if ($post->isDraft())
                            <form method="POST"
                                  action="{{ route('posts.publish', $post) }}">
                                @csrf
                                @method('PATCH')
                                <button type="submit"
                                        class="publish-btn px-3 py-1.5 text-sm bg-green-600 text-white rounded hover:bg-green-700 transition">
                                    Publish
                                </button>
                            </form>
                        @endif

                        {{-- Delete button --}}
                        <form method="POST"
                              action="{{ route('posts.destroy', $post) }}"
                              onsubmit="return confirm('Delete this post?')">
                            @csrf
                            @method('DELETE')
                            <button type="submit"
                                    class="delete-btn px-3 py-1.5 text-sm bg-red-600 text-white rounded hover:bg-red-700 transition">
                                Delete
                            </button>
                        </form>

                    </footer>
                @endif
            @endauth

        </article>
    @empty
        <p class="text-gray-500" data-empty-message>No published posts yet.</p>
    @endforelse

@endsection
