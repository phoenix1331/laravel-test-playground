<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <title>Posts</title>
</head>
<body>
    <h1>Posts</h1>

    @if (session('success'))
        <p class="flash-success">{{ session('success') }}</p>
    @endif

    @if (session('error'))
        <p class="flash-error">{{ session('error') }}</p>
    @endif

    @forelse ($posts as $post)
        <article data-post-id="{{ $post->id }}">
            <h2>{{ $post->title }}</h2>
            <p>{{ $post->body }}</p>
        </article>
    @empty
        <p>No posts yet.</p>
    @endforelse

    @auth
        <a href="{{ route('posts.create') }}">New Post</a>
    @endauth
</body>
</html>
