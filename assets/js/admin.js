/**
 * AIO Optimizer — Admin JS
 * Handles: preset selection, auto-detect selector, walkthrough tour.
 */
( function () {
    'use strict';

    // =========================================================================
    // Preset definitions
    // 1 = checked, 0 = unchecked, null = leave unchanged
    // =========================================================================
    const PRESETS = {
        safe: {
            label: 'Safe',
            // Debloat: all on
            debloat_emoji:          1,
            debloat_jquery_migrate: 1,
            debloat_xmlrpc:         1,
            debloat_generator:      1,
            debloat_wlwmanifest:    1,
            debloat_rsd:            1,
            debloat_rest_links:     1,
            debloat_shortlink:      1,
            debloat_self_ping:      1,
            debloat_query_strings:  1,
            debloat_heartbeat:      1,
            debloat_oembed:         1,
            // Autoptimize: minimal
            auto_defer_js:          0,
            auto_async_js:          0,
            auto_footer_js:         0,
            auto_clean_style_tag:   1,
            // Lazy load: images + iframes only
            lazy_images:            1,
            lazy_iframes:           1,
            lazy_bg:                0,
            lazy_skip_first:        1,
            // Flying pages: off
            fly_enable:             0,
            fly_mobile:             0,
            // Flying images: off
            fly_images:             0,
            // SPA: off
            spa_enable:             0,
        },
        balanced: {
            label: 'Balanced',
            // Debloat: all on
            debloat_emoji:          1,
            debloat_jquery_migrate: 1,
            debloat_xmlrpc:         1,
            debloat_generator:      1,
            debloat_wlwmanifest:    1,
            debloat_rsd:            1,
            debloat_rest_links:     1,
            debloat_shortlink:      1,
            debloat_self_ping:      1,
            debloat_query_strings:  1,
            debloat_heartbeat:      1,
            debloat_oembed:         1,
            // Autoptimize: defer + footer
            auto_defer_js:          1,
            auto_async_js:          0,
            auto_footer_js:         1,
            auto_clean_style_tag:   1,
            // Lazy load: images + iframes + bg
            lazy_images:            1,
            lazy_iframes:           1,
            lazy_bg:                1,
            lazy_skip_first:        1,
            // Flying pages: on, no mobile
            fly_enable:             1,
            fly_mobile:             0,
            // Flying images: off
            fly_images:             0,
            // SPA: off
            spa_enable:             0,
        },
        full: {
            label: 'Full Performance',
            // Debloat: all on
            debloat_emoji:          1,
            debloat_jquery_migrate: 1,
            debloat_xmlrpc:         1,
            debloat_generator:      1,
            debloat_wlwmanifest:    1,
            debloat_rsd:            1,
            debloat_rest_links:     1,
            debloat_shortlink:      1,
            debloat_self_ping:      1,
            debloat_query_strings:  1,
            debloat_heartbeat:      1,
            debloat_oembed:         1,
            // Autoptimize: all on
            auto_defer_js:          1,
            auto_async_js:          0,
            auto_footer_js:         1,
            auto_clean_style_tag:   1,
            // Lazy load: everything
            lazy_images:            1,
            lazy_iframes:           1,
            lazy_bg:                1,
            lazy_skip_first:        1,
            // Flying pages: on + mobile
            fly_enable:             1,
            fly_mobile:             1,
            // Flying images: on
            fly_images:             1,
            // SPA: on
            spa_enable:             1,
        },
    };

    // =========================================================================
    // Apply a preset to all checkboxes in the form
    // =========================================================================
    function applyPreset( key ) {
        const preset = PRESETS[ key ];
        if ( ! preset ) return;

        const optionBase = 'aio_optimizer_options';

        Object.entries( preset ).forEach( function ( [ field, value ] ) {
            if ( value === null ) return;

            const selector = 'input[name="' + optionBase + '[' + field + ']"][type="checkbox"]';
            const el       = document.querySelector( selector );
            if ( ! el ) return;

            el.checked = value === 1;
        } );

        // Visual feedback on the active button.
        document.querySelectorAll( '.aio-preset-btn' ).forEach( function ( btn ) {
            btn.classList.toggle( 'aio-preset-btn--active', btn.dataset.preset === key );
        } );

        // Show a notice telling the user to save.
        const notice = document.getElementById( 'aio-preset-notice' );
        if ( notice ) {
            notice.textContent = 'Preset "' + preset.label + '" applied — click Save Changes to store.';
            notice.style.display = 'block';
        }
    }

    // =========================================================================
    // Auto-detect content selector
    // =========================================================================
    function detectSelector() {
        const btn    = document.getElementById( 'aio-detect-selector' );
        const input  = document.getElementById( 'spa_selector' );
        const result = document.getElementById( 'aio-detect-result' );
        if ( ! btn || ! input ) return;

        const homeUrl = ( window.aioAdmin && window.aioAdmin.homeUrl ) || '/';

        btn.disabled    = true;
        btn.textContent = 'Scanning\u2026';
        if ( result ) result.textContent = '';

        // Ordered from most specific to most generic — first large match wins.
        const CANDIDATES = [
            '#content', '#main-content', '#primary', '#main',
            '.site-main', '.main-content', '.content-area', '.entry-content',
            'main', 'article',
        ];

        fetch( homeUrl, { credentials: 'same-origin' } )
            .then( function ( r ) { return r.text(); } )
            .then( function ( html ) {
                const doc     = new DOMParser().parseFromString( html, 'text/html' );
                var bestSel   = null;
                var bestLen   = 0;

                CANDIDATES.forEach( function ( sel ) {
                    try {
                        const el = doc.querySelector( sel );
                        if ( el ) {
                            const len = el.innerHTML.length;
                            if ( len > bestLen ) { bestLen = len; bestSel = sel; }
                        }
                    } catch ( e ) {}
                } );

                if ( bestSel ) {
                    input.value = bestSel;
                    if ( result ) {
                        result.style.color = '#1e7e34';
                        result.textContent = '\u2713 Detected: ' + bestSel + ' (' + Math.round( bestLen / 1024 ) + ' KB of content)';
                    }
                } else {
                    if ( result ) {
                        result.style.color = '#c62d2d';
                        result.textContent = '\u2717 Could not detect \u2014 please enter your theme\'s content wrapper manually.';
                    }
                }
            } )
            .catch( function () {
                if ( result ) {
                    result.style.color = '#c62d2d';
                    result.textContent = '\u2717 Fetch failed \u2014 check browser console.';
                }
            } )
            .finally( function () {
                btn.disabled    = false;
                btn.textContent = 'Auto-detect';
            } );
    }

    // =========================================================================
    // Walkthrough tour
    // =========================================================================

    const TOUR_STORAGE_KEY = 'aio_tour_v1';

    const TOUR_STEPS = [
        {
            badge: 'Welcome',
            title: 'All-in-One Optimizer',
            body:  'A lightweight WordPress performance plugin with zero external dependencies. This quick tour walks you through each module so you know what to enable for your site.',
        },
        {
            badge: 'Quick start',
            title: 'Use a Preset',
            body:  'The preset bar applies recommended settings in one click.<br><br><strong>Safe</strong> &mdash; debloat only, nothing that could break your layout.<br><strong>Balanced</strong> &mdash; safe + defer JS + lazy load + page prefetch.<br><strong>Full Performance</strong> &mdash; everything on, including SPA navigation.',
        },
        {
            badge: 'Tab 1',
            title: 'Debloat',
            body:  'Removes unnecessary WordPress output: emoji scripts, jQuery Migrate, XML-RPC, the generator meta tag, wlwmanifest, RSD, REST API head links, heartbeat, and version query strings from static assets.<br><br>All options are safe to enable on any WordPress site.',
        },
        {
            badge: 'Tab 2',
            title: 'Autoptimize',
            body:  'Adds <code>defer</code> to script tags and moves JS to the footer &mdash; no file rewriting involved.<br><br>jQuery and core WordPress handles (<code>wp-polyfill</code>, <code>wp-hooks</code>, etc.) are automatically protected and will never be deferred, even without adding them to the exclude list.',
        },
        {
            badge: 'Tab 3',
            title: 'Lazy Load',
            body:  'Images, iframes, and CSS background images load only when they enter the viewport via IntersectionObserver.<br><br>The first image is always skipped (your LCP / hero image). A &lt;noscript&gt; fallback is added for browsers without JavaScript.<br><br>Auto-disabled if Smush, EWWW, or Rocket Lazy Load is active.',
        },
        {
            badge: 'Tab 4',
            title: 'Flying Pages',
            body:  'Pre-fetches internal pages in the background as soon as you hover a link (default: 65 ms delay).<br><br>Pre-warmed pages are stored in <code>window.aioPageCache</code> and served instantly when SPA navigation intercepts the click &mdash; making navigation feel near-instant.',
        },
        {
            badge: 'Tab 5',
            title: 'SPA Navigation',
            body:  'Intercepts internal link clicks and swaps only the content area via <code>fetch()</code> &mdash; no full page reload.<br><br>Includes a progress bar, smooth View Transitions animation, and automatic fallback to normal navigation on any error.<br><br>Use <strong>Auto-detect</strong> to find your theme\'s content wrapper selector automatically.',
        },
        {
            badge: 'Tab 6',
            title: 'Diagnostics',
            body:  'After enabling modules, visit the Diagnostics tab to confirm everything is running correctly.<br><br>It shows plugin version, PHP/WP versions, module status, conflict detection, and a browser console cheat-sheet. The front-end diagnostics link adds a live overlay on your site.',
        },
        {
            badge: 'Done',
            title: 'Ready to go',
            body:  'Apply a preset <strong>or</strong> enable individual features across the tabs, then click <strong>Save Changes</strong>.<br><br>If something breaks, check the Diagnostics tab first, or add <code>data-no-spa</code> to a specific link to force a normal page load for that link.',
        },
    ];

    var tourCurrentStep = 0;

    function buildTourModal() {
        var overlay = document.createElement( 'div' );
        overlay.id  = 'aio-tour-overlay';

        var modal  = document.createElement( 'div' );
        modal.id   = 'aio-tour-modal';
        modal.setAttribute( 'role', 'dialog' );
        modal.setAttribute( 'aria-modal', 'true' );

        // Close button.
        var closeBtn       = document.createElement( 'button' );
        closeBtn.type      = 'button';
        closeBtn.id        = 'aio-tour-close';
        closeBtn.setAttribute( 'aria-label', 'Close tour' );
        closeBtn.innerHTML = '&times;';
        modal.appendChild( closeBtn );

        // Badge.
        var badge = document.createElement( 'div' );
        badge.id  = 'aio-tour-badge';
        modal.appendChild( badge );

        // Title.
        var title = document.createElement( 'h2' );
        title.id  = 'aio-tour-title';
        modal.appendChild( title );

        // Body.
        var body = document.createElement( 'p' );
        body.id  = 'aio-tour-body';
        modal.appendChild( body );

        // Progress dots.
        var dots = document.createElement( 'div' );
        dots.id  = 'aio-tour-dots';
        TOUR_STEPS.forEach( function ( _, i ) {
            var d           = document.createElement( 'button' );
            d.type          = 'button';
            d.className     = 'aio-tour-dot';
            d.dataset.step  = i;
            d.setAttribute( 'aria-label', 'Step ' + ( i + 1 ) );
            dots.appendChild( d );
        } );
        modal.appendChild( dots );

        // Navigation.
        var nav      = document.createElement( 'div' );
        nav.id       = 'aio-tour-nav';
        var prevBtn  = document.createElement( 'button' );
        prevBtn.type = 'button';
        prevBtn.id   = 'aio-tour-prev';
        prevBtn.className = 'button';
        prevBtn.innerHTML = '\u2190 Back';
        var nextBtn  = document.createElement( 'button' );
        nextBtn.type = 'button';
        nextBtn.id   = 'aio-tour-next';
        nextBtn.className = 'button button-primary';
        nextBtn.innerHTML = 'Next \u2192';
        nav.appendChild( prevBtn );
        nav.appendChild( nextBtn );
        modal.appendChild( nav );

        // Skip link.
        var skip      = document.createElement( 'a' );
        skip.href     = '#';
        skip.id       = 'aio-tour-skip';
        skip.textContent = 'Skip tour';
        modal.appendChild( skip );

        overlay.appendChild( modal );
        document.body.appendChild( overlay );

        // Wire events.
        closeBtn.addEventListener( 'click', closeTour );
        skip.addEventListener( 'click', function ( e ) { e.preventDefault(); closeTour(); } );
        prevBtn.addEventListener( 'click', function () {
            if ( tourCurrentStep > 0 ) showTourStep( tourCurrentStep - 1 );
        } );
        nextBtn.addEventListener( 'click', function () {
            if ( tourCurrentStep < TOUR_STEPS.length - 1 ) {
                showTourStep( tourCurrentStep + 1 );
            } else {
                closeTour();
            }
        } );
        // Dot navigation.
        dots.addEventListener( 'click', function ( e ) {
            var dot = e.target.closest( '.aio-tour-dot' );
            if ( dot ) showTourStep( parseInt( dot.dataset.step, 10 ) );
        } );
        // Close on backdrop click.
        overlay.addEventListener( 'click', function ( e ) {
            if ( e.target === overlay ) closeTour();
        } );
        // Close on Escape key.
        document.addEventListener( 'keydown', function ( e ) {
            if ( e.key === 'Escape' ) closeTour();
        } );
    }

    function showTourStep( n ) {
        tourCurrentStep = n;
        var step = TOUR_STEPS[ n ];

        document.getElementById( 'aio-tour-badge' ).textContent = step.badge;
        document.getElementById( 'aio-tour-title' ).textContent = step.title;
        document.getElementById( 'aio-tour-body' ).innerHTML    = step.body;

        // Dots.
        document.querySelectorAll( '.aio-tour-dot' ).forEach( function ( d, i ) {
            d.classList.toggle( 'aio-tour-dot--active', i === n );
            d.setAttribute( 'aria-current', i === n ? 'step' : 'false' );
        } );

        // Buttons.
        var prev = document.getElementById( 'aio-tour-prev' );
        var next = document.getElementById( 'aio-tour-next' );
        prev.style.visibility = ( n === 0 ) ? 'hidden' : '';
        next.innerHTML = ( n === TOUR_STEPS.length - 1 ) ? 'Get started' : 'Next \u2192';

        // Counter.
        var counter = document.getElementById( 'aio-tour-counter' );
        if ( counter ) counter.textContent = ( n + 1 ) + ' / ' + TOUR_STEPS.length;

        document.getElementById( 'aio-tour-overlay' ).classList.add( 'aio-tour-visible' );
    }

    function openTour() {
        if ( ! document.getElementById( 'aio-tour-overlay' ) ) {
            buildTourModal();
        }
        showTourStep( 0 );
    }

    function closeTour() {
        var overlay = document.getElementById( 'aio-tour-overlay' );
        if ( overlay ) {
            overlay.classList.remove( 'aio-tour-visible' );
        }
        try { localStorage.setItem( TOUR_STORAGE_KEY, '1' ); } catch ( e ) {}
    }

    // =========================================================================
    // Wire up all buttons after DOM is ready
    // =========================================================================
    document.addEventListener( 'DOMContentLoaded', function () {

        // Preset buttons.
        document.querySelectorAll( '.aio-preset-btn' ).forEach( function ( btn ) {
            btn.addEventListener( 'click', function ( e ) {
                e.preventDefault();
                applyPreset( btn.dataset.preset );
            } );
        } );

        // Auto-detect selector.
        var detectBtn = document.getElementById( 'aio-detect-selector' );
        if ( detectBtn ) {
            detectBtn.addEventListener( 'click', function ( e ) {
                e.preventDefault();
                detectSelector();
            } );
        }

        // View Guide button.
        var tourBtn = document.getElementById( 'aio-open-tour' );
        if ( tourBtn ) {
            tourBtn.addEventListener( 'click', function ( e ) {
                e.preventDefault();
                openTour();
            } );
        }

        // Auto-show tour on first visit (only once, stored in localStorage).
        try {
            if ( ! localStorage.getItem( TOUR_STORAGE_KEY ) ) {
                // Short delay so the page fully renders before the modal appears.
                setTimeout( openTour, 400 );
            }
        } catch ( e ) {}
    } );

} )();
