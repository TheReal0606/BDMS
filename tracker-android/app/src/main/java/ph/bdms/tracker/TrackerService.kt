package ph.bdms.tracker

import android.annotation.SuppressLint
import android.app.Notification
import android.app.NotificationChannel
import android.app.NotificationManager
import android.app.PendingIntent
import android.app.Service
import android.content.Context
import android.content.Intent
import android.content.IntentFilter
import android.location.Location
import android.location.LocationListener
import android.location.LocationManager
import android.os.BatteryManager
import android.os.Build
import android.os.Handler
import android.os.IBinder
import android.os.Looper
import android.os.PowerManager
import java.io.File
import java.util.concurrent.Executors

/** Foreground service: collects GPS every ~5 s and uploads queued points whenever there is signal. */
class TrackerService : Service() {
    private lateinit var prefs: Prefs
    private lateinit var queue: PointQueue
    private lateinit var lm: LocationManager
    private var wake: PowerManager.WakeLock? = null
    private val io = Executors.newSingleThreadExecutor()
    private val main = Handler(Looper.getMainLooper())
    private var lastQueuedAt = 0L

    private val listener = object : LocationListener {
        override fun onLocationChanged(loc: Location) = onFix(loc)
        @Deprecated("legacy") override fun onStatusChanged(p: String?, s: Int, e: android.os.Bundle?) {}
        override fun onProviderEnabled(p: String) {}
        override fun onProviderDisabled(p: String) {}
    }

    private val retry = object : Runnable {
        override fun run() { flush(); main.postDelayed(this, 15000) }
    }

    override fun onBind(i: Intent?): IBinder? = null

    override fun onCreate() {
        super.onCreate()
        prefs = Prefs(this)
        queue = PointQueue(File(filesDir, "points.csv"))
        lm = getSystemService(Context.LOCATION_SERVICE) as LocationManager
        val pm = getSystemService(Context.POWER_SERVICE) as PowerManager
        wake = pm.newWakeLock(PowerManager.PARTIAL_WAKE_LOCK, "bdms:tracker").apply { acquire() }
    }

    override fun onStartCommand(intent: Intent?, flags: Int, startId: Int): Int {
        if (!prefs.paired) { stopSelf(); return START_NOT_STICKY }
        startForeground(1, notification())
        subscribe()
        main.removeCallbacks(retry)
        main.post(retry)
        return START_STICKY
    }

    @SuppressLint("MissingPermission")
    private fun subscribe() {
        try {
            lm.removeUpdates(listener)
            if (lm.isProviderEnabled(LocationManager.GPS_PROVIDER))
                lm.requestLocationUpdates(LocationManager.GPS_PROVIDER, INTERVAL_MS, 0f, listener, Looper.getMainLooper())
            if (lm.isProviderEnabled(LocationManager.NETWORK_PROVIDER))
                lm.requestLocationUpdates(LocationManager.NETWORK_PROVIDER, INTERVAL_MS, 0f, listener, Looper.getMainLooper())
        } catch (e: SecurityException) {
            // Location permission missing: stay up so the UI can show it and the user can grant it.
        }
    }

    private fun onFix(loc: Location) {
        val now = System.currentTimeMillis()
        if (loc.hasAccuracy() && loc.accuracy > 150f) return
        // GPS and network both report; keep roughly one point per interval.
        if (now - lastQueuedAt < INTERVAL_MS - 1000) return
        lastQueuedAt = now
        prefs.lastFixAt = now
        queue.add(Point(
            loc.latitude, loc.longitude,
            if (loc.hasSpeed()) loc.speed * 3.6 else 0.0,
            if (loc.hasBearing()) loc.bearing.toDouble() else 0.0,
            battery(), now / 1000
        ))
        prefs.queued = queue.size()
        flush()
    }

    private fun battery(): Int {
        val i = registerReceiver(null, IntentFilter(Intent.ACTION_BATTERY_CHANGED)) ?: return -1
        val level = i.getIntExtra(BatteryManager.EXTRA_LEVEL, -1)
        val scale = i.getIntExtra(BatteryManager.EXTRA_SCALE, 100)
        return if (level < 0) 0 else level * 100 / scale
    }

    private fun flush() {
        io.execute {
            if (!prefs.paired) return@execute
            while (true) {
                val batch = queue.peek(100)
                if (batch.isEmpty()) break
                when (ApiClient.upload(prefs.baseUrl, prefs.token, batch)) {
                    UploadResult.OK -> { queue.drop(batch.size); prefs.lastSentAt = System.currentTimeMillis() }
                    UploadResult.UNAUTHORIZED -> {
                        prefs.unpair()
                        main.post { stopSelf() }
                        return@execute
                    }
                    UploadResult.RETRY -> break
                }
            }
            prefs.queued = queue.size()
        }
    }

    private fun notification(): Notification {
        val nm = getSystemService(Context.NOTIFICATION_SERVICE) as NotificationManager
        if (Build.VERSION.SDK_INT >= 26)
            nm.createNotificationChannel(NotificationChannel(CHANNEL, "Tracking", NotificationManager.IMPORTANCE_LOW))
        val open = PendingIntent.getActivity(this, 0, Intent(this, MainActivity::class.java), PendingIntent.FLAG_IMMUTABLE)
        return Notification.Builder(this, CHANNEL)
            .setContentTitle("BDMS Tracking ON")
            .setContentText(prefs.busName.ifEmpty { "Sharing bus location" })
            .setSmallIcon(android.R.drawable.ic_menu_mylocation)
            .setContentIntent(open)
            .setOngoing(true)
            .build()
    }

    override fun onDestroy() {
        main.removeCallbacksAndMessages(null)
        try { lm.removeUpdates(listener) } catch (e: Exception) {}
        wake?.let { if (it.isHeld) it.release() }
        io.shutdown()
        super.onDestroy()
    }

    companion object {
        const val INTERVAL_MS = 5000L
        private const val CHANNEL = "bdms_tracking"

        fun start(ctx: Context) {
            val i = Intent(ctx, TrackerService::class.java)
            if (Build.VERSION.SDK_INT >= 26) ctx.startForegroundService(i) else ctx.startService(i)
        }
        fun stop(ctx: Context) { ctx.stopService(Intent(ctx, TrackerService::class.java)) }
    }
}
