# BDMS - Bus and Dispatchment Monitoring System

Live GPS tracking for bus companies. Each bus carries an Android phone running the **BDMS Tracker** app.
The company admin watches all buses on a live map (dark, Waze-style).

## Local setup (XAMPP)
1. Start Apache + MySQL. This install serves on **http://localhost:8081/BDMS/**.
2. Database `bdms` and user are already created; credentials are in `config.php`. To recreate: import `schema.sql`.
3. Open `/setup.php` and create the first company + admin. (Setup is open only until the first admin exists.)
4. **Buses** page -> add a bus -> a 6-character **pairing code** appears.
5. Install `downloads/bdms-tracker.apk` on the bus phone, enter the server address and the code. Done.

## How it works
- No phone number is used. The phone is identified by a token it receives when paired with the code.
- Tracker sends GPS about every 5 s; with no signal it queues points and uploads them later.
- Status: moving (> 2 km/h), stopped, offline (no contact for 120 s).
- Multiple companies are supported; admins only see their own company's buses.
- "New code" on the Buses page unlinks the old phone and issues a fresh code.

## Deploy to an online server
- PHP 8+ with `pdo_mysql`, MySQL/MariaDB, Apache (`.htaccess` is used) - or translate `.htaccess` rules for nginx.
- Upload the folder (leave out `tests/`, `docs/`, `tracker-android/` if you like), create a database, import `schema.sql`, edit `config.php`.
- **Use HTTPS.** Tokens and passwords travel over the network. Then open `/setup.php` immediately to create your admin.
- Map tiles: `assets/tiles.js` uses public OpenStreetMap tiles with a dark filter. For a production fleet use a tile
  provider with an API key (MapTiler, Stadia, Mapbox) and change `url`/`attribution` there.

## Android tracker
Source: `tracker-android/`. Build (Windows, this PC):
```
set JAVA_HOME=C:\Program Files\Android\Android Studio1\jbr
set ANDROID_HOME=C:\Users\MTRCB\AppData\Local\Android\Sdk
gradle testDebugUnitTest assembleDebug
```
Output: `app/build/outputs/apk/debug/app-debug.apk` (a debug-signed APK, fine for installing directly on your own phones).
- The manifest allows plain `http://` (for testing on your LAN). Remove `android:usesCleartextTraffic="true"` before using HTTPS only.
- Phone setup: mobile data on, location **Allow all the time**, battery optimization off for the app, keep it charging.
- Restart after reboot is best effort; newer Android versions may require opening the app once after boot.

## Dev tools
- `php tests/run.php` - helper tests. `php tests/api_test.php` - API tests (Apache must be running; they create and remove their own data).
- `php tests/simulate.php CODE 60` - pretend to be a bus moving through Metro Manila.
