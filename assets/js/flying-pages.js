/**
 * Flying Pages — prefetch internal links on hover using the fetch API.
 *
 * Fetched HTML is stored in window.aioPageCache (Map) so the SPA module
 * can consume it instantly without a second network request.
 *
 * Config injected by WordPress via wp_localize_script as `aioFlyConfig`.
 */
( function () {
    'use strict';

    const cfg    = window.aioFlyConfig || {};
    const delay  = parseInt( cfg.delay, 10 ) || 65;
    const mobile = !! cfg.mobile;

    // Shared cache consumed by spa.js.
    window.aioPageCache = window.aioPageCache || new Map();
    const cache = window.aioPageCache;

    // -------------------------------------------------------------------------
    // Guard clauses — abort early when prefetch would waste bandwidth
    // -------------------------------------------------------------------------

    const conn = navigator.connection || navigator.mozConnection || navigator.webkitConnection;
    if ( conn ) {
        if ( conn.saveData ) return;
        if ( conn.effectiveType && /\b(slow-2g|2g)\b/.test( conn.effectiveType ) ) return;
    }

    if ( ! mobile && window.matchMedia( '(hover: none)' ).matches ) return;

    // -------------------------------------------------------------------------
    // Safety checks
    // -------------------------------------------------------------------------

    function isSafe( url ) {
        try {
            const u = new URL( url, location.href );
            if ( u.origin !== location.origin )               return false;
            if ( u.pathname === location.pathname && u.hash ) return false;
            if ( cache.has( u.href ) )                        return false; // already cached

            const ext  = u.pathname.split( '.' ).pop().toLowerCase();
            const skip = [ 'pdf','zip','rar','gz','tar','mp4','mp3','avi','mov',
                           'jpg','jpeg','png','gif','webp','svg','ico','woff','woff2' ];
            if ( skip.includes( ext ) )                       return false;

            return true;
        } catch {
            return false;
        }
    }

    // -------------------------------------------------------------------------
    // Fetch and cache a page
    // -------------------------------------------------------------------------

    async function prefetch( url ) {
        const canonical = new URL( url, location.href ).href;
        if ( ! isSafe( canonical ) ) return;

        // Mark as in-flight with null so we don't fire duplicate requests.
        cache.set( canonical, null );

        try {
            const res = await fetch( canonical, {
                credentials: 'same-origin',
                headers:     { 'X-AIO-SPA': '1', 'X-AIO-Prefetch': '1' },
            } );

            if ( res.ok && res.headers.get( 'content-type' )?.includes( 'text/html' ) ) {
                const html = await res.text();
                cache.set( canonical, html );
            } else {
                cache.delete( canonical );
            }
        } catch {
            cache.delete( canonical );
        }
    }

    // -------------------------------------------------------------------------
    // Event listeners
    // -------------------------------------------------------------------------

    let timer = null;

    function getAnchor( el ) {
        while ( el && el.tagName !== 'A' ) el = el.parentElement;
        return el;
    }

    document.addEventListener( 'mouseover', function ( e ) {
        const a = getAnchor( e.target );
        if ( ! a || ! a.href )                     return;
        if ( a.dataset.noPrefetch !== undefined )   return;

        clearTimeout( timer );
        timer = setTimeout( function () {
            // Use requestIdleCallback if available so prefetch never blocks rendering.
            if ( window.requestIdleCallback ) {
                requestIdleCallback( function () { prefetch( a.href ); }, { timeout: 1000 } );
            } else {
                prefetch( a.href );
            }
        }, delay );
    } );

    document.addEventListener( 'mouseout', function () {
        clearTimeout( timer );
    } );

    // Touch: prefetch immediately on touchstart (if mobile enabled).
    if ( mobile ) {
        document.addEventListener( 'touchstart', function ( e ) {
            const a = getAnchor( e.target );
            if ( a && a.href ) prefetch( a.href );
        }, { passive: true } );
    }

} )();
