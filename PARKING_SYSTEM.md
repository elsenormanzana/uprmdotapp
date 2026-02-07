# UPRM DOT – Parking & Infractions System

Three role-based areas: **Admin**, **Security Guard**, and **Student**.

## Roles & Access

| Role | Routes | Features |
|------|--------|----------|
| **Admin** | `/admin/*` | Search infractions (multas), create permit types (blue, white, yellow, orange, green, violet), GeoSelect permit zones (map + JSON polygon), assign permits to students (validate via Student ID / QR) |
| **Security Guard** | `/guard/*` | Issue vehicle infractions, validate Student ID (scan QR or enter manually) |
| **Student** | `/student/*` | Register vehicles, request permits, pay infraction balance, campus map (parking types), My ID & QR (Student ID + QR code) |

## Running the App

```bash
composer install
cp .env.example .env && php artisan key:generate
php artisan migrate --force
php artisan db:seed --force
npm install && npm run build
php artisan serve
```

For development with Vite:

```bash
composer dev   # serves app, queue, logs, Vite
```

## Seeded Accounts

| Role | Email | Password |
|------|--------|----------|
| Admin | `admin@uprm.edu` | `password` |
| Security Guard | `guard@uprm.edu` | `password` |

Students register at `/register` (requires **Student ID**). New users get role `student` by default.

## QR Code & Maps

- **Student ID QR**: Shown on **My ID & QR** (`/student/profile`). Uses [QR Server API](https://api.qrserver.com/) for generation. Admin/Guard validate by scanning or entering Student ID.
- **Map views**: **Permit Zones** (admin) and **Campus Map** (student) use **Leaflet** with **OpenStreetMap** tiles (not Google Maps). Admin defines zones via JSON polygons (e.g. `[[18.21,-67.14],[18.22,-67.13],...]`). Maps work on full page load and with `wire:navigate`. Leaflet is loaded globally in the layout.

## Routes Overview

- `/` → Welcome  
- `/dashboard` → Redirects by role to `/admin`, `/guard`, or `/student`  
- `/admin` – dashboard, infractions, permit-types, permit-zones, assign-permit  
- `/guard` – dashboard, issue-infraction, validate-student  
- `/student` – dashboard, vehicles, infractions, campus-map, profile (ID & QR)
