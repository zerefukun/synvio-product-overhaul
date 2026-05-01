/**
 * Color Drawer
 *
 * Collapses the swatch grid to a 2-row tile and adds a "+X" chip that
 * opens a side drawer with all swatches plus a search filter. Inspired
 * by the Betonstunter pattern; styled in BCW look (teal accent, dashed
 * border, clean drawer slide-in).
 *
 * Why a separate module:
 *   - Single responsibility (one feature, one file).
 *   - Cache-safe: pure client-side, clones the server-rendered swatch
 *     <a> tags so href navigation keeps working as-is. No new server
 *     state, no per-user payload baked into HTML, no Mar-19 nonce risk.
 *   - Lazy DOM: the drawer panel is only created when there are enough
 *     swatches to need one.
 *
 * @package OZ_Variations_BCW
 * @since 2.2.0
 */


/**
 * Read the actual column count from the grid's computed style.
 * Auto-fill grid means CSS already decided how many columns fit, so we
 * just trust it. Fallback to 5 if the count can't be parsed (very old
 * browsers, rare).
 */
function getColumnCount(list) {
  var cs = window.getComputedStyle(list);
  var cols = (cs.gridTemplateColumns || '').split(' ').filter(Boolean).length;
  return cols > 0 ? cols : 5;
}


/**
 * Wire up the chip + drawer for the given swatches list.
 *
 * Scope: Variant C only. Variants A and B keep the original full
 * swatch grid (no collapse, no chip, no drawer) so the A/B/C test
 * compares one isolated UX change at a time.
 *
 * Idempotent: a `data-drawer-wired` flag on the list prevents double-wiring.
 */
export function setupColorDrawer() {
  if (!document.documentElement.classList.contains('oz-ab-tools-c')) return;
  var list = document.querySelector('.oz-color-swatches');
  if (!list) return;
  if (list.dataset.drawerWired === '1') return;

  var swatches = Array.prototype.slice.call(list.querySelectorAll('.oz-color-swatch'));
  if (swatches.length < 6) return; // Too few to bother with a drawer

  var cols = getColumnCount(list);
  // Show first (cols * 2 - 1) so the chip occupies the last cell of row 2.
  // If everything already fits in 2 rows with no overflow, skip.
  if (swatches.length <= cols * 2) return;

  var visibleCount = Math.max(cols * 2 - 1, 5);

  // Hide overflow swatches in the inline grid. They still exist in the
  // DOM so we can clone them into the drawer.
  swatches.forEach(function (sw, i) {
    if (i >= visibleCount) sw.classList.add('oz-swatch-collapsed');
  });

  var overflow = swatches.length - visibleCount;

  // Build the chip cell. It looks like a swatch tile but is a button.
  var chip = document.createElement('button');
  chip.type = 'button';
  chip.className = 'oz-color-swatch oz-color-more-chip';
  chip.setAttribute('aria-label', 'Bekijk alle ' + swatches.length + ' kleuren');
  chip.innerHTML =
    '<span class="oz-swatch-img oz-more-chip-img">' +
      '<span class="oz-more-chip-count">+' + overflow + '</span>' +
    '</span>' +
    '<span class="oz-swatch-name">Bekijk alle</span>';
  list.appendChild(chip);

  var drawer = buildDrawer(swatches);
  document.body.appendChild(drawer.root);

  chip.addEventListener('click', function (e) {
    // The chip wears the .oz-color-swatch class so it sits in the grid
    // cleanly, but the global delegate treats every .oz-color-swatch as
    // a navigation target and would redirect to "/undefined". Stop the
    // event before it reaches the document-level handler.
    e.preventDefault();
    e.stopPropagation();
    openDrawer(drawer);
  });
  drawer.closeBtn.addEventListener('click', function () { closeDrawer(drawer); });
  drawer.backdrop.addEventListener('click', function () { closeDrawer(drawer); });

  // RAL/NCS CTA inside the drawer. We don't duplicate the input here
  // (single source of truth for validation lives in product-page.js).
  // Instead we close the drawer, flip the inline mode toggle to ral_ncs,
  // and focus the existing input so the user lands on it directly.
  if (drawer.ralBtn) {
    drawer.ralBtn.addEventListener('click', function () {
      closeDrawer(drawer);
      var inlineRalBtn = document.querySelector('.oz-color-mode-btn[data-mode="ral_ncs"]');
      if (inlineRalBtn) inlineRalBtn.click();
      // Focus the input after the inline UI re-renders. setTimeout 0
      // gives syncUI a tick to add the visible class.
      setTimeout(function () {
        var input = document.getElementById('customColorInput');
        if (input) {
          input.focus();
          input.scrollIntoView({ behavior: 'smooth', block: 'center' });
        }
      }, 50);
    });
  }

  document.addEventListener('keydown', function (e) {
    if (e.key === 'Escape' && drawer.root.classList.contains('open')) {
      closeDrawer(drawer);
    }
  });

  // Live filter as the user types. We do a simple substring match on
  // the swatch name text; case-insensitive.
  drawer.search.addEventListener('input', function () {
    var term = drawer.search.value.trim().toLowerCase();
    var anyVisible = false;
    drawer.items.forEach(function (item) {
      var name = item.dataset.colorName || '';
      var match = !term || name.indexOf(term) !== -1;
      item.style.display = match ? '' : 'none';
      if (match) anyVisible = true;
    });
    drawer.empty.style.display = anyVisible ? 'none' : 'block';
  });

  list.dataset.drawerWired = '1';
}


/**
 * Build the drawer DOM. Clones each existing swatch link so href + image
 * stay intact, meaning the existing navigation handler in product-page.js
 * still works for clicks inside the drawer.
 */
function buildDrawer(swatches) {
  var root = document.createElement('div');
  root.className = 'oz-color-drawer';
  root.setAttribute('role', 'dialog');
  root.setAttribute('aria-modal', 'true');
  root.setAttribute('aria-label', 'Alle kleuren');

  var backdrop = document.createElement('div');
  backdrop.className = 'oz-color-drawer-backdrop';

  var panel = document.createElement('div');
  panel.className = 'oz-color-drawer-panel';

  var header = document.createElement('div');
  header.className = 'oz-color-drawer-header';

  var title = document.createElement('h2');
  title.className = 'oz-color-drawer-title';
  title.textContent = 'Alle kleuren (' + swatches.length + ')';

  var closeBtn = document.createElement('button');
  closeBtn.type = 'button';
  closeBtn.className = 'oz-color-drawer-close';
  closeBtn.setAttribute('aria-label', 'Sluiten');
  closeBtn.innerHTML =
    '<svg width="22" height="22" viewBox="0 0 24 24" fill="none" stroke="currentColor" ' +
    'stroke-width="2" stroke-linecap="round"><line x1="18" y1="6" x2="6" y2="18"/>' +
    '<line x1="6" y1="6" x2="18" y2="18"/></svg>';

  var search = document.createElement('input');
  search.type = 'search';
  search.className = 'oz-color-drawer-search';
  search.placeholder = 'Zoek kleur (cement, blue, nude...)';
  search.setAttribute('aria-label', 'Zoek kleur');

  var grid = document.createElement('div');
  grid.className = 'oz-color-drawer-grid';

  var empty = document.createElement('div');
  empty.className = 'oz-color-drawer-empty';
  empty.textContent = 'Geen kleur gevonden.';
  empty.style.display = 'none';

  // Clone each swatch into the drawer. Use deep clone to keep the
  // <img> + name. Drop the .selected class on clones to avoid two
  // selected indicators across the inline grid + drawer (selection only
  // makes sense in the inline grid where the user picked it).
  var items = swatches.map(function (sw) {
    var clone = sw.cloneNode(true);
    clone.classList.remove('oz-swatch-collapsed');
    var nameEl = clone.querySelector('.oz-swatch-name');
    var name = nameEl ? nameEl.textContent.trim() : '';
    clone.dataset.colorName = name.toLowerCase();
    grid.appendChild(clone);
    return clone;
  });

  // Build RAL/NCS CTA only when the page actually has the RAL/NCS mode
  // toggle (some products are swatch-only). We detect by looking for the
  // inline mode button and skip the section otherwise.
  var ralBtn = null;
  var ralSection = null;
  if (document.querySelector('.oz-color-mode-btn[data-mode="ral_ncs"]')) {
    ralSection = document.createElement('div');
    ralSection.className = 'oz-color-drawer-ral';
    ralSection.innerHTML =
      '<div class="oz-color-drawer-ral-text">' +
        '<strong>Eigen kleur op maat</strong>' +
        '<span>Voer een RAL of NCS code in. Wij mengen elke kleurcode op maat.</span>' +
      '</div>';

    ralBtn = document.createElement('button');
    ralBtn.type = 'button';
    ralBtn.className = 'oz-color-drawer-ral-btn';
    ralBtn.textContent = 'RAL / NCS code invoeren';
    ralSection.appendChild(ralBtn);
  }

  header.appendChild(title);
  header.appendChild(closeBtn);
  panel.appendChild(header);
  panel.appendChild(search);
  panel.appendChild(grid);
  panel.appendChild(empty);
  if (ralSection) panel.appendChild(ralSection);
  root.appendChild(backdrop);
  root.appendChild(panel);

  return {
    root: root,
    panel: panel,
    closeBtn: closeBtn,
    search: search,
    grid: grid,
    items: items,
    empty: empty,
    backdrop: backdrop,
    ralBtn: ralBtn,
  };
}


function openDrawer(d) {
  d.root.classList.add('open');
  document.body.classList.add('oz-color-drawer-locked');
  // Slight delay so the focus ring doesn't flash before the slide-in.
  setTimeout(function () { d.search.focus(); }, 80);
}


function closeDrawer(d) {
  d.root.classList.remove('open');
  document.body.classList.remove('oz-color-drawer-locked');
}
