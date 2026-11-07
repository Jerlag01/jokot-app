<?php

namespace App\Http\Controllers;

use App\Models\Event;
use Illuminate\Contracts\View\View;

class EventController extends Controller
{
    public function show(Event $event): View
    {
        abort_unless($event->is_published, 404);

        return view('events.show', ['event' => $event]);
    }
}
