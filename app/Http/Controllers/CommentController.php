<?php

namespace App\Http\Controllers;

use App\Models\Comment;
use App\Models\Post;
use Illuminate\Http\Request;

class CommentController extends Controller
{
    /**
     * Store a newly created resource in storage.
     */
    public function store(Request $request, Post $post)
    {
        $data = $request->validate(['body' => ['required', 'string', 'max:255']]);

        $post->comments()->create([...$data, 'user_id' => $request->user()->id]);

        return to_route('posts.show', $post)->withFragment('comments');
    }

    /**
     * Remove the specified resource from storage.
     */
    public function destroy(Post $post, Comment $comment)
    {
        $this->authorize('delete', $comment);

        $comment->delete();

        return to_route('posts.show', $post)->withFragment('comments');
    }
}
