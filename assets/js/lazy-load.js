/**
 * AIO Lazy Load — IntersectionObserver-based image & iframe loader.
 *
 * Handles:
 *  – <img data-src> / <img data-srcset>
 *  – <iframe data-src>
 *  – Elements with data-bg (background-image)
 *
 * Fallback: if IntersectionObserver is unavailable, all deferred assets
 * are loaded immediately so the page still works without any JS APIs.
 *
 * Re-initialises after each SPA navigation via the `aio:navigate` event.
 */
( function () {
    'use strict';

    // How far outside the viewport to start loading (px).
    const ROOT_MARGIN = '200px 0px';

    // -------------------------------------------------------------------------
    // Load helpers
    // -------------------------------------------------------------------------

    function loadImg( img ) {
        if ( img.dataset.srcset ) {
            img.srcset = img.dataset.srcset;
            delete img.dataset.srcset;
        }
        if ( img.dataset.src ) {
            img.src = img.dataset.src;
            delete img.dataset.src;
        }
        img.classList.remove( 'aio-lazy' );
        img.classList.add( 'aio-lazy-loaded' );
    }

    function loadIframe( iframe ) {
        if ( iframe.dataset.src ) {
            iframe.src = iframe.dataset.src;
            delete iframe.dataset.src;
        }
        iframe.classList.remove( 'aio-lazy' );
        iframe.classList.add( 'aio-lazy-loaded' );
    }

    function loadBg( el ) {
        if ( el.dataset.bg ) {
            el.style.backgroundImage = el.dataset.bg;
            delete el.dataset.bg;
        }
        el.classList.remove( 'aio-lazy-bg' );
        el.classList.add( 'aio-lazy-bg-loaded' );
    }

    function loadElement( el ) {
        if ( el.tagName === 'IMG' )    return loadImg( el );
        if ( el.tagName === 'IFRAME' ) return loadIframe( el );
        return loadBg( el );
    }

    // -------------------------------------------------------------------------
    // Observer setup
    // -------------------------------------------------------------------------

    let observer = null;

    function createObserver() {
        if ( ! ( 'IntersectionObserver' in window ) ) {
            return null;
        }
        return new IntersectionObserver(
            function ( entries ) {
                entries.forEach( function ( entry ) {
                    if ( ! entry.isIntersecting ) return;
                    loadElement( entry.target );
                    observer.unobserve( entry.target );
                } );
            },
            { rootMargin: ROOT_MARGIN }
        );
    }

    // -------------------------------------------------------------------------
    // Init / re-init
    // -------------------------------------------------------------------------

    function init() {
        const imgs    = document.querySelectorAll( 'img.aio-lazy[data-src]' );
        const iframes = document.querySelectorAll( 'iframe.aio-lazy[data-src]' );
        const bgs     = document.querySelectorAll( '[data-bg].aio-lazy-bg' );

        if ( ! imgs.length && ! iframes.length && ! bgs.length ) return;

        // Ensure observer exists (recreate after SPA swap if needed).
        if ( ! observer ) {
            observer = createObserver();
        }

        if ( ! observer ) {
            // No IntersectionObserver support — load everything right now.
            imgs.forEach( loadImg );
            iframes.forEach( loadIframe );
            bgs.forEach( loadBg );
            return;
        }

        imgs.forEach(    function ( el ) { observer.observe( el ); } );
        iframes.forEach( function ( el ) { observer.observe( el ); } );
        bgs.forEach(     function ( el ) { observer.observe( el ); } );
    }

    // -------------------------------------------------------------------------
    // Bootstrap
    // -------------------------------------------------------------------------

    if ( document.readyState === 'loading' ) {
        document.addEventListener( 'DOMContentLoaded', init );
    } else {
        init();
    }

    // Re-initialise after each SPA page swap.
    document.addEventListener( 'aio:navigate', init );

} )();
