<div align="center">

# 🎉 Owambe Connect — Core

**The engine behind the Owambe Connect vendor marketplace.**

A commission‑free directory that connects event hosts with African, Caribbean & South‑Asian event vendors — caterers, photographers, decorators, DJs, venues, cake makers, MUAs, planners and more.

[![Version](https://img.shields.io/badge/version-1.2.0-6E0F2C.svg)](#-changelog)
[![WordPress](https://img.shields.io/badge/WordPress-6.0%2B-21759B.svg)](https://wordpress.org/)
[![PHP](https://img.shields.io/badge/PHP-7.4%2B-777BB4.svg)](https://www.php.net/)
[![License](https://img.shields.io/badge/license-GPL--2.0--or--later-C9A961.svg)](https://www.gnu.org/licenses/gpl-2.0.html)

</div>

---

## 📖 Table of Contents

- [What It Does](#-what-it-does)
- [Who It's For](#-who-its-for)
- [Feature Highlights](#-feature-highlights)
- [Requirements](#-requirements)
- [Installation](#-installation)
- [Data Model](#-data-model)
- [Shortcodes](#-shortcodes)
- [Vendor Business Card](#-vendor-business-card)
- [Developer Reference](#-developer-reference)
- [Directory Structure](#-directory-structure)
- [FAQ](#-faq)
- [Changelog](#-changelog)
- [License & Credits](#-license--credits)

---

## ✨ What It Does

The **entire marketplace is built from this one plugin**. It provides a custom vendor post type, a self‑service application & approval workflow, a frontend vendor dashboard, a searchable directory, transactional emails, a Mailchimp integration, security hardening and admin analytics — all composed onto pages via **shortcodes** and **Elementor widgets**.

The model is **discovery without a middleman**: hosts browse rich vendor profiles and contact vendors **directly** via WhatsApp, email or social — no commission, no gatekeeping.

---

## 👥 Who It's For

| Audience | What they do | Account needed? |
|----------|--------------|:---------------:|
| **Event hosts / customers** | Search, browse, view profiles, contact vendors, request a vendor | ❌ No |
| **Vendors** | Register, build a profile, submit for review, manage their listing | ✅ `oc_vendor` role |
| **Clients (signed‑in hosts)** | Save favourites & see recently contacted — sign in with **Google _or_ email/password** | ✅ `oc_client` role |
| **Admins** | Review/approve vendors, import/export, view analytics | ✅ WP admin |

> Vendors and clients get a **frontend‑only** experience — they never touch `/wp-admin`.

---

## 🚀 Feature Highlights

### Public / customer‑facing
- 🔎 **Hero search** — category dropdown + smart UK location typeahead + quick‑pick category pills.
- 🗂️ **Vendor directory** — free‑text search, category / region / city **and cultural‑specialty** filters, pagination, live result count and tailored empty states. Only approved vendors are public.
- 🏷️ **Category grid** — browse by category as a photo carousel or icon grid.
- ⭐ **Featured vendors** — homepage grid of admin‑flagged vendors (falls back to newest).
- 👤 **Vendor profile** — hero banner, logo, badges, gallery lightbox, contact card, similar‑vendors carousel and JSON‑LD SEO schema.
- 🧭 **Floating section nav** — a sticky pill bar (About · Services · Portfolio · How to book · Reviews · Contact) with a **gold‑underline scroll‑spy** and smooth scrolling; wraps onto two lines on mobile.
- 🔗 **Tappable badges** — category and cultural‑specialty chips link straight to the filtered directory.
- 📨 **Contact form & "Request a Vendor" button** — logged to the admin enquiry log with delivery‑status tracking.

### Client accounts & safety
- 🔐 **Two ways to sign up** — one‑tap **Google** *or* a native **email/password** account (for Yahoo/Outlook users), on `/client-login/`.
- ☑️ **Mandatory Terms & Conditions** — a required consent tick on both sign‑up methods; the Terms link is set in **Settings** (falls back to `/terms/`).
- 💗 **Client dashboard** — saved vendors + recently contacted, with **clickable stat tiles** that jump to each list (no reload).
- 🛡️ **Website Safety page** — a seeded `/safety/` page of practical safety tips, with an admin‑editable intro and a filterable tip set (`oc_safety_items`).

### Vendor lifecycle
- ⚡ **Minimal registration** at `/apply/` — auto‑login + welcome email.
- ✅ **Email verification** — hashed token, 7‑day expiry, resend throttle.
- 🧭 **Self‑service dashboard** — tabbed profile editor (Business / Story / Contact / Photos / Account) with AJAX per‑file uploads and a live completion checklist.
- 📤 **Submit for review** — gated by a completion checklist; optional auto‑approve.
- 🔐 **Branded password reset** — never `/wp-login.php`.

### Admin
- 📊 **Analytics dashboard** — KPI cards, time‑series / category / price / location charts (Chart.js), plus **search & discovery** insights (top keywords, empty searches, most‑searched & most‑clicked categories).
- 📇 **Vendors list** — KPI cards, filters and per‑row actions (approve / reject with reason / feature / verify / trash).
- ➕ **Add vendor** & 📥 **CSV import/export** — fuzzy column mapping, dry‑run preview, batched processing.
- 📈 **Vendor analytics tracking** — profile views + per‑channel contact clicks (WhatsApp / Email / Instagram / Facebook / Website).
- 🗒️ **Activity & enquiry logs**, 🛡️ **security health checklist**, ✉️ **bulk vendor emails**, and an in‑admin **developer guide**.

### Integrations & infrastructure
- 📧 **Mailchimp sync**, 🤖 **reCAPTCHA v3**, 📉 **GA4**, 💳 **Stripe scaffolding** *(Phase 2)*.
- 🛡️ **Security hardening** — XML‑RPC off, user‑enumeration blocked, secure cookies, security headers and per‑IP rate limiting.
- ✉️ **Transactional emails** — shared branded header/footer, SMTP‑friendly.

---

## 🧰 Requirements

| | Minimum |
|---|---|
| **WordPress** | 6.0+ |
| **PHP** | 7.4+ (tested on 8.2 / 8.4) |
| **PHP GD** | Required for the business card & QR (with FreeType for crisp fonts) |
| **SMTP plugin** | Recommended (FluentSMTP / WP Mail SMTP) for reliable email delivery |
| **Elementor** | Optional — widgets load only when Elementor is active |

---

## 📦 Installation

1. Copy the `owambe-connect-core` folder into `wp-content/plugins/`.
2. Activate **Owambe Connect Core** in **Plugins**.
   On activation it registers the vendor CPT, taxonomy and roles, creates the core pages (`/vendors/`, `/apply/`, dashboard, login, legal, etc.), seeds the default categories and flushes rewrite rules.
3. *(Optional)* Run **Appearance → Import Demo** to seed 9 pages, 10 categories, 10 sample vendors and the primary menu.
4. Configure keys in **Vendors → Settings** (Mailchimp, reCAPTCHA, Google, Stripe, GA4, brand colours…).

> **Deploying to a live server?** This is a file‑copy deploy — the versioned self‑heal recreates its tables and flushes rewrites on the first admin load, but **re‑activating the plugin once after upload is the safest path**. Remember to upload the whole plugin including the `assets/` folder.

---

## 🗃️ Data Model

| Type | Name | Notes |
|------|------|-------|
| **Post type** | `oc_vendor` | Vendor listings — rewrites to `/vendor/{slug}/` |
| **Taxonomy** | `oc_vendor_category` | Hierarchical (Catering, Photography, Venues…) |
| **Post type** | `oc_review` | Client reviews *(Phase 2, admin‑moderated)* |
| **Role** | `oc_vendor` | Cap `oc_edit_own_vendor` — edits only their own listing |
| **Role** | `oc_client` | Read‑only — saved vendors + event pages *(Phase 2)* |

**Vendor statuses:** `oc_pending` (Pending Review) · `oc_rejected` (Needs Changes) · `publish` (Approved).

---

## 🧩 Shortcodes

The frontend is assembled from **25 shortcodes**, each mirrored by an Elementor widget of the same name.

| Shortcode | Purpose |
|-----------|---------|
| `[oc_hero_search]` | Homepage hero + search bar |
| `[oc_directory]` | Searchable / filterable vendor directory |
| `[oc_vendor_profile]` | Single vendor profile |
| `[oc_category_grid]` | Browse by category |
| `[oc_featured_vendors]` | Featured vendors grid |
| `[oc_register_form]` · `[oc_login_form]` | Vendor sign‑up / sign‑in |
| `[oc_vendor_dashboard]` | Self‑service vendor dashboard |
| `[oc_forgot_password]` · `[oc_reset_password]` | Branded password reset |
| `[oc_contact_form]` · `[oc_vendor_request_fab]` | Contact + "Request a vendor" |
| `[oc_client_login]` · `[oc_client_dashboard]` | Client accounts (Google + email/password) |
| `[oc_safety_info]` | Website Safety Information page |
| `[oc_how_it_works]` · `[oc_testimonials]` · `[oc_faq]` · `[oc_stats]` · `[oc_about_blocks]` · `[oc_feature_row]` · `[oc_become_a_vendor_cta]` · `[oc_navbar]` · `[oc_footer]` · `[oc_breadcrumb]` | Page‑building blocks |

> A full, always‑current list (with hooks, meta keys and file paths) lives in the in‑admin **Developer Guide**.

---

## 💳 Vendor Business Card

Every approved vendor gets a **premium, downloadable business card** — generated server‑side with GD (no external APIs).

- 🎨 **Two variants** — the signature **gold‑on‑burgundy** design and a clean **black‑&‑white** version (`?variant=bw`).
- 🖼️ **Two formats** — high‑resolution **PNG** and a print‑ready single‑page **PDF** — so **four one‑click downloads** in all.
- 🔗 **Live QR code** that deep‑links to the vendor's public profile, with rounded corners and a framed panel.
- 📇 **Crisp, tinted contact icons** (WhatsApp, Email, Instagram, Web) bundled under `assets/icons/` and recoloured to match each variant.
- 🧠 **Smart, single‑line location** — collapses multiple regions to *"Primary Region & UK‑wide"* and never overflows the card.
- 🖼️ **Robust logo fitting** — aspect‑correct, padded, transparency‑safe (and desaturated in the B&W variant).

Vendors grab them from the dashboard's **Share My Business → Business Card** menu:

```
Colour PNG        → …/admin-post.php?action=oc_business_card&format=png
White & Black PNG → …&format=png&variant=bw
Colour PDF        → …&format=pdf
White & Black PDF → …&format=pdf&variant=bw
```

---

## 🛠️ Developer Reference

### Core constants (`includes/helpers.php`)
```php
OC_CPT              = 'oc_vendor';
OC_TAX              = 'oc_vendor_category';
OC_ROLE             = 'oc_vendor';
OC_CAP_EDIT_OWN     = 'oc_edit_own_vendor';
OC_STATUS_PENDING   = 'oc_pending';
OC_STATUS_REJECTED  = 'oc_rejected';
OC_STATUS_APPROVED  = 'publish';   // there is no separate "approved" status
OC_CLIENT_ROLE      = 'oc_client';
```

### Action hooks you can listen to
| Hook | Fires when |
|------|-----------|
| `oc_after_vendor_registered` | A vendor account is created (`$post_id`, `$user_id`) |
| `oc_after_vendor_approved` | A listing is approved |
| `oc_after_vendor_rejected` | A listing is rejected (`$id`, `$note`) |
| `oc_after_vendor_updated` | A vendor edits their listing |
| `oc_vendor_status_changed` | Any status transition (single source of truth) |
| `oc_after_email_verified` | A vendor verifies their email |
| `oc_review_status_changed` | A review is approved / rejected |
| `oc_stripe_event` | A verified Stripe webhook arrives |

### Handy filters
`oc_business_card_fields` · `oc_security_limits` · `oc_suppress_page_title_slugs` · `oc_cities_by_country` · `oc_vendor_gallery_cap` · `oc_mail_*` · `oc_trusted_proxy`

### REST routes (namespace `oc/v1`)
- `POST /stripe-webhook` — signature‑verified Stripe webhook.
- `POST /google-login` — server‑verified Google sign‑in.

---

## 🗂️ Directory Structure

```
owambe-connect-core/
├── owambe-connect-core.php     # Bootstrap: constants, includes, activation, boot
├── uninstall.php               # Data wipe (gated by oc_uninstall_keep_data)
├── includes/                   # All PHP logic (~40 classes)
│   ├── class-plugin.php        #   Singleton orchestrator
│   ├── class-cpt.php           #   CPT, taxonomy, statuses, roles, meta
│   ├── class-dashboard.php     #   Frontend vendor dashboard
│   ├── class-shortcodes.php    #   24 shortcodes + routing
│   ├── class-business-card.php #   PNG/PDF business card + QR
│   ├── class-admin-*.php       #   Admin screens (list, analytics, import…)
│   ├── elementor/              #   20 Elementor widgets
│   ├── lib/phpqrcode.php       #   Bundled pure‑PHP QR library
│   └── helpers.php             #   Constants, field schema, utilities
├── templates/                  # Theme‑overridable views (shortcodes, emails, partials)
└── assets/
    ├── css/                    #   Admin + dashboard styles
    ├── js/                     #   Frontend + business‑card scripts
    ├── fonts/                  #   Inter + Playfair Display (bundled)
    └── icons/                  #   Tintable contact icons (WhatsApp, Email, …)
```

---

## ❓ FAQ

**Does contacting a vendor cost commission?**
No. Hosts contact vendors directly via WhatsApp / email / social — the platform never sits between them.

**Do customers need an account to browse?**
No. Browsing and searching are fully public; an account is only needed to list a business (vendors) or to save favourites & track contacts (clients).

**How do event hosts sign up — do they need a Google account?**
No. Hosts can sign in with **Google** *or* create a normal **email/password** account on `/client-login/` (so Yahoo/Outlook users are covered). Both require accepting the Terms & Conditions.

**Why aren't my emails arriving?**
The plugin logs every enquiry even if mail fails, but reliable delivery needs an SMTP plugin (FluentSMTP or WP Mail SMTP). Check **Vendors → Enquiries** for the delivery status of each message.

**Can I override the templates?**
Yes — everything in `templates/` (except emails) is theme‑overridable via `oc_get_template()`.

---

## 📝 Changelog

### 1.2.x — *Client accounts, safety & profile polish*
- 🔐 **Native client accounts** — email/username + password **sign‑up and login** alongside Google, on `/client-login/` and the vendor‑login "Client" tab.
- ☑️ **Mandatory Terms & Conditions** consent on both sign‑up methods; the Terms link is configurable in **Settings** (`client_terms_url`).
- 🔁 **Client‑aware password reset** — resets route hosts back to the client login (and let Google‑only users set a password).
- 🧭 **Floating profile section nav** — sticky pill bar with a gold‑underline **scroll‑spy**, smooth in‑page scrolling and a two‑line mobile layout.
- 🔗 **Tappable category & cultural‑specialty badges** → a new **cultural‑specialty filter** in the directory.
- 🪪 **Business card** — grouped **Colour / Black‑&‑White × PNG / PDF** downloads under a tidied **"Share My Business"** sidebar menu.
- 💗 **Client dashboard** — clickable *Saved Vendors* / *Vendors Contacted* stat tiles that switch panels with no reload.
- 🛡️ **Website Safety** — new `[oc_safety_info]` shortcode + seeded `/safety/` page + admin intro setting.
- 📊 **Admin analytics** — clickable KPI drill‑downs, a contact‑method breakdown, and search‑&‑discovery insights.

### 1.2.0 — *Phase 2 (Weeks 1–2)*
- ➕ Client accounts + Google sign‑in, saved vendors & recently‑contacted.
- 📈 Vendor analytics tracking (views + per‑channel clicks) with admin charts and search‑discovery insights.
- ⭐ Reviews (client‑submitted, admin‑moderated) with cached rating aggregates.
- 🌐 Vendor mini‑site — native `/vendor/{slug}/` URLs.
- 💳 Redesigned **business card** — premium layout, colour + B&W variants, crisp bundled icons, rounded QR panel and smart single‑line location.
- 💳 Stripe scaffolding (webhook + connection test).

### 1.1.x — *Phase 1 (MVP)*
- Vendor CPT, application & approval workflow, vendor dashboard.
- Directory, hero search, category grid, featured vendors.
- Transactional emails, Mailchimp sync, security hardening, admin analytics, CSV import/export.

---

## 📜 License & Credits

Released under the **GPL‑2.0‑or‑later** license.

Crafted with care by **[Instaquirk](https://instaquirk.com)** for **[Owambe Connect](https://owambeconnect.com)**.

<div align="center">

**Brand palette** — Burgundy `#6E0F2C` · Deep Burgundy `#800020` · Gold `#C9A961`

*Made for the culture. 💃🕺*

</div>
