@extends('layouts.site', ['title' => $page->title, 'description' => $page->meta_description])

@section('content')
    @php($blocks = $page->blocks ?? [])
    @unless ($page->isHome() || (($blocks[0]['type'] ?? null) === 'hero'))
        <section class="page-header">
            <div class="container">
                <h1>{{ $page->title }}</h1>
            </div>
        </section>
    @endunless

    @foreach ($blocks as $block)
        @includeIf('blocks.'.str_replace('_', '-', $block['type'] ?? ''), ['data' => $block['data'] ?? [], 'page' => $page])
    @endforeach
@endsection
