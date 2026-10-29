package ph.bdms.tracker

import org.junit.Assert.assertEquals
import org.junit.Assert.assertTrue
import org.junit.Rule
import org.junit.Test
import org.junit.rules.TemporaryFolder
import org.json.JSONObject

class PointQueueTest {
    @get:Rule val tmp = TemporaryFolder()

    private fun p(t: Long) = Point(14.5995, 120.9842, 30.0, 90.0, 80, t)

    @Test fun add_peek_drop_keeps_order() {
        val q = PointQueue(tmp.newFile("q.txt"))
        for (i in 1L..5L) q.add(p(i))
        assertEquals(listOf(1L, 2L, 3L), q.peek(3).map { it.t })
        q.drop(2)
        assertEquals(listOf(3L, 4L, 5L), q.peek(10).map { it.t })
        assertEquals(3, q.size())
    }

    @Test fun survives_reinstantiation() {
        val f = tmp.newFile("q.txt")
        PointQueue(f).apply { add(p(10)); add(p(11)) }
        val again = PointQueue(f)
        assertEquals(listOf(10L, 11L), again.peek(10).map { it.t })
    }

    @Test fun drops_oldest_beyond_cap() {
        val q = PointQueue(tmp.newFile("q.txt"), cap = 3)
        for (i in 1L..5L) q.add(p(i))
        assertEquals(listOf(3L, 4L, 5L), q.peek(10).map { it.t })
    }

    @Test fun ignores_corrupt_lines() {
        val f = tmp.newFile("q.txt")
        f.writeText("garbage\n14.5,121.0,10.0,5.0,70,99\n")
        assertEquals(listOf(99L), PointQueue(f).peek(10).map { it.t })
    }

    @Test fun upload_json_has_expected_shape() {
        val json = JSONObject(ApiClient.buildUploadJson(listOf(p(1700000000L))))
        val pt = json.getJSONArray("points").getJSONObject(0)
        assertEquals(14.5995, pt.getDouble("lat"), 1e-9)
        assertEquals(120.9842, pt.getDouble("lng"), 1e-9)
        assertEquals(30.0, pt.getDouble("speed"), 1e-9)
        assertEquals(80, pt.getInt("battery"))
        assertEquals(1700000000L, pt.getLong("t"))
    }

    @Test fun base_url_is_normalised() {
        assertEquals("https://bus.example.com", ApiClient.normalizeBase(" bus.example.com/ "))
        assertEquals("http://10.0.2.2:8081/BDMS", ApiClient.normalizeBase("http://10.0.2.2:8081/BDMS/"))
        assertTrue(ApiClient.normalizeBase("").isEmpty())
    }
}
