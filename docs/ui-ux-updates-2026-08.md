# UI/UX Updates — August 2026

Running technical documentation for the post-Phase-2 UI/UX sprint. Each entry
follows the same structure: **What was done · Assumptions · Blockers / Settings /
Migrations · How to test.**

All work was done on the staging test build (`localhost/owamlive`). Nothing is
deployed to live without explicit approval.

---

## 1. Contact CTA Cards (N1)

### What was done
- Redesigned the vendor-profile contact section from plain links into data-driven,
  whole-row-clickable CTA cards (WhatsApp, Email, Instagram, Facebook, Website).
- **Component refactor** (`shortcode-vendor-profile.php`): each channel renders from
  a single `$channels` array — icon tile, label, subtitle, and an accent-coloured
  action button — so every row shares identical markup and tracking.
- **Styling** (`vendor-profile.css`): a single CSS custom property (`--ch`) per row
  drives the accent colour for the icon, detail and button.
- **Accessibility & UX:** descriptive `aria-label` on the outer link, `aria-hidden`
  on the decorative button, `:focus-visible` outline ring, responsive fallbacks.
- **Empty state:** "This vendor hasn't added contact details yet." shows when no
  channels exist.

**Follow-up revision — Clean & Minimal Compact:** the section was later rebuilt into
the final compact style. The CSS namespace was renamed `.oc-vp__cta*` → `.oc-vp__cchan*`
to fix a collision with the hero action container (`.oc-vp__cta`, which forced
`flex-direction: column` onto the buttons). Rows are now compact (~56px): icon tile +
label + subtitle on the left, a compact pill button on the right (Chat / Email / View /
Follow / Visit) with a chevron that nudges on hover. White cards with a `#E5E7EB`
hairline; the accent appears only on the icon tile and the pill. The panel is now white
with a hairline "Get in touch" header (was a cream gradient + gold rule). The detail
line was dropped for compactness — the full value stays in the link + `aria-label`.

### Assumptions
- Used the site's active brand burgundy `#6E0F2C` for Email/Website rather than the
  spec's `#800020` for visual consistency (revertible in one line if requested).
- External channel links keep `target="_blank"` + `rel="noopener noreferrer"`; Email
  (`mailto:`) links omit `target`.
- Short pill labels are acceptable because the full descriptive action is preserved in
  the `aria-label` (e.g. "Chat with {vendor} on WhatsApp").

### Blockers / Settings / Migrations
- **Blockers:** None.
- **Settings/Env:** Frontend PHP/CSS only (`shortcode-vendor-profile.php`,
  `vendor-profile.css`). No DB migrations or environment changes.

### How to test
1. Hard refresh (`Ctrl + Shift + R`) a vendor profile with 2+ populated channels.
2. Confirm compact CTA rows with accent only on the icon tile + pill, and a chevron
   that shifts on hover.
3. Inspect external links → confirm `data-oc-track`, `target="_blank"`,
   `rel="noopener noreferrer"` are present.
4. View a vendor with no contact info → confirm the empty-state fallback text.
5. Confirm a long email truncates cleanly instead of breaking the row.

---

## 2. Vendor Hero — Category Pill Hover Fix

### What was done
- Fixed category pills (`.oc-vp__pill`) showing burgundy text on a burgundy fill on
  hover (invisible label). The theme's global `a { color: burgundy }` was overriding
  the white hover text. Scoped the rule to `.oc-vp .oc-vp__pill` and forced
  `color: #fff !important` on hover.

### Assumptions
- `!important` is correct here — it matches the pattern the breadcrumb links already
  use to beat the theme's global link colour.

### Blockers / Settings / Migrations
- **Blockers:** None.
- **Settings/Env:** CSS only (`vendor-profile.css`). No DB/env changes.

### How to test
1. Hover a category pill in the vendor hero → the label turns white on burgundy
   (readable), not invisible.

---

## 3. Client Dashboard — "My event page" Refinement

### What was done
- **Empty state (no event):** replaced the passive "coming soon" placeholder with a
  CTA panel — icon medallion, prominent large **"+ Create Event"** button, and a
  "what happens next" hint.
- **Active state (event exists):** solid primary **"Manage event page"** (edit icon) +
  a new sleek outline secondary **"View / share ↗"**; added an eyebrow, clean serif
  title, and a **Live / Draft** status pill.
- **New reusable button variants:** `.oc-vd__btn--outline` (secondary) and
  `.oc-vd__btn--lg` (large). Icons are inline SVGs (no dashicon-font dependency).
  Removed inline `style="…"` in favour of proper classes/spacing.

### Assumptions
- Live vs Draft status is derived from the event post's `post_status`
  (`publish` = Live, otherwise Draft).

### Blockers / Settings / Migrations
- **Blockers:** None.
- **Settings/Env:** `shortcode-client-dashboard.php`, `vendor-dashboard.css`.
  No DB/env changes.

### How to test
1. As a client **without** an event → confirm the CTA panel + "+ Create Event" button.
2. As a client **with** an event → confirm primary/secondary button hierarchy +
   Live/Draft status pill.

---

## 4. Legal / Policy Pages — Premium Redesign

### What was done
- Added a **theme-side presentation layer** that transforms the legal content (Privacy
  Policy, Client Terms, Community Guidelines, and the legacy Terms/Privacy pages) into a
  premium layout **without changing the stored content** (the plugin still owns the HTML).
- **New files:** `inc/legal.php` (DOMDocument renderer — lifts the H1 + Effective Date
  into a hero, auto-generates the ToC from the H2s, wraps each section in a card, appends
  a contact callout), `assets/css/legal.css`, `assets/js/legal.js`.
  **Edited:** `page.php` (legal branch calls `oc_legal_render()`), `inc/assets.php`
  (conditional enqueue), `functions.php` (require).
- **Hero:** eyebrow, large Playfair title, one-line overview, "Effective" +
  "Last updated" metadata pills.
- **Layout & typography:** 1200px container, left-aligned banner (title lines up under
  the logo), 2-column grid (`280px + 1fr`, 20px gap), Playfair headings with an accent
  underline, custom bullet/numbered list styling, per-section cards, single uniform
  vertical spacing rhythm.
- **Sticky ToC:** `position: sticky; top: 20px` (52px when the WP admin bar shows). The
  card scrolls internally when long, with a pinned "On this page" header + reading-progress
  bar. Scroll-spy highlights and **auto-scrolls** to the active section; collapses to a
  dropdown on mobile; smooth anchor scrolling. Every ToC link verified to resolve to a
  real section.
- **Sticky fix:** the base theme's `body { overflow-x: hidden }` was breaking
  `position: sticky`; overrode it to `overflow-x: clip` (scoped to legal pages only) —
  still clips horizontal overflow but doesn't create a scroll container.

### Assumptions
- Legacy `/terms/` and `/privacy/` intentionally show **no** Effective Date (their content
  has no such line — dates are not fabricated). The three new documents display it.
- Per-slug overview text is a navigational descriptor (not policy text), kept factual/generic.

### Blockers / Settings / Migrations
- **Blockers:** None.
- **Settings/Env:** No database changes. Note: the three new legal pages remain **DRAFTS**
  pending client sign-off (unchanged by this work).

### How to test
1. Hard refresh `/privacy-policy/` (also `/community-guidelines/`,
   `/client-terms-and-conditions/`).
2. Confirm: left-aligned hero within a 1200px container; sticky ToC that stays pinned and
   **scrolls internally / auto-follows** as you read; no duplicate ToC numbers; even card
   spacing.
3. Shrink the viewport → the ToC collapses to a dropdown.
4. Click a ToC item → smooth-scrolls to that section.

---

## 5. Primary vs Secondary Locations (N2)

### What was done
- New single-value meta **`_oc_primary_location`** alongside the existing
  multi-value `_oc_location_areas` (secondary areas).
- **Helpers** (`helpers.php`): `oc_all_cities()`, `oc_sanitize_primary_location()`
  (validates against the known city list), and `oc_primary_location_options_html()`
  (shared `<optgroup>` markup for both forms).
- **Vendor Dashboard form** (`shortcode-vendor-dashboard.php` + `class-dashboard.php`):
  a "Primary location" `<select>` above the areas chips; saved (validated) into
  `_oc_primary_location` via the existing `$pairs` write.
- **Admin Add/Edit Vendor form** (`class-admin-add-vendor.php`): the same primary
  `<select>` + save.
- **Geo / radius search** (`class-geo.php`): `resolve_coords()` now centres the
  radius search on `_oc_primary_location` first, falling back to the first secondary
  area; `_oc_primary_location` was added to the `added/updated/deleted_post_meta`
  resync so changing it re-writes `_oc_lat` / `_oc_lng`.
- **Profile Quick Info** (`shortcode-vendor-profile.php`): displays
  **`Primary: [X] · Also covers: [Y, Z]`** (secondary = coverage minus the primary,
  `esc_html`-escaped); a single location shows just `Primary: [X]`. The hero meta lists
  the primary first, and the fact-row renderer was extended to allow a pre-escaped
  `value_html`.
- **Legacy display fallback:** when `_oc_primary_location` is empty, the FIRST covered
  place is derived as the primary and the rest become "Also covers". Coverage source is
  `_oc_location_areas` (cities); region-only vendors fall back to `_oc_location_regions`.
  The separate "Regions covered" row now shows only as a supplement when a vendor has
  both cities and regions.
- **Migration:** a versioned, option-gated auto-backfill
  (`OC_Geo::maybe_backfill_primary_location`, runs once on `admin_init`) sets
  `_oc_primary_location` = first `_oc_location_areas` for vendors without one; plus a
  standalone CLI/PHP script (`tools/backfill-primary-location.php`).

### Assumptions
- Primary must be one of the known cities (same list that populates the areas chips);
  an unknown value is treated as empty.
- Primary is stored independently of the areas list; the display de-dupes
  (secondary = areas excluding the primary).
- The backfill never overwrites a vendor-set primary and is idempotent.

### Blockers / Settings / Migrations
- **Blockers:** None.
- **Migration:** `_oc_primary_location` backfill — runs automatically once on the first
  wp-admin load after deploy, or manually via
  `wp eval-file wp-content/plugins/owambe-connect-core/tools/backfill-primary-location.php`.
  Verified on the clone: **432/432** vendors with areas backfilled (primary == first
  area, 0 mismatches).
- **Settings/Env:** No new settings. Radius search reuses the existing `_oc_lat` /
  `_oc_lng` coords + `maps_api_key`.

### How to test
1. **Migration:** run the script (or load wp-admin once) → every vendor with areas has
   a primary equal to its first area.
2. **Dashboard:** open the vendor dashboard → the "Primary location" select shows the
   vendor's primary pre-selected; change it + the areas, save, reload → persists.
3. **Admin:** Add/Edit Vendor → set a primary + areas, save → `_oc_primary_location` is
   stored.
4. **Profile:** open a vendor profile → Quick info shows
   "Primary: [X] · Also covers: [Y, Z]"; the hero lists the primary first.
5. **Radius:** set a vendor's primary to a known city, run a "near me" search centred on
   that city → the vendor appears; changing the primary re-centres it.
