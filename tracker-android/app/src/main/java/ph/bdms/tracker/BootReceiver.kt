package ph.bdms.tracker

import android.content.BroadcastReceiver
import android.content.Context
import android.content.Intent

/** Restart tracking after reboot / app update if the driver had it on. Best effort: newer Android may refuse. */
class BootReceiver : BroadcastReceiver() {
    override fun onReceive(ctx: Context, intent: Intent) {
        val p = Prefs(ctx)
        if (p.paired && p.wantTracking) {
            try { TrackerService.start(ctx) } catch (e: Exception) { /* app must be opened once */ }
        }
    }
}
