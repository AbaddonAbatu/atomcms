<div class="aurora-card" wire:key="aurora-article-comments">
    @php($viewer = auth()->user())
    @if ($article->can_comment)
        @if ($viewer && !$article->userHasReachedArticleCommentLimit($viewer))
            <form wire:submit="postComment">
                <label for="aurora-comment">Post a comment</label>
                <textarea id="aurora-comment" wire:model="comment" maxlength="255"></textarea>
                @error('comment')<p role="alert">{{ $message }}</p>@enderror
                <button class="aurora-button" type="submit">Post comment</button>
            </form>
        @endif
        <h2>Comments</h2>
        @forelse ($article->comments->sortByDesc('created_at') as $comment)
            <article wire:key="article-comment-{{ $comment->id }}">
                <a href="{{ route('home.show', $comment->user) }}">{{ $comment->user->username }}</a>
                <p>{{ $comment->comment }}</p>
                @can('delete', $comment)
                    <button type="button" wire:click="deleteComment({{ $comment->id }})">Delete comment</button>
                @endcan
            </article>
        @empty
            <p class="aurora-muted">No comments yet.</p>
        @endforelse
    @endif
</div>
