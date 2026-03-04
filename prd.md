Technical Proposal: Universal Performance & SPA Plugin

Goal:
Create a WordPress plugin that optimizes performance, implements SPA-like behavior, and handles images smartly, while remaining compatible with any theme.

Architecture Overview

Plugin Core

Namespaced PHP classes to avoid conflicts.

Minimal hooks in init, wp_enqueue_scripts, and template_redirect.

Configurable via an admin settings page.

Debloating Module

Conditionally deregister scripts and styles not used on a page.

Use WordPress hooks:

wp_enqueue_scripts for frontend assets.

admin_enqueue_scripts for backend assets (optional).

Compatible with any theme by detecting theme-dependent scripts dynamically.

Flying Pages (SPA Prefetch)

JavaScript-based prefetch for anchor links within the site.

Uses the fetch API to preload pages.

Smooth page transitions with minimal flicker.

Fallback: if JS disabled, standard page load applies.

Autoptimize Image Lazy Load

Replace <img> src with a data-src and load on scroll or intersection.

Support for:

Standard <img> tags.

Background images via inline styles.

Automatic WebP conversion if server supports it (optional integration with existing optimization libraries).

Flying Images with SPA

Maintain image continuity between pages for a seamless experience.

Use a lightweight JS framework or vanilla JS:

Cache images already loaded.

Animate transitions when navigating between pages.

Works with any theme by detecting <img> and background images dynamically.

Implementation Considerations

Theme Compatibility

Avoid hard-coded selectors; use generic CSS selectors.

Detect existing lazy-load scripts and avoid conflicts.

Add !important carefully only when necessary.

Plugin Settings Page

Toggle each module individually (Debloat, Flying Pages, Lazy Load, Flying Images).

Options for:

Excluding certain scripts/styles.

Controlling prefetch behavior.

Image optimization settings.

Performance

Minimal JS footprint (<50KB).

Use requestIdleCallback and IntersectionObserver where possible.

Load SPA scripts conditionally only on frontend pages.

Fallbacks

Disable SPA transitions if JS errors occur.

Standard lazy-load fallback if IntersectionObserver not supported.

Safe deregistration of scripts for debloating if theme relies on them.

Potential Libraries & Tools

JavaScript

Vanilla JS or Barba.js
 for SPA transitions.

IntersectionObserver for lazy loading.

PHP

WordPress hooks (wp_enqueue_scripts, wp_head, template_redirect).

Namespaced classes for modularity.

Optional

Integrate with existing caching or image optimization plugins for WebP support.