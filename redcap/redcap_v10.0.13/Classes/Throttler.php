<?php

class Throttler
{

    /**
     * HTTP status code
     */
    const ERROR_CODE = 429; // 429 = too many requests

    /**
     * maximum age of PHP thread
     * https://dev.mysql.com/doc/refman/5.5/en/expressions.html#temporal-intervals
     */
    private $max_pid_age = '1 HOUR';

    /**
     * constructor
     *
     * @param string $max_pid_age interval used in MySQL query to skip old PHP threads
     */
    public function __construct($max_pid_age=null)
    {
        if($max_pid_age) $this->max_pid_age = $max_pid_age;
    }

    /**
     * get the running requets for a specific page
     *
     * @param string $page
     * @return object[]
     */
    private function getActivePageRequestse($page)
    {
        $pid = getmypid();
        $query_string = sprintf('SELECT
                                view.log_view_id, view.ts, view.page, view.user, view.session_id,
                                requests.mysql_process_id, requests.php_process_id, requests.script_execution_time,
                                requests.ui_id
                                
                                FROM redcap_log_view AS view
                                LEFT JOIN redcap_log_view_requests AS requests ON view.log_view_id = requests.log_view_id
                                LEFT JOIN redcap_sessions AS sessions ON sessions.session_id = view.session_id

                                WHERE requests.script_execution_time IS NULL
                                AND requests.php_process_id IS NOT NULL
                                # ignore requests older than INTERVAL
                                AND view.ts > DATE_SUB("%s", INTERVAL %s)
                                # ignore current PID
                                AND requests.php_process_id != %u
                                AND view.page = "%s"
                                ORDER BY view.ts DESC', NOW, db_real_escape_string($this->max_pid_age), $pid, db_real_escape_string($page) ) ;
        $result = db_query($query_string);
        $list = array();
        while($object = db_fetch_object($result))
        {
            $list[] = $object;
        }
        return $list;
    }

    /**
     * throttle the request of a page if there are too many active requests
     *
     * @param string $page
     * @param integer $limit
     * @return boolean true if the request must be throttled
     */
    public function throttle($page, $limit)
    {
        $activeRequests = $this->getActivePageRequestse($page);
        return count($activeRequests) >= $limit;
    }

}