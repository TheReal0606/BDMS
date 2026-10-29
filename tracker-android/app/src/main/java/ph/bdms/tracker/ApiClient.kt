package ph.bdms.tracker

import org.json.JSONArray
import org.json.JSONObject
import java.net.HttpURLConnection
import java.net.URL

data class PairResult(val token: String, val busName: String, val plate: String)

enum class UploadResult { OK, UNAUTHORIZED, RETRY }

class PairException(message: String) : Exception(message)

object ApiClient {
    private const val TIMEOUT_MS = 12000

    /** "bus.example.com/" -> "https://bus.example.com"; blank stays blank. */
    fun normalizeBase(raw: String): String {
        var s = raw.trim().trimEnd('/')
        if (s.isEmpty()) return ""
        if (!s.startsWith("http://") && !s.startsWith("https://")) s = "https://$s"
        return s
    }

    fun buildUploadJson(points: List<Point>): String {
        val arr = JSONArray()
        points.forEach {
            arr.put(JSONObject()
                .put("lat", it.lat).put("lng", it.lng)
                .put("speed", it.speed).put("heading", it.heading)
                .put("battery", it.battery).put("t", it.t))
        }
        return JSONObject().put("points", arr).toString()
    }

    fun pair(baseUrl: String, code: String, deviceName: String): PairResult {
        val body = JSONObject().put("code", code.trim().uppercase()).put("device_name", deviceName).toString()
        val (status, text) = try {
            post("${normalizeBase(baseUrl)}/api/pair.php", body, null)
        } catch (e: Exception) {
            throw PairException("Cannot reach server. Check the address and your internet.")
        }
        val json = try { JSONObject(text) } catch (e: Exception) { throw PairException("Unexpected server reply ($status).") }
        if (status != 200) throw PairException(json.optString("error", "Pairing failed ($status)"))
        val bus = json.getJSONObject("bus")
        return PairResult(json.getString("token"), bus.getString("name"), bus.getString("plate"))
    }

    fun upload(baseUrl: String, token: String, points: List<Point>): UploadResult {
        return try {
            val (status, _) = post("${normalizeBase(baseUrl)}/api/location.php", buildUploadJson(points), token)
            when {
                status == 200 -> UploadResult.OK
                status == 401 -> UploadResult.UNAUTHORIZED
                else -> UploadResult.RETRY
            }
        } catch (e: Exception) { UploadResult.RETRY }
    }

    private fun post(url: String, body: String, token: String?): Pair<Int, String> {
        val c = URL(url).openConnection() as HttpURLConnection
        try {
            c.requestMethod = "POST"
            c.connectTimeout = TIMEOUT_MS
            c.readTimeout = TIMEOUT_MS
            c.doOutput = true
            c.setRequestProperty("Content-Type", "application/json")
            if (token != null) c.setRequestProperty("Authorization", "Bearer $token")
            c.outputStream.use { it.write(body.toByteArray()) }
            val status = c.responseCode
            val stream = if (status >= 400) c.errorStream else c.inputStream
            return status to (stream?.bufferedReader()?.use { it.readText() } ?: "")
        } finally { c.disconnect() }
    }
}
