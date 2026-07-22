# Owambe Connect — Platform Overview

**Full structure, features, and user stories**
_Plugin: `owambe-connect-core` · Version 1.1.1 (Phase 1 / MVP) · Prepared by Instaquirk_

---

## 1. What Owambe Connect Is

Owambe Connect is a **vendor marketplace and directory for African, Caribbean, and South-Asian event vendors** (caterers, photographers, decorators, DJs, venues, cake makers, MUAs, planners, and more). Couples and event hosts discover vendors, view rich profiles, and contact them **directly** — there is no commission or middleman. Vendors get a self-service listing they manage themselves, reviewed by an admin before going live.

The whole site is built from the plugin: a custom vendor post type, an application & approval workflow, a frontend vendor dashboard, a searchable directory, transactional emails, a Mailchimp integration, security hardening, and admin analytics — composed onto pages via shortcodes and Elementor widgets.

---

## 2. The Three Audiences

| Audience | What they do | Account needed? |
|----------|--------------|-----------------|
| **Event hosts / customers** | Search, browse, view profiles, contact vendors, request a vendor | No |
| **Vendors** | Register, build a profile, submit for review, manage their listing | Yes (`oc_vendor` role) |
| **Admins** | Review/approve vendors, manage listings, import/export, view analytics | Yes (WP admin) |

---

## 3. System Structure

### 3.1 Data model
- **`oc_vendor`** — custom post type for vendor listings.
- **`oc_category`** — hierarchical taxonomy (Catering, Photography, Venues, etc.).
- **`oc_vendor`** — custom user role with capability `oc_edit_own_vendor` (vendors edit only their own listing; they never touch `/wp-admin`).

**Vendor statuses:**
- `pending_review` — awaiting admin approval
- `approved` — live and public
- `needs_changes` — sent back to the vendor with a reason

**Key profile fields (post meta):** business name, bio, services, price range, location (country / regions / areas), cultural specialties, Nigerian-events flag, registered-business flag, vendor tags, languages, WhatsApp, public email, Instagram, Facebook, website, logo, banner, gallery, plus admin flags (`featured`, `verified`, `founding`), email-verified state, vendor number, and a cached profile-completion percentage.

### 3.2 Plugin architecture
A singleton orchestrator (`OC_Plugin::boot()`) loads ~21 classes on `plugins_loaded`. Core classes always load (settings, security, CPT, registration, email verification, vendor dashboard, shortcodes, assets, Mailchimp, activity log, enquiry log); admin classes load only in `/wp-admin`. Elementor widgets load lazily only when Elementor is active.

### 3.3 How pages are built
The frontend is assembled from **24 shortcodes** (and matching **17+ Elementor widgets**): navbar, hero search, category grid, featured vendors, directory, vendor profile, register/login/forgot/reset forms, vendor dashboard, contact form, become-a-vendor CTA, how-it-works, testimonials, FAQ, stats, about blocks, feature rows, footer, breadcrumb, and a "Request a Vendor" floating button. A one-click **demo importer** seeds 9 pages, 10 categories, 10 sample vendors, and the primary menu.

---

## 4. Feature Catalogue

### 4.1 Public / customer-facing
- **Hero search** — category dropdown + smart location typeahead (UK countries, 9 England regions, major cities) + quick-pick category pills.
- **Vendor directory** — searchable, filterable grid: free-text search, category filter, region filter, city/area filter, pagination, live result count, reset, and tailored empty states. Only approved vendors are public.
- **Category grid** — browse by category as a photo carousel or icon grid, each card linking to a filtered directory.
- **Featured vendors** — homepage grid of admin-flagged featured vendors (falls back to most recent).
- **Vendor profile page** — hero banner, logo, verification/founding badges, vendor number, about, specialties, services, "what we offer" tags, lightbox portfolio gallery, languages, "How to book" steps, contact card (WhatsApp/email/Instagram/Facebook/website), quick-info sidebar, social share menu, report link, similar-vendors carousel, and JSON-LD SEO schema.
- **Contact form** & **"Request a Vendor" floating button** — both logged to the admin enquiry log with delivery-status tracking.

### 4.2 Vendor lifecycle
- **Minimal registration** — `/apply/`: email, password, business name, terms consent, reCAPTCHA. Vendor is auto-logged-in and sent to the dashboard; a welcome email goes out immediately.
- **Email verification** — hashed token, 7-day expiry, 60-second resend throttle; verification is required before a listing can be submitted.
- **Self-service dashboard** — tabs for Overview, Business, Story, Contact, Photos, Account. Vendors edit all profile fields, upload logo/banner/gallery (AJAX, per-file), change password, and raise support/feedback tickets. Editing an approved listing keeps it live; editing a rejected one auto-promotes it back to pending and re-notifies the admin.
- **Submit for review** — gated by a completion checklist (email verified, business name, ≥1 category, location, some content, ≥1 image). Auto-approve setting decides whether it goes live instantly or waits for admin.
- **Password reset** — branded `/forgot-password/` and `/reset-password/` pages (not `/wp-login.php`), with anti-enumeration messaging.

### 4.3 Admin
- **Vendors list** — KPI cards (total / approved / pending / needs-changes / featured), status filters, search + category + location filters, and per-row actions: approve, reject (with required reason), feature, verify, view, trash. Bulk actions including permanent delete (also removes the linked user safely).
- **Add vendor** — full admin create/edit form with status control, admin flags, and optional vendor user account + credential email.
- **CSV import/export** — fuzzy column mapping, dry-run preview, auto-category creation, skip-existing, grandfather-verify, batched processing for large files, one-click "delete this batch," and round-trippable export with status filters.
- **Analytics dashboard** — 6 KPI cards, time-series / category / price / location charts (Chart.js), date-range and category filters, and a recent-applications table.
- **Activity log** — per-vendor (50 events) and site-wide (200 events): registrations, profile updates, and admin/vendor status changes, with filters and KPI tiles.
- **Security health checklist** — ~16 checks across WordPress hardening, plugin protections, integrations, external services, and performance, with a scored circular progress.
- **Vendor emails tool** — bulk-email a chosen cohort (latest batch / pending / unverified / all) with custom subject and body, AJAX-paced.
- **Developer guide** — in-admin reference for shortcodes, hooks, data model, meta keys, file paths, and template overrides.

### 4.4 Integrations & infrastructure
- **Mailchimp sync** — one audience; upserts approved vendors on approval, on update (if already approved), and via manual backfill. Maps real merge fields (business name, category, Instagram, website, about, services, areas, phone) and tags (`Owambe Vendor`, `Founding Vendors`). _Note: First name and City are deliberately not overwritten._
- **reCAPTCHA v3** — on registration and contact forms (server-side score check).
- **Google Analytics 4** — GA4 tag injected when an ID is configured.
- **Security hardening** — XML-RPC disabled, version hidden, user-enumeration blocked, REST users endpoint locked for non-admins, application passwords off, generic login errors, secure cookies, security headers, and per-IP rate limiting (5 registrations/hr, 8 logins/15 min, 6 contact submissions/hr).
- **Transactional emails (9)** — application received, admin new-application, approved, rejected (with reason), password reset, contact message, support ticket, vendor feedback, vendor request — all using a shared branded header/footer, with CC to a shared inbox. Requires an SMTP plugin (FluentSMTP / WP Mail SMTP) for reliable delivery.
- **Settings** — tagline, auto-approve toggle, brand colours, email senders, directory counts, upload limits, languages list, and all integration keys.

---

## 5. User Stories

### 5.1 Event host / customer
- As a host, I want to **search vendors by category and location** so I can quickly find relevant suppliers for my event.
- As a host, I want to **filter the directory** by category, region, and city so I can narrow results to what fits my celebration.
- As a host, I want to **view a detailed vendor profile** (gallery, services, specialties, reviews of how to book) so I can judge whether they suit my event.
- As a host, I want to **contact a vendor directly** via WhatsApp, Instagram, email, or website so I can negotiate without a middleman or commission.
- As a host, I want to **share a vendor** with my partner/family so we can decide together.
- As a host, I want to **request a vendor type that isn't listed yet** so the platform can help me find one.
- As a host, I want to **send a general enquiry** through the contact form and trust it reaches the team even if email fails.

### 5.2 Vendor
- As a vendor, I want to **sign up quickly** with just my email, password, and business name so I'm not blocked by a long form.
- As a vendor, I want to **verify my email** so my listing is trusted and eligible to go live.
- As a vendor, I want to **build my profile gradually** from a dashboard (bio, services, categories, location, contact details, photos) so I can complete it at my own pace.
- As a vendor, I want a **completion checklist** so I know exactly what's needed before I can submit.
- As a vendor, I want to **submit my listing for review** and be notified whether it's approved or needs changes.
- As a vendor, I want to **see admin feedback when rejected** and resubmit by simply editing my listing.
- As a vendor, I want to **edit my live listing anytime** without it disappearing from the directory.
- As a vendor, I want to **receive enquiries directly** (WhatsApp/email/social) so I keep full control of the booking and pay no commission.
- As a vendor, I want to **reset my password** and **raise support or feedback** from my dashboard.

### 5.3 Admin
- As an admin, I want a **dashboard of marketplace KPIs** so I can see pending, live, and needs-changes counts at a glance.
- As an admin, I want to **review pending applications and approve or reject them** (with a reason) so only quality vendors go live.
- As an admin, I want to **feature, verify, and tag founding vendors** so I can curate and reward standout listings.
- As an admin, I want to **add vendors manually** and **bulk-import via CSV** (with a dry-run) so I can onboard at scale.
- As an admin, I want to **export vendors** in a round-trippable CSV so I can back up and edit in bulk.
- As an admin, I want **approved vendors to sync to Mailchimp automatically** so my marketing list stays current without overwriting real names and cities.
- As an admin, I want **analytics and an activity log** so I can understand growth and trace what changed and who changed it.
- As an admin, I want a **security health score** so I know the site is hardened.
- As an admin, I want to **email cohorts of vendors** (e.g. unverified, pending) so I can nudge them to complete onboarding.

---

## 6. Visitor & Vendor Journeys (at a glance)

**Customer journey:** Discover (hero search / category grid) → Explore (directory → vendor profile) → Contact (WhatsApp / email / social / contact form) → optionally Request a vendor.

**Vendor journey:** Register (minimal) → Verify email → Build profile (dashboard) → Submit for review → Admin approves / requests changes → Listing live → Manage anytime → Auto-synced to Mailchimp.

**Admin journey:** New application notification → Review in vendors list → Approve/reject → Curate (feature/verify) → Monitor (analytics, activity, security) → Grow (import/export, vendor emails).

---

_This document describes Phase 1 (the current MVP). Planned enhancements — reviews & ratings, geolocation search, event pages & gift registry, referral programme, vendor verification, blog, planning resources, social logins, and homepage spotlights — are covered in **PHASE-2-PLAN.md**._
