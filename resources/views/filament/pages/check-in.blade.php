<x-filament-panels::page>
    @php
        $events = $this->events();
        $stats = $this->stats();
    @endphp

    @if ($events->isEmpty())
        <x-filament::section>
            Er zijn geen evenementen met ticketverkoop vandaag of in de komende twee weken.
        </x-filament::section>
    @else
        <div style="display: grid; gap: 1rem; grid-template-columns: repeat(auto-fit, minmax(300px, 1fr)); align-items: start;">
            <x-filament::section>
                <x-slot name="heading">Evenement</x-slot>
                <x-filament::input.wrapper>
                    <x-filament::input.select wire:model.live="event">
                        @foreach ($events as $option)
                            <option value="{{ $option->id }}">{{ $option->starts_at->format('d/m H:i') }} - {{ $option->title }}</option>
                        @endforeach
                    </x-filament::input.select>
                </x-filament::input.wrapper>

                <div style="display: flex; gap: 1rem; margin-top: 1rem; align-items: baseline;">
                    <span style="font-size: 2.25rem; font-weight: 700; line-height: 1;">{{ $stats['checked_in'] }}</span>
                    <span style="opacity: .7;">van {{ $stats['tickets'] }} binnen</span>
                </div>
                <div style="height: .5rem; border-radius: 999px; background: rgba(127,127,127,.2); margin-top: .5rem; overflow: hidden;">
                    <div style="height: 100%; background: var(--jk-accent); width: {{ $stats['tickets'] ? round($stats['checked_in'] / $stats['tickets'] * 100) : 0 }}%;"></div>
                </div>

                <form wire:submit="scan" style="display: flex; gap: .5rem; margin-top: 1.25rem;">
                    <x-filament::input.wrapper style="flex: 1;">
                        <x-filament::input type="text" wire:model="code" placeholder="Code intypen of scannen met een handscanner" autocomplete="off" autofocus />
                    </x-filament::input.wrapper>
                    <x-filament::button type="submit">Check</x-filament::button>
                </form>
            </x-filament::section>

            <x-filament::section>
                <x-slot name="heading">Camera</x-slot>
                <div x-data="jokotScanner($wire)" x-on:checkin-result.window="flash($event.detail)">
                    <div style="position: relative; border-radius: .75rem; overflow: hidden; background: #000; aspect-ratio: 4 / 3;">
                        <video x-ref="video" playsinline muted style="width: 100%; height: 100%; object-fit: cover;" x-show="running"></video>
                        <div x-show="! running" style="position: absolute; inset: 0; display: grid; place-items: center; color: #fff; text-align: center; padding: 1rem;">
                            <span x-text="error || 'Camera staat uit'"></span>
                        </div>
                        <div x-show="flashColor" x-transition.opacity :style="'position: absolute; inset: 0; background:' + flashColor + '; opacity: .45;'"></div>
                    </div>
                    <div style="margin-top: .75rem; display: flex; gap: .5rem;">
                        <x-filament::button x-show="! running" x-on:click="start()" icon="heroicon-o-camera">Camera starten</x-filament::button>
                        <x-filament::button x-show="running" x-on:click="stop()" color="gray" icon="heroicon-o-stop">Stoppen</x-filament::button>
                    </div>
                </div>
            </x-filament::section>
        </div>

        @if ($result)
            @php
                $colors = ['ok' => ['#16a34a', '#dcfce7'], 'already_checked_in' => ['#d97706', '#fef3c7']];
                [$border, $background] = $colors[$result['status']] ?? ['#dc2626', '#fee2e2'];
            @endphp
            <div style="border: 3px solid {{ $border }}; background: {{ $background }}; color: #111; border-radius: .75rem; padding: 1.25rem 1.5rem;">
                <div style="font-size: 1.6rem; font-weight: 800;">
                    {{ $result['ok'] ? '✔ Geldig' : ($result['status'] === 'already_checked_in' ? '⚠ Al gescand' : '✖ Niet geldig') }}
                </div>
                <div style="font-size: 1.1rem; margin-top: .25rem;">{{ $result['message'] }}</div>
                @if ($result['ticket'])
                    <div style="opacity: .75; margin-top: .25rem; font-family: monospace;">{{ $result['ticket']['code'] }} · {{ $result['ticket']['type'] }} · bestelling {{ $result['ticket']['order'] }}</div>
                    @if ($result['ok'])
                        <button type="button" wire:click="undo('{{ $result['ticket']['code'] }}')" style="margin-top: .5rem; text-decoration: underline; font-size: .85rem;">Ongedaan maken</button>
                    @endif
                @endif
            </div>
        @endif

        <x-filament::section>
            <x-slot name="heading">Laatst binnen</x-slot>
            @forelse ($this->recent() as $ticket)
                <div style="display: flex; justify-content: space-between; gap: 1rem; padding: .35rem 0; border-bottom: 1px solid rgba(127,127,127,.15);">
                    <span>{{ $ticket->checked_in_at->format('H:i:s') }} · {{ $ticket->holder_name }} · {{ $ticket->ticketType?->name }}</span>
                    <span style="opacity: .6;">{{ $ticket->checkedInBy?->name }}</span>
                </div>
            @empty
                <span style="opacity: .6;">Nog niemand gescand.</span>
            @endforelse
        </x-filament::section>
    @endif

    <script src="{{ asset('vendor/jsqr/jsQR.js') }}"></script>
    <script>
        // Camera scanning: the browser's BarcodeDetector where available (Chrome/Android), jsQR otherwise (iPhone).
        window.jokotScanner = window.jokotScanner || function ($wire) {
            return {
                running: false, error: null, flashColor: null, stream: null, detector: null, canvas: null, lastCode: null, lastAt: 0,
                async start() {
                    this.error = null;
                    try {
                        this.stream = await navigator.mediaDevices.getUserMedia({ video: { facingMode: 'environment' }, audio: false });
                    } catch (e) {
                        this.error = 'Geen toegang tot de camera (' + e.name + '). Gebruik https en geef toestemming.';
                        return;
                    }
                    this.$refs.video.srcObject = this.stream;
                    await this.$refs.video.play();
                    if ('BarcodeDetector' in window) {
                        try { this.detector = new BarcodeDetector({ formats: ['qr_code'] }); } catch (e) { this.detector = null; }
                    }
                    this.canvas = document.createElement('canvas');
                    this.running = true;
                    this.loop();
                },
                stop() {
                    this.running = false;
                    (this.stream?.getTracks() || []).forEach((track) => track.stop());
                },
                async loop() {
                    if (!this.running) { return; }
                    const video = this.$refs.video;
                    let code = null;
                    if (video.readyState >= 2) {
                        if (this.detector) {
                            try { const found = await this.detector.detect(video); code = found[0]?.rawValue || null; } catch (e) { this.detector = null; }
                        } else if (window.jsQR) {
                            const w = video.videoWidth, h = video.videoHeight;
                            const scale = Math.min(1, 640 / w);
                            this.canvas.width = w * scale; this.canvas.height = h * scale;
                            const ctx = this.canvas.getContext('2d', { willReadFrequently: true });
                            ctx.drawImage(video, 0, 0, this.canvas.width, this.canvas.height);
                            const image = ctx.getImageData(0, 0, this.canvas.width, this.canvas.height);
                            code = window.jsQR(image.data, image.width, image.height, { inversionAttempts: 'dontInvert' })?.data || null;
                        }
                    }
                    // The same code within 3 seconds is the same scan.
                    if (code && (code !== this.lastCode || Date.now() - this.lastAt > 3000)) {
                        this.lastCode = code; this.lastAt = Date.now();
                        $wire.scan(code);
                    }
                    setTimeout(() => this.loop(), 150);
                },
                flash(detail) {
                    this.flashColor = detail.ok ? '#16a34a' : (detail.status === 'already_checked_in' ? '#d97706' : '#dc2626');
                    if (navigator.vibrate) { navigator.vibrate(detail.ok ? 80 : [80, 60, 80]); }
                    setTimeout(() => this.flashColor = null, 700);
                },
            };
        };
    </script>
</x-filament-panels::page>
