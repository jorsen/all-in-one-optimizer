/**
 * AIO SPA — fetch-based single-page navigation for WordPress.
 *
 * – Consumes the window.aioPageCache pre-warmed by flying-pages.js
 * – Smooth page transitions (View Transitions API → fade fallback)
 * – Thin progress bar (zero dependencies)
 * – Re-executes inline <script> tags in swapped content
 * – Fires `aio:before-navigate` before swap, `aio:navigate` after
 * – Uses requestIdleCallback for non-critical work
 * – Disables itself gracefully on any unrecoverable JS error
 * – Fallback: hard navigate if SPA cannot render the page
 *
 * Config: window.aioSpaConfig = { selector, exclude[], home }
 */
( function () {
    'use strict';

    // -------------------------------------------------------------------------
    // Guard: disable SPA entirely if this flag is set by error handler below.
    // -------------------------------------------------------------------------
    if ( window.__aioSpaDisabled ) {
        console.warn( '[AIO SPA] Disabled (window.__aioSpaDisabled is set).' );
        return;
    }

    const cfg          = window.aioSpaConfig || {};
    // Extended selector covers the most common WordPress theme content wrappers,
    // including Elementor full-page / canvas templates (Hello theme + Elementor).
    const rawSel       = cfg.selector  || '#content, #main-content, #primary, .site-main, .main-content, .content-area, main, [data-elementor-type="wp-page"]';
    const excludes     = cfg.exclude   || [];
    const adminPath    = cfg.adminPath || '/wp-admin';
    const REDUCED_MOTION = window.matchMedia( '(prefers-reduced-motion: reduce)' ).matches;

    console.log( '[AIO SPA] Loaded. Selector:', rawSel );

    // Shared page cache populated by flying-pages.js.
    window.aioPageCache = window.aioPageCache || new Map();
    const cache = window.aioPageCache;

    // -------------------------------------------------------------------------
    // Progress bar + preloader + View Transitions API styles
    // -------------------------------------------------------------------------

    const BAR_ID    = 'aio-progress';
    const LOADER_ID = 'aio-preloader';

    ( function injectStyles() {
        const s = document.createElement( 'style' );
        s.textContent = `
            #${BAR_ID}{position:fixed;top:0;left:0;height:3px;width:0;
            background:var(--aio-bar-color,#0073aa);z-index:99999;
            transition:width .25s ease,opacity .4s ease;pointer-events:none}
            #${BAR_ID}.loading{width:70%;opacity:1}
            #${BAR_ID}.done{width:100%;opacity:0}

            #${LOADER_ID}{
                position:fixed;top:0;left:0;width:100%;height:4px;
                z-index:100000;overflow:hidden;
                opacity:0;pointer-events:none;
                transition:opacity .15s ease}
            #${LOADER_ID}.active{opacity:1}
            #${LOADER_ID}::after{
                content:'';position:absolute;top:0;left:-45%;
                width:45%;height:100%;
                background:var(--aio-bar-color,#0073aa);
                border-radius:0 2px 2px 0;
                animation:aio-line 1s cubic-bezier(.4,0,.6,1) infinite}
            @keyframes aio-line{
                0%{left:-45%;width:45%}
                50%{left:35%;width:35%}
                100%{left:110%;width:45%}}

            @media(prefers-reduced-motion:no-preference){
                ::view-transition-old(root){
                    animation:180ms ease-out both aio-vt-out}
                ::view-transition-new(root){
                    animation:280ms ease-out both aio-vt-in}
                @keyframes aio-vt-out{
                    to{opacity:0;transform:translateY(-6px) scale(.99)}}
                @keyframes aio-vt-in{
                    from{opacity:0;transform:translateY(8px)}}}
        `;
        document.head.appendChild( s );
    } )();

    function bar() {
        return document.getElementById( BAR_ID ) || ( function () {
            const el  = document.createElement( 'div' );
            el.id     = BAR_ID;
            document.body.prepend( el );
            return el;
        } )();
    }

    function startBar() {
        const b = bar();
        b.className = '';
        b.getBoundingClientRect(); // force reflow
        b.classList.add( 'loading' );
    }

    function finishBar() {
        const b = bar();
        b.classList.remove( 'loading' );
        b.classList.add( 'done' );
        b.addEventListener( 'transitionend', function h() {
            b.className = '';
            b.removeEventListener( 'transitionend', h );
        } );
    }

    // -------------------------------------------------------------------------
    // Preloader line
    // -------------------------------------------------------------------------

    let loaderTimer = null;

    function loader() {
        return document.getElementById( LOADER_ID ) || ( function () {
            const el  = document.createElement( 'div' );
            el.id     = LOADER_ID;
            document.body.appendChild( el );
            return el;
        } )();
    }

    function showLoader() {
        // Only show if navigation takes longer than 200 ms (avoids flash on fast connections).
        loaderTimer = setTimeout( function () {
            loader().classList.add( 'active' );
        }, 200 );
    }

    function hideLoader() {
        clearTimeout( loaderTimer );
        loaderTimer = null;
        const l = document.getElementById( LOADER_ID );
        if ( l ) l.classList.remove( 'active' );
    }

    // -------------------------------------------------------------------------
    // Fade transition helpers (fallback for browsers without View Transitions API)
    // -------------------------------------------------------------------------

    function fadeOut( el ) {
        return new Promise( function ( resolve ) {
            el.style.transition = 'opacity 0.15s ease';
            el.style.opacity    = '0';
            el.addEventListener( 'transitionend', function h() {
                el.removeEventListener( 'transitionend', h );
                resolve();
            } );
            // Safety timeout in case transitionend doesn't fire.
            setTimeout( resolve, 200 );
        } );
    }

    function fadeIn( el ) {
        el.style.opacity    = '0';
        el.style.transition = 'opacity 0.25s ease';
        requestAnimationFrame( function () {
            requestAnimationFrame( function () {
                el.style.opacity = '1';
                el.addEventListener( 'transitionend', function h() {
                    el.style.transition = '';
                    el.style.opacity    = '';
                    el.removeEventListener( 'transitionend', h );
                } );
            } );
        } );
    }

    // -------------------------------------------------------------------------
    // Helpers
    // -------------------------------------------------------------------------

    function queryContent( doc, selector ) {
        const parts = selector.split( ',' ).map( function ( s ) { return s.trim(); } );
        for ( const sel of parts ) {
            try {
                const el = doc.querySelector( sel );
                if ( el ) return { el, sel };
            } catch {}
        }
        console.warn( '[AIO SPA] No content container found for selector:', selector,
            '— Add your theme\'s content wrapper in Settings → AIO Optimizer → SPA tab.' );
        return null;
    }

    function isInternal( url ) {
        try { return new URL( url, location.href ).origin === location.origin; }
        catch { return false; }
    }

    function isExcluded( url ) {
        try {
            const p = new URL( url, location.href ).pathname;
            // Always exclude admin pages, using the dynamic path from PHP.
            if ( p.startsWith( adminPath ) ) return true;
            return excludes.some( function ( pat ) { return p.startsWith( pat.trim() ); } );
        } catch { return false; }
    }

    function isDownload( url ) {
        try {
            const ext  = new URL( url, location.href ).pathname.split( '.' ).pop().toLowerCase();
            return [ 'pdf','zip','rar','gz','tar','mp4','mp3','avi','mov','docx','xlsx' ].includes( ext );
        } catch { return false; }
    }

    /** Update <meta name="..."> in <head>. */
    function updateMeta( name, content ) {
        if ( ! content ) return;
        let el = document.querySelector( 'meta[name="' + name + '"]' );
        if ( ! el ) { el = document.createElement( 'meta' ); el.name = name; document.head.appendChild( el ); }
        el.setAttribute( 'content', content );
    }

    /** Update <meta property="og:..."> in <head>. */
    function updateOgMeta( prop, content ) {
        if ( ! content ) return;
        let el = document.querySelector( 'meta[property="' + prop + '"]' );
        if ( ! el ) { el = document.createElement( 'meta' ); el.setAttribute( 'property', prop ); document.head.appendChild( el ); }
        el.setAttribute( 'content', content );
    }

    /** Update <link rel="canonical"> in <head>. */
    function updateCanonical( newDoc ) {
        const newCanon = newDoc.querySelector( 'link[rel="canonical"]' );
        if ( ! newCanon ) return;
        let canon = document.querySelector( 'link[rel="canonical"]' );
        if ( ! canon ) { canon = document.createElement( 'link' ); canon.rel = 'canonical'; document.head.appendChild( canon ); }
        canon.href = newCanon.href;
    }

    /**
     * Sync stylesheets from the incoming page's <head> into the current document.
     *
     * 1. Add any <link rel="stylesheet"> not already loaded (by href).
     * 2. Update / add <style id="..."> blocks (WordPress / page-builder pattern).
     *    Elementor, Beaver Builder, and WP itself output per-page inline styles
     *    with unique IDs (e.g. elementor-post-123-css) — these must be refreshed
     *    on every navigation or each page will inherit the previous page's layout.
     *
     * Returns a Promise that resolves once all new external sheets have loaded
     * (capped at 1.5 s to never block navigation on a slow asset server).
     */
    async function syncHeadStyles( newDoc ) {
        // --- External stylesheets ---
        const currentHrefs = new Set(
            Array.from( document.querySelectorAll( 'link[rel="stylesheet"]' ) )
                 .map( function ( l ) { return l.href; } )
        );
        const pending = [];
        newDoc.querySelectorAll( 'link[rel="stylesheet"]' ).forEach( function ( newLink ) {
            if ( ! newLink.href || currentHrefs.has( newLink.href ) ) return;
            const link = document.createElement( 'link' );
            link.rel  = 'stylesheet';
            link.href = newLink.href;
            if ( newLink.id ) link.id = newLink.id;
            const p = new Promise( function ( resolve ) { link.onload = link.onerror = resolve; } );
            pending.push( p );
            document.head.appendChild( link );
        } );

        // --- Inline <style id="..."> blocks (head + top-level body) ---
        newDoc.querySelectorAll( 'head style[id], body > style[id]' ).forEach( function ( newStyle ) {
            const existing = document.getElementById( newStyle.id );
            if ( existing ) {
                if ( existing.textContent !== newStyle.textContent ) {
                    existing.textContent = newStyle.textContent;
                }
            } else {
                const s     = document.createElement( 'style' );
                s.id        = newStyle.id;
                s.textContent = newStyle.textContent;
                document.head.appendChild( s );
            }
        } );

        // Wait for new external sheets, but never block navigation more than 1.5 s.
        if ( pending.length ) {
            await Promise.race( [
                Promise.all( pending ),
                new Promise( function ( resolve ) { setTimeout( resolve, 1500 ); } ),
            ] );
        }
    }

    /** Re-execute <script> tags cloned into DOM via innerHTML / DOMParser. */
    function executeScripts( container ) {
        container.querySelectorAll( 'script' ).forEach( function ( inert ) {
            // Skip external scripts already loaded by the page (avoid double-loading).
            if ( inert.src ) return;

            // Skip scripts with a non-JS type (e.g. application/ld+json, text/template).
            const t = inert.getAttribute( 'type' );
            if ( t && t !== 'text/javascript' && t !== 'module' ) return;

            const live = document.createElement( 'script' );
            Array.from( inert.attributes ).forEach( function ( a ) { live.setAttribute( a.name, a.value ); } );
            live.textContent = inert.textContent;

            // Wrap execution so a broken third-party inline script
            // doesn't crash the SPA cycle or trigger the error safety-net above.
            const original = live.textContent;
            live.textContent = 'try{' + original + '}catch(e){console.warn("[AIO] Skipped inline script:",e.message);}';

            inert.parentNode.replaceChild( live, inert );
        } );

        // Re-initialize Gravity Forms after SPA swap.
        // The inline scripts above (gform.initializeOnLoaded calls) re-queue
        // per-form setup (spinners, validators, AJAX frames). Additionally,
        // we fire the post-render hooks so GF add-ons and conditional logic
        // are also re-applied.
        if ( typeof window.gform !== 'undefined' ) {
            container.querySelectorAll( '.gform_wrapper[id]' ).forEach( function ( wrapper ) {
                const m = wrapper.id.match( /gform_wrapper_(\d+)/ );
                if ( ! m ) return;
                const formId = parseInt( m[1], 10 );
                // Main GF post-render action — triggers all GF built-in inits.
                if ( typeof gform.doAction === 'function' ) {
                    try { gform.doAction( 'gform_post_render', formId, 1 ); } catch ( e ) {}
                }
                // jQuery event version — GF add-ons and some themes listen to this.
                if ( window.jQuery ) {
                    try { jQuery( document ).trigger( 'gform_post_render', [ formId, 1 ] ); } catch ( e ) {}
                }
                // Re-apply conditional logic rules.
                if ( typeof window.gf_apply_rules === 'function' ) {
                    try { gf_apply_rules( formId, [], true ); } catch ( e ) {}
                }
            } );
        }
    }

    // -------------------------------------------------------------------------
    // Core navigation
    // -------------------------------------------------------------------------

    let controller = null;

    async function navigate( url, pushState ) {
        const canonical = new URL( url, location.href ).href;

        if ( controller ) controller.abort();
        controller = new AbortController();

        startBar();
        showLoader();

        let html = null;

        try {
            // Consume pre-warmed cache from flying-pages.js if available.
            if ( cache.has( canonical ) && cache.get( canonical ) !== null ) {
                html = cache.get( canonical );
                cache.delete( canonical );
            } else {
                const res = await fetch( canonical, {
                    credentials: 'same-origin',
                    signal:      controller.signal,
                    headers:     { 'X-AIO-SPA': '1' },
                } );

                if ( ! res.ok || ! res.headers.get( 'content-type' )?.includes( 'text/html' ) ) {
                    location.href = url;
                    return;
                }
                html = await res.text();
            }

            const parser     = new DOMParser();
            const newDoc     = parser.parseFromString( html, 'text/html' );
            const newContent = queryContent( newDoc, rawSel );

            if ( ! newContent ) { location.href = url; return; }

            const current = queryContent( document, newContent.sel );
            if ( ! current ) { location.href = url; return; }

            // ── Sync CSS before any DOM mutation so new styles are ready
            //    when the content appears (prevents flash of wrong styles).
            await syncHeadStyles( newDoc );

            // ── Sync <body> class + id so page-specific CSS selectors like
            //    `.page-slug-home .page-header` resolve correctly after nav.
            if ( newDoc.body.className !== document.body.className ) {
                document.body.className = newDoc.body.className;
            }
            if ( newDoc.body.id && newDoc.body.id !== document.body.id ) {
                document.body.id = newDoc.body.id;
            }

            // ── Co-swap: update page-specific sections that live OUTSIDE the
            //    main content container (e.g. a page-title banner sibling to
            //    the content area). Collect references BEFORE any DOM mutation.
            //
            //    IMPORTANT: Never swap the site navigation header or footer.
            //    Those elements are identical across all pages and have JS state
            //    attached (sticky scroll, mobile menu, dropdowns). Replacing their
            //    outerHTML destroys all event listeners, breaking navigation.
            //    Only swap sections whose content actually CHANGED between pages.
            //
            //    Pass 1 — page-specific banner / hero class selectors only.
            //    Pass 2 — direct <body> children with IDs whose innerHTML
            //             actually differs between the two documents.
            const SKIP_IDS = new Set( [
                'wpadminbar', BAR_ID, LOADER_ID,
                // Common WP theme navigation header IDs — never replace.
                'masthead', 'site-header', 'site-navigation', 'main-navigation',
                'header', 'navbar', 'top-bar', 'site-footer', 'colophon', 'footer',
            ] );

            // Page-specific banner selectors — NOT the global nav header.
            const BANNER_SELS = [
                '#page-header', '#banner', '#hero',
                '.page-header', '.hero-section', '.page-hero',
                '.post-hero', '.site-hero', '.entry-banner', '.page-title-area',
                '.elementor-location-footer',
            ].join( ', ' );

            const heroSwaps = [];
            const seenHero  = new Set();

            // Pass 1: page-specific banner selectors.
            document.querySelectorAll( BANNER_SELS ).forEach( function ( curEl ) {
                if ( seenHero.has( curEl ) ) return;
                if ( current.el.contains( curEl ) ) return;
                if ( curEl.id && SKIP_IDS.has( curEl.id ) ) return;
                seenHero.add( curEl );
                const elSel = curEl.id
                    ? '#' + CSS.escape( curEl.id )
                    : curEl.tagName.toLowerCase() + ( curEl.classList.length ? '.' + curEl.classList[0] : '' );
                try {
                    const newEl = newDoc.querySelector( elSel );
                    // Only swap if content actually changed — avoids destroying
                    // JS state on elements that are the same on every page.
                    if ( newEl && newEl.innerHTML !== curEl.innerHTML ) {
                        heroSwaps.push( { cur: curEl, html: newEl.outerHTML } );
                    }
                } catch ( e ) {}
            } );

            // Pass 2: body descendants up to 2 levels deep with IDs.
            // Scanning 2 levels handles the common WordPress pattern where the
            // banner is a child of a #page / .page-wrapper container rather than
            // a direct body child.
            // Diff check: skip elements whose content hasn't changed (same banner
            // on every inner page never triggers a swap; homepage banner does).
            // Ancestor check: if a parent element is already scheduled for swap,
            // skip the child — the parent's outerHTML replacement covers it.
            document.querySelectorAll( 'body > [id], body > :not(script):not(style) > [id]' ).forEach( function ( curEl ) {
                if ( seenHero.has( curEl ) ) return;
                if ( SKIP_IDS.has( curEl.id ) ) return;
                if ( current.el === curEl || current.el.contains( curEl ) || curEl.contains( current.el ) ) return;

                // Skip if any ancestor is already in the swap list.
                var anc = curEl.parentElement;
                while ( anc && anc !== document.body ) {
                    if ( seenHero.has( anc ) ) return;
                    anc = anc.parentElement;
                }

                seenHero.add( curEl );
                const newEl = newDoc.getElementById( curEl.id );
                if ( newEl && newEl.innerHTML !== curEl.innerHTML ) {
                    heroSwaps.push( { cur: curEl, html: newEl.outerHTML } );
                }
            } );

            // Fire before-navigate so flying-images.js can capture positions.
            document.dispatchEvent( new CustomEvent( 'aio:before-navigate', {
                detail: { from: location.href, to: canonical },
                bubbles: true,
            } ) );

            // ── DOM swap: use View Transitions API for a smooth animation when
            // supported; fall back to a simple fade for older browsers.
            const doSwap = function () {
                current.el.innerHTML = newContent.el.innerHTML;
                heroSwaps.forEach( function ( swap ) {
                    try { swap.cur.outerHTML = swap.html; } catch ( e ) {}
                } );
            };

            if ( document.startViewTransition && ! REDUCED_MOTION ) {
                // VTA: browser captures old state → doSwap() → captures new state → animates.
                const vt = document.startViewTransition( doSwap );
                // Wait until the animation has started (DOM is already updated at this point).
                await vt.ready;
            } else {
                // Fallback: manual fade out → swap → fade in.
                await fadeOut( current.el );
                doSwap();
                fadeIn( current.el );
            }

            // ── Update <head> ──
            document.title = newDoc.title;

            // Standard meta.
            const desc = newDoc.querySelector( 'meta[name="description"]' );
            updateMeta( 'description', desc?.getAttribute( 'content' ) );

            // Open Graph tags (important for social sharing and some SEO plugins).
            [ 'og:title', 'og:description', 'og:url', 'og:image' ].forEach( function ( prop ) {
                const m = newDoc.querySelector( 'meta[property="' + prop + '"]' );
                if ( m ) updateOgMeta( prop, m.getAttribute( 'content' ) );
            } );

            // Canonical URL.
            updateCanonical( newDoc );

            if ( pushState ) {
                history.pushState( { url: canonical }, newDoc.title, canonical );
            }

            // Scroll.
            const hash = new URL( canonical ).hash;
            if ( hash ) {
                const target = document.querySelector( hash );
                target ? target.scrollIntoView( { behavior: 'smooth' } ) : window.scrollTo( 0, 0 );
            } else {
                window.scrollTo( 0, 0 );
            }

            hideLoader();
            finishBar();

            // Re-run inline scripts (non-critical: use rIC so it doesn't block paint).
            const idle = window.requestIdleCallback || function ( fn ) { setTimeout( fn, 0 ); };
            idle( function () { executeScripts( current.el ); } );

            // Fire a resize event so layout-dependent plugins (sliders, masonry,
            // sticky elements) recalculate their dimensions after the swap.
            window.dispatchEvent( new Event( 'resize' ) );

            // ── Re-initialize page builder widgets ──
            // Each builder has its own re-init hook. All calls are wrapped in
            // try/catch so a missing builder never crashes the SPA cycle.
            idle( function () {
                const el = document.querySelector( current.sel );
                const $el = window.jQuery ? jQuery( el ) : null;

                // Elementor — re-run element handlers on the new content area.
                if ( window.elementorFrontend ) {
                    try {
                        if ( elementorFrontend.hooks ) {
                            elementorFrontend.hooks.doAction( 'frontend/element_ready/global', $el, jQuery );
                        }
                        // Elementor Pro widgets (motion effects, sticky, etc.).
                        if ( elementorFrontend.elementsHandler ) {
                            elementorFrontend.elementsHandler.runReadyTrigger( $el );
                        }
                    } catch ( e ) {}
                }

                // WP Bakery (Visual Composer).
                if ( typeof window.vc_js === 'function' ) {
                    try { window.vc_js(); } catch ( e ) {}
                }

                // Beaver Builder.
                if ( window.FLBuilder ) {
                    try { FLBuilder._initModules(); } catch ( e ) {}
                }

                // Divi.
                if ( window.ET_Builder ) {
                    try { ET_Builder.run(); } catch ( e ) {}
                }
            } );

            // Notify other modules (lazy-load.js, flying-images.js, custom code).
            document.dispatchEvent( new CustomEvent( 'aio:navigate', {
                detail: { url: canonical, title: newDoc.title },
                bubbles: true,
            } ) );

        } catch ( err ) {
            if ( err.name === 'AbortError' ) return;
            hideLoader();
            finishBar();
            location.href = url;
        }
    }

    // -------------------------------------------------------------------------
    // Click interception
    // -------------------------------------------------------------------------

    document.addEventListener( 'click', function ( e ) {
        if ( e.metaKey || e.ctrlKey || e.shiftKey || e.altKey || e.button !== 0 ) return;

        const a = e.composedPath().find( function ( el ) { return el.tagName === 'A'; } );
        if ( ! a || ! a.href )             return;
        if ( ! isInternal( a.href ) )      return;
        if ( a.target === '_blank' )        return;
        if ( a.download )                   return;
        if ( isDownload( a.href ) )         return;
        if ( isExcluded( a.href ) )         return;
        if ( a.dataset.noSpa !== undefined ) return;

        const u = new URL( a.href );
        if ( u.pathname === location.pathname && ! u.search && u.hash ) return;

        e.preventDefault();
        console.log( '[AIO SPA] Navigating to:', a.href );
        navigate( a.href, true );
    } );

    // -------------------------------------------------------------------------
    // Browser back / forward
    // -------------------------------------------------------------------------

    window.addEventListener( 'popstate', function ( e ) {
        navigate( e.state?.url || location.href, false );
    } );

    history.replaceState( { url: location.href }, document.title, location.href );

    // -------------------------------------------------------------------------
    // Error safety net — disable SPA on unhandled errors to avoid blank pages
    // -------------------------------------------------------------------------

    window.addEventListener( 'error', function ( e ) {
        if ( e.filename && e.filename.includes( 'spa.js' ) ) {
            window.__aioSpaDisabled = true;
            console.warn( '[AIO] SPA disabled due to JS error:', e.message );
        }
    } );

} )();
