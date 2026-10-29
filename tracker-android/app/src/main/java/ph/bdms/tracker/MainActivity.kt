package ph.bdms.tracker

import android.Manifest
import android.app.Activity
import android.content.Context
import android.content.Intent
import android.content.pm.PackageManager
import android.graphics.Color
import android.graphics.drawable.GradientDrawable
import android.net.Uri
import android.os.Build
import android.os.Bundle
import android.os.Handler
import android.os.Looper
import android.os.PowerManager
import android.provider.Settings
import android.text.InputType
import android.view.Gravity
import android.view.View
import android.widget.Button
import android.widget.EditText
import android.widget.LinearLayout
import android.widget.ScrollView
import android.widget.TextView
import java.text.SimpleDateFormat
import java.util.Date
import java.util.Locale
import java.util.concurrent.Executors

class MainActivity : Activity() {
    private lateinit var prefs: Prefs
    private val ui = Handler(Looper.getMainLooper())
    private val io = Executors.newSingleThreadExecutor()
    private lateinit var root: LinearLayout
    private var status: TextView? = null
    private var error: TextView? = null

    private val BG = Color.parseColor("#0f1720")
    private val PANEL = Color.parseColor("#18222e")
    private val ACCENT = Color.parseColor("#33ccff")
    private val MUTED = Color.parseColor("#8fa3b8")

    private val tick = object : Runnable {
        override fun run() { refreshStatus(); ui.postDelayed(this, 1000) }
    }

    override fun onCreate(b: Bundle?) {
        super.onCreate(b)
        prefs = Prefs(this)
        val scroll = ScrollView(this).apply { setBackgroundColor(BG) }
        root = LinearLayout(this).apply { orientation = LinearLayout.VERTICAL; setPadding(dp(22), dp(48), dp(22), dp(30)) }
        scroll.addView(root)
        setContentView(scroll)
    }

    override fun onResume() {
        super.onResume()
        build()
        ui.post(tick)
        if (prefs.paired && prefs.wantTracking && hasLocation()) TrackerService.start(this)
    }

    override fun onPause() { ui.removeCallbacks(tick); super.onPause() }

    private fun dp(v: Int) = (v * resources.displayMetrics.density).toInt()

    private fun text(s: String, size: Float, color: Int = Color.WHITE, bold: Boolean = false) = TextView(this).apply {
        text = s; textSize = size; setTextColor(color)
        if (bold) setTypeface(typeface, android.graphics.Typeface.BOLD)
        setPadding(0, dp(4), 0, dp(4))
    }

    private fun input(hint: String) = EditText(this).apply {
        this.hint = hint; setHintTextColor(MUTED); setTextColor(Color.WHITE)
        inputType = InputType.TYPE_CLASS_TEXT or InputType.TYPE_TEXT_FLAG_NO_SUGGESTIONS
        setSingleLine(); setPadding(dp(16), dp(12), dp(16), dp(12))
        background = GradientDrawable().apply { setColor(PANEL); cornerRadius = dp(14).toFloat() }
        layoutParams = LinearLayout.LayoutParams(-1, -2).apply { topMargin = dp(10) }
    }

    private fun button(label: String, primary: Boolean, onClick: () -> Unit) = Button(this).apply {
        text = label; isAllCaps = false; textSize = 16f
        setTextColor(if (primary) Color.parseColor("#04202c") else Color.WHITE)
        background = GradientDrawable().apply { setColor(if (primary) ACCENT else PANEL); cornerRadius = dp(26).toFloat() }
        layoutParams = LinearLayout.LayoutParams(-1, dp(52)).apply { topMargin = dp(14) }
        setOnClickListener { onClick() }
    }

    private fun build() {
        root.removeAllViews()
        status = null; error = null
        root.addView(text("BDMS Tracker", 26f, Color.WHITE, true))
        if (!prefs.paired) buildPair() else buildTracking()
    }

    private fun buildPair() {
        root.addView(text("Enter the pairing code from your bus company's dashboard.", 15f, MUTED))
        val server = input("Server address (e.g. bdms.example.com)").apply { setText(prefs.baseUrl) }
        val code = input("Pairing code (6 characters)").apply {
            inputType = InputType.TYPE_CLASS_TEXT or InputType.TYPE_TEXT_FLAG_CAP_CHARACTERS or InputType.TYPE_TEXT_FLAG_NO_SUGGESTIONS
            filters = arrayOf(android.text.InputFilter.LengthFilter(6))
        }
        root.addView(server); root.addView(code)
        val err = text("", 14f, Color.parseColor("#ff8d84")); error = err
        root.addView(err)
        root.addView(button("Pair this phone", true) {
            val base = ApiClient.normalizeBase(server.text.toString())
            val c = code.text.toString().trim()
            if (base.isEmpty() || c.length != 6) { err.text = "Enter the server address and the 6-character code."; return@button }
            err.text = "Pairing..."
            io.execute {
                try {
                    val r = ApiClient.pair(base, c, "${Build.MANUFACTURER} ${Build.MODEL}")
                    prefs.baseUrl = base; prefs.token = r.token; prefs.busName = r.busName; prefs.plate = r.plate
                    prefs.wantTracking = true
                    ui.post { build(); startTracking() }
                } catch (e: PairException) {
                    ui.post { err.text = e.message }
                }
            }
        })
    }

    private fun buildTracking() {
        root.addView(text("${prefs.busName}  ·  ${prefs.plate}", 18f, ACCENT, true))
        val s = text("", 22f, Color.WHITE, true); status = s
        s.setPadding(0, dp(18), 0, dp(6))
        root.addView(s)
        val err = text("", 14f, Color.parseColor("#f5b041")); error = err
        root.addView(err)
        root.addView(button("Start tracking", true) { prefs.wantTracking = true; startTracking() })
        root.addView(button("Stop tracking", false) { prefs.wantTracking = false; TrackerService.stop(this); refreshStatus() })
        root.addView(button("Unlink this phone", false) {
            prefs.unpair(); TrackerService.stop(this); build()
        })
        root.addView(text("Keep mobile data on and the phone charging. Location must be set to \"Allow all the time\".", 13f, MUTED).apply { setPadding(0, dp(18), 0, 0) })
    }

    private fun refreshStatus() {
        val s = status ?: return
        val fmt = { t: Long -> if (t == 0L) "never" else SimpleDateFormat("h:mm:ss a", Locale.US).format(Date(t)) }
        val on = prefs.wantTracking && hasLocation()
        s.text = (if (on) "Tracking ON" else "Tracking OFF") +
            "\nLast GPS fix: ${fmt(prefs.lastFixAt)}\nLast sent: ${fmt(prefs.lastSentAt)}\nWaiting to upload: ${prefs.queued}"
        s.setTextColor(if (on) Color.parseColor("#2ecc71") else Color.parseColor("#e74c3c"))
        s.textSize = 16f
        error?.text = when {
            !hasLocation() -> "Location permission is needed."
            !bgLocation() -> "Set location to \"Allow all the time\" so tracking works with the screen off."
            else -> ""
        }
    }

    private fun hasLocation() = checkSelfPermission(Manifest.permission.ACCESS_FINE_LOCATION) == PackageManager.PERMISSION_GRANTED
    private fun bgLocation() = Build.VERSION.SDK_INT < 29 ||
        checkSelfPermission(Manifest.permission.ACCESS_BACKGROUND_LOCATION) == PackageManager.PERMISSION_GRANTED

    private fun startTracking() {
        val need = ArrayList<String>()
        if (!hasLocation()) need.add(Manifest.permission.ACCESS_FINE_LOCATION)
        if (Build.VERSION.SDK_INT >= 33 && checkSelfPermission(Manifest.permission.POST_NOTIFICATIONS) != PackageManager.PERMISSION_GRANTED)
            need.add(Manifest.permission.POST_NOTIFICATIONS)
        if (need.isNotEmpty()) { requestPermissions(need.toTypedArray(), 1); return }
        afterForeground()
    }

    private fun afterForeground() {
        if (!hasLocation()) return
        TrackerService.start(this)
        // Android 11+ requires background location to be requested separately, after foreground.
        if (!bgLocation()) {
            if (Build.VERSION.SDK_INT >= 30) {
                startActivity(Intent(Settings.ACTION_APPLICATION_DETAILS_SETTINGS, Uri.parse("package:$packageName")))
            } else requestPermissions(arrayOf(Manifest.permission.ACCESS_BACKGROUND_LOCATION), 2)
        }
        val pm = getSystemService(Context.POWER_SERVICE) as PowerManager
        if (!pm.isIgnoringBatteryOptimizations(packageName)) {
            try { startActivity(Intent(Settings.ACTION_REQUEST_IGNORE_BATTERY_OPTIMIZATIONS, Uri.parse("package:$packageName"))) }
            catch (e: Exception) { /* some devices lack this screen */ }
        }
    }

    override fun onRequestPermissionsResult(code: Int, perms: Array<out String>, res: IntArray) {
        super.onRequestPermissionsResult(code, perms, res)
        if (code == 1) afterForeground()
    }
}
