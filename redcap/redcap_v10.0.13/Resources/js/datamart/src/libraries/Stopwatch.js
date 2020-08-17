class Stopwatch {

    time_start = 0
    time_end = 0
    /**
     * wheter the stopwatch is running
     */
    is_running = false

    constructor(start=false) {
        if(start) this.start()
    }

    start() {
        if(this.is_running) return
        this.time_start = performance.now()
        this.is_running = true
    }

    /**
     * stop the time and return the total
     */
    stop() {
        if(!this.is_running) return this.total
        this.time_end = performance.now()
        this.is_running = false
        return this.total
    }

    reset() {
        this.start = 0
        this.end = 0
    }

    /**
     * total time in milliseconds
     */
    get total() {
        if(this.is_running && this.time_start>0) {
            const total = performance.now()-this.time_start
            return total
        }else {
            return this.time_end-this.time_start
        }
        
    }
}

export default Stopwatch