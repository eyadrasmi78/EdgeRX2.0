# EdgeRX — Customer Training Guide

> **Production URL:** https://www.edgerx.app
> *(The bare `edgerx.app` redirects to `www.edgerx.app`. Use the `www.` form for API calls.)*

This document is the canonical reference for the demo accounts, their roles, and the suggested walkthrough flow for a customer training session.

---

## 1. Demo accounts (live in production)

All accounts share the password `password` except the admin which uses `admin`. Login uses **email** (the field is labelled "Email" on the login screen).

| Role | Login (email) | Password | Display name | Notes |
|------|---------------|----------|--------------|-------|
| **ADMIN** | `admin` | `admin` | Admin User | Platform operator. Approves registrations, curates buying groups, approves agreements. |
| **SUPPLIER** | `supplier@mediglobal.com` | `password` | MediGlobal Suppliers | Primary local distributor. Has the largest catalog. |
| **SUPPLIER** | `info@gulfhealth.ae` | `password` | Gulf Health Agents | Secondary local distributor. Mid-size catalog. |
| **CUSTOMER** | `hospital@citygeneral.com` | `password` | City General Hospital | Hospital procurement buyer. Has multiple active orders. |
| **FOREIGN_SUPPLIER** | `global@biotech-germany.com` | `password` | BioTech Germany | International manufacturer using the partnership pipeline. |
| **PHARMACY_MASTER** | `master@gulfgroup.kw` | `password` | Gulf Pharmacy Group | Owns 4 child pharmacies (PharmaZone 1–4). |

### Pharmacy Master's child pharmacies
The master at `master@gulfgroup.kw` owns these 4 child pharmacies. Each is also a CUSTOMER account that can log in independently:

| Email | Name | Location |
|-------|------|----------|
| `pharmazone1@example.com` | PharmaZone 1 | Salmiya, Block 4 |
| `pharmazone2@example.com` | PharmaZone 2 | Hawalli, Tunis St. |
| `pharmazone3@example.com` | PharmaZone 3 | Salmiya, Block 11 |
| `pharmazone4@example.com` | PharmaZone 4 | Farwaniya |

All child pharmacies use password `password`.

### Buying-group seeded customer accounts
These three CUSTOMER accounts exist specifically to demo the Buying Group feature:

| Email | Name |
|-------|------|
| `alsalam@pharma.kw` | Al-Salam Pharmacy |
| `mishref@medical.kw` | Mishref Medical Center |
| `sabah@hospital.kw` | Sabah Hospital Procurement |

All use password `password`.

---

## 2. Suggested 30-minute training flow

### Chapter 1 — Login + landing (2 min)
1. Open `https://www.edgerx.app/`.
2. Show the language toggle (top-right globe icon) — switch to Arabic and back.
3. Log in as `admin` / `admin`.
4. Tour the navigation: Home, Admin, Transfers, Agreements.

### Chapter 2 — Customer journey (8 min)
1. Log out, log in as `hospital@citygeneral.com` / `password`.
2. **Catalog**: search and filter products across both suppliers.
3. **Cart**: add 3 items to cart, open the drawer (right side).
4. **Checkout**: place the order. Show the success toast.
5. **My Requests** tab: view the new order, open chat, open status history.
6. **Buying Groups** tab: show any pending invitations.
7. **Transfers** tab: explore the marketplace listings (if any).
8. **Agreements** tab: review any active pricing agreements.

### Chapter 3 — Supplier journey (8 min)
1. Log in as `supplier@mediglobal.com` / `password`.
2. **Orders** tab: see the order the customer just placed.
3. Move it through `In Progress → Shipment OTW → Delivered`.
4. **Catalog Management**: add a new product (any details).
5. **Agreements** tab: draft a new agreement → send to a customer.
6. **Transfers** tab: show the QC console (if there's a pending transfer).
7. **Partnerships**: show inbound from `BioTech Germany`.

### Chapter 4 — Pharmacy Master / chain journey (5 min)
1. Log in as `master@gulfgroup.kw` / `password`.
2. **Catalog**: add a product to cart for **PharmaZone 1**.
3. Switch the on-behalf-of selector and add a second item for **PharmaZone 2**.
4. Cart drawer now shows two-level grouping by child pharmacy.
5. Checkout — both child pharmacies receive their own order.
6. **My Requests** tab now shows orders for all 4 children consolidated.

### Chapter 5 — Admin oversight (5 min)
1. Log back in as `admin` / `admin`.
2. **Admin Portal → Users**: approve/reject a pending registration.
3. **Buying Groups**: create a new group (3 customers, target qty 100 of any product).
4. Switch to one of the customer accounts → COMMIT and ACCEPT.
5. Back to admin → release. N orders fire at the supplier.
6. **Agreements queue**: approve any pending agreement.

### Chapter 6 — Foreign supplier (2 min)
1. Log in as `global@biotech-germany.com` / `password`.
2. View profile, partnership requests pipeline.
3. Show how a local agent can browse foreign listings.

---

## 3. Live URLs

| URL | Purpose |
|-----|---------|
| https://www.edgerx.app/ | Customer-facing SPA |
| https://www.edgerx.app/api/healthz | Backend liveness probe (returns `{"status":"ok"}`) |
| https://www.edgerx.app/sanctum/csrf-cookie | CSRF priming endpoint (called automatically by the SPA) |

---

## 4. Reset / re-seed (DigitalOcean)

To re-seed the demo data on production (e.g. after the customer modifies records during a training session):

1. Set the env var `RUN_DEMO_SEEDER=true` on the `backend` component in DO App Platform.
2. Trigger a redeploy.
3. The boot script will run `php artisan db:seed --force --class=DemoDataSeeder` which is **idempotent** — it `updateOrCreate`s every demo row.
4. Once seeding is complete, **unset `RUN_DEMO_SEEDER`** and trigger another deploy so future deploys don't reseed.

---

## 5. Known operational notes

- **Login field is `email`, not `username`.** Frontend payload is `{"email": "...", "password": "..."}`.
- **Notifications email links** point to `https://edgerx.app` (config-cached, post-fix).
- **Rate limits** are intentionally lenient on demo accounts so live training isn't throttled.
- **Tailwind CDN console warning** is suppressed at the page-load level (post-fix). Safe to ignore if it ever resurfaces.
- **Demo seeder includes** ~21 products, 11 feed items, 1 sample order, 4 bonus rules, 1 master + 4 children, 3 buying-group customers, and 7 user accounts spanning all roles.

---

## 6. If something goes wrong during the demo

| Symptom | Likely cause | Quick recovery |
|---------|--------------|----------------|
| Login fails with 401 | Cookie not set | Hard refresh (`Cmd+Shift+R`) — re-primes CSRF |
| All API calls 401 mid-session | Session expired (2h SPA TTL) | Log out + log back in |
| Bell icon shows nothing | No unread notifications for that user | Try a customer account; admin accounts often have many |
| Arabic flips look wrong on a specific page | RTL fix outstanding for that component | Switch back to EN and continue; bug already on roadmap |
| Order status update returns 403 | Role-gated transition (post-BE-9 fix) | Check that the right user is acting on the order |
| Transfer or agreement page empty | No fixtures for that role yet | Use admin account to seed one inline |

---

*Document version: 2026-05-04 — generated post-blocker-fix sweep (commit `c76be5c`).*
