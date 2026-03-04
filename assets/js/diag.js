/**
 * AIO Front-end Diagnostics Panel
 *
 * Loaded only when ?aio_diag=1 is present and the visitor is an admin.
 * Patches console early to capture [AIO ...] log lines, then renders a
 * floating status panel after the page has initialised.
 */
( function () {
    'use strict';

    // ── Capture AIO console messages before any other script runs ────────────
    window.__aioLogs = window.__aioLogs || [];
    var logs      = window.__aioLogs;
    var origLog   = console.log;
    var origWarn  = console.warn;
    var origError = console.error;

    function capture( level, args ) {
        if ( args[0] && String( args[0] ).indexOf( '[AIO' ) === 0 ) {
            logs.push( {
                level : level,
                msg   : Array.prototype.join.call( args, ' ' ),
                time  : new Date().toLocaleTimeString(),
            } );
        }
    }

    console.log   = function () { capture( 'log',   arguments ); return origLog.apply( console, arguments ); };
    console.warn  = function () { capture( 'warn',  arguments ); return origWarn.apply( console, arguments ); };
    console.error = function () { capture( 'error', arguments ); return origError.apply( console, arguments ); };

    // ── Panel builder ─────────────────────────────────────────────────────────

    function buildPanel() {
        // Remove any existing panel first.
        var existing = document.getElementById( 'aio-diag-panel' );
        if ( existing ) existing.remove();

        var cfg      = window.aioSpaConfig  || null;
        var flyCfg   = window.aioFlyConfig  || null;
        var cache    = window.aioPageCache  || null;
        var disabled = window.__aioSpaDisabled;

        // Check which selector actually matches the live DOM.
        var selMatch = null;
        if ( cfg && cfg.selector ) {
            var parts = cfg.selector.split( ',' );
            for ( var i = 0; i < parts.length; i++ ) {
                try {
                    var el = document.querySelector( parts[i].trim() );
                    if ( el ) { selMatch = parts[i].trim(); break; }
                } catch ( e ) {}
            }
        }

        var checks = [
            {
                label  : 'spa.js loaded',
                ok     : !! cfg,
                detail : cfg ? 'window.aioSpaConfig present' : 'window.aioSpaConfig missing — is SPA enabled?',
            },
            {
                label  : 'SPA not disabled',
                ok     : ! disabled,
                detail : disabled ? 'window.__aioSpaDisabled is set — a JS error crashed spa.js' : 'Running normally',
            },
            {
                label  : 'Content selector',
                ok     : !! selMatch,
                detail : selMatch
                    ? 'Matched: ' + selMatch
                    : 'No match for: ' + ( cfg ? cfg.selector : '—' ) + ' — SPA cannot swap content',
            },
            {
                label  : 'Flying Pages',
                ok     : !! flyCfg,
                detail : flyCfg ? 'delay: ' + flyCfg.delay + 'ms, mobile: ' + flyCfg.mobile : 'window.aioFlyConfig missing',
            },
            {
                label  : 'Page cache',
                ok     : !! cache,
                detail : cache ? 'entries: ' + cache.size : 'window.aioPageCache not initialised',
            },
        ];

        // ── Build HTML ────────────────────────────────────────────────────────

        var statusRows = checks.map( function ( c ) {
            var icon = c.ok
                ? '<span style="color:#1e7e34;font-weight:700">&#10003;</span>'
                : '<span style="color:#c62d2d;font-weight:700">&#10007;</span>';
            return '<tr>'
                + '<td style="padding:3px 6px 3px 0;white-space:nowrap">' + icon + '</td>'
                + '<td style="padding:3px 8px 3px 0;white-space:nowrap;font-weight:500">' + c.label + '</td>'
                + '<td style="padding:3px 0;color:#666;font-size:11px">' + c.detail + '</td>'
                + '</tr>';
        } ).join( '' );

        var logItems = logs.length
            ? logs.map( function ( l ) {
                var color = l.level === 'warn'  ? '#7a4b10'
                          : l.level === 'error' ? '#c62d2d'
                          : '#333';
                return '<div style="padding:2px 0;font-size:11px;color:' + color + ';border-bottom:1px solid #f0f0f0">'
                    + '<span style="color:#999;margin-right:4px">[' + l.time + ']</span>'
                    + l.msg
                    + '</div>';
            } ).join( '' )
            : '<div style="color:#999;font-size:11px;padding:4px 0">No AIO log entries yet — try navigating to another page.</div>';

        // ── DOM ───────────────────────────────────────────────────────────────

        var panel = document.createElement( 'div' );
        panel.id  = 'aio-diag-panel';
        panel.setAttribute( 'style', [
            'position:fixed',
            'bottom:20px',
            'right:20px',
            'z-index:999999',
            'background:#fff',
            'border:2px solid #0073aa',
            'border-radius:6px',
            'box-shadow:0 4px 24px rgba(0,0,0,.22)',
            'width:400px',
            'max-height:520px',
            'overflow:auto',
            'font-family:-apple-system,BlinkMacSystemFont,"Segoe UI",sans-serif',
            'font-size:13px',
            'line-height:1.5',
        ].join( ';' ) );

        panel.innerHTML = ''
            // Header
            + '<div style="background:#0073aa;color:#fff;padding:10px 14px;display:flex;'
            +   'justify-content:space-between;align-items:center;border-radius:4px 4px 0 0">'
            +   '<strong style="font-size:14px">AIO Diagnostics</strong>'
            +   '<span style="display:flex;gap:8px;align-items:center">'
            +   '<button id="aio-diag-refresh" title="Refresh" style="background:rgba(255,255,255,.2);'
            +     'border:none;color:#fff;font-size:15px;cursor:pointer;padding:2px 7px;border-radius:3px">&#8635;</button>'
            +   '<button id="aio-diag-close" title="Close" style="background:none;border:none;color:#fff;'
            +     'font-size:20px;cursor:pointer;padding:0;line-height:1">&times;</button>'
            +   '</span>'
            + '</div>'
            // Body
            + '<div style="padding:14px">'
            // Status checks
            +   '<table style="width:100%;border-collapse:collapse">' + statusRows + '</table>'
            // Log section
            +   '<div style="margin-top:12px;border-top:1px solid #e5e5e5;padding-top:10px">'
            +     '<div style="display:flex;justify-content:space-between;align-items:center;margin-bottom:6px">'
            +       '<strong style="font-size:12px;text-transform:uppercase;letter-spacing:.5px;color:#666">Console Logs</strong>'
            +       '<button id="aio-diag-clear" style="background:#f0f0f1;border:1px solid #ccc;'
            +         'color:#555;font-size:11px;padding:2px 8px;border-radius:3px;cursor:pointer">Clear</button>'
            +     '</div>'
            +     '<div id="aio-diag-logs" style="max-height:140px;overflow-y:auto">' + logItems + '</div>'
            +   '</div>'
            // Footer note
            +   '<p style="margin:10px 0 0;font-size:11px;color:#999">'
            +     'URL: <code style="font-size:11px">' + location.href + '</code>'
            +   '</p>'
            + '</div>';

        document.body.appendChild( panel );

        document.getElementById( 'aio-diag-close' ).addEventListener( 'click', function () {
            panel.remove();
        } );

        document.getElementById( 'aio-diag-refresh' ).addEventListener( 'click', function () {
            buildPanel();
        } );

        document.getElementById( 'aio-diag-clear' ).addEventListener( 'click', function () {
            window.__aioLogs = logs = [];
            document.getElementById( 'aio-diag-logs' ).innerHTML =
                '<div style="color:#999;font-size:11px;padding:4px 0">Logs cleared.</div>';
        } );
    }

    // ── Init ──────────────────────────────────────────────────────────────────

    // Small delay so spa.js & flying-pages.js finish their synchronous init.
    if ( document.readyState === 'loading' ) {
        document.addEventListener( 'DOMContentLoaded', function () { setTimeout( buildPanel, 150 ); } );
    } else {
        setTimeout( buildPanel, 150 );
    }

    // Rebuild after each SPA navigation (checks & logs change).
    document.addEventListener( 'aio:navigate', function () {
        setTimeout( buildPanel, 150 );
    } );

} )();
