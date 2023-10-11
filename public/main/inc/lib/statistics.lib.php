<?php
/* For licensing terms, see /license.txt */

use Chamilo\CoreBundle\Component\Utils\ChamiloApi;
use Chamilo\CoreBundle\Entity\UserRelUser;

/**
 * This class provides some functions for statistics.
 */
class Statistics
{
    /**
     * Converts a number of bytes in a formatted string.
     *
     * @param int $size
     *
     * @return string Formatted file size
     */
    public static function makeSizeString($size)
    {
        if ($size < pow(2, 10)) {
            return $size." bytes";
        }
        if ($size >= pow(2, 10) && $size < pow(2, 20)) {
            return round($size / pow(2, 10), 0)." KB";
        }
        if ($size >= pow(2, 20) && $size < pow(2, 30)) {
            return round($size / pow(2, 20), 1)." MB";
        }
        if ($size > pow(2, 30)) {
            return round($size / pow(2, 30), 2)." GB";
        }
    }

    /**
     * Count courses.
     *
     * @param string $categoryCode Code of a course category.
     *                             Default: count all courses.
     *
     * @return int Number of courses counted
     */
    public static function countCourses($categoryCode = null)
    {
        $course_table = Database::get_main_table(TABLE_MAIN_COURSE);
        $tblCourseCategory = Database::get_main_table(TABLE_MAIN_CATEGORY);
        $access_url_rel_course_table = Database::get_main_table(TABLE_MAIN_ACCESS_URL_REL_COURSE);
        $urlId = api_get_current_access_url_id();

        $categoryJoin = '';
        $categoryCondition = '';

        if (!empty($categoryCode)) {
            //$categoryJoin = " LEFT JOIN $tblCourseCategory course_category ON course.category_id = course_category.id ";
            //$categoryCondition = " course_category.code = '".Database::escape_string($categoryCode)."' ";
        }

        if (api_is_multiple_url_enabled()) {
            $sql = "SELECT COUNT(*) AS number
                    FROM ".$course_table." as c, $access_url_rel_course_table as u
                    $categoryJoin
                    WHERE u.c_id = c.id AND access_url_id='".$urlId."'";
            if (isset($categoryCode)) {
                $sql .= " AND $categoryCondition";
            }
        } else {
            $sql = "SELECT COUNT(*) AS number
                    FROM $course_table $categoryJoin";
            if (isset($categoryCode)) {
                $sql .= " WHERE $categoryCondition";
            }
        }

        $res = Database::query($sql);
        $obj = Database::fetch_object($res);

        return $obj->number;
    }

    /**
     * Count courses by visibility.
     *
     * @param int $visibility visibility (0 = closed, 1 = private, 2 = open, 3 = public) all courses
     *
     * @return int Number of courses counted
     */
    public static function countCoursesByVisibility($visibility = null)
    {
        if (!isset($visibility)) {
            return 0;
        }
        $course_table = Database::get_main_table(TABLE_MAIN_COURSE);
        $access_url_rel_course_table = Database::get_main_table(TABLE_MAIN_ACCESS_URL_REL_COURSE);
        $urlId = api_get_current_access_url_id();
        if (api_is_multiple_url_enabled()) {
            $sql = "SELECT COUNT(*) AS number
                    FROM $course_table as c, $access_url_rel_course_table as u
                    WHERE u.c_id = c.id AND access_url_id='".$urlId."'";
            if (isset($visibility)) {
                $sql .= " AND visibility = ".intval($visibility);
            }
        } else {
            $sql = "SELECT COUNT(*) AS number FROM $course_table ";
            if (isset($visibility)) {
                $sql .= " WHERE visibility = ".intval($visibility);
            }
        }
        $res = Database::query($sql);
        $obj = Database::fetch_object($res);

        return $obj->number;
    }

    /**
     * Count users.
     *
     * @param int    $status                user status (COURSEMANAGER or STUDENT) if not setted it'll count all users
     * @param string $categoryCode          course category code. Default: count only users without filtering category
     * @param bool   $countInvisibleCourses Count invisible courses (todo)
     * @param bool   $onlyActive            Count only active users (false to only return currently active users)
     *
     * @return int Number of users counted
     */
    public static function countUsers(
        $status = null,
        $categoryCode = null,
        $countInvisibleCourses = true,
        $onlyActive = false
    ) {
        // Database table definitions
        $course_user_table = Database::get_main_table(TABLE_MAIN_COURSE_USER);
        $course_table = Database::get_main_table(TABLE_MAIN_COURSE);
        $user_table = Database::get_main_table(TABLE_MAIN_USER);
        $access_url_rel_user_table = Database::get_main_table(TABLE_MAIN_ACCESS_URL_REL_USER);
        $tblCourseCategory = Database::get_main_table(TABLE_MAIN_CATEGORY);
        $tblCourseRelCategory = Database::get_main_table(TABLE_MAIN_COURSE_REL_CATEGORY);
        $urlId = api_get_current_access_url_id();
        $active_filter = $onlyActive ? ' AND active = 1' : '';
        $status_filter = isset($status) ? ' AND status = '.intval($status) : '';

        if (api_is_multiple_url_enabled()) {
            $sql = "SELECT COUNT(DISTINCT(u.id)) AS number
                    FROM $user_table as u, $access_url_rel_user_table as url
                    WHERE
                        u.id = url.user_id AND
                        access_url_id = $urlId
                        $status_filter $active_filter";
            if (isset($categoryCode)) {
                $categoryCode = Database::escape_string($categoryCode);
                $sql = "SELECT COUNT(DISTINCT(cu.user_id)) AS number
                        FROM $course_user_table cu, $course_table c, $access_url_rel_user_table as url, $tblCourseRelCategory crc, $tblCourseCategory cc
                        WHERE
                            c.id = cu.c_id AND
                            cc.code = '$categoryCode' AND
                            crc.course_category_id = cc.id AND
                            crc.course_id = c.id AND
                            cu.user_id = url.user_id AND
                            access_url_id = $urlId
                            $status_filter $active_filter";
            }
        } else {
            $sql = "SELECT COUNT(DISTINCT(id)) AS number
                    FROM $user_table
                    WHERE 1 = 1 $status_filter $active_filter";
            if (isset($categoryCode)) {
                $categoryCode = Database::escape_string($categoryCode);
                $status_filter = isset($status) ? ' AND status = '.intval($status) : '';
                $sql = "SELECT COUNT(DISTINCT(cu.user_id)) AS number
                        FROM $course_user_table cu, $course_table c, $tblCourseRelCategory crc, $tblCourseCategory cc
                        WHERE
                            c.id = cu.c_id AND
                            cc.code = '$categoryCode' AND
                            crc.course_category_id = cc.id AND
                            crc.course_id = c.id AND
                            $status_filter
                            $active_filter
                        ";
            }
        }

        $res = Database::query($sql);
        $obj = Database::fetch_object($res);

        return $obj->number;
    }

    /**
     * @param string $startDate
     * @param string $endDate
     *
     * @return array
     */
    public static function getCoursesWithActivity($startDate, $endDate)
    {
        $access_url_rel_course_table = Database::get_main_table(TABLE_MAIN_ACCESS_URL_REL_COURSE);
        $table = Database::get_main_table(TABLE_STATISTIC_TRACK_E_LASTACCESS);
        $startDate = Database::escape_string($startDate);
        $endDate = Database::escape_string($endDate);

        $urlId = api_get_current_access_url_id();

        if (api_is_multiple_url_enabled()) {
            $sql = "SELECT DISTINCT(t.c_id) FROM $table t , $access_url_rel_course_table a
                    WHERE
                        t.c_id = a.c_id AND
                        access_url_id='".$urlId."' AND
                        access_date BETWEEN '$startDate' AND '$endDate'
                    ";
        } else {
            $sql = "SELECT DISTINCT(t.c_id) FROM $table t
                   access_date BETWEEN '$startDate' AND '$endDate' ";
        }

        $result = Database::query($sql);

        return Database::store_result($result);
    }

    /**
     * Count activities from track_e_default_table.
     *
     * @return int Number of activities counted
     */
    public static function getNumberOfActivities($courseId = 0, $sessionId = 0)
    {
        // Database table definitions
        $track_e_default = Database::get_main_table(TABLE_STATISTIC_TRACK_E_DEFAULT);
        $table_user = Database::get_main_table(TABLE_MAIN_USER);
        $access_url_rel_user_table = Database::get_main_table(TABLE_MAIN_ACCESS_URL_REL_USER);
        $urlId = api_get_current_access_url_id();
        if (api_is_multiple_url_enabled()) {
            $sql = "SELECT count(default_id) AS total_number_of_items
                    FROM $track_e_default, $table_user user, $access_url_rel_user_table url
                    WHERE
                        default_user_id = user.id AND
                        user.id=url.user_id AND
                        access_url_id = '".$urlId."'";
        } else {
            $sql = "SELECT count(default_id) AS total_number_of_items
                    FROM $track_e_default, $table_user user
                    WHERE default_user_id = user.id ";
        }

        if (!empty($courseId)) {
            $courseId = (int) $courseId;
            $sql .= " AND c_id = $courseId";
            $sql .= api_get_session_condition($sessionId);
        }

        if (isset($_GET['keyword'])) {
            $keyword = Database::escape_string(trim($_GET['keyword']));
            $sql .= " AND (
                        user.username LIKE '%".$keyword."%' OR
                        default_event_type LIKE '%".$keyword."%' OR
                        default_value_type LIKE '%".$keyword."%' OR
                        default_value LIKE '%".$keyword."%') ";
        }
        $res = Database::query($sql);
        $obj = Database::fetch_object($res);

        return $obj->total_number_of_items;
    }

    /**
     * Get activities data to display.
     *
     * @param int    $from
     * @param int    $numberOfItems
     * @param int    $column
     * @param string $direction
     * @param int    $courseId
     * @param int    $sessionId
     *
     * @return array
     */
    public static function getActivitiesData(
        $from,
        $numberOfItems,
        $column,
        $direction,
        $courseId = 0,
        $sessionId = 0
    ) {
        $track_e_default = Database::get_main_table(TABLE_STATISTIC_TRACK_E_DEFAULT);
        $table_user = Database::get_main_table(TABLE_MAIN_USER);
        $access_url_rel_user_table = Database::get_main_table(TABLE_MAIN_ACCESS_URL_REL_USER);
        $urlId = api_get_current_access_url_id();
        $column = (int) $column;
        $from = (int) $from;
        $numberOfItems = (int) $numberOfItems;
        $direction = strtoupper($direction);

        if (!in_array($direction, ['ASC', 'DESC'])) {
            $direction = 'DESC';
        }

        if (api_is_multiple_url_enabled()) {
            $sql = "SELECT
                        default_event_type  as col0,
                        default_value_type    as col1,
                        default_value        as col2,
                        c_id         as col3,
                        session_id as col4,
                        user.username         as col5,
                        user.id         as col6,
                        default_date         as col7
                    FROM $track_e_default as track_default,
                    $table_user as user,
                    $access_url_rel_user_table as url
                    WHERE
                        track_default.default_user_id = user.id AND
                        url.user_id = user.id AND
                        access_url_id= $urlId ";
        } else {
            $sql = "SELECT
                       default_event_type  as col0,
                       default_value_type    as col1,
                       default_value        as col2,
                       c_id         as col3,
                       session_id as col4,
                       user.username         as col5,
                       user.id         as col6,
                       default_date         as col7
                   FROM $track_e_default track_default, $table_user user
                   WHERE track_default.default_user_id = user.id ";
        }

        if (!empty($_GET['keyword'])) {
            $keyword = Database::escape_string(trim($_GET['keyword']));
            $sql .= " AND (user.username LIKE '%".$keyword."%' OR
                        default_event_type LIKE '%".$keyword."%' OR
                        default_value_type LIKE '%".$keyword."%' OR
                        default_value LIKE '%".$keyword."%') ";
        }

        if (!empty($courseId)) {
            $courseId = (int) $courseId;
            $sql .= " AND c_id = $courseId";
            $sql .= api_get_session_condition($sessionId);
        }

        if (!empty($column) && !empty($direction)) {
            $sql .= " ORDER BY col$column $direction";
        } else {
            $sql .= " ORDER BY col7 DESC ";
        }
        $sql .= " LIMIT $from, $numberOfItems ";

        $res = Database::query($sql);
        $activities = [];
        while ($row = Database::fetch_row($res)) {
            if (false === strpos($row[1], '_object') &&
                false === strpos($row[1], '_array')
            ) {
                $row[2] = $row[2];
            } else {
                if (!empty($row[2])) {
                    $originalData = str_replace('\\', '', $row[2]);
                    $row[2] = UnserializeApi::unserialize('not_allowed_classes', $originalData);
                    if (is_array($row[2]) && !empty($row[2])) {
                        $row[2] = implode_with_key(', ', $row[2]);
                    } else {
                        $row[2] = $originalData;
                    }
                }
            }

            if (!empty($row['default_date'])) {
                $row['default_date'] = api_get_local_time($row['default_date']);
            } else {
                $row['default_date'] = '-';
            }

            if (!empty($row[7])) {
                $row[7] = api_get_local_time($row[7]);
            } else {
                $row[7] = '-';
            }

            if (!empty($row[5])) {
                // Course
                if (!empty($row[3])) {
                    $row[3] = Display::url(
                        $row[3],
                        api_get_path(WEB_CODE_PATH).'admin/course_edit.php?id='.$row[3]
                    );
                } else {
                    $row[3] = '-';
                }

                // session
                if (!empty($row[4])) {
                    $row[4] = Display::url(
                        $row[4],
                        api_get_path(WEB_CODE_PATH).'session/resume_session.php?id_session='.$row[4]
                    );
                } else {
                    $row[4] = '-';
                }

                // User id.
                $row[5] = Display::url(
                    $row[5],
                    api_get_path(WEB_AJAX_PATH).'user_manager.ajax.php?a=get_user_popup&user_id='.$row[6],
                    ['class' => 'ajax']
                );

                $row[6] = Tracking::get_ip_from_user_event(
                    $row[6],
                    $row[7],
                    true
                );
                if (empty($row[6])) {
                    $row[6] = get_lang('Unknown');
                }
            }
            $activities[] = $row;
        }

        return $activities;
    }

    /**
     * Rescale data.
     *
     * @param array $data The data that should be rescaled
     * @param int   $max  The maximum value in the rescaled data (default = 500);
     *
     * @return array The rescaled data, same key as $data
     */
    public static function rescale($data, $max = 500)
    {
        $data_max = 1;
        foreach ($data as $index => $value) {
            $data_max = ($data_max < $value ? $value : $data_max);
        }
        reset($data);
        $result = [];
        $delta = $max / $data_max;
        foreach ($data as $index => $value) {
            $result[$index] = (int) round($value * $delta);
        }

        return $result;
    }

    /**
     * Show statistics.
     *
     * @param string $title      The title
     * @param array  $stats
     * @param bool   $showTotal
     * @param bool   $isFileSize
     *
     * @return string HTML table
     */
    public static function printStats(
        $title,
        $stats,
        $showTotal = true,
        $isFileSize = false
    ) {
        $total = 0;
        $content = '<table class="table table-hover table-striped data_table" cellspacing="0" cellpadding="3" width="90%">
            <thead><tr><th colspan="'.($showTotal ? '4' : '3').'">'.$title.'</th></tr></thead><tbody>';
        $i = 0;
        foreach ($stats as $subtitle => $number) {
            $total += $number;
        }

        foreach ($stats as $subtitle => $number) {
            if (!$isFileSize) {
                $number_label = number_format($number, 0, ',', '.');
            } else {
                $number_label = self::makeSizeString($number);
            }
            $percentage = ($total > 0 ? number_format(100 * $number / $total, 1, ',', '.') : '0');

            $content .= '<tr class="row_'.(0 == $i % 2 ? 'odd' : 'even').'">
                    <td width="25%" style="vertical-align:top;">'.$subtitle.'</td>
                    <td width="60%">'.Display::bar_progress($percentage, false).'</td>
                    <td width="5%" align="right" style="vertical-align:top;">'.$number_label.'</td>';
            if ($showTotal) {
                $content .= '<td width="5%" align="right"> '.$percentage.'%</td>';
            }
            $content .= '</tr>';
            $i++;
        }
        $content .= '</tbody>';
        if ($showTotal) {
            if (!$isFileSize) {
                $total_label = number_format($total, 0, ',', '.');
            } else {
                $total_label = self::makeSizeString($total);
            }
            $content .= '
                <tfoot><tr><th colspan="4" align="right">'.get_lang('Total').': '.$total_label.'</td></tr></tfoot>
            ';
        }
        $content .= '</table>';

        return $content;
    }

    /**
     * Show some stats about the number of logins.
     *
     * @param string $type month, hour or day
     */
    public static function printLoginStats($type)
    {
        $table = Database::get_main_table(TABLE_STATISTIC_TRACK_E_LOGIN);
        $access_url_rel_user_table = Database::get_main_table(TABLE_MAIN_ACCESS_URL_REL_USER);
        $urlId = api_get_current_access_url_id();

        $table_url = null;
        $where_url = null;
        $now = api_get_utc_datetime();
        $where_url_last = ' WHERE login_date > DATE_SUB("'.$now.'",INTERVAL 1 %s)';
        if (api_is_multiple_url_enabled()) {
            $table_url = ", $access_url_rel_user_table";
            $where_url = " WHERE login_user_id=user_id AND access_url_id='".$urlId."'";
            $where_url_last = ' AND login_date > DATE_SUB("'.$now.'",INTERVAL 1 %s)';
        }

        $period = get_lang('Month');
        $periodCollection = api_get_months_long();
        $sql = "SELECT
                DATE_FORMAT( login_date, '%Y-%m' ) AS stat_date ,
                count( login_id ) AS number_of_logins
                FROM $table $table_url $where_url
                GROUP BY stat_date
                ORDER BY login_date DESC";
        $sql_last_x = null;

        switch ($type) {
            case 'hour':
                $period = get_lang('Hour');
                $sql = "SELECT
                          DATE_FORMAT( login_date, '%H') AS stat_date,
                          count( login_id ) AS number_of_logins
                        FROM $table $table_url $where_url
                        GROUP BY stat_date
                        ORDER BY stat_date ";
                $sql_last_x = "SELECT
                                DATE_FORMAT( login_date, '%H' ) AS stat_date,
                                count( login_id ) AS number_of_logins
                               FROM $table $table_url $where_url ".sprintf($where_url_last, 'DAY')."
                               GROUP BY stat_date
                               ORDER BY stat_date ";
                break;
            case 'day':
                $periodCollection = api_get_week_days_long();
                $period = get_lang('Day');
                $sql = "SELECT DATE_FORMAT( login_date, '%w' ) AS stat_date ,
                        count( login_id ) AS number_of_logins
                        FROM  $table $table_url $where_url
                        GROUP BY stat_date
                        ORDER BY DATE_FORMAT( login_date, '%w' ) ";
                $sql_last_x = "SELECT
                                DATE_FORMAT( login_date, '%w' ) AS stat_date,
                                count( login_id ) AS number_of_logins
                               FROM $table $table_url $where_url ".sprintf($where_url_last, 'WEEK')."
                               GROUP BY stat_date
                               ORDER BY DATE_FORMAT( login_date, '%w' ) ";
                break;
        }

        $content = '';
        if ($sql_last_x) {
            $res_last_x = Database::query($sql_last_x);
            $result_last_x = [];
            while ($obj = Database::fetch_object($res_last_x)) {
                $stat_date = ('day' === $type) ? $periodCollection[$obj->stat_date] : $obj->stat_date;
                $result_last_x[$stat_date] = $obj->number_of_logins;
            }
            $content .= self::printStats(get_lang('LastLogins').' ('.$period.')', $result_last_x, true);
            flush(); //flush web request at this point to see something already while the full data set is loading
            $content .= '<br />';
        }
        $res = Database::query($sql);
        $result = [];
        while ($obj = Database::fetch_object($res)) {
            $stat_date = $obj->stat_date;
            switch ($type) {
                case 'month':
                    $stat_date = explode('-', $stat_date);
                    $stat_date[1] = $periodCollection[$stat_date[1] - 1];
                    $stat_date = implode(' ', $stat_date);
                    break;
                case 'day':
                    $stat_date = $periodCollection[$stat_date];
                    break;
            }
            $result[$stat_date] = $obj->number_of_logins;
        }
        $content .= self::printStats(get_lang('AllLogins').' ('.$period.')', $result, true);

        return $content;
    }

    /**
     * Print the number of recent logins.
     *
     * @param bool  $distinct        whether to only give distinct users stats, or *all* logins
     * @param int   $sessionDuration Number of minutes a session must have lasted at a minimum to be taken into account
     * @param array $periods         List of number of days we want to query (default: [1, 7, 31] for last 1 day, last 7 days, last 31 days)
     *
     * @throws Exception
     *
     * @return string HTML table
     */
    public static function printRecentLoginStats($distinct = false, $sessionDuration = 0, $periods = [])
    {
        $table = Database::get_main_table(TABLE_STATISTIC_TRACK_E_LOGIN);
        $access_url_rel_user_table = Database::get_main_table(TABLE_MAIN_ACCESS_URL_REL_USER);
        $urlId = api_get_current_access_url_id();
        $table_url = '';
        $where_url = '';
        if (api_is_multiple_url_enabled()) {
            $table_url = ", $access_url_rel_user_table";
            $where_url = " AND login_user_id=user_id AND access_url_id='".$urlId."'";
        }

        $now = api_get_utc_datetime();
        $field = 'login_id';
        if ($distinct) {
            $field = 'DISTINCT(login_user_id)';
        }

        if (empty($periods)) {
            $periods = [1, 7, 31];
        }
        $sqlList = [];

        $sessionDuration = (int) $sessionDuration * 60; // convert from minutes to seconds
        foreach ($periods as $day) {
            $date = new DateTime($now);
            $startDate = $date->format('Y-m-d').' 00:00:00';
            $endDate = $date->format('Y-m-d').' 23:59:59';

            if ($day > 1) {
                $startDate = $date->sub(new DateInterval('P'.$day.'D'));
                $startDate = $startDate->format('Y-m-d').' 00:00:00';
            }

            $localDate = api_get_local_time($startDate, null, null, false, false);
            $localEndDate = api_get_local_time($endDate, null, null, false, false);

            $label = sprintf(get_lang('Last %s days'), $day);
            if (1 == $day) {
                $label = get_lang('Today');
            }
            $label .= " <span class=\"muted right\" style=\"float: right; margin-right: 5px;\">[$localDate - $localEndDate]</span>";
            $sql = "SELECT count($field) AS number
                    FROM $table $table_url
                    WHERE ";
            if (0 == $sessionDuration) {
                $sql .= " logout_date != login_date AND ";
            } else {
                $sql .= " UNIX_TIMESTAMP(logout_date) - UNIX_TIMESTAMP(login_date) > $sessionDuration AND ";
            }
            $sql .= "login_date BETWEEN '$startDate' AND '$endDate'
                        $where_url";
            $sqlList[$label] = $sql;
        }

        $sql = "SELECT count($field) AS number
                FROM $table $table_url ";
        if (0 == $sessionDuration) {
            $sql .= " WHERE logout_date != login_date $where_url";
        } else {
            $sql .= " WHERE UNIX_TIMESTAMP(logout_date) - UNIX_TIMESTAMP(login_date) > $sessionDuration $where_url";
        }
        $sqlList[get_lang('Total')] = $sql;
        $totalLogin = [];
        foreach ($sqlList as $label => $query) {
            $res = Database::query($query);
            $obj = Database::fetch_object($res);
            $totalLogin[$label] = $obj->number;
        }

        if ($distinct) {
            $content = self::printStats(get_lang('DistinctUsersLogins'), $totalLogin, false);
        } else {
            $content = self::printStats(get_lang('Logins'), $totalLogin, false);
        }

        return $content;
    }

    /**
     * Get the number of recent logins.
     *
     * @param bool $distinct            Whether to only give distinct users stats, or *all* logins
     * @param int  $sessionDuration     Number of minutes a session must have lasted at a minimum to be taken into account
     * @param bool $completeMissingDays Whether to fill the daily gaps (if any) when getting a list of logins
     *
     * @throws Exception
     *
     * @return array
     */
    public static function getRecentLoginStats($distinct = false, $sessionDuration = 0, $completeMissingDays = true)
    {
        $table = Database::get_main_table(TABLE_STATISTIC_TRACK_E_LOGIN);
        $access_url_rel_user_table = Database::get_main_table(TABLE_MAIN_ACCESS_URL_REL_USER);
        $urlId = api_get_current_access_url_id();
        $table_url = '';
        $where_url = '';
        if (api_is_multiple_url_enabled()) {
            $table_url = ", $access_url_rel_user_table";
            $where_url = " AND login_user_id=user_id AND access_url_id='".$urlId."'";
        }

        $now = api_get_utc_datetime();
        $date = new DateTime($now);
        $date->sub(new DateInterval('P31D'));
        $newDate = $date->format('Y-m-d h:i:s');
        $totalLogin = self::buildDatesArray($newDate, $now, true);

        $field = 'login_id';
        if ($distinct) {
            $field = 'DISTINCT(login_user_id)';
        }
        $sessionDuration = (int) $sessionDuration * 60; //Convert from minutes to seconds

        $sql = "SELECT count($field) AS number, date(login_date) as login_date
                FROM $table $table_url
                WHERE ";
        if (0 == $sessionDuration) {
            $sql .= " logout_date != login_date AND ";
        } else {
            $sql .= " UNIX_TIMESTAMP(logout_date) - UNIX_TIMESTAMP(login_date) > $sessionDuration AND ";
        }
        $sql .= " login_date >= '$newDate' $where_url
                GROUP BY date(login_date)";

        $res = Database::query($sql);
        while ($row = Database::fetch_array($res, 'ASSOC')) {
            $monthAndDay = substr($row['login_date'], 5, 5);
            $totalLogin[$monthAndDay] = $row['number'];
        }

        return $totalLogin;
    }

    /**
     * Get course tools usage statistics for the whole platform (by URL if multi-url).
     */
    public static function getToolsStats()
    {
        $table = Database::get_main_table(TABLE_STATISTIC_TRACK_E_ACCESS);
        $access_url_rel_course_table = Database::get_main_table(TABLE_MAIN_ACCESS_URL_REL_COURSE);
        $urlId = api_get_current_access_url_id();

        $tools = [
            'announcement',
            'assignment',
            'calendar_event',
            'chat',
            'course_description',
            'document',
            'dropbox',
            'group',
            'learnpath',
            'link',
            'quiz',
            'student_publication',
            'user',
            'forum',
        ];
        $tool_names = [];
        foreach ($tools as $tool) {
            $tool_names[$tool] = get_lang(ucfirst($tool), '');
        }
        if (api_is_multiple_url_enabled()) {
            $sql = "SELECT access_tool, count( access_id ) AS number_of_logins
                    FROM $table t , $access_url_rel_course_table a
                    WHERE
                        access_tool IN ('".implode("','", $tools)."') AND
                        t.c_id = a.c_id AND
                        access_url_id='".$urlId."'
                        GROUP BY access_tool
                    ";
        } else {
            $sql = "SELECT access_tool, count( access_id ) AS number_of_logins
                    FROM $table
                    WHERE access_tool IN ('".implode("','", $tools)."')
                    GROUP BY access_tool ";
        }

        $res = Database::query($sql);
        $result = [];
        while ($obj = Database::fetch_object($res)) {
            $result[$tool_names[$obj->access_tool]] = $obj->number_of_logins;
        }

        return $result;
    }

    /**
     * Show some stats about the accesses to the different course tools.
     *
     * @param array $result If defined, this serves as data. Otherwise, will get the data from getToolsStats()
     *
     * @return string HTML table
     */
    public static function printToolStats($result = null)
    {
        if (empty($result)) {
            $result = self::getToolsStats();
        }

        return self::printStats(get_lang('Tools access'), $result, true);
    }

    /**
     * Show some stats about the number of courses per language.
     */
    public static function printCourseByLanguageStats()
    {
        $table = Database::get_main_table(TABLE_MAIN_COURSE);
        $access_url_rel_course_table = Database::get_main_table(TABLE_MAIN_ACCESS_URL_REL_COURSE);
        $urlId = api_get_current_access_url_id();
        if (api_is_multiple_url_enabled()) {
            $sql = "SELECT course_language, count( c.code ) AS number_of_courses
                    FROM $table as c, $access_url_rel_course_table as u
                    WHERE u.c_id = c.id AND access_url_id='".$urlId."'
                    GROUP BY course_language
                    ORDER BY number_of_courses DESC";
        } else {
            $sql = "SELECT course_language, count( code ) AS number_of_courses
                   FROM $table GROUP BY course_language
                   ORDER BY number_of_courses DESC";
        }
        $res = Database::query($sql);
        $result = [];
        while ($obj = Database::fetch_object($res)) {
            $result[$obj->course_language] = $obj->number_of_courses;
        }

        return $result;
    }

    /**
     * Shows the number of users having their picture uploaded in Dokeos.
     */
    public static function printUserPicturesStats()
    {
        $user_table = Database::get_main_table(TABLE_MAIN_USER);
        $access_url_rel_user_table = Database::get_main_table(TABLE_MAIN_ACCESS_URL_REL_USER);
        $urlId = api_get_current_access_url_id();
        $url_condition = null;
        $url_condition2 = null;
        $table = null;
        if (api_is_multiple_url_enabled()) {
            $url_condition = ", $access_url_rel_user_table as url WHERE url.user_id=u.id AND access_url_id='".$urlId."'";
            $url_condition2 = " AND url.user_id=u.id AND access_url_id='".$urlId."'";
            $table = ", $access_url_rel_user_table as url ";
        }
        $sql = "SELECT COUNT(*) AS n FROM $user_table as u ".$url_condition;
        $res = Database::query($sql);
        $count1 = Database::fetch_object($res);
        $sql = "SELECT COUNT(*) AS n FROM $user_table as u $table ".
               "WHERE LENGTH(picture_uri) > 0 $url_condition2";
        $res = Database::query($sql);
        $count2 = Database::fetch_object($res);
        // #users without picture
        $result[get_lang('No')] = $count1->n - $count2->n;
        $result[get_lang('Yes')] = $count2->n; // #users with picture

        return self::printStats(get_lang('Number of users').' ('.get_lang('Picture').')', $result, true);
    }

    /**
     * Important activities.
     */
    public static function printActivitiesStats()
    {
        $content = '<h4>'.get_lang('Important activities').'</h4>';
        // Create a search-box
        $form = new FormValidator(
            'search_simple',
            'get',
            api_get_path(WEB_CODE_PATH).'admin/statistics/index.php',
            '',
            'width=200px',
            false
        );
        $renderer = &$form->defaultRenderer();
        $renderer->setCustomElementTemplate('<span>{element}</span> ');
        $form->addHidden('report', 'activities');
        $form->addHidden('activities_direction', 'DESC');
        $form->addHidden('activities_column', '4');
        $form->addElement('text', 'keyword', get_lang('Keyword'));
        $form->addButtonSearch(get_lang('Search'), 'submit');
        $content .= '<div class="actions">';
        $content .= $form->returnForm();
        $content .= '</div>';

        $table = new SortableTable(
            'activities',
            ['Statistics', 'getNumberOfActivities'],
            ['Statistics', 'getActivitiesData'],
            7,
            50,
            'DESC'
        );
        $parameters = [];

        $parameters['report'] = 'activities';
        if (isset($_GET['keyword'])) {
            $parameters['keyword'] = Security::remove_XSS($_GET['keyword']);
        }

        $table->set_additional_parameters($parameters);
        $table->set_header(0, get_lang('Event type'));
        $table->set_header(1, get_lang('Data type'));
        $table->set_header(2, get_lang('Value'));
        $table->set_header(3, get_lang('Course'));
        $table->set_header(4, get_lang('Session'));
        $table->set_header(5, get_lang('Username'));
        $table->set_header(6, get_lang('IP address'));
        $table->set_header(7, get_lang('Date'));
        $content .= $table->return_table();

        return $content;
    }

    /**
     * Shows statistics about the time of last visit to each course.
     */
    public static function printCourseLastVisit()
    {
        $access_url_rel_course_table = Database::get_main_table(TABLE_MAIN_ACCESS_URL_REL_COURSE);
        $urlId = api_get_current_access_url_id();

        $columns[0] = 't.c_id';
        $columns[1] = 'access_date';
        $sql_order[SORT_ASC] = 'ASC';
        $sql_order[SORT_DESC] = 'DESC';
        $per_page = isset($_GET['per_page']) ? intval($_GET['per_page']) : 10;
        $page_nr = isset($_GET['page_nr']) ? intval($_GET['page_nr']) : 1;
        $column = isset($_GET['column']) ? intval($_GET['column']) : 0;
        $direction = isset($_GET['direction']) ? $_GET['direction'] : SORT_ASC;

        if (!in_array($direction, [SORT_ASC, SORT_DESC])) {
            $direction = SORT_ASC;
        }
        $form = new FormValidator('courselastvisit', 'get');
        $form->addElement('hidden', 'report', 'courselastvisit');
        $form->addText('date_diff', get_lang('days'), true);
        $form->addRule('date_diff', 'InvalidNumber', 'numeric');
        $form->addButtonSearch(get_lang('Search'), 'submit');
        if (!isset($_GET['date_diff'])) {
            $defaults['date_diff'] = 60;
        } else {
            $defaults['date_diff'] = Security::remove_XSS($_GET['date_diff']);
        }
        $form->setDefaults($defaults);
        $content = $form->returnForm();

        $values = $form->exportValues();
        $date_diff = $values['date_diff'];
        $table = Database::get_main_table(TABLE_STATISTIC_TRACK_E_LASTACCESS);
        if (api_is_multiple_url_enabled()) {
            $sql = "SELECT * FROM $table t , $access_url_rel_course_table a
                   WHERE
                        t.c_id = a.c_id AND
                        access_url_id='".$urlId."'
                   GROUP BY t.c_id
                   HAVING t.c_id <> ''
                   AND DATEDIFF( '".api_get_utc_datetime()."' , access_date ) <= ".$date_diff;
        } else {
            $sql = "SELECT * FROM $table t
                   GROUP BY t.c_id
                   HAVING t.c_id <> ''
                   AND DATEDIFF( '".api_get_utc_datetime()."' , access_date ) <= ".$date_diff;
        }
        $sql .= ' ORDER BY `'.$columns[$column].'` '.$sql_order[$direction];
        $from = ($page_nr - 1) * $per_page;
        $sql .= ' LIMIT '.$from.','.$per_page;

        $content .= '<p>'.get_lang('Latest access').' &gt;= '.$date_diff.' '.get_lang('days').'</p>';
        $res = Database::query($sql);
        if (Database::num_rows($res) > 0) {
            $courses = [];
            while ($obj = Database::fetch_object($res)) {
                $courseInfo = api_get_course_info_by_id($obj->c_id);
                $course = [];
                $course[] = '<a href="'.api_get_path(WEB_COURSE_PATH).$courseInfo['code'].'">'.$courseInfo['code'].' <a>';
                // Allow sort by date hiding the numerical date
                $course[] = '<span style="display:none;">'.$obj->access_date.'</span>'.api_convert_and_format_date($obj->access_date);
                $courses[] = $course;
            }
            $parameters['date_diff'] = $date_diff;
            $parameters['report'] = 'courselastvisit';
            $table_header[] = [get_lang("CourseCode"), true];
            $table_header[] = [get_lang("LastAccess"), true];

            ob_start();
            Display:: display_sortable_table(
                $table_header,
                $courses,
                ['column' => $column, 'direction' => $direction],
                [],
                $parameters
            );
            $content .= ob_get_contents();
            ob_end_clean();
        } else {
            $content = get_lang('NoSearchResults');
        }

        return $content;
    }

    /**
     * Displays the statistics of the messages sent and received by each user in the social network.
     *
     * @param string $messageType Type of message: 'sent' or 'received'
     *
     * @return array Message list
     */
    public static function getMessages($messageType)
    {
        $message_table = Database::get_main_table(TABLE_MESSAGE);
        $user_table = Database::get_main_table(TABLE_MAIN_USER);
        $access_url_rel_user_table = Database::get_main_table(TABLE_MAIN_ACCESS_URL_REL_USER);

        $urlId = api_get_current_access_url_id();

        switch ($messageType) {
            case 'sent':
                $field = 'user_sender_id';
                break;
            case 'received':
                $field = 'user_receiver_id';
                break;
        }

        if (api_is_multiple_url_enabled()) {
            $sql = "SELECT lastname, firstname, username, COUNT($field) AS count_message
                FROM $access_url_rel_user_table as url, $message_table m
                LEFT JOIN $user_table u ON m.$field = u.id
                WHERE  url.user_id = m.$field AND  access_url_id='".$urlId."'
                GROUP BY m.$field
                ORDER BY count_message DESC ";
        } else {
            $sql = "SELECT lastname, firstname, username, COUNT($field) AS count_message
                FROM $message_table m
                LEFT JOIN $user_table u ON m.$field = u.id
                GROUP BY m.$field ORDER BY count_message DESC ";
        }
        $res = Database::query($sql);
        $messages_sent = [];
        while ($messages = Database::fetch_array($res)) {
            if (empty($messages['username'])) {
                $messages['username'] = get_lang('Unknown');
            }
            $users = api_get_person_name(
                $messages['firstname'],
                $messages['lastname']
            ).'<br />('.$messages['username'].')';
            $messages_sent[$users] = $messages['count_message'];
        }

        return $messages_sent;
    }

    /**
     * Count the number of friends for social network users.
     */
    public static function getFriends()
    {
        $user_friend_table = Database::get_main_table(TABLE_MAIN_USER_REL_USER);
        $user_table = Database::get_main_table(TABLE_MAIN_USER);
        $access_url_rel_user_table = Database::get_main_table(TABLE_MAIN_ACCESS_URL_REL_USER);
        $urlId = api_get_current_access_url_id();

        if (api_is_multiple_url_enabled()) {
            $sql = "SELECT lastname, firstname, username, COUNT(friend_user_id) AS count_friend
                    FROM $access_url_rel_user_table as url, $user_friend_table uf
                    LEFT JOIN $user_table u
                    ON (uf.user_id = u.id)
                    WHERE
                        uf.relation_type <> '".UserRelUser::USER_RELATION_TYPE_RRHH."' AND
                        uf.user_id = url.user_id AND
                        access_url_id = '".$urlId."'
                    GROUP BY uf.user_id
                    ORDER BY count_friend DESC ";
        } else {
            $sql = "SELECT lastname, firstname, username, COUNT(friend_user_id) AS count_friend
                    FROM $user_friend_table uf
                    LEFT JOIN $user_table u
                    ON (uf.user_id = u.id)
                    WHERE uf.relation_type <> '".UserRelUser::USER_RELATION_TYPE_RRHH."'
                    GROUP BY uf.user_id
                    ORDER BY count_friend DESC ";
        }
        $res = Database::query($sql);
        $list_friends = [];
        while ($friends = Database::fetch_array($res)) {
            $users = api_get_person_name($friends['firstname'], $friends['lastname']).'<br />('.$friends['username'].')';
            $list_friends[$users] = $friends['count_friend'];
        }

        return $list_friends;
    }

    /**
     * Print the number of users that didn't login for a certain period of time.
     */
    public static function printUsersNotLoggedInStats()
    {
        $totalLogin = [];
        $table = Database::get_main_table(TABLE_STATISTIC_TRACK_E_LOGIN);
        $access_url_rel_user_table = Database::get_main_table(TABLE_MAIN_ACCESS_URL_REL_USER);
        $urlId = api_get_current_access_url_id();
        $total = self::countUsers();
        if (api_is_multiple_url_enabled()) {
            $table_url = ", $access_url_rel_user_table";
            $where_url = " AND login_user_id=user_id AND access_url_id='".$urlId."'";
        } else {
            $table_url = '';
            $where_url = '';
        }
        $now = api_get_utc_datetime();
        $sql[get_lang('This day')] =
            "SELECT count(distinct(login_user_id)) AS number ".
            " FROM $table $table_url ".
            " WHERE DATE_ADD(login_date, INTERVAL 1 DAY) >= '$now' $where_url";
        $sql[get_lang('In the last 7 days')] =
            "SELECT count(distinct(login_user_id)) AS number ".
            " FROM $table $table_url ".
            " WHERE DATE_ADD(login_date, INTERVAL 7 DAY) >= '$now' $where_url";
        $sql[get_lang('In the last 31 days')] =
            "SELECT count(distinct(login_user_id)) AS number ".
            " FROM $table $table_url ".
            " WHERE DATE_ADD(login_date, INTERVAL 31 DAY) >= '$now' $where_url";
        $sql[sprintf(get_lang('Last %i months'), 6)] =
            "SELECT count(distinct(login_user_id)) AS number ".
            " FROM $table $table_url ".
            " WHERE DATE_ADD(login_date, INTERVAL 6 MONTH) >= '$now' $where_url";
        $sql[get_lang('Never connected')] =
            "SELECT count(distinct(login_user_id)) AS number ".
            " FROM $table $table_url WHERE 1=1 $where_url";
        foreach ($sql as $index => $query) {
            $res = Database::query($query);
            $obj = Database::fetch_object($res);
            $r = $total - $obj->number;
            $totalLogin[$index] = $r < 0 ? 0 : $r;
        }

        return self::printStats(
            get_lang('Not logged in for some time'),
            $totalLogin,
            false
        );
    }

    /**
     * Returns an array with indexes as the 'yyyy-mm-dd' format of each date
     * within the provided range (including limits). Dates are assumed to be
     * given in UTC.
     *
     * @param string $startDate  Start date, in Y-m-d or Y-m-d h:i:s format
     * @param string $endDate    End date, in Y-m-d or Y-m-d h:i:s format
     * @param bool   $removeYear Whether to remove the year in the results (for easier reading)
     *
     * @return array|bool False on error in the params, array of [date1 => 0, date2 => 0, ...] otherwise
     */
    public static function buildDatesArray($startDate, $endDate, $removeYear = false)
    {
        if (strlen($startDate) > 10) {
            $startDate = substr($startDate, 0, 10);
        }
        if (strlen($endDate) > 10) {
            $endDate = substr($endDate, 0, 10);
        }
        if (!preg_match('/\d\d\d\d-\d\d-\d\d/', $startDate)) {
            return false;
        }
        if (!preg_match('/\d\d\d\d-\d\d-\d\d/', $startDate)) {
            return false;
        }
        $startTimestamp = strtotime($startDate);
        $endTimestamp = strtotime($endDate);
        $list = [];
        for ($time = $startTimestamp; $time < $endTimestamp; $time += 86400) {
            $datetime = api_get_utc_datetime($time);
            if ($removeYear) {
                $datetime = substr($datetime, 5, 5);
            } else {
                $dateTime = substr($datetime, 0, 10);
            }
            $list[$datetime] = 0;
        }

        return $list;
    }

    /**
     * Prepare the JS code to load a chart.
     *
     * @param string $url     URL for AJAX data generator
     * @param string $type    bar, line, pie, etc
     * @param string $options Additional options to the chart (see chart-specific library)
     * @param string A JS code for loading the chart together with a call to AJAX data generator
     */
    public static function getJSChartTemplate($url, $type = 'pie', $options = '', $elementId = 'canvas')
    {
        $chartCode = '
        <script>
        $(function() {
            $.ajax({
                url: "'.$url.'",
                type: "POST",
                success: function(data) {
                    Chart.defaults.global.responsive = true;
                    var ctx = document.getElementById("'.$elementId.'").getContext("2d");
                    var chart = new Chart(ctx, {
                        type: "'.$type.'",
                        data: data,
                        options: {'.$options.'}
                    });
                    var title = chart.options.title.text;
                    $("#'.$elementId.'_title").html(title);
                    $("#'.$elementId.'_table").html(data.table);
                }
            });
        });
        </script>';

        return $chartCode;
    }

    public static function getJSChartTemplateWithData($data, $type = 'pie', $options = '', $elementId = 'canvas')
    {
        $data = json_encode($data);
        $chartCode = '
        <script>
            $(function() {
                Chart.defaults.global.responsive = true;
                var ctx = document.getElementById("'.$elementId.'").getContext("2d");
                var chart = new Chart(ctx, {
                    type: "'.$type.'",
                    data: '.$data.',
                    options: {'.$options.'}
                });
            });
        </script>';

        return $chartCode;
    }

    public static function buildJsChartData($all, $chartName)
    {
        $list = [];
        $palette = ChamiloApi::getColorPalette(true, true);
        foreach ($all as $tick => $tock) {
            $list['labels'][] = $tick;
        }

        $list['datasets'][0]['label'] = $chartName;
        $list['datasets'][0]['borderColor'] = 'rgba(255,255,255,1)';

        $i = 0;
        foreach ($all as $tick => $tock) {
            $j = $i % count($palette);
            $list['datasets'][0]['data'][] = $tock;
            $list['datasets'][0]['backgroundColor'][] = $palette[$j];
            $i++;
        }

        $scoreDisplay = ScoreDisplay::instance();
        $table = new HTML_Table(['class' => 'data_table']);
        $headers = [
            get_lang('Name'),
            get_lang('Count'),
            get_lang('Percentage'),
        ];
        $row = 0;
        $column = 0;
        foreach ($headers as $header) {
            $table->setHeaderContents($row, $column, $header);
            $column++;
        }

        $total = 0;
        foreach ($all as $name => $value) {
            $total += $value;
        }
        $row++;
        foreach ($all as $name => $value) {
            $table->setCellContents($row, 0, $name);
            $table->setCellContents($row, 1, $value);
            $table->setCellContents($row, 2, $scoreDisplay->display_score([$value, $total], SCORE_PERCENT));
            $row++;
        }
        $table = Display::page_subheader2($chartName).$table->toHtml();

        return ['chart' => $list, 'table' => $table];
    }

    /**
     * Display the Logins By Date report and allow export its result to XLS.
     */
    public static function printLoginsByDate()
    {
        if (isset($_GET['export']) && 'xls' === $_GET['export']) {
            $result = self::getLoginsByDate($_GET['start'], $_GET['end']);
            $data = [[get_lang('Username'), get_lang('First name'), get_lang('Last name'), get_lang('Total time')]];

            foreach ($result as $i => $item) {
                $data[] = [
                    $item['username'],
                    $item['firstname'],
                    $item['lastname'],
                    api_time_to_hms($item['time_count']),
                ];
            }

            Export::arrayToXls($data);
            exit;
        }

        $content = Display::page_header(get_lang('Logins by date'));

        $actions = '';
        $form = new FormValidator('frm_logins_by_date', 'get');
        $form->addDateRangePicker(
            'daterange',
            get_lang('Date range'),
            true,
            ['format' => 'YYYY-MM-DD', 'timePicker' => 'false', 'validate_format' => 'Y-m-d']
        );
        $form->addHidden('report', 'logins_by_date');
        $form->addButtonFilter(get_lang('Search'));

        if ($form->validate()) {
            $values = $form->exportValues();

            $result = self::getLoginsByDate($values['daterange_start'], $values['daterange_end']);

            if (!empty($result)) {
                $actions = Display::url(
                    Display::return_icon('excel.png', get_lang('ExportToXls'), [], ICON_SIZE_MEDIUM),
                    api_get_self().'?'.http_build_query(
                        [
                            'report' => 'logins_by_date',
                            'export' => 'xls',
                            'start' => Security::remove_XSS($values['daterange_start']),
                            'end' => Security::remove_XSS($values['daterange_end']),
                        ]
                    )
                );
            }

            $table = new HTML_Table(['class' => 'data_table']);
            $table->setHeaderContents(0, 0, get_lang('Username'));
            $table->setHeaderContents(0, 1, get_lang('First name'));
            $table->setHeaderContents(0, 2, get_lang('Last name'));
            $table->setHeaderContents(0, 3, get_lang('Total time'));

            foreach ($result as $i => $item) {
                $table->setCellContents($i + 1, 0, $item['username']);
                $table->setCellContents($i + 1, 1, $item['firstname']);
                $table->setCellContents($i + 1, 2, $item['lastname']);
                $table->setCellContents($i + 1, 3, api_time_to_hms($item['time_count']));
            }

            $table->setColAttributes(0, ['class' => 'text-center']);
            $table->setColAttributes(3, ['class' => 'text-center']);
            $content = $table->toHtml();
        }

        $content .= $form->returnForm();

        if (!empty($actions)) {
            $content .= Display::toolbarAction('logins_by_date_toolbar', [$actions]);
        }

        return $content;
    }

    public static function getBossTable($bossId)
    {
        $students = UserManager::getUsersFollowedByStudentBoss(
            $bossId,
            0,
            false,
            false,
            false,
            null,
            null,
            null,
            null,
            1
        );

        if (!empty($students)) {
            $table = new HTML_Table(['class' => 'table table-responsive', 'id' => 'table_'.$bossId]);
            $headers = [
                get_lang('Name'),
                //get_lang('LastName'),
            ];
            $row = 0;
            $column = 0;
            foreach ($headers as $header) {
                $table->setHeaderContents($row, $column, $header);
                $column++;
            }
            $row++;
            foreach ($students as $student) {
                $column = 0;
                $content = api_get_person_name($student['firstname'], $student['lastname']).'';
                $content = '<div style="width: 200px; overflow-wrap: break-word;">'.$content.'</div>';
                $table->setCellContents(
                    $row,
                    $column++,
                    $content
                );
                $row++;
            }

            return $table->toHtml();
        }

        return '<table id="table_'.$bossId.'"></table>';
    }

    /**
     * @param string $startDate
     * @param string $endDate
     *
     * @return array
     */
    private static function getLoginsByDate($startDate, $endDate)
    {
        $startDate = api_get_utc_datetime("$startDate 00:00:00");
        $endDate = api_get_utc_datetime("$endDate 23:59:59");

        if (empty($startDate) || empty($endDate)) {
            return [];
        }

        $tblUser = Database::get_main_table(TABLE_MAIN_USER);
        $tblLogin = Database::get_main_table(TABLE_STATISTIC_TRACK_E_LOGIN);
        $urlJoin = '';
        $urlWhere = '';

        if (api_is_multiple_url_enabled()) {
            $tblUrlUser = Database::get_main_table(TABLE_MAIN_ACCESS_URL_REL_USER);

            $urlJoin = "INNER JOIN $tblUrlUser au ON u.id = au.user_id";
            $urlWhere = 'AND au.access_url_id = '.api_get_current_access_url_id();
        }

        $sql = "SELECT u.id,
                    u.firstname,
                    u.lastname,
                    u.username,
                    SUM(TIMESTAMPDIFF(SECOND, l.login_date, l.logout_date)) AS time_count
                FROM $tblUser u
                INNER JOIN $tblLogin l
                ON u.id = l.login_user_id
                $urlJoin
                WHERE l.login_date BETWEEN '$startDate' AND '$endDate'
                $urlWhere
                GROUP BY u.id";

        $stmt = Database::query($sql);

        return Database::store_result($stmt, 'ASSOC');
    }
}
