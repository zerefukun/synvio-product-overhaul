/**
 * esbuild Configuration — OZ Variations BCW
 *
 * Two entry points:
 * 1. product-page.js → assets/js/oz-product-page.js  (main product page bundle)
 * 2. cookie-banner.js → assets/js/oz-cookie-banner.js (standalone, no imports)
 *
 * Output: IIFE format (no module loader needed), ES5-compatible.
 * Runs in ~50ms. No node_modules shipped to server.
 *
 * Usage:
 *   npm run build       — one-shot build
 *   npm run watch       — rebuild on file changes
 */

import * as esbuild from 'esbuild';

const isWatch = process.argv.includes('--watch');

/** @type {import('esbuild').BuildOptions} */
const config = {
  // Two independent entry points → two separate bundles
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

if (isWatch) {
  // Watch mode — rebuilds on file changes
  const ctx = await esbuild.context(config);
  await ctx.watch();
  console.log('Watching for changes...');
} else {
  // One-shot build
  await esbuild.build(config);
}
