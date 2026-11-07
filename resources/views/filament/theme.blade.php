{{--
    Same structure as erp-app's theme (dark sidebar, accent marker on the active item), in the black and pink-red of jokot.be.
    Filament ships precompiled CSS, so the overrides live here instead of a Tailwind build.
--}}
<style>
    :root {
        --jk-black: #0e0e0e;
        --jk-black-2: #000;
        --jk-accent: #e9204f;
    }

    .jk-mark-label { color: var(--jk-accent); }

    .fi-main-sidebar { background: linear-gradient(180deg, var(--jk-black) 0%, var(--jk-black-2) 100%) !important; color: #fff; }
    .fi-main-sidebar .fi-sidebar-header { background: transparent !important; box-shadow: none !important; --tw-ring-color: rgba(255,255,255,.08) !important; }
    .fi-main-sidebar .fi-logo { color: #fff; }
    .fi-main-sidebar .fi-sidebar-group-label { font-size: .6875rem; letter-spacing: .18em; text-transform: uppercase; color: var(--jk-accent); }
    .fi-main-sidebar .fi-sidebar-group-btn .fi-icon,
    .fi-main-sidebar .fi-sidebar-group-collapse-btn { color: rgba(255,255,255,.45); }
    .fi-main-sidebar .fi-sidebar-item-label { color: rgba(255,255,255,.82); }
    .fi-main-sidebar .fi-sidebar-item-icon { color: rgba(255,255,255,.5); }
    .fi-main-sidebar .fi-sidebar-item-btn { border-left: 3px solid transparent; border-radius: .375rem; }
    .fi-main-sidebar .fi-sidebar-item-btn:hover { background: rgba(255,255,255,.07) !important; }
    .fi-main-sidebar .fi-sidebar-item.fi-active > .fi-sidebar-item-btn { background: rgba(233,32,79,.14) !important; border-left-color: var(--jk-accent); }
    .fi-main-sidebar .fi-sidebar-item.fi-active .fi-sidebar-item-label,
    .fi-main-sidebar .fi-sidebar-item.fi-active .fi-sidebar-item-icon { color: #fff !important; }
    .fi-main-sidebar .fi-badge { --tw-ring-color: transparent; }
    .fi-main-sidebar .fi-sidebar-close-overlay-btn, .fi-main-sidebar .fi-icon-btn { color: rgba(255,255,255,.6); }

    .fi-simple-layout { background: radial-gradient(ellipse at top, rgba(233,32,79,.25), transparent 60%), var(--jk-black) !important; }
    .fi-simple-main { border-top: 4px solid var(--jk-accent); }

    /* Admin availability calendar */
    .jk-cal { display: grid; grid-template-columns: repeat(7, minmax(0, 1fr)); gap: 4px; }
    .jk-cal-head { text-align: center; font-size: .75rem; font-weight: 600; text-transform: uppercase; letter-spacing: .08em; opacity: .6; padding: .25rem 0; }
    .jk-cal-day { min-height: 6.5rem; border-radius: .5rem; padding: .35rem .45rem; border: 1px solid rgba(127,127,127,.2); display: flex; flex-direction: column; gap: .25rem; font-size: .75rem; }
    .jk-cal-day.is-outside { opacity: .35; }
    .jk-cal-day.is-today { border-color: var(--jk-accent); box-shadow: inset 0 0 0 1px var(--jk-accent); }
    .jk-cal-num { font-weight: 600; font-size: .8rem; }
    .jk-cal-item { display: block; border-radius: .3rem; padding: .15rem .35rem; line-height: 1.3; overflow: hidden; text-overflow: ellipsis; white-space: nowrap; color: #fff; }
    .jk-cal-item.is-confirmed { background: #16a34a; }
    .jk-cal-item.is-option { background: #d97706; }
    .jk-cal-item.is-new { background: #0284c7; }
    .jk-cal-item.is-event { background: var(--jk-accent); }
    .jk-cal-item.is-blocked { background: repeating-linear-gradient(135deg, #52525b 0 6px, #3f3f46 6px 12px); color: #e4e4e7; }
    @media (max-width: 767px) { .jk-cal-day { min-height: 3.5rem; } .jk-cal-item { font-size: .65rem; padding: .1rem .2rem; } }
</style>
