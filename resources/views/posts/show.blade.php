<x-app-layout>
    <div class="max-w-5xl mx-auto px-2 py-6">
        <div>
            <h1 class="text-3xl font-semibold">{{ $post->title }}</h1>
            <span class="text-sm text-gray-600">
                {{ $post->created_at->diffForHumans() }} by {{ $post->user->name }}
            </span>
        </div>

        <div class="prose mt-6">
            {!! $post->html !!}
        </div>

        <div class="mt-12">
            <h2 id="comments" class="text-2xl font-semibold">Comments</h2>

            @auth
                <form action="{{ route('posts.comments.store', $post) }}" method="post" class="mt-2">
                    @csrf

                    <textarea name="body" id="body" cols="30" rows="5" class="w-full"></textarea>
                    <x-primary-button type="submit">Add Comment</x-primary-button>
                </form>
            @endauth

            <ul class="divide-y mt-4">
                @foreach($comments as $comment)
                    <li class="py-4 px-2">
                        <p>{{ $comment->body }}</p>
                        <span class="text-sm text-gray-600">
                            {{ $comment->created_at->diffForHumans() }} by {{ $comment->user->name }}
                        </span>

                        @can('delete', $comment)
                            <form
                                action="{{ route('posts.comments.destroy', ['post' => $post, 'comment' => $comment]) }}"
                                method="post" class="mt-2">
                                @csrf
                                @method('DELETE')

                                <x-danger-button type="submit">Delete</x-danger-button>
                            </form>
                        @endcan
                    </li>
                @endforeach
            </ul>

            <div class="mt-2">
                {{ $comments->fragment('comments')->links() }}
            </div>
        </div>
    </div>
</x-app-layout>
