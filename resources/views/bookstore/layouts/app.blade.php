<!DOCTYPE html>
<html lang="{{ str_replace('_', '-', app()->getLocale()) }}">
    <head>
        <meta charset="utf-8">
        <meta name="viewport" content="width=device-width, initial-scale=1">
        <meta name="cache-control" content="no-cache, no-store, must-revalidate">

        <title>{{ config('app.name', 'Laravel') }}</title>

        <!-- Fonts -->
        <link rel="preconnect" href="https://fonts.bunny.net">
        <link href="https://fonts.bunny.net/css?family=figtree:400,500,600&display=swap" rel="stylesheet" />

        <!-- Scripts -->
{{--        @vite(['resources/css/app.css', 'resources/js/app.js']) --}}
        @vite(['resources/sass/app.scss', 'resources/js/app.js'])
    </head>
    <body>
        <!-- Page Content -->
        <main>
            <nav class="navbar navbar-expand-lg bg-light">
                <div class="container-fluid">
                    <a class="navbar-brand" href="#">Bookstore</a>
                    <button class="navbar-toggler" type="button" data-bs-toggle="collapse" data-bs-target="#navbarSupportedContent" aria-controls="navbarSupportedContent" aria-expanded="false" aria-label="Toggle navigation">
                        <span class="navbar-toggler-icon"></span>
                    </button>
                    <div class="collapse navbar-collapse" id="navbarSupportedContent">
                        <ul class="navbar-nav me-auto mb-2 mb-lg-0">
                            <li class="nav-item">
                                <a class="nav-link active" aria-current="page" href="#">Authors</a>
                            </li>
                            <li class="nav-item">
                                <a class="nav-link" href="#">Genres</a>
                            </li>
                            <li class="nav-item dropdown">
                                <a class="nav-link dropdown-toggle" href="#" role="button" data-bs-toggle="dropdown" aria-expanded="false">
                                    Books
                                </a>
                                <ul class="dropdown-menu">
                                    <li><a class="dropdown-item" href="#">By Author</a></li>
                                    <li><a class="dropdown-item" href="#">By Genre</a></li>
                                    <li><a class="dropdown-item" href="#">By Title</a></li>
                                    {{--<li><hr class="dropdown-divider"></li>
                                    <li><a class="dropdown-item" href="#">Something else here</a></li>--}}
                                </ul>
                            </li>

                            {{--<li class="nav-item">
                                <a class="nav-link disabled">Disabled</a>
                            </li>--}}
                        </ul>
                        <form class="d-flex" role="search">
                            <input class="form-control me-2" type="search" placeholder="Search" aria-label="Search">
                            <button class="btn btn-outline-success" type="submit">Search</button>
                        </form>
                    </div>
                </div>
            </nav>
            <div class="container">
                <div class="row">
                    @foreach($books as $book)
                        <div class="col-3 border m-5 pt-4 rounded">
                            <div class="container">
                                <div class="mb-3">
                                    <img src="{{ $book->cover }}" alt="" class="img-fluid">
                                </div>
                                <h4>{{ $book->title }}</h4>
                                <p>Author: {{ $book->author->first_name }} {{ $book->author->last_name }}</p>
                                <p class="fst-italic">Genre: {{ $book->genre->genre }}</p>
                                <p>ISBN: {{ $book->isbn }}</p>
                            </div>

                        </div>
                    @endforeach
                </div>
                <div class="row">
                    <div class="px-5">
                        {!! $books->withQueryString()->links('pagination::bootstrap-5') !!}
                    </div>
                </div>
            </div>

        </main>
    </body>
</html>
