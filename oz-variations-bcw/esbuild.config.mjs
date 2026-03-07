/**
 * esbuild Configuration — OZ Variations BCW
 *
 * Builds both JS and CSS from src/ modules into assets/ bundles.
 *
 * JS entry points:
 * 1. product-page.js → assets/js/oz-product-page.js  (main product page bundle)
 * 2. cookie-banner.js → assets/js/oz-cookie-banner.js (standalone, no imports)
 *
 * CSS entry point:
 * 1. product-page.css → assets/css/oz-product-page.css (all @imports bundled)
 *
 * Output: IIFE for JS, single bundled file for CSS.
 * Runs in ~50ms. No node_modules shipped to server.
 *
 * Usage:
 *   npm run build       — one-shot build
 *   npm run watch       — rebuild on file changes
 */

import * as esbuild from 'esbuild';

const isWatch = process.argv.includes('--watch');

/** @type {import('esbuild').BuildOptions} */
const jsConfig = {
  // Two independent JS entry points → two separate bundles
  entryPoints: {
    'oz-product-page': 'src/js/product-page.js',
    'oz-cookie-banner': 'src/js/cookie-banner.js',
  },

  // Output to assets/js/ — PHP enqueues from here
  outdir: 'assets/js',

  // IIFE wraps everything in (function(){...})() — no global leaks
  format: 'iife',

  // Single file per entry — all imports inlined
  bundle: true,

  // Minify for production (comments stripped, names shortened)
  minify: false,

  // Source maps for debugging (separate .map file, not inline)
  sourcemap: true,

  // Target browsers: ES2017 covers 98%+ of traffic
  // Keeps async/await, template literals, arrow functions etc.
  target: ['es2017'],

  // Banner comment so devs know not to edit the output
  banner: {
    js: '/* OZ Variations BCW — Built by esbuild. Do not edit. Source: src/js/ */',
  },

  // Log build results
  logLevel: 'info',
};

/** @type {import('esbuild').BuildOptions} */
const cssConfig = {
  // CSS entry point — @imports get bundled into single file
  entryPoints: {
    'oz-product-page': 'src/css/product-page.css',
  },

  // Output to assets/css/ — PHP enqueues from here
  outdir: 'assets/css',

  // Bundle @import statements into single output
  bundle: true,

  // Source maps for debugging
  sourcemap: true,

  // Banner comment so devs know not to edit the output
  banner: {
    css: '/* OZ Variations BCW — Built by esbuild. Do not edit. Source: src/css/ */',
  },

  // Log build results
  logLevel: 'info',
};

if (isWatch) {
  // Watch mode — rebuilds on file changes (both JS and CSS)
  const [jsCtx, cssCtx] = await Promise.all([
    esbuild.context(jsConfig),
    esbuild.context(cssConfig),
  ]);
  await Promise.all([jsCtx.watch(), cssCtx.watch()]);
  console.log('Watching for changes...');
} else {
  // One-shot build (JS and CSS in parallel)
  await Promise.all([
    esbuild.build(jsConfig),
    esbuild.build(cssConfig),
  ]);
}
