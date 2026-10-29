package ph.bdms.tracker

import android.content.Context
import android.content.SharedPreferences

/** Persistent pairing + status shared by the UI and the service. */
class Prefs(ctx: Context) {
    private val sp: SharedPreferences = ctx.getSharedPreferences("bdms", Context.MODE_PRIVATE)

    var baseUrl: String
        get() = sp.getString("base", "") ?: ""
        set(v) = sp.edit().putString("base", v).apply()
    var token: String
        get() = sp.getString("token", "") ?: ""
        set(v) = sp.edit().putString("token", v).apply()
    var busName: String
        get() = sp.getString("bus", "") ?: ""
        set(v) = sp.edit().putString("bus", v).apply()
    var plate: String
        get() = sp.getString("plate", "") ?: ""
        set(v) = sp.edit().putString("plate", v).apply()
    /** User wants tracking on (survives reboot). */
    var wantTracking: Boolean
        get() = sp.getBoolean("want", false)
        set(v) = sp.edit().putBoolean("want", v).apply()
    var lastSentAt: Long
        get() = sp.getLong("lastSent", 0)
        set(v) = sp.edit().putLong("lastSent", v).apply()
    var lastFixAt: Long
        get() = sp.getLong("lastFix", 0)
        set(v) = sp.edit().putLong("lastFix", v).apply()
    var queued: Int
        get() = sp.getInt("queued", 0)
        set(v) = sp.edit().putInt("queued", v).apply()

    val paired: Boolean get() = token.isNotEmpty()

    fun unpair() {
        sp.edit().remove("token").remove("bus").remove("plate").putBoolean("want", false).apply()
    }
}
