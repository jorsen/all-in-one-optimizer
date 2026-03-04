/**
 * AIO Optimizer — Admin JS
 * Handles preset selection: fills all form checkboxes based on chosen preset.
 */
( function () {
    'use strict';

    // -------------------------------------------------------------------------
    // Preset definitions
    // 1 = checked, 0 = unchecked, null = leave unchanged
    // -------------------------------------------------------------------------
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

    // -------------------------------------------------------------------------
    // Apply a preset to all checkboxes in the form
    // -------------------------------------------------------------------------
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

    // -------------------------------------------------------------------------
    // Wire up buttons after DOM is ready
    // -------------------------------------------------------------------------
    document.addEventListener( 'DOMContentLoaded', function () {
        document.querySelectorAll( '.aio-preset-btn' ).forEach( function ( btn ) {
            btn.addEventListener( 'click', function ( e ) {
                e.preventDefault();
                applyPreset( btn.dataset.preset );
            } );
        } );
    } );

} )();
