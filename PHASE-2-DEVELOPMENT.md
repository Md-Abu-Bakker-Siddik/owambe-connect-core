# Owambe Connect — Phase 2 Development Plan

**Audience:** Instaquirk dev team · **Builds on:** `owambe-connect-core` v1.1.1 (Phase 1 / MVP) + `owambe-connect` theme
**Prepared:** July 2026 · Internal working document (not the client-facing proposal)
Every code reference below was verified against the v1.1.1 source (line numbers may drift a few lines as the build progresses).

> **Status legend:** ✅ Confirmed · 🆕 New (this round) · ❓ Needs client input · ⏸ Deferred / future · 🔎 Under review

> **⚙️ BUILD STATUS — Weeks 1 & 2 implemented (plugin v1.2.0), on staging, pending client acceptance.**
> Client accounts + Google sign-in, analytics & enquiry tracking, WhatsApp prefill, reviews (client-submitted, admin-moderated), vendor mini-site (`/vendor/{slug}/` URL flip), business card PDF+PNG+QR, client dashboard (saved + recently contacted), and the Stripe scaffolding are **built, self-tested, and adversarially reviewed**. Subcategory fix + planning checklists (also W1 in the plan) are **not yet done** — deferred into the W5 "independents" batch as scheduled in §3, so they don't block the W1/W2 acceptance pass. See the **Build Log** at the end of this doc for exactly what shipped, the 20 review findings fixed, and the acceptance-test checklist to run on staging.

---

## 1. Context & stack

- WordPress plugin (`OC_Plugin::boot()`), extended for Phase 2. Frontend assembled from shortcodes + Elementor widgets; non-admin roles never touch `/wp-admin`.
- Existing model to reuse/extend: `oc_vendor` (CPT), **`oc_vendor_category`** (hierarchical taxonomy — note: the real name, constant `OC_TAX`; not "oc_category"), `oc_vendor` role (`oc_edit_own_vendor`), vendor post-meta, transactional emails (shared header/footer + SMTP), Mailchimp sync, GA4 tag, reCAPTCHA v3, admin analytics (Chart.js), enquiry log, activity log, security hardening.
- **New surfaces this phase:** client role/accounts, event CPT, registry layer, reviews layer, Stripe billing, featured-listing layer (two types, expiry), tier system, homepage rebuild.
- **Out of Phase 2 scope (client items excluded):** availability calendar, sponsored advertising, category image rotation — see §12.

## 2. Environment, delivery & launch approach

- **Stripe:** ✅ client created the account and invited `fahadhossain5269@gmail.com` as developer; KYC done on the live account, no separate sandbox needed. Development still runs against the account's **test mode** keys; **live keys** are entered at go-live with the billing master switch OFF (no accidental live charges).
- **Staging-first:** every feature is built and tested on staging.
- ✅ **Everything goes live together** after full testing (per Sakib) — the live site is not disrupted mid-build. A few priority items (analytics first) are demoed on staging early so dependent client decisions (pricing/tiers from lead numbers) can be made.
- ~5 weeks build → go-live → **14 days post-launch support** → maintenance agreement.
- Corner cases (§14) resolved with the client as they surface (agreed).
- **Deploy reality:** Hostinger, no git — manual deploy of plugin + theme together (frontend CSS lives in the theme). Full file + DB backup before each deploy. Local copy is currently ahead of live — reconcile before the first staging push.

## 3. Build order (per client reorder)

| Week | Sprint | Scope |
|---|--------|-------|
| 1 | Foundations & analytics | Client accounts + Google sign-in · Stripe integration setup · **Analytics & enquiry tracking (client priority — build first)** · shared plumbing |
| 2 | Vendor experience *(elevated)* | Reviews (admin-approved) · Vendor mini-site + business card + QR + share · Client dashboard (saved list, recently contacted) |
| 3 | Event page & registry | Event page (all sections, unlisted) · Gift registry Part A + Part B · Admin approved-retailers |
| 4 | Monetisation & homepage | Subscriptions + tiers + admin controls · Vendor verification (Stripe, £15) · Featured listings (homepage + category) · Homepage rebuild |
| 5 | Independents & launch | Geolocation search · Blog · Planning-resource PDFs · Subcategory fix · Final testing + go-live |
| — | After Phase 2 | Referral programme (once model finalised) |
| ⏸ | Out of scope / deferred | Availability calendar · Sponsored advertising · Category image rotation (🔎) |

Order dependencies that make this sequence work: Stripe scaffolding (W1) is needed by W4; the client role (W1) is needed by reviews/dashboard (W2) and events (W3); the PDF upload validator (W3) is reused by verification doc upload (W4). All satisfied in this order.

---

## 4. Codebase ground rules (verified v1.1.1 — follow for every feature)

### 4.1 Constants & data model (helpers.php:11-17)
`OC_CPT`=`oc_vendor` · `OC_TAX`=`oc_vendor_category` · `OC_ROLE`=`oc_vendor` · `OC_CAP_EDIT_OWN`=`oc_edit_own_vendor` · `OC_STATUS_PENDING`=`oc_pending` · `OC_STATUS_REJECTED`=`oc_rejected` · **`OC_STATUS_APPROVED` = core `publish`** (never invent a separate approved status). Vendor ownership = `post_author`; the ownership primitive is `current_user_can( OC_CAP_EDIT_OWN, $post_id )` — vendors do **not** have `edit_posts`, so `current_user_can('edit_post', …)` is always false for them. `oc_get_current_vendor_post()` (helpers.php:677) assumes one vendor post per user.

### 4.2 Booting a new class
`require_once` in `owambe-connect-core.php` → `( new OC_X() )->register()` in `OC_Plugin::boot()` (class-plugin.php:29-39; admin-only classes inside the `is_admin()` block :58-76). Anything needing roles/CPTs at activation: also call its static `register_*` from `OC_Activator::activate()` **before** `flush_rewrite_rules()`. Add every new CPT/role/option/page/table to **`uninstall.php`** by hand (it doesn't load helpers; wipe gated by `oc_uninstall_keep_data`, default keep).

### 4.3 New frontend surface = the "triple"
Shortcode in `OC_Shortcodes::register()` → `oc_get_template('shortcode-<name>.php', $atts)` (all template vars null-coalesced; theme-overridable) → Elementor widget whose `get_name()` **equals the shortcode tag**, registered in `OC_Elementor::register_widgets()` → page entry in `OC_Page_Seeder::page_specs()`. Pages with their own H1 go on the `oc_suppress_page_title_slugs` filter list (class-shortcodes.php:78-88) or you get double H1s. Critical pages get a self-heal clone of `maybe_self_heal_auth_pages()` (class-plugin.php:104-129). **Gotchas:** re-running Import Demo overwrites `_elementor_data` for every `page_specs()` slug; menu seeding only runs on an empty menu — since everything launches together, menu items are wired once at go-live.

### 4.4 Public forms — the Phase 2 standard
Prescriptive rule for every **new** public form (Phase 1 applies it fully only on the contact form + FAB; registration lacks the honeypot, login lacks reCAPTCHA — don't copy those gaps): POST to `admin-post.php` + `wp_nonce_field(ACTION, 'oc_*_nonce')` + honeypot `oc_hp` + `oc_recaptcha_field('<context>')` + a throttle bucket via the `oc_security_limits` filter (class-security.php:23, priority-1 hook clone of :68-72). Redirect back with `?oc_notice/?oc_error` **and round-trip typed values as query args** — validation must never wipe user input (standing client requirement). reCAPTCHA **fails open** when unconfigured (helpers.php:541) — never the sole guard.
**Toast dependency:** the global toast renderer lives inside the FAB template (shortcode-vendor-request-fab.php:161-214); pages that suppress the FAB (event pages) lose it — extract to `oc-frontend.js` in W1.

### 4.5 Settings
One array option `oc_settings` with a strict whitelist sanitizer — every new key needs `defaults()` + `sanitize()` + a `render()` section (class-settings.php:15/:87/:126+). `get()` treats `''` as unset — toggles are `0/1` ints. Secrets = password inputs with `autocomplete="new-password"`. One-off action buttons go **after** `submit_button()`.

### 4.6 Admin actions
Convention: row/bulk actions = `admin_post_*` with per-item nonces (`oc_toggle_verified_{ID}` pattern, class-admin.php:140-150); long-running batch jobs = logged-in `wp_ajax_*` run-queues (import/email pattern). No `wp_ajax_nopriv_*` exists yet — W1's tracking endpoint is the first (new attack surface: throttle it, and nonces go stale on cached pages, so beacons are nonce-less + rate-limited). **Two parallel list UIs must both get any new row/bulk action** (native `edit.php` + custom `oc-vendors` page), and notice maps are duplicated in `OC_Admin::admin_notices()` and `OC_Admin_Vendors_List::render_notices()`. New admin slugs need an icon rule in `OC_Admin::submenu_icons_css()`. Capability split: vendor management `edit_posts`; keys/settings `manage_options`.

### 4.7 Email
Static method on `OC_Mail` + template in `templates/emails/` using the shared header/footer pair (header opens a `<td>` the footer closes). `send()` = no CC; `send_admin()` = auto-CC shared inbox. **Subjects ASCII-only, no `[brackets]`, no em-dashes** (class-mail.php:289-294 — silently dropped otherwise). Cron-driven sends must capture + log the `wp_mail()` bool. Email templates are not theme-overridable.

### 4.8 Storage
The existing logs (`oc_recent_activity`, `oc_enquiry_log`, `_oc_activity_log`) are capped read-modify-write arrays — **never** use for counters, RSVPs, or anything unbounded/billing-relevant. Phase 2 adds the first custom tables (§5.3). Sensitive meta (payment details) registers `show_in_rest => false`.

### 4.9 Theme facts
No `single.php`/`home.php`/`archive.php` exist (posts + new CPT singles fall to `index.php` = title + excerpt only). `page.php` has a hardcoded full-width slug whitelist (:21-25). `search.php` redirects **all** searches to `/vendors/?s=`. `header.php` login button is role-blind (:66-86) and only renders when neither Elementor Pro nor HFE header is active (:26-33) — **check which header live uses first**. `security.php:17` hard-disables browser geolocation. Frontend CSS lives in the theme, enqueued unconditionally by design, versioned with `oc_theme_asset_version()` (filemtime). Google Fonts come from CDN — bundle font files for server-side rendering. Brand: burgundy `#6E0F2C`, gold `#C9A961` (respect Customizer overrides).

### 4.10 Vendor URL reality (matters for W2)
The CPT already rewrites to `/vendor/{slug}/` (class-cpt.php:55), but Phase 1 reverses it: `route_vendor_permalink` (class-shortcodes.php:52,132) rewrites `get_permalink()` to `/vendor-profile/?v={slug}` and `redirect_legacy_cpt_url` (:53,140) 301s direct CPT hits. Cards, share menu, JSON-LD and the approval email all call `get_permalink()`, so the W2 flip is a two-hook change. When the hooks are removed, WP's template hierarchy serves the CPT via the **theme's** `single-oc_vendor.php` (get_header + `[oc_vendor_profile]` — full menu retained automatically). The plugin's `templates/single-oc_vendor.php` is orphaned (nothing registers it; its docblock cites a method that doesn't exist) — use the theme template, or add `template_include` wiring deliberately.

---

## 5. New data model

### 5.1 Role
**`oc_client`** — `read` only. Registered idempotently on `init` (clone `register_role()`, class-cpt.php:108-125) + from the activator. Roles stack: a client who later applies as a vendor gains `oc_vendor` (dashboard create-on-save already does `add_role`). **Clients are Google-sign-in only** in Phase 2 — no email/password client registration (no password-change UI for them; account card shows "Signed in with Google").

### 5.2 CPTs
- **`oc_event`** — unlisted event pages. `publicly_queryable => true`, `exclude_from_search => true`, `has_archive => false`, `show_in_rest => false`, rewrite `['slug' => 'event', 'with_front' => false]`, supports `title, author`. Unlisted = unguessable slug (random token suffix baked into `post_name` at creation — the slug *is* the token) + `noindex` via `wp_robots`. **Don't copy the vendor `pre_get_posts` publish-gate** — unlisted pages must resolve by direct URL. Optional personalised URL (client's paid extra) = admin sets a custom `post_name` on request.
- **`oc_review`** — `public => false`, `show_ui => false`, supports `title, editor, author`. Status machine clones the vendor one (pending + `publish` = approved; `transition_post_status` handler firing `oc_review_status_changed`, mirror of class-cpt.php:152-190). Vendor link `_oc_review_vendor_id`; rating `_oc_review_rating` (1-5).

### 5.3 Custom tables (dbDelta in `OC_Activator`; drop in `uninstall.php` wipe path)
- **`{prefix}oc_vendor_stats`** — `(vendor_id, stat_date, metric, count, PRIMARY KEY(vendor_id, stat_date, metric))`, written with `INSERT … ON DUPLICATE KEY UPDATE count = count + 1`. Metrics: `view`, `click_whatsapp`, `click_email`, `click_instagram`, `click_facebook`, `click_website`.
- **`{prefix}oc_rsvps`** — `(id PK, event_id, guest_name, guest_email NULL, attending, guest_count, note, created)`.

### 5.4 Post meta
On `oc_vendor` (add to `oc_vendor_fields()` so meta box/guide/registration stay schema-driven): `_oc_featured_until`, `_oc_featured_type` (`homepage|category|both`), `_oc_featured_request`, `_oc_featured_credit_used` (+ year anchor, for the Premium 2/year allowance), `_oc_sub_plan` (`professional|elite|premium` — names in use, definitions pending), `_oc_sub_status`, `_oc_free_until`, `_oc_sub_stripe_customer`, `_oc_sub_stripe_sub`, `_oc_verification_request` (+ doc attachment id + paid flag), `_oc_lat`/`_oc_lng`, `_oc_rating_avg`/`_oc_rating_count` (cached, recomputed on review transition — `_oc_completion_pct` pattern).
On `oc_event`: one `oc_event_fields()` schema map mirroring `oc_vendor_fields()` (helpers.php:45-74) — cover/date/location, welcome, about, event info, dietary, `_oc_event_gallery_ids`, YouTube URL, schedule + invitation attachment ids, wedding party (repeatable), how-we-met, vendor showcase IDs, registry config (parts A+B, items, funds, **client payment details**, **delivery address** — both `show_in_rest => false`, address rendered only on buy-&-ship items, progress toggle + manual value).
User meta (clients): `_oc_google_sub`, `_oc_saved_vendors`, `_oc_recent_contacts` (capped array `{vendor_id, channel, ts}`).

### 5.5 Settings keys (`oc_settings` unless noted)
`google_client_id`/`google_client_secret`, `vendor_analytics_enabled` (vendor-facing analytics visibility — off until leads are meaningful), `whatsapp_prefill_template`, `billing_enabled` (master), tier definitions + prices (pending client), `featured_price_day/week/month`, `recently_added_slots` (homepage carousel cap, admin-adjustable), `verification_fee_enabled` (+ £15 amount), `stripe_pk/stripe_sk/stripe_webhook_secret`, `registry_part_a_enabled`/`registry_part_b_enabled`. Separate option **`oc_registry_shops`** (rows: name, domain, affiliate param + code, visible) — repeatable rows get their own small admin UI. Existing `maps_api_key` (already labelled Phase 2, class-settings.php:291-302) serves W5.

### 5.6 Pages (seeder + self-heal + theme `page.php` whitelist + H1-suppression list + uninstall)
`client-login`, `client-dashboard`, `checklists`, `my-event`, blog page (via `page_for_posts`, **not** `page_specs()` — demo re-import would clobber it).

---

## 6. Week 1 — Foundations & analytics

### 6.1 Shared plumbing (do first)
- Toast renderer out of the FAB template into `oc-frontend.js` (§4.4).
- `oc_vendor_stats` + `oc_rsvps` tables via dbDelta; uninstall entries.
- Stripe scaffolding: vendored SDK, `includes/class-stripe.php`, settings keys (test-mode keys of the live account for now), webhook REST route `oc/v1/stripe-webhook` with signature verification, "test connection" button after `submit_button()`, Security Health check row for the webhook.

### 6.2 Client accounts + Google sign-in ✅
**New:** `includes/class-client.php`, `includes/class-google-auth.php`, `templates/shortcode-client-login.php` + widget, seeded `client-login` page (+ self-heal + `page.php` whitelist + H1 list), `client-welcome.php` email (clone `application-received.php`, trimmed; `send()`, no CC).
- Callback = `admin_post_(nopriv_)oc_google_auth` — **never `wp-login.php`** (hijacked by `redirect_wp_login_to_branded`, class-dashboard.php:993-1044). Verify the ID token server-side (remote-verify shape of `oc_verify_recaptcha`, helpers.php:539-560), check `aud` + Google's `g_csrf_token` double-cookie. `email_exists()` → **link** (store `_oc_google_sub`); new users: `wp_insert_user` with `user_login = email` (site convention), random password, role `oc_client`, then `wp_set_current_user()` + `wp_set_auth_cookie($uid, true)`. Throttle bucket on the endpoint.
- **Google also serves vendors** (client decision): same button on `vendor-login`, linking by email to the existing vendor account. Social (FB) deferred.
- **Role-routing edits (all mandatory):** `block_wp_admin_for_vendors` extends to `oc_client` (class-dashboard.php:73-91 — keep the load-bearing admin-post/admin-ajax exemption); `redirect_vendor_after_login` (:93-98) + logout redirect (:1051-1056) become role routers; `redirect_logged_in_from_auth_pages` (class-shortcodes.php:92) role-aware; navbar branch (shortcode-navbar.php:66-102); theme `header.php:66-86` branch (confirm live header source first); Security Health :117 accepts the client role; `uninstall.php`; `OC_DATA` gets `client_login_url`.
- **Guard:** clients gated off the vendor-dashboard page — `run_save()` create-on-save (:207-265) silently promotes any logged-in user to `oc_vendor`.

### 6.3 Analytics & enquiry tracking ✅ *(client priority)*
**New:** `includes/class-tracking.php` — the plugin's first `wp_ajax_nopriv_*` endpoint (`oc_track`) + a Security Health check row for it.
- **Views:** increment in `OC_Shortcodes::vendor_profile()` (single funnel, before the template return at class-shortcodes.php:370); self-exclusion via **`current_user_can( OC_CAP_EDIT_OWN, $id )`** (vendors lack `edit_post` — that check would only exclude admins); per-IP transient dedupe.
- **Clicks:** `data-oc-track` + `data-vendor="{id}"` on the five profile anchors (shortcode-vendor-profile.php:203, :392, :403, :414, :425, :436); delegated listener in `oc-frontend.js` firing `navigator.sendBeacon(OC_DATA.ajax_url, …)`; nonce-less but throttled (cached pages, §4.6). *(The contact-page WhatsApp link is the platform's own number — no vendor in scope, not tracked per-vendor.)*
- **Per-vendor enquiry attribution comes from the click beacon** — the existing contact form + FAB are platform-level (no vendor context); their `OC_Enquiry_Log` entries stay as-is.
- **Admin:** "Profile views"/"Contact clicks" KPI cards + time-series in `OC_Admin_Analytics` (kpi_card :386, chart bridge :269-341); stat queries in a static helper class so the frontend reuses them.
- **Vendor-facing tab:** new `analytics` tab in the dashboard `$tabs` (shortcode-vendor-dashboard.php:118-125) + panel **outside the master form** (like Account :883); gated by `vendor_analytics_enabled` — hidden until the admin enables it. Chart.js bundled locally (no CDN on the public site).
- **WhatsApp prefill:** optional `$text` arg on `oc_whatsapp_link()` (helpers.php:638-641) + `?text=` encode; update the profile call site (shortcode-vendor-profile.php:58 — feeds hero + contact card). Message template in settings: *"Hi {business}, I found your profile on Owambe Connect and would like to enquire about your services for an upcoming event."* Numbers already +44-canonical. **Documented limit (surface in vendor UI):** only the click is measurable; the WhatsApp conversation is off-site.

### Week 1 — done when
- [ ] Google sign-in creates/links a client; client lands on client dashboard; vendor Google sign-in links to vendor account
- [ ] Clients blocked from `/wp-admin` and from the vendor dashboard form
- [ ] Views + per-channel clicks accumulate in `oc_vendor_stats` (own-profile views excluded); admin charts render
- [ ] Vendor analytics tab invisible until `vendor_analytics_enabled` is on
- [ ] WhatsApp buttons open with the prefilled message
- [ ] Stripe test connection green; webhook signature-verified
- [ ] Phase 1 regression: vendor registration/login/save, contact form, FAB all unchanged

---

## 7. Week 2 — Vendor experience

### 7.1 Reviews ✅ (signed-in clients, admin-approved)
**New:** `includes/class-reviews.php`, `includes/class-admin-reviews.php`, `review-approved.php` email. *(No review-request email — "request a review" is a share link; an email path can be added later if asked.)*
- Submission form on the profile (clients only; logged-out see a sign-in prompt with `redirect_to`): full §4.4 stack + role check; **star + text** (build both; display toggle covers the ❓ text-only option); one review per client per vendor; approved-vendor targets only.
- Moderation: pending-count menu badge (clone `add_pending_badge`, class-admin.php:89-99); approve/reject admin-post handlers with per-ID nonces (:140-187 pattern); on approval recompute `_oc_rating_avg`/`_oc_rating_count` + email vendor (ASCII subject); notices in **both** maps; log `review_*` via `OC_Vendor_Activity::record()` (labels/colors class-vendor-activity.php:550-572 — free activity-log UI).
- Display: Reviews section after Services (~shortcode-vendor-profile.php:298) + hero aggregate stars (:155-191); `aggregateRating` in the profile JSON-LD (:90-104) and theme schema (inc/seo.php:61-72); rating line on `vendor-card.php`; only `publish` reviews render.
- Vendor dashboard: read-only `reviews` tab + "request a review" share link (copy/WA/mailto → `get_permalink() . '#reviews'`) in the menu-foot (:309-320).

### 7.2 Vendor mini-site ✅ (`/vendor/<slug>`)
- **URL flip:** remove `route_vendor_permalink` + `redirect_legacy_cpt_url` (class-shortcodes.php:52-53); the **theme's** `single-oc_vendor.php` then serves the CPT (full Owambe menu retained via `get_header()` — menu style ❓ A vs C: A works out of the box; C = a slim-header variant of that template, decide before W2 ends). Add the reverse 301 (`/vendor-profile/?v=` → `/vendor/{slug}/`) + one-time versioned rewrite flush.
- **Section nav:** `id` anchors on the profile sections (About :250 → How to book :359) + sticky pill nav after the hero (:244); `scroll-margin-top` ~80px (sticky header z-100). **Don't class it `.oc-nav`** — theme CSS hides that class on mobile (`style.css:92` `display:none`, desktop-only at :137) and it inherits header nav styles.
- Share-my-business: reuse the existing share menu markup/JS (:213-240, :612-690) on the dashboard; per-vendor OG tags already exist (inc/seo.php:15-46) so previews are correct for free.
- Gallery ~10 on paid plans: make the uploader slot cap plan-derived (`oc_vendor_gallery_cap()` helper) — enforced when tiers land.

### 7.3 Business card ✅ (PDF **and** PNG, QR)
`includes/class-business-card.php` — `admin_post_oc_business_card` GET handler (`?format=pdf|png`), ownership via `oc_get_current_vendor_post()`, streams the file. Data all exists (business name, logo, category, location, `oc_get_vendor_number()`, WhatsApp, email); **QR target = `get_permalink()`** (follows the URL flip automatically). Local rendering (dompdf/FPDF + GD + pure-PHP QR — no external API); **bundle Inter/Playfair font files**. Fields ❓ — render from a filterable field list so the client's answer is config, not code.

### 7.4 Client dashboard ✅ (saved + recently contacted)
Triple + seeded `client-dashboard` page (+ self-heal + whitelist + H1 list). Clone the vendor dashboard shell (tab JS + toast are role-agnostic, template:1016-1133); logged-out gate clone of `redirect_logged_out_from_dashboard` (class-shortcodes.php:113-129 — `redirect_to` round-trips free).
- Saved vendors: heart button on `vendor-card.php` + profile hero — **card gotcha:** the CTA stretched-link `::after` covers the card (:71-74); the button needs `position:relative` + higher z-index. Storage `_oc_saved_vendors`; render via `oc_get_template('partials/vendor-card.php', …)` grid.
- Recently contacted: from `_oc_recent_contacts` (written by the W1 beacon for logged-in clients).
- Account card: "Signed in with Google" (no password UI — clients are Google-only, §5.1). Placeholder tile for "My event page" (W3).

### Week 2 — done when
- [ ] Review lifecycle: submit → pending badge → approve → vendor email → stars on profile/card/schema; duplicates blocked; reject works
- [ ] `/vendor/<slug>/` live with full menu; old `?v=` URLs 301; section nav works on mobile
- [ ] Business card downloads in both formats; QR scans to the live profile URL
- [ ] Save/unsave from card + profile; both dashboard lists populate
- [ ] Regression: directory links, share menu, JSON-LD validity, vendor dashboard save

---

## 8. Week 3 — Event page & registry

### 8.1 Event page ✅ (unlisted)
**New:** `includes/class-events.php`, `[oc_event_editor]` triple + seeded `my-event` page (+ self-heal + whitelist + H1 list + uninstall), theme `single-oc_event.php` (3-line delegate, clone of the vendor one), `rsvp-received.php` email.
- CPT per §5.2; slug token at creation; `noindex`; `oc_event` OG branch in theme `inc/seo.php` (generic branch emits `og:url=home_url` — wrong for events) so WhatsApp invites preview the cover; suppress the FAB via `oc_show_vendor_request_fab` (class-shortcodes.php:282) — safe post-W1 toast extraction.
- All sections modular/optional (§5.4 list, incl. the 🆕 additions: schedule/programme + image upload, invitation card, wedding party, how we met). Editor = client-dashboard-style tabbed form; one event per client to start (`oc_get_current_event_post()` mirroring the vendor helper).
- Uploads: **clone** `ajax_gallery_upload` (class-dashboard.php:779-861) with event resolution + `post_author` check; keep the tamper-guard commit (attachments committed only when `post_parent` matches, run_save:440-498); reuse the single/multi uploader JS (template:1695-1953). **New PDF validator** (parallel to images-only `oc_image_mimes_only`) + non-image preview for programme/invitation.
- Vendor showcase: approved-vendor picker → ID array → compact similar-card markup (shortcode-vendor-profile.php:579-599), links via `get_permalink()` (free vendor advertising).
- **RSVP:** public form, full §4.4 stack (own throttle bucket; typed values preserved on error). Fields: attending yes/no, name, guest count, **optional email**, note → `oc_rsvps`. Host email `OC_Mail::rsvp_received` (clone the `vendor_request` label→value table, class-mail.php:237+; recipient = event author via `send()`; Reply-To = guest email when given; subject `New RSVP for {event}` — ASCII). **Guest-list CSV** ✅ (per 22 June answer): `admin_post_oc_rsvp_export` cloning `stream_export()` (class-admin-import.php:1490-1615 — UTF-8 BOM, streamed fputcsv) gated on **event ownership**, not `manage_options`.

### 8.2 Gift registry ✅ (no platform-held funds — no Stripe here)
Global part A/B toggles in settings **and** per-event toggles.
- **Part A — external registries:** repeatable rows (shop from approved list or other + URL) → "View Registry" buttons; affiliate param appended server-side where configured.
- **Part B — Owambe registry:** product URL (validated against approved-shop domains) + auto-pull image/title/price (server-side OG fetch, cached, **manual override fields as the contract** — scraping is best-effort; store the original URL) + affiliate code at render. Per item: **Buy** (click-out) or **Contribute** (direct to client). Targets: physical gift · experience fund · vendor-service fund (optionally linked to a showcased vendor — keeps money in the marketplace) · general fund. Contribute modal (clone `.oc-vrq` modal incl. the `[hidden]{display:none!important}` rule) shows the client's own payment details — bank / PayPal / Revolut / Monzo.me / Wise / any link — + optional reference. **Delivery address** shown **only on buy-&-ship items**, never on cash funds. Guest contact = client's WhatsApp deep link (prefilled). Optional **manual** progress bar (client-set; `.oc-vd__upload-bar` style). Registry wording, not fundraising ("contribute", never "donate").
- **Approved retailers admin:** `oc_registry_shops` UI — add/remove, show/hide, affiliate param + code; part A/B master toggles. Client supplies the launch list ❓ — seed placeholders.

### Week 3 — done when
- [ ] Client creates/edits an event; unlisted semantics hold (direct link works logged-out; absent from search/directory/sitemap; noindex header present)
- [ ] All sections render; image + PDF uploads pass; YouTube embeds
- [ ] RSVP: submit, validation preserves input, host email (Reply-To = guest), CSV opens in Excel with all columns
- [ ] Registry: A + B toggles (global + per event), affiliate params on out-links, auto-pull with manual fallback, contribute modal correct on mobile, delivery address only on buy-&-ship, progress bar manual
- [ ] Showcase links land on vendor profiles (which record W1 views)
- [ ] Regression: vendor profile, FAB/toasts elsewhere

---

## 9. Week 4 — Monetisation & homepage

### 9.1 Subscriptions & tiers ✅/🆕 (built now, switched OFF)
**New:** `includes/class-subscriptions.php`, `includes/class-admin-subscriptions.php`, expiry emails (gold-callout style of `vendor-rejected.php`; log the send bool).
- **Tiers 🆕:** Professional / Elite / Premium — stored in `_oc_sub_plan`; definitions + pricing ❓ (client working on it). Build plan-agnostic: tiers, prices and perk flags are all settings. **Premium includes 2 featured placements/year** 🆕 (`_oc_featured_credit_used` + year anchor).
- **Free periods:** `_oc_free_until`. Founding (~200, `_oc_founding_vendor=1`) = 12 months; new vendors = 3 months; admin can override per vendor with a start date. Start basis ❓ — their draft says approval date; the 22 June answer said "immediately after build" — implementation is date-agnostic (backfill computes from either anchor; clone the `oc_backfill_vendor_numbers` meta-NOT-EXISTS pattern). New vendors: anchor via a listener on `oc_after_vendor_registered` — fires on **both** frontend paths *and* per CSV-imported row (class-admin-import.php:1222 — keep the listener cheap/idempotent for bulk imports); only the **admin Add Vendor** path needs an explicit anchor (it never fires that hook).
- **Master switch** `billing_enabled`: off = no charges, no billing UI, clock still runs. On + free period over → prompt to subscribe (Stripe Checkout; `customer.subscription.*` webhooks drive `_oc_sub_status`).
- **Expiry behaviour** ❓ (22 June answer: keep on free level): listing **never unpublished by billing**; paid perks gate off an `oc_vendor_has_paid_plan()` helper (featured eligibility, gallery cap, premium collection placement).
- **Admin controls** (both list UIs + both notice maps): assign plan/free period, extend/shorten, cancel/revoke, status pill `free/active/expiring/expired/cancelled` + end date (clone founding badge + completion mini-bar, vendors-list:342-380); Add Vendor "Admin flags" card (:658-679, `$pairs` :1017); prices + switch in Settings.
- **Cron** `oc_daily_maintenance` (shared with featured expiry): `expiring` flags (T-14/T-3 emails), flip `expired`. Status changes via `wp_update_post` → single transition handler. **Double-fire warning:** custom-list bulk approve fires `oc_after_vendor_approved` explicitly *and* via transition (vendors-list:590-598) — approval listeners must be idempotent.

### 9.2 Vendor verification ✅/🆕 (Stripe-backed, **£15**)
Flow ends in the **existing** `_oc_verified` flag (admin toggle class-admin.php:140; badges render everywhere already via `oc_verified_badge_html`). Build: dashboard "request verification" + doc upload (W3 PDF validator) → **£15 Stripe Checkout one-off** (behind `verification_fee_enabled` — activation timing ❓) → admin review queue (pending badge) → approve sets `_oc_verified` + confirmation email. Dashboard gets a verified pill (`.oc-vd__verified-pill` style exists). **Default = not verified** — never copy `OC_Email_Verification::is_verified()`'s missing-meta-passes legacy default.

### 9.3 Featured listings 🆕 (two types, auto-expiry)
- `_oc_featured` stays the single display flag; add `_oc_featured_until` + `_oc_featured_type` (`homepage|category|both`). Daily cron zeroes expired flags (+ `featured_expired` activity log) — consumers stay truthful with zero query changes: featured-grid query `OC_Queries::featured()` (class-queries.php:72-94 — note the deliberate newest-vendors fallback; keep or consciously drop), profile star (shortcode-vendor-profile.php:34/:137), KPI raw SQL (vendors-list:552, analytics:432-437).
- **Homepage featured** = carousel (extend `[oc_featured_vendors]` with a carousel layout or a new widget). **Category featured** 🆕 = carousel atop the directory **when a category filter is active** (the "category page" *is* `/vendors/?cat=` — the taxonomy template redirects there), querying featured vendors in that term with type `category|both`.
- Vendor flow: `featured` dashboard tab — duration (days/weeks/months, priced from settings). Billing off → request-only to admin (meta + notify, `submit_for_review` handler shape incl. the pending-state disabled button); billing on → Stripe Checkout; Premium credits consumed first (2/year). Admin: approve/deny/grant/extend (row action, both UIs); `.oc-card--featured` gold border in theme.

### 9.4 Homepage rebuild 🆕
Section order: **1** Hero · **2** Search · **3** Categories (as-is; admin can swap images manually — rotation is 🔎 out of scope) · **4** Homepage Featured carousel · **5** Recently Added carousel (newest approved; slot count = `recently_added_slots` setting) · **6** Premium Collection carousel (tier = premium; unlimited) · **7** Blog carousel (latest posts; empty-safe until W5 content) · **8** CTA. *(The sponsored-ads slot is dropped — out of scope, §12.)*
Each new section = shortcode + widget triple (`[oc_recently_added]`, `[oc_premium_collection]`, `[oc_blog_carousel]`); the homepage is Elementor-built, so sections are arranged in Elementor on staging — **don't** rely on re-running the demo importer (it overwrites `_elementor_data`). One shared carousel JS in `oc-frontend.js` (auto-scroll gentle, swipe on mobile, arrows on desktop).

### Week 4 — done when
- [ ] Billing off: zero charge UI, statuses correct, clocks running (spot-check founding vs new)
- [ ] Staging + Stripe test mode: subscribe → webhook → active; cancel; force-expire → email logged; vendor stays live on free tier, perks gated
- [ ] Admin: assign/extend/cancel per vendor from both list UIs; status pills accurate
- [ ] Featured: request (billing off) → admin grant → homepage + correct category carousel → cron expiry flips flag, star/KPIs consistent; Premium credit decrements
- [ ] Verification: request + doc + (fee if enabled) + approve → badge everywhere + email
- [ ] Homepage: section order correct; carousels swipe; recently-added respects slot cap

---

## 10. Week 5 — Independents & launch

### 10.1 Geolocation (radius) search ✅
`_oc_lat`/`_oc_lng` in `oc_vendor_fields()`. Source: **static lat/lng table for the 76 gov.uk cities** (`oc_cities_by_country()`, helpers.php:110 — client-locked list) covers most vendors with zero API calls; `maps_api_key` geocoding as fallback. Multi-area vendors → first area (documented). Write on save (`oc_after_vendor_updated`, completion-pct pattern) + batched-AJAX backfill (clone the `OC_Admin_Emails` run-queue :310-389; **lowercase transient keys** — `sanitize_key()` lowercases on read-back). Query: bounding-box `meta_query` + Haversine `posts_clauses` in `OC_Queries::directory()`, keeping the `_oc_location` LIKE fallback (hero search depends on the folded summary string). UI: radius select in the directory filter (shortcode-directory.php:55-97) carried through pagination `add_args` (:138-143) + filter count (:41-44). "Near me": relax theme `Permissions-Policy` to `geolocation=(self)` (inc/security.php:17 — currently hard-disabled).

### 10.2 Blog ✅
Theme templates (they don't exist): `single.php` (prose pattern of `page.php:37-40` + featured image/meta), `home.php`/`archive.php` (`.oc-card` grids, `oc-card` 800×450); make `search.php`'s vendor redirect conditional; `is_singular('post')` OG branch in `inc/seo.php`; blog page via `page_for_posts`; additive menu-item helper (menu wiring happens once at go-live since everything launches together); homepage blog carousel goes live. Client supplies 1–2 posts.

### 10.3 Planning resources ✅ + subcategory fix ✅
- `[oc_checklists]` triple + seeded page (+ whitelist + H1 list); client-supplied PDFs via Media Library; surfaced on the client dashboard too.
- **Subcategory fix:** taxonomy is already `hierarchical => true` (class-cpt.php:68) — wp-admin saves parents fine; the bug is flat rendering + parentless creation. Fix: hierarchical variant of `OC_Queries::categories_with_counts()` (class-queries.php:96-102) with indented rendering in all consumers (directory select shortcode-directory.php:62-67, hero select shortcode-hero-search.php:56-66, dashboard chips template:506-519, admin add-vendor :608-615, vendors-list filter :154-161, analytics filter :75); CSV import `resolve_category_term()` (class-admin-import.php:1385-1409) gains `Parent > Child` syntax + slug-within-parent matching; analytics donut rolls children up (class-admin-analytics.php:517-534). Directory `tax_query` already includes children. **Dynamic tag editor not built** (client agreed — subcategory fix covers the need).

### 10.4 Final testing & go-live
Full §15 pass on staging → deploy (checklist in §2: backup, plugin+theme together, re-activate/flush rewrites, run backfills — free periods + geocoding, live Stripe keys with `billing_enabled=0`, SMTP confirmed) → §15 spot-pass on live → menu wiring → 14-day support window starts.

### Week 5 — done when
- [ ] Radius search returns correct nearby vendors; near-me prompts + works; pagination keeps the radius
- [ ] Blog post renders full content; archive grid; posts searchable; homepage carousel fills
- [ ] Checklists downloadable from page + client dashboard
- [ ] Nested subcategories display correctly in all seven consumers; `Parent > Child` imports nest
- [ ] Full live acceptance pass (§15) signed off

---

## 11. After Phase 2 — referral programme ✅ (model ❓)
Built once rewards are finalised. Shape: clients earn draw entries; vendors earn subscription credit (or a free month for 3 referrals) + softer perks; rewards only count on approved sign-ups; whole programme toggleable. Everything it needs ships in Phase 2: client accounts, billing layer (credits), `oc_after_vendor_approved` (trigger), `OC_Admin_Emails` cohorts (nudges), dashboard tab pattern.

## 12. Out of scope / deferred (client items excluded from this build)
- **Availability calendar** ⏸ — per-provider approvals (Google/Outlook/Apple), ~7–10 days alone; future phase (client agreed).
- **Sponsored advertising** ⏸ — external-brand ad carousel; flagged beyond agreed Phase 2 scope; **excluded** until scope + effort are signed off. The homepage rebuild ships without the sponsored slot; adding it later is a new carousel widget + ad CPT (impressions would go in the stats table, never option arrays).
- **Category image rotation** 🔎 — performance concern; assess later (client agreed, not urgent).

## 13. Config values & open items

| Item | Value | Status |
|---|---|---|
| Verification fee | **£15** (was £10) | 🆕 |
| Premium featured allowance | **2 / year** (was monthly) | 🆕 |
| Founding / new vendor free period | 12 months / 3 months | ✅ |
| Featured durations | days / weeks / months, auto-expire | ✅ |
| Gallery images (paid plans) | ~10 | ✅ |
| Recently-added homepage slots | admin-adjustable setting | 🆕 |
| Post-launch support | 14 days → maintenance agreement | ✅ |

**Open (❓) — with prior answers where recorded:**
1. Tier definitions + pricing (Professional/Elite/Premium) — *client sending.*
2. Stripe automation ideas + featured-placement info — *client sending.*
3. Vendor profile menu: A vs C — *22 June answer leaned A ("whatever best redirects guests"); A is also the zero-extra-work option.*
4. Free-period start: approval date vs build/launch date — *22 June answer: "immediately after build"; implementation supports either.*
5. Expiry behaviour — *22 June answer: keep on free level (built that way; reconfirm).*
6. £15 verification fee: active from launch, or with the billing switch?
7. Approved-retailer launch list + affiliate accounts.
8. Business card fields (formats resolved: PDF + PNG).
9. Reviews: star + text vs text only (built star+text with a display toggle).
10. ~~RSVP guest-list export~~ — resolved ✅ (22 June: yes, downloadable).

## 14. Corner cases to resolve during development
- Registry auto-pull failures / scraping blocks → manual-entry fallback is the contract.
- Affiliate approval status + attribution windows per retailer (esp. Amazon ~24h).
- Featured expiry collisions: overlapping placements, type changes mid-run, Premium allowance interaction.
- Subscription state machine at downgrade: which perks revoke, what happens to an active featured slot.
- Google sign-in vs Phase 1 hardening (wp-login interception, throttles) — routed via admin-post, §6.2.
- WhatsApp number normalisation across saved formats (Phase 1 normaliser is UK-only — internationalisation would touch two savers).
- Bulk import firing per-row hooks (free-period anchor listener must be cheap + idempotent).
- Page-cache interaction with view/click beacons (nonce-less by design; watch for bot inflation, throttle + UA filter if needed).

---

## 15. Live acceptance tests (run on staging before launch, then on live)

Tick every box. Where a step charges money, run it in Stripe **test mode on staging only**; on live, verify the UI is absent (billing off).

### A. Phase 1 regression (nothing broke)
- [ ] Vendor registers at `/apply/` → auto-login → dashboard; welcome email arrives
- [ ] Email verification link verifies; vendor completes profile; submit-for-review gates on checklist
- [ ] Admin approves → vendor email + listing live in directory; approved vendor appears in Mailchimp (FNAME/City untouched)
- [ ] Reject with reason → email contains reason; vendor edit resubmits
- [ ] Directory search/filters/pagination; hero search; category grid links
- [ ] Contact form + Request-a-Vendor FAB submit → enquiry log entries + emails; toasts still show everywhere
- [ ] Password reset via branded pages works; `/wp-admin` blocked for vendors
- [ ] CSV import dry-run + commit on a small file; export round-trips

### B. Accounts & sign-in
- [ ] Google sign-in creates a new client → lands on client dashboard
- [ ] Google sign-in with an email that already has an account links (no duplicate user)
- [ ] Google button on vendor-login signs a vendor in → vendor dashboard
- [ ] Client cannot open `/wp-admin` (redirected) and cannot reach the vendor dashboard form
- [ ] Logout returns each role to the right place; browsing/contacting needs no account anywhere

### C. Analytics & tracking
- [ ] Open a profile logged-out → view count +1 (admin chart); reload spam doesn't inflate (dedupe)
- [ ] Vendor viewing own profile does NOT count
- [ ] Each contact button (WA/email/IG/FB/website) click increments its own metric
- [ ] WhatsApp opens with the prefilled message incl. the business name
- [ ] Vendor analytics tab hidden; enable the switch → tab appears with correct numbers
- [ ] GA4 still fires site-wide

### D. Reviews
- [ ] Logged-out "leave a review" → sign-in prompt → returns to the profile after Google sign-in
- [ ] Submit star+text → not publicly visible; admin badge count +1
- [ ] Approve → vendor email; stars + review on profile, rating on cards, `aggregateRating` in page source
- [ ] Second review of the same vendor blocked; reject removes from queue
- [ ] Vendor dashboard shows reviews read-only + request-a-review link copies/shares correctly

### E. Mini-site & business card
- [ ] `/vendor/<slug>/` loads with the full Owambe menu; sections anchor-scroll (test mobile)
- [ ] Old `/vendor-profile/?v=<slug>` 301s to the new URL
- [ ] Share buttons work; pasted link previews with logo/banner (WhatsApp OG check)
- [ ] Business card downloads as PDF and PNG; QR scans to the live profile

### F. Client dashboard
- [ ] Save/unsave a vendor from a directory card and from a profile; saved list updates
- [ ] Recently-contacted fills after clicking a vendor's contact button while signed in

### G. Event page & RSVP
- [ ] Client creates an event; URL contains the random token; page opens logged-out via the link
- [ ] Event absent from site search, directory, sitemap; response has noindex
- [ ] Every section renders; gallery images, programme/invitation PDF upload, YouTube embed
- [ ] RSVP submits (yes + no); invalid submit keeps typed values; host receives the email (Reply-To = guest)
- [ ] Guest-list CSV downloads, opens in Excel, contains all rows/columns
- [ ] Vendor showcase links open the vendors' mini-sites

### H. Gift registry
- [ ] Part A: View Registry button opens the external registry; affiliate param present on approved shops
- [ ] Part B: adding a product link auto-fills image/name/price; manual override works when auto-pull fails
- [ ] Buy click-out carries the affiliate code; Contribute modal shows the client's payment details + reference (mobile check)
- [ ] Delivery address appears ONLY on buy-&-ship items, never on cash funds
- [ ] Progress bar reflects the client's manual value; toggles (global + per-event, A and B independently) hide the right sections
- [ ] Admin can add/hide a retailer and it reflects on event pages

### I. Subscriptions, verification & featured (staging = test mode; live = billing off)
- [ ] LIVE: no pricing/checkout UI anywhere with `billing_enabled=0`; vendor statuses + end dates correct in admin
- [ ] STAGING: subscribe via Checkout → webhook flips status to active; cancel works; forced expiry sends the email and drops perks — listing stays live on free level
- [ ] Admin assign/extend/cancel a free period from both the custom list and native list; status pill updates
- [ ] Featured request (duration select) → admin grant → appears in the homepage carousel and only the right category carousel
- [ ] Cron expiry removes it automatically (star + admin KPI consistent); Premium vendor consumes a credit instead of paying
- [ ] Verification: request + document + (test £15 payment if fee enabled) → admin approve → badge on profile/cards/dashboard + email

### J. Homepage
- [ ] Section order: hero → search → categories → featured → recently added → premium collection → blog → CTA
- [ ] Recently-added honours the admin slot count; premium collection lists only premium-tier vendors
- [ ] Carousels: gentle auto-scroll, swipe on mobile, arrows on desktop; no sponsored slot present

### K. Geolocation & blog
- [ ] Radius search returns vendors within N miles, ordered sensibly; pagination keeps the radius
- [ ] "Near me" prompts for location and works (header no longer blocks geolocation)
- [ ] Blog post shows full content with featured image; archive grid renders; site search finds posts; homepage blog carousel fills

### L. Categories & resources
- [ ] Create a subcategory under a parent in wp-admin → shows nested (indented) in: directory filter, hero search, dashboard picker, admin filters, analytics donut
- [ ] CSV import row with `Parent > Child` creates a nested term
- [ ] Checklist PDFs download from the page and the client dashboard

### M. Infrastructure & security
- [ ] Security Health all green, including the new rows (Stripe webhook, tracking endpoint, client role)
- [ ] Rapid-fire submits on RSVP/review/track endpoints get throttled
- [ ] All new emails arrive (check spam), subjects clean ASCII; cron-send failures appear in the log
- [ ] With a page cache active: beacons still record, forms still submit
- [ ] `WP_DEBUG` on staging: no notices on the new surfaces; page speed comparable to Phase 1

---

# Build Log — Weeks 1 & 2 (plugin v1.1.1 → v1.2.0)

_Status: implemented on staging, self-tested + adversarially reviewed, awaiting client acceptance. Everything runs on the local/staging site; nothing is on live yet._

## What shipped

**New plugin classes** (`includes/`): `class-client.php` (oc_client role, saved/recent-contact APIs, page gating), `class-google-auth.php` (server-verified Google sign-in), `class-tracking.php` (views/clicks store + read API), `class-stripe.php` (webhook + request wrapper — scaffolding only), `class-reviews.php` (oc_review CPT + submission + aggregates), `class-admin-reviews.php` (moderation queue), `class-business-card.php` (PNG/PDF/QR).
**New templates/partials**: `shortcode-client-login.php`, `shortcode-client-dashboard.php`, `partials/review-form.php`, `partials/review-list.php`, `emails/client-welcome.php`, `emails/review-approved.php`; Elementor `widget-client-login.php`, `widget-client-dashboard.php`.
**New assets**: theme `assets/css/phase2.css`; bundled `includes/lib/phpqrcode.php`; `assets/fonts/` (Inter Regular/Bold, Playfair Bold).
**New data**: role `oc_client`; tables `{prefix}oc_vendor_stats`, `{prefix}oc_rsvps`; pages `client-login`, `client-dashboard`; settings keys (Google, Stripe, `vendor_analytics_enabled`, `whatsapp_prefill_template`, `billing_enabled`).
**Modified Phase 1 files**: `owambe-connect-core.php`, `class-plugin.php`, `class-activator.php`, `uninstall.php`, `helpers.php`, `class-settings.php`, `class-mail.php`, `class-admin-security-health.php`, `class-admin-analytics.php`, `class-assets.php`, `class-elementor.php`, `class-shortcodes.php`, `class-dashboard.php`, templates (`shortcode-vendor-profile.php`, `partials/vendor-card.php`, `shortcode-vendor-dashboard.php`, `shortcode-login-form.php`, `shortcode-vendor-request-fab.php`), `assets/js/oc-frontend.js`; theme `header.php`, `page.php`, `inc/assets.php`.

## Verified working (self-test)
Activation (tables/role/pages/rewrite-flush); Google sign-in fail-closed + role-routing; tracking (views recorded, self-views + bots excluded, `view` metric rejected from beacon, throttle caps at 30, IP-spoof headers ignored by default); reviews lifecycle (pending → approve → cached aggregate → stars on hero/cards/JSON-LD → vendor email; recompute on publish/trash/hard-delete/vendor-delete); `/vendor/{slug}/` clean URL + legacy 301; business card PNG+PDF render and parse; client dashboard (save toggle, recent contacts, gating); admin reviews page (pending/approved/rejected + pagination); settings + security-health render. 113 PHP files lint on PHP 8.2 **and** 8.4; zero notices on public pages.

## Adversarial review — 20 findings, all resolved
6 dimension reviewers + per-finding verification. **7 major fixed:** (1) Google sign-in could link/log-in to an admin/editor account by matching email — now refuses privileged/non-marketplace accounts on the nopriv endpoint; (2) create-on-save could promote a client to vendor — now blocked in `run_save()` itself, not just the view layer; (3) tracking trusted spoofable `X-Forwarded-For`/`CF-Connecting-IP` — new `oc_client_ip()` defaults to `REMOTE_ADDR`, honours forwarded headers only behind the `oc_trusted_proxy` filter; (4) admin analytics ignored the filter's end date — added `totals_range()`/`timeseries_range()`; (5) Phase 2 tables had no self-heal for a file-copy deploy — added to the versioned upgrade path; (6) review duplicate race — atomic `add_option()` claim; (7) nonce-less endpoint mutated a client's recent-contacts list (CSRF) — that branch now requires the same-origin nonce. **13 minor fixed:** per-vendor throttle scoping, Google throttle order, review orphan-vendor guard + vendor-delete cleanup, moderation pagination + rejected/restore view, `.oc-vd` tab-selector collision (unique id), Elementor bracket-in-attr escaping, saved-list cap (200), dead `redirect_url` widget control, single-template fallback filter, `oc_debug_log($force)` for webhooks. **1 deferred (documented):** reflected `?oc_error`/`?oc_notice` text is HTML-escaped (no XSS) but is low-severity content-spoofing via a crafted link — the proper fix is a cross-cutting message-code refactor across Phase 1 forms, better done deliberately than rushed into this batch.

## Not done yet (by design)
Subcategory nesting fix and downloadable planning checklists — listed under W1 in the plan but scheduled into the W5 "independents & launch" batch (§3/§10.3); they don't gate the W1/W2 vendor-experience acceptance pass.

## Before this goes to live (client's staging acceptance = §15 A–F)
Configure on staging to exercise fully: **Google** — create an OAuth Web client, add the staging origin, paste the Client ID in Settings → Google Sign-In. **Stripe** — paste **test** keys, add the webhook endpoint shown in Settings, click "Test Stripe connection". **SMTP** — confirm the mail plugin is active so review/welcome emails send. Then run the acceptance checklist (§15 sections **A** regression, **B** accounts/sign-in, **C** analytics, **D** reviews, **E** mini-site/business card, **F** client dashboard). Deploy note: this is a file-copy deploy (no git) — the versioned self-heal creates the tables + flushes rewrites on first admin load, but re-activating the plugin once after upload is the safest path.
