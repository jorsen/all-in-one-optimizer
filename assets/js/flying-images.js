/**
 * AIO Flying Images — shared-element image transitions between SPA pages.
 *
 * On each SPA navigation:
 *  1. `aio:before-navigate` → capture positions of all visible loaded images
 *  2. `aio:navigate`        → for images whose src also appears in the new page,
 *                             animate them from old position to new position
 *
 * Works with any theme by detecting <img> tags dynamically.
 * Also caches decoded image bitmaps so images that appear on multiple pages
 * never need to be re-decoded.
 *
 * Uses requestIdleCallback for non-critical work and respects
 * prefers-reduced-motion.
 */
( function () {
    'use strict';

    const REDUCED_MOTION = window.matchMedia( '(prefers-reduced-motion: reduce)' ).matches;

    // Bitmap cache — keeps decoded images in memory across page swaps.
    const imageCache = new Map(); // normalised src → true (loaded)

    // Captured positions from the previous page.
    const prevPositions = new Map(); // normalised src → DOMRect-like object

    // -------------------------------------------------------------------------
    // Normalise a src URL so relative and absolute forms match.
    // -------------------------------------------------------------------------

    function normSrc( src ) {
        try { return new URL( src, location.href ).href; }
        catch { return src; }
    }

    // -------------------------------------------------------------------------
    // Capture visible image positions before the page swaps.
    // -------------------------------------------------------------------------

    function capturePositions() {
        prevPositions.clear();

        document.querySelectorAll( 'img' ).forEach( function ( img ) {
            const src = img.src || img.dataset.src;
            if ( ! src || src.startsWith( 'data:' ) ) return;

            const rect = img.getBoundingClientRect();
            // Only capture images actually visible in the viewport.
            if ( rect.width < 1 || rect.height < 1 ) return;
            if ( rect.bottom < 0 || rect.top > window.innerHeight ) return;

            prevPositions.set( normSrc( src ), {
                x:      rect.left + window.scrollX,
                y:      rect.top  + window.scrollY,
                width:  rect.width,
                height: rect.height,
            } );
        } );
    }

    // -------------------------------------------------------------------------
    // Animate images from their old position to their new position.
    // -------------------------------------------------------------------------

    function animateFlyingImages() {
        if ( prevPositions.size === 0 || REDUCED_MOTION ) return;

        document.querySelectorAll( 'img' ).forEach( function ( img ) {
            const src = img.src || img.dataset.src;
            if ( ! src || src.startsWith( 'data:' ) ) return;

            const prev = prevPositions.get( normSrc( src ) );
            if ( ! prev ) return;

            const rect = img.getBoundingClientRect();
            if ( rect.width < 1 || rect.height < 1 ) return;

            const newX = rect.left + window.scrollX;
            const newY = rect.top  + window.scrollY;

            const dx     = prev.x - newX;
            const dy     = prev.y - newY;
            const scaleX = prev.width  / rect.width;
            const scaleY = prev.height / rect.height;

            // Skip trivial moves (< 4px and < 5% scale change).
            if ( Math.abs( dx ) < 4 && Math.abs( dy ) < 4 &&
                 Math.abs( scaleX - 1 ) < 0.05 && Math.abs( scaleY - 1 ) < 0.05 ) {
                return;
            }

            // Apply starting transform instantly, then transition to identity.
            img.style.willChange     = 'transform';
            img.style.transformOrigin = 'top left';
            img.style.transform      = 'translate(' + dx + 'px,' + dy + 'px) scaleX(' + scaleX + ') scaleY(' + scaleY + ')';
            img.style.transition     = 'none';

            // Two rAF frames to ensure the browser has painted the start state.
            requestAnimationFrame( function () {
                requestAnimationFrame( function () {
                    img.style.transition = 'transform 0.35s cubic-bezier(0.4,0,0.2,1)';
                    img.style.transform  = 'translate(0,0) scale(1)';

                    img.addEventListener( 'transitionend', function cleanup() {
                        img.style.willChange      = '';
                        img.style.transformOrigin = '';
                        img.style.transform       = '';
                        img.style.transition      = '';
                        img.removeEventListener( 'transitionend', cleanup );
                    } );
                } );
            } );
        } );

        prevPositions.clear();
    }

    // -------------------------------------------------------------------------
    // Cache image bitmaps so the browser doesn't re-decode on next page.
    // -------------------------------------------------------------------------

    function cacheLoadedImages() {
        document.querySelectorAll( 'img.aio-lazy-loaded, img:not(.aio-lazy)' ).forEach( function ( img ) {
            if ( img.complete && img.naturalWidth > 0 && ! imageCache.has( img.src ) ) {
                imageCache.set( normSrc( img.src ), true );
            }
        } );
    }

    // -------------------------------------------------------------------------
    // Event wiring
    // -------------------------------------------------------------------------

    document.addEventListener( 'aio:before-navigate', capturePositions );

    document.addEventListener( 'aio:navigate', function () {
        const idle = window.requestIdleCallback || function ( fn ) { setTimeout( fn, 0 ); };
        idle( animateFlyingImages );
        idle( cacheLoadedImages );
    } );

    // Seed cache on initial load.
    if ( document.readyState === 'loading' ) {
        document.addEventListener( 'DOMContentLoaded', function () {
            const idle = window.requestIdleCallback || function ( fn ) { setTimeout( fn, 0 ); };
            idle( cacheLoadedImages );
        } );
    } else {
        const idle = window.requestIdleCallback || function ( fn ) { setTimeout( fn, 0 ); };
        idle( cacheLoadedImages );
    }

} )();
