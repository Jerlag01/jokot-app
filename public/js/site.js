(function () {
    'use strict';

    var header = document.querySelector('[data-header]');
    var toggle = document.querySelector('[data-menu-toggle]');
    var toTop = document.querySelector('[data-to-top]');

    if (toggle && header) {
        toggle.addEventListener('click', function () {
            var open = header.classList.toggle('menu-open');
            toggle.setAttribute('aria-expanded', open ? 'true' : 'false');
        });
    }

    function onScroll() {
        var scrolled = window.scrollY > 40;
        if (header) { header.classList.toggle('is-scrolled', scrolled); }
        if (toTop) { toTop.classList.toggle('is-visible', window.scrollY > 600); }
    }
    window.addEventListener('scroll', onScroll, { passive: true });
    onScroll();

    // Availability calendar. Clicks are delegated because the grid is replaced when the month changes.
    var calendar = document.querySelector('[data-calendar]');
    var dateInput = document.querySelector('[data-event-date]');
    var loading = null;

    function markPicked() {
        if (!calendar || !dateInput) { return; }
        calendar.querySelectorAll('.calendar-day.is-picked').forEach(function (el) { el.classList.remove('is-picked'); });
        var picked = dateInput.value && calendar.querySelector('[data-pick-date="' + dateInput.value + '"]');
        if (picked) { picked.classList.add('is-picked'); }
    }

    function showMonth(month, link) {
        if (loading) { loading.abort(); }
        loading = new AbortController();
        calendar.classList.add('is-loading');
        calendar.setAttribute('aria-busy', 'true');

        var url = new URL(calendar.getAttribute('data-calendar-url'), window.location.href);
        url.searchParams.set('maand', month);

        fetch(url, { headers: { 'X-Requested-With': 'XMLHttpRequest' }, signal: loading.signal })
            .then(function (response) {
                if (!response.ok) { throw new Error('HTTP ' + response.status); }
                return response.text();
            })
            .then(function (html) {
                calendar.innerHTML = html;
                markPicked();
                // Keep the month in the address bar, so a reload or a shared link shows the same month.
                var pageUrl = new URL(window.location.href);
                pageUrl.searchParams.set('maand', month);
                pageUrl.hash = '';
                history.replaceState(null, '', pageUrl);
                // Keep keyboard focus on the same arrow; at the end of the range it is disabled, so take the other one.
                var nav = calendar.querySelector('a.calendar-nav[aria-label="' + link.getAttribute('aria-label') + '"]') || calendar.querySelector('a.calendar-nav');
                if (nav) { nav.focus({ preventScroll: true }); }
            })
            .catch(function (error) {
                // Fall back to the normal link (full page load) if fetching fails.
                if (error.name !== 'AbortError') { window.location.href = link.href; }
            })
            .finally(function () {
                calendar.classList.remove('is-loading');
                calendar.removeAttribute('aria-busy');
            });
    }

    if (calendar) {
        calendar.addEventListener('click', function (event) {
            var nav = event.target.closest('[data-calendar-month]');
            if (nav) {
                event.preventDefault();
                showMonth(nav.getAttribute('data-calendar-month'), nav);
                return;
            }

            // A free day fills in the date field of the rental form on the same page.
            var day = event.target.closest('[data-pick-date]');
            if (day && dateInput) {
                event.preventDefault();
                dateInput.value = day.getAttribute('data-pick-date');
                markPicked();
                document.getElementById('huuraanvraag').scrollIntoView({ behavior: 'smooth' });
                setTimeout(function () { dateInput.focus({ preventScroll: true }); }, 500);
            }
        });

        if (dateInput) { dateInput.addEventListener('change', markPicked); }
        markPicked();
    }

    // Ticket form: running total.
    var ticketSelects = document.querySelectorAll('[data-ticket-price]');
    var ticketTotal = document.querySelector('[data-ticket-total]');
    function updateTicketTotal() {
        var total = 0;
        ticketSelects.forEach(function (select) { total += parseFloat(select.getAttribute('data-ticket-price')) * parseInt(select.value || '0', 10); });
        if (ticketTotal) { ticketTotal.textContent = '€' + total.toFixed(2).replace('.', ',').replace(',00', ''); }
    }
    ticketSelects.forEach(function (select) { select.addEventListener('change', updateTicketTotal); });
    updateTicketTotal();

    // Contract signature pad (pointer events: mouse, pen and touch).
    var pad = document.querySelector('[data-signature-pad]');
    if (pad) {
        var ctx = pad.getContext('2d');
        var drawing = false;
        var hasInk = false;
        ctx.lineWidth = 2.5;
        ctx.lineCap = 'round';
        ctx.lineJoin = 'round';
        ctx.strokeStyle = '#111';

        function point(event) {
            var rect = pad.getBoundingClientRect();
            return { x: (event.clientX - rect.left) * (pad.width / rect.width), y: (event.clientY - rect.top) * (pad.height / rect.height) };
        }
        pad.addEventListener('pointerdown', function (event) {
            drawing = true;
            pad.setPointerCapture(event.pointerId);
            var p = point(event);
            ctx.beginPath();
            ctx.moveTo(p.x, p.y);
        });
        pad.addEventListener('pointermove', function (event) {
            if (!drawing) { return; }
            var p = point(event);
            ctx.lineTo(p.x, p.y);
            ctx.stroke();
            hasInk = true;
        });
        ['pointerup', 'pointercancel', 'pointerleave'].forEach(function (name) {
            pad.addEventListener(name, function () { drawing = false; });
        });
        document.querySelector('[data-signature-clear]').addEventListener('click', function () {
            ctx.clearRect(0, 0, pad.width, pad.height);
            hasInk = false;
        });
        document.querySelector('[data-signature-form]').addEventListener('submit', function () {
            document.querySelector('[data-signature-input]').value = hasInk ? pad.toDataURL('image/png') : '';
        });
    }
})();
