@props(['post' => null, 'errors' => []])

<form action="{{ $post ? route('posts.update', $post) : route('posts.store') }}" method="post">
    @csrf
    @method($post ? 'PUT' : 'POST')

    <div>
        <x-input-label>Title</x-input-label>
        <x-text-input name="title" id="title" class="mt-1 w-full" :value="old('title', $post?->title)"></x-text-input>
        <x-input-error :messages="$errors->get('title')" class="mt-1"></x-input-error>
    </div>

    <div class="mt-2">
        <x-input-label>Content</x-input-label>
        <textarea name="body" id="body" class="mt-1 w-full rounded-md shadow-sm border-gray-300" rows="20">{{ old('body', $post?->body) }}</textarea>
        <x-input-error :messages="$errors->get('body')" class="mt-1"></x-input-error>
    </div>

    <x-primary-button type="submit" class="mt-2">{{ $post ? "Update" : "Create" }} Post</x-primary-button>
</form>
