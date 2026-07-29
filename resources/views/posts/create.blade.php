<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <title>New Post</title>
</head>
<body>
    <h1>New Post</h1>

    @if ($errors->any())
        <ul class="errors">
            @foreach ($errors->all() as $error)
                <li>{{ $error }}</li>
            @endforeach
        </ul>
    @endif

    <form method="POST" action="{{ route('posts.store') }}">
        @csrf

        <div>
            <label for="title">Title</label>
            <input id="title" name="title" type="text" value="{{ old('title') }}">
        </div>

        <div>
            <label for="body">Body</label>
            <textarea id="body" name="body">{{ old('body') }}</textarea>
        </div>

        <button type="submit">Save Draft</button>
    </form>

    <a href="{{ route('posts.index') }}">Back to posts</a>
</body>
</html>
