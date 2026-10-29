# BDMS - Bus and Dispatchment Monitoring System: Design

## Intent
A private, online GPS tracking system for Philippine bus companies. Each bus carries an Android phone running a tracker app. The phone's own GPS is sent to the server in real time. A company admin watches all their buses on a live, Waze-style map.

## Goals
- Admin sees live bus positions, moving smoothly, updated every few seconds.
- Tracker keeps working with screen off, survives reboot, buffers points offline.
- Waze-like look: dark, rounded, clean, mobile-friendly.
- Hostable on a plain PHP + MySQL server (XAMPP locally, shared/VPS online).

## Non-goals
Passenger app, ETA/routing, fares, SMS, payments.

## Anti-goals
Strangers registering fake buses; one company seeing another's buses; tracker draining the battery or dying in the background.

## Decisions (from the user)
- Phone number is NOT used for identity or tracking. Dropped.
- Identity = admin-created bus + pairing code typed once in the app.
- Native Android tracker APK (built on this PC; Android SDK present).
- Hosted online on a private server; Philippines focus.

## Builder's calls (not user-confirmed; change freely)
- Stack: plain PHP 8 + PDO + MySQL, no framework. Vanilla JS front end.
- Map: Leaflet + OpenStreetMap tiles (CARTO dark basemap), polling every 3s.
- Multi-company: `companies` table; every admin, bus and location row is scoped by company.
- Device auth: after pairing, app holds a random API token; sent as Bearer header.
- Tracker: Kotlin, FusedLocationProvider in a foreground service, ~5s interval, local queue (Room-free: simple file/SharedPreferences queue), boot receiver.
- Extras: route history page per bus; offline (no update >2 min) status.

## Data model
- companies(id, name, created_at)
- admins(id, company_id, email, password_hash, name)
- buses(id, company_id, name, plate, contact_no NULL, pairing_code, pairing_used, api_token NULL, created_at)
- locations(id, bus_id, lat, lng, speed, heading, battery, recorded_at, received_at)
- buses_last: last position columns on buses (last_lat, last_lng, last_speed, last_battery, last_seen) for fast map reads.

## API
- POST /api/pair.php {code, device_name} -> {token, bus}
- POST /api/location.php (Bearer token) {points:[{lat,lng,speed,heading,battery,t}]} -> {ok}
- GET  /api/buses.php (admin session) -> buses with last positions + status
- GET  /api/history.php?bus_id&from&to (admin session) -> points

## Screens
Admin: login, live map (bus list sidebar + map), buses CRUD with pairing code, bus history replay, register-company/first-admin setup page.
Tracker: pairing screen, "Tracking ON" status screen with stop/logout.

## Verification
Simulate a tracker with curl posting points; dashboard shows moving marker; build APK with Gradle and confirm it assembles.
