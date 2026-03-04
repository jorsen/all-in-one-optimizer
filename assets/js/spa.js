/**
 * AIO SPA — fetch-based single-page navigation for WordPress.
 *
 * – Consumes the window.aioPageCache pre-warmed by flying-pages.js
 * – Smooth fade transition between pages
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

    const cfg       = window.aioSpaConfig || {};
    // Extended selector covers the most common WordPress theme content wrappers.
    const rawSel    = cfg.selector  || '#content, #main-content, #primary, .site-main, .main-content, .content-area, main';
    const excludes  = cfg.exclude   || [];
    const adminPath = cfg.adminPath || '/wp-admin';

    console.log( '[AIO SPA] Loaded. Selector:', rawSel );

    // Shared page cache populated by flying-pages.js.
    window.aioPageCache = window.aioPageCache || new Map();
    const cache = window.aioPageCache;

    // -------------------------------------------------------------------------
    // Progress bar
    // -------------------------------------------------------------------------

    const BAR_ID = 'aio-progress';

    const LOADER_ID = 'aio-preloader';

    ( function injectBarStyles() {
        const s = document.createElement( 'style' );
        s.textContent = `
            #${BAR_ID}{position:fixed;top:0;left:0;height:3px;width:0;
            background:var(--aio-bar-color,#0073aa);z-index:99999;
            transition:width .25s ease,opacity .4s ease;pointer-events:none}
            #${BAR_ID}.loading{width:70%;opacity:1}
            #${BAR_ID}.done{width:100%;opacity:0}

            #${LOADER_ID}{
                position:fixed;inset:0;z-index:99998;
                display:flex;align-items:center;justify-content:center;
                background:rgba(255,255,255,0.55);
                backdrop-filter:blur(2px);-webkit-backdrop-filter:blur(2px);
                opacity:0;pointer-events:none;
                transition:opacity .2s ease}
            #${LOADER_ID}.active{opacity:1;pointer-events:all}
            #${LOADER_ID} .aio-spinner{
                width:44px;height:44px;
                border:3px solid rgba(0,115,170,.18);
                border-top-color:var(--aio-bar-color,#0073aa);
                border-radius:50%;
                animation:aio-spin .75s linear infinite}
            @keyframes aio-spin{to{transform:rotate(360deg)}}
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
    // Preloader spinner
    // -------------------------------------------------------------------------

    let loaderTimer = null;

    function loader() {
        return document.getElementById( LOADER_ID ) || ( function () {
            const el  = document.createElement( 'div' );
            el.id     = LOADER_ID;
            el.innerHTML = '<div class="aio-spinner"></div>';
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
    // Fade transition helpers
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
        el.style.transition = 'opacity 0.2s ease';
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

    function updateMeta( name, content ) {
        if ( ! content ) return;
        let el = document.querySelector( 'meta[name="' + name + '"]' );
        if ( ! el ) { el = document.createElement( 'meta' ); el.name = name; document.head.appendChild( el ); }
        el.setAttribute( 'content', content );
    }

    /** Re-execute <script> tags cloned into DOM via innerHTML / DOMParser. */
    function executeScripts( container ) {
        container.querySelectorAll( 'script' ).forEach( function ( inert ) {
            // Skip external scripts already loaded by the page (avoid double-loading).
            if ( inert.src ) return;

            const live = document.createElement( 'script' );
            Array.from( inert.attributes ).forEach( function ( a ) { live.setAttribute( a.name, a.value ); } );
            live.textContent = inert.textContent;

            // Wrap execution so a broken third-party inline script (e.g. gform, GTM)
            // doesn't crash the SPA cycle or trigger the error safety-net above.
            const original = live.textContent;
            live.textContent = 'try{' + original + '}catch(e){console.warn("[AIO] Skipped inline script:",e.message);}';

            inert.parentNode.replaceChild( live, inert );
        } );
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

            // ── Co-swap: also update hero/banner sections that live OUTSIDE
            // the main content container (e.g. .page-header sibling sections).
            // Collect references BEFORE any DOM mutation.
            const HERO_SELS = '.page-header, .hero-section, .page-hero, .post-hero, .site-hero, .entry-banner';
            const heroSwaps = [];
            document.querySelectorAll( HERO_SELS ).forEach( function ( curEl ) {
                if ( current.el.contains( curEl ) ) return; // already inside swap zone
                // Build a selector that uniquely identifies this element in the new doc.
                const elSel = curEl.id
                    ? '#' + curEl.id
                    : curEl.tagName.toLowerCase() + ( curEl.classList.length ? '.' + curEl.classList[0] : '' );
                try {
                    const newEl = newDoc.querySelector( elSel );
                    if ( newEl ) {
                        heroSwaps.push( { cur: curEl, html: newEl.outerHTML } );
                    }
                } catch ( e ) {}
            } );

            // Fire before-navigate so flying-images.js can capture positions.
            document.dispatchEvent( new CustomEvent( 'aio:before-navigate', {
                detail: { from: location.href, to: canonical },
                bubbles: true,
            } ) );

            // Fade out current content.
            await fadeOut( current.el );

            // Swap main content.
            current.el.innerHTML = newContent.el.innerHTML;

            // Apply hero co-swaps.
            heroSwaps.forEach( function ( swap ) {
                try { swap.cur.outerHTML = swap.html; } catch ( e ) {}
            } );

            // Update head.
            document.title = newDoc.title;
            const desc = newDoc.querySelector( 'meta[name="description"]' );
            updateMeta( 'description', desc?.getAttribute( 'content' ) );

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

            // Fade in new content.
            hideLoader();
            fadeIn( current.el );
            finishBar();

            // Re-run scripts (non-critical: use rIC).
            const idle = window.requestIdleCallback || function ( fn ) { setTimeout( fn, 0 ); };
            idle( function () { executeScripts( current.el ); } );

            // Notify other modules.
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
