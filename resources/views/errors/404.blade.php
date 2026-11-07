@extends('layouts.site', ['title' => 'Pagina niet gevonden'])

@section('content')
    <section class="page-header">
        <div class="container">
            <h1>Pagina niet gevonden</h1>
        </div>
    </section>
    <section class="section">
        <div class="container narrow prose">
            <p>Deze pagina bestaat niet (meer). <a href="{{ route('home') }}">Ga naar de startpagina</a>.</p>
        </div>
    </section>
@endsection
