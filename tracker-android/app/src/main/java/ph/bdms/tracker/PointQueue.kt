package ph.bdms.tracker

import java.io.File

data class Point(
    val lat: Double,
    val lng: Double,
    val speed: Double,   // km/h
    val heading: Double, // degrees
    val battery: Int,    // percent
    val t: Long          // unix seconds
)

/** File-backed FIFO of GPS points waiting to be uploaded. One CSV line per point. */
class PointQueue(private val file: File, private val cap: Int = 20000) {
    private val items = ArrayList<Point>()

    init {
        if (file.exists()) {
            file.readLines().forEach { parse(it)?.let(items::add) }
            trimToCap()
        }
    }

    @Synchronized fun add(p: Point) {
        items.add(p)
        if (items.size > cap) { trimToCap(); persist() }
        else file.appendText(format(p) + "\n")
    }

    @Synchronized fun peek(n: Int): List<Point> = items.take(n)

    @Synchronized fun drop(n: Int) {
        repeat(minOf(n, items.size)) { items.removeAt(0) }
        persist()
    }

    @Synchronized fun size(): Int = items.size

    private fun trimToCap() { while (items.size > cap) items.removeAt(0) }

    private fun persist() { file.writeText(items.joinToString("") { format(it) + "\n" }) }

    private fun format(p: Point) = "${p.lat},${p.lng},${p.speed},${p.heading},${p.battery},${p.t}"

    private fun parse(line: String): Point? {
        val f = line.split(',')
        if (f.size != 6) return null
        return try {
            Point(f[0].toDouble(), f[1].toDouble(), f[2].toDouble(), f[3].toDouble(), f[4].toInt(), f[5].toLong())
        } catch (e: NumberFormatException) { null }
    }
}
