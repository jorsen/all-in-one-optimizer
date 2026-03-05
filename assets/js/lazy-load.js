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
    // Viewport force-check
    //
    // Gallery and slider plugins (Swiper, Slick, Isotope, etc.) often:
    //  a) initialise AFTER lazy-load.js, revealing images the observer already
    //     evaluated as off-screen / hidden.
    //  b) clone slides — cloned <img data-src> elements are never registered
    //     with the IntersectionObserver.
    //
    // checkViewport() loads any .aio-lazy element whose bounding rect is
    // currently inside the extended viewport (same 200 px rootMargin).
    // -------------------------------------------------------------------------

    function checkViewport() {
        const threshold = 200;
        const vph = window.innerHeight || document.documentElement.clientHeight;

        document.querySelectorAll( 'img.aio-lazy[data-src], iframe.aio-lazy[data-src], [data-bg].aio-lazy-bg' )
            .forEach( function ( el ) {
                const r = el.getBoundingClientRect();
                if ( r.bottom >= -threshold && r.top <= vph + threshold ) {
                    loadElement( el );
                    if ( observer ) observer.unobserve( el );
                }
            } );
    }

    // -------------------------------------------------------------------------
    // MutationObserver — watch for slider-cloned nodes added to the DOM
    // -------------------------------------------------------------------------

    let mutObs = null;

    function startMutationObserver() {
        if ( mutObs || ! ( 'MutationObserver' in window ) ) return;

        mutObs = new MutationObserver( function ( mutations ) {
            mutations.forEach( function ( m ) {
                m.addedNodes.forEach( function ( node ) {
                    if ( node.nodeType !== 1 ) return; // elements only

                    // The added node itself might be a lazy image.
                    const candidates = [];
                    if ( node.matches && node.matches( 'img.aio-lazy[data-src], iframe.aio-lazy[data-src]' ) ) {
                        candidates.push( node );
                    }
                    // Also check descendants.
                    node.querySelectorAll( 'img.aio-lazy[data-src], iframe.aio-lazy[data-src], [data-bg].aio-lazy-bg' )
                        .forEach( function ( el ) { candidates.push( el ); } );

                    candidates.forEach( function ( el ) {
                        if ( observer ) {
                            observer.observe( el );
                        } else {
                            loadElement( el );
                        }
                    } );
                } );
            } );
        } );

        mutObs.observe( document.body, { childList: true, subtree: true } );
    }

    // -------------------------------------------------------------------------
    // Bootstrap
    // -------------------------------------------------------------------------

    function bootstrap() {
        init();
        startMutationObserver();

        // Run a viewport check after gallery/slider plugins have had time to
        // initialise and reveal their elements.
        setTimeout( checkViewport, 300 );
        setTimeout( checkViewport, 800 );
    }

    if ( document.readyState === 'loading' ) {
        document.addEventListener( 'DOMContentLoaded', bootstrap );
    } else {
        bootstrap();
    }

    // Re-initialise after each SPA page swap.
    document.addEventListener( 'aio:navigate', function () {
        init();
        setTimeout( checkViewport, 300 );
    } );

} )();
