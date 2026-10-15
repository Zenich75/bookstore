<div class="grid gap-3 md:grid-cols-2 xl:grid-cols-3">
    @foreach ($books as $book)
        @include('lab.n-plus-one._card', ['book' => $book, 'feed' => $feed, 'showReviewBook' => $showReviewBook])
    @endforeach
</div>
