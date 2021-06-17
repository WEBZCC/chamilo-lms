<?php

/* For licensing terms, see /license.txt */

use Chamilo\CoreBundle\Entity\SysAnnouncement;
use Chamilo\CoreBundle\Framework\Container;

/**
 * Class SystemAnnouncementManager.
 */
class SystemAnnouncementManager
{
    public const VISIBLE_GUEST = 'visible_guest';
    public const VISIBLE_STUDENT = 'visible_student';
    public const VISIBLE_TEACHER = 'visible_teacher';
    public const VISIBLE_DRH = 'visible_drh';
    public const VISIBLE_SESSION_ADMIN = 'visible_session_admin';
    public const VISIBLE_STUDENT_BOSS = 'visible_boss';

    public static function getVisibilityList(): array
    {
        return [
            self::VISIBLE_TEACHER => get_lang('Trainer'),
            self::VISIBLE_STUDENT => get_lang('Learner'),
            self::VISIBLE_GUEST => get_lang('Guest'),
            self::VISIBLE_DRH => get_lang('Human Resources Manager'),
            self::VISIBLE_SESSION_ADMIN => get_lang('Session administrator'),
            self::VISIBLE_STUDENT_BOSS => get_lang('Superior (n+1)'),
        ];
    }

    /**
     * @param string $visibility
     *
     * @return string
     */
    public static function getVisibilityCondition($visibility)
    {
        $list = self::getVisibilityList();
        $visibilityCondition = " AND ".self::VISIBLE_GUEST." = 1 ";
        if (in_array($visibility, array_keys($list))) {
            $visibilityCondition = " AND $visibility = 1 ";
        }

        return $visibilityCondition;
    }

    /**
     * Displays all announcements.
     *
     * @param string $visibility VISIBLE_GUEST, VISIBLE_STUDENT or VISIBLE_TEACHER
     * @param int    $id         The identifier of the announcement to display
     */
    public static function display_announcements($visibility, $id = -1)
    {
        $user_selected_language = api_get_language_isocode();
        $db_table = Database::get_main_table(TABLE_MAIN_SYSTEM_ANNOUNCEMENTS);
        $tbl_announcement_group = Database::get_main_table(TABLE_MAIN_SYSTEM_ANNOUNCEMENTS_GROUPS);
        $userGroup = new UserGroupModel();

        $temp_user_groups = $userGroup->get_groups_by_user(api_get_user_id(), 0);
        $groups = [];
        foreach ($temp_user_groups as $user_group) {
            $groups = array_merge($groups, [$user_group['id']]);
            $groups = array_merge(
                $groups,
                $userGroup->get_parent_groups($user_group['id'])
            );
        }

        $groups_string = '('.implode($groups, ',').')';
        $now = api_get_utc_datetime();
        $sql = "SELECT *, DATE_FORMAT(date_start,'%d-%m-%Y %h:%i:%s') AS display_date
                FROM  $db_table
                WHERE
                    (lang='$user_selected_language' OR lang IS NULL) AND
                    (('$now' BETWEEN date_start AND date_end) OR date_end='0000-00-00') ";

        $sql .= self::getVisibilityCondition($visibility);

        if (count($groups) > 0) {
            $sql .= " OR id IN (
                        SELECT announcement_id FROM $tbl_announcement_group
                        WHERE group_id in $groups_string
                    ) ";
        }
        $current_access_url_id = 1;
        if (api_is_multiple_url_enabled()) {
            $current_access_url_id = api_get_current_access_url_id();
        }
        $sql .= " AND access_url_id = '$current_access_url_id' ";
        $sql .= " ORDER BY date_start DESC LIMIT 0,7";

        $announcements = Database::query($sql);
        if (Database::num_rows($announcements) > 0) {
            $url = api_get_self();
            echo '<div class="system_announcements">';
            echo '<h3>'.get_lang('Portal news').'</h3>';
            echo '<div style="margin:10px;text-align:right;"><a href="news_list.php">'.get_lang('More').'</a></div>';

            while ($announcement = Database::fetch_object($announcements)) {
                if ($id != $announcement->id) {
                    $show_url = 'news_list.php#'.$announcement->id;
                    $display_date = api_convert_and_format_date($announcement->display_date, DATE_FORMAT_LONG);
                    echo '<a name="'.$announcement->id.'"></a>
                        <div class="system_announcement">
                            <div class="system_announcement_title">
                                <a name="ann'.$announcement->id.'" href="'.$show_url.'">'.
                        $announcement->title.'</a>
                            </div>
                            <div class="system_announcement_date">'.$display_date.'</div>
                        </div>';
                } else {
                    echo '<div class="system_announcement">
                            <div class="system_announcement_title">'
                        .$announcement->display_date.'
                                <a name="ann'.$announcement->id.'" href="'.$url.'?#ann'.$announcement->id.'">'.
                        $announcement->title.'
                                </a>
                            </div>';
                }
                echo '<br />';
            }
            echo '</div>';
        }
    }

    /**
     * @param string $visibility
     * @param int    $id
     * @param int    $start
     * @param string $user_id
     *
     * @return string
     */
    public static function displayAllAnnouncements(
        $visibility,
        $id = -1,
        $start = 0,
        $user_id = ''
    ) {
        $user_selected_language = api_get_language_isocode();
        $start = (int) $start;
        $userGroup = new UserGroupModel();
        $tbl_announcement_group = Database::get_main_table(TABLE_MAIN_SYSTEM_ANNOUNCEMENTS_GROUPS);
        $temp_user_groups = $userGroup->get_groups_by_user(api_get_user_id(), 0);
        $groups = [];
        foreach ($temp_user_groups as $user_group) {
            $groups = array_merge($groups, [$user_group['id']]);
            $groups = array_merge($groups, $userGroup->get_parent_groups($user_group['id']));
        }

        // Checks if tables exists to not break platform not updated
        $groups_string = '('.implode($groups, ',').')';

        $table = Database::get_main_table(TABLE_MAIN_SYSTEM_ANNOUNCEMENTS);
        $now = api_get_utc_datetime();

        $sql = "SELECT * FROM $table
                WHERE
                    (lang = '$user_selected_language' OR lang IS NULL) AND
                    ( '$now' >= date_start AND '$now' <= date_end) ";

        $sql .= self::getVisibilityCondition($visibility);

        if (count($groups) > 0) {
            $sql .= " OR id IN (
                    SELECT announcement_id FROM $tbl_announcement_group
                    WHERE group_id in $groups_string
                    ) ";
        }

        if (api_is_multiple_url_enabled()) {
            $current_access_url_id = api_get_current_access_url_id();
            $sql .= " AND access_url_id IN ('1', '$current_access_url_id')";
        }

        if (!isset($_GET['start']) || 0 == $_GET['start']) {
            $sql .= " ORDER BY date_start DESC LIMIT ".$start.",20";
        } else {
            $sql .= " ORDER BY date_start DESC LIMIT ".($start + 1).",20";
        }
        $announcements = Database::query($sql);
        $content = '';
        if (Database::num_rows($announcements) > 0) {
            $content .= '<div class="system_announcements">';
            $content .= '<h3>'.get_lang('Portal news').'</h3>';
            $content .= '<table align="center">';
            $content .= '<tr>';
            $content .= '<td>';
            $content .= self::display_arrow($user_id);
            $content .= '</td>';
            $content .= '</tr>';
            $content .= '</table>';
            $content .= '<table align="center" border="0" width="900px">';
            while ($announcement = Database::fetch_object($announcements)) {
                $display_date = api_convert_and_format_date($announcement->display_date, DATE_FORMAT_LONG);
                $content .= '<tr><td>';
                $content .= '<a name="'.$announcement->id.'"></a>
                        <div class="system_announcement">
                        <h2>'.$announcement->title.'</h2>
                        <div class="system_announcement_date">'.$display_date.'</div>
                        <br />
                        <div class="system_announcement_content">'
                    .$announcement->content.'
                        </div>
                      </div><br />';
                $content .= '</tr></td>';
            }
            $content .= '</table>';

            $content .= '<table align="center">';
            $content .= '<tr>';
            $content .= '<td>';
            $content .= self::display_arrow($user_id);
            $content .= '</td>';
            $content .= '</tr>';
            $content .= '</table>';
            $content .= '</div>';
        }

        return $content;
    }

    /**
     * @param int $user_id
     *
     * @return string
     */
    public static function display_arrow($user_id)
    {
        $start = (int) $_GET['start'];
        $nb_announcement = self::count_nb_announcement($start, $user_id);
        $next = ((int) $_GET['start'] + 19);
        $prev = ((int) $_GET['start'] - 19);
        $content = '';
        if (!isset($_GET['start']) || 0 == $_GET['start']) {
            if ($nb_announcement > 20) {
                $content .= '<a href="news_list.php?start='.$next.'">'.get_lang('Next').' >> </a>';
            }
        } else {
            echo '<a href="news_list.php?start='.$prev.'"> << '.get_lang('Prev').'</a>';
            if ($nb_announcement > 20) {
                $content .= '<a href="news_list.php?start='.$next.'">'.get_lang('Next').' >> </a>';
            }
        }

        return $content;
    }

    /**
     * Update announcements picture.
     *
     * @param int $announcement_id
     * @param   string  the full system name of the image
     * from which course picture will be created
     * @param string $cropParameters Optional string that contents "x,y,width,height" of a cropped image format
     *
     * @return bool Returns the resulting. In case of internal error or negative validation returns FALSE.
     */
    public static function update_announcements_picture(
        $announcement_id,
        $source_file = null,
        $cropParameters = null
    ) {
        if (empty($announcement_id)) {
            return false;
        }

        // course path
        /*$store_path = api_get_path(SYS_UPLOAD_PATH).'announcements';

        if (!file_exists($store_path)) {
            mkdir($store_path);
        }
        // image name
        $announcementPicture = $store_path.'/announcement_'.$announcement_id.'.png';
        $announcementPictureSmall = $store_path.'/announcement_'.$announcement_id.'_100x100.png';

        if (file_exists($announcementPicture)) {
            unlink($announcementPicture);
        }
        if (file_exists($announcementPictureSmall)) {
            unlink($announcementPictureSmall);
        }

        //Crop the image to adjust 4:3 ratio
        $image = new Image($source_file);
        $image->crop($cropParameters);

        $medium = new Image($source_file);
        $medium->resize(100);
        $medium->send_image($announcementPictureSmall, -1, 'png');

        $normal = new Image($source_file);
        $normal->send_image($announcementPicture, -1, 'png');

        $result = $normal;

        return $result ? $result : false;*/
    }

    /**
     * @param int    $start
     * @param string $user_id
     *
     * @return int
     */
    public static function count_nb_announcement($start = 0, $user_id = '')
    {
        $start = intval($start);
        $user_selected_language = api_get_language_isocode();
        $db_table = Database::get_main_table(TABLE_MAIN_SYSTEM_ANNOUNCEMENTS);
        $sql = 'SELECT id FROM '.$db_table.'
                WHERE (lang="'.$user_selected_language.'" OR lang IS NULL) ';

        $visibility = self::getCurrentUserVisibility();
        $sql .= self::getVisibilityCondition($visibility);

        $current_access_url_id = 1;
        if (api_is_multiple_url_enabled()) {
            $current_access_url_id = api_get_current_access_url_id();
        }
        $sql .= " AND access_url_id = '$current_access_url_id' ";
        $sql .= 'LIMIT '.$start.', 21';
        $announcements = Database::query($sql);
        $i = 0;
        while ($rows = Database::fetch_array($announcements)) {
            $i++;
        }

        return $i;
    }

    /**
     * Adds an announcement to the database.
     *
     * @param string $title           Title of the announcement
     * @param string $content         Content of the announcement
     * @param string $date_start      Start date (YYYY-MM-DD HH:II: SS)
     * @param string $date_end        End date (YYYY-MM-DD HH:II: SS)
     * @param array  $visibility
     * @param string $lang            The language for which the announvement should be shown. Leave null for all langages
     * @param int    $send_mail       Whether to send an e-mail to all users (1) or not (0)
     * @param bool   $add_to_calendar
     * @param bool   $sendEmailTest
     * @param int    $careerId
     * @param int    $promotionId
     *
     * @return mixed insert_id on success, false on failure
     */
    public static function add_announcement(
        $title,
        $content,
        $date_start,
        $date_end,
        $visibility,
        $lang = '',
        $send_mail = 0,
        $add_to_calendar = false,
        $sendEmailTest = false,
        $careerId = 0,
        $promotionId = 0
    ) {
        $original_content = $content;
        $a_dateS = explode(' ', $date_start);
        $a_arraySD = explode('-', $a_dateS[0]);
        $a_arraySH = explode(':', $a_dateS[1]);
        $date_start_to_compare = array_merge($a_arraySD, $a_arraySH);

        $a_dateE = explode(' ', $date_end);
        $a_arrayED = explode('-', $a_dateE[0]);
        $a_arrayEH = explode(':', $a_dateE[1]);
        $date_end_to_compare = array_merge($a_arrayED, $a_arrayEH);

        $db_table = Database::get_main_table(TABLE_MAIN_SYSTEM_ANNOUNCEMENTS);

        if (!checkdate($date_start_to_compare[1], $date_start_to_compare[2], $date_start_to_compare[0])) {
            Display::addFlash(
                Display::return_message(get_lang('Invalid start date was given.'), 'warning')
            );

            return false;
        }

        if (($date_end_to_compare[1] ||
                $date_end_to_compare[2] ||
                $date_end_to_compare[0]) &&
            !checkdate($date_end_to_compare[1], $date_end_to_compare[2], $date_end_to_compare[0])
        ) {
            Display::addFlash(
                Display::return_message(get_lang('Invalid end date was given.'), 'warning')
            );

            return false;
        }

        if (0 == strlen(trim($title))) {
            Display::addFlash(
                Display::return_message(get_lang('Please enter a title'), 'warning')
            );

            return false;
        }

        $start = api_get_utc_datetime($date_start, null, true);
        $end = api_get_utc_datetime($date_end, null, true);

        //Fixing urls that are sent by email
        //$content = str_replace('src=\"/home/', 'src=\"'.api_get_path(WEB_PATH).'home/', $content);
        //$content = str_replace('file=/home/', 'file='.api_get_path(WEB_PATH).'home/', $content);
        $content = str_replace(
            'src=\"'.api_get_path(REL_HOME_PATH),
            'src=\"'.api_get_path(WEB_PATH).api_get_path(REL_HOME_PATH),
            $content
        );
        $content = str_replace(
            'file='.api_get_path(REL_HOME_PATH),
            'file='.api_get_path(WEB_PATH).api_get_path(REL_HOME_PATH),
            $content
        );
        $lang = is_null($lang) ? '' : $lang;

        $sysRepo = Container::getSysAnnouncementRepository();

        $sysAnnouncement = (new SysAnnouncement())
            ->setTitle($title)
            ->setContent($content)
            ->setDateStart($start)
            ->setDateEnd($end)
            ->setLang($lang)
            ->setUrl(api_get_url_entity())
            ->setRoles($visibility)
        ;

        if (api_get_configuration_value('allow_careers_in_global_announcements') && !empty($careerId)) {
            $careerRepo = Container::getCareerRepository();
            $sysAnnouncement->setCareer($careerRepo->find($careerId));

            $promotionRepo = Container::getPromotionRepository();
            $sysAnnouncement->setPromotion($promotionRepo->find($promotionId));
        }

        $sysRepo->update($sysAnnouncement);
        $resultId = $sysAnnouncement->getId();

        if ($resultId) {
            if ($sendEmailTest) {
                self::send_system_announcement_by_email(
                    $sysAnnouncement,
                    $visibility,
                    true
                );
            } else {
                if (1 == $send_mail) {
                    self::send_system_announcement_by_email(
                        $sysAnnouncement,
                        $visibility
                    );
                }
            }

            if ($add_to_calendar) {
                $agenda = new Agenda('admin');
                $agenda->addEvent(
                    $date_start,
                    $date_end,
                    false,
                    $title,
                    $original_content
                );
            }

            return $resultId;
        }

        return false;
    }

    /**
     * Makes the announcement id visible only for groups in groups_array.
     *
     * @param int   $announcement_id
     * @param array $group_array     array of group id
     *
     * @return bool
     */
    public static function announcement_for_groups($announcement_id, $group_array)
    {
        $tbl_announcement_group = Database::get_main_table(
            TABLE_MAIN_SYSTEM_ANNOUNCEMENTS_GROUPS
        );
        //first delete all group associations for this announcement
        $res = Database::query(
            "DELETE FROM $tbl_announcement_group
             WHERE announcement_id=".intval($announcement_id)
        );

        if (false === $res) {
            return false;
        }

        foreach ($group_array as $group_id) {
            if (0 != intval($group_id)) {
                $sql = "INSERT INTO $tbl_announcement_group SET
                        announcement_id=".intval($announcement_id).",
                        group_id=".intval($group_id);
                $res = Database::query($sql);
                if (false === $res) {
                    return false;
                }
            }
        }

        return true;
    }

    /**
     * Gets the groups of this announce.
     *
     * @param int announcement id
     *
     * @return array array of group id
     */
    public static function get_announcement_groups($announcement_id)
    {
        $tbl_announcement_group = Database::get_main_table(TABLE_MAIN_SYSTEM_ANNOUNCEMENTS_GROUPS);
        $tbl_group = Database::get_main_table(TABLE_USERGROUP);
        //first delete all group associations for this announcement
        $sql = "SELECT
                    g.id as group_id,
                    g.name as group_name
                FROM $tbl_group g , $tbl_announcement_group ag
                WHERE
                    announcement_id =".intval($announcement_id)." AND
                    ag.group_id = g.id";
        $res = Database::query($sql);
        $groups = Database::fetch_array($res);

        return $groups;
    }

    /**
     * Updates an announcement to the database.
     *
     * @param int    $id            of the announcement
     * @param string $title         title of the announcement
     * @param string $content       content of the announcement
     * @param array  $date_start    start date (0 => day ; 1 => month ; 2 => year ; 3 => hour ; 4 => minute)
     * @param array  $date_end      end date of (0 => day ; 1 => month ; 2 => year ; 3 => hour ; 4 => minute)
     * @param array  $visibility
     * @param array  $lang
     * @param int    $send_mail
     * @param bool   $sendEmailTest
     * @param int    $careerId
     * @param int    $promotionId
     *
     * @return bool True on success, false on failure
     */
    public static function update_announcement(
        $id,
        $title,
        $content,
        $date_start,
        $date_end,
        $visibility,
        $lang = null,
        $send_mail = 0,
        $sendEmailTest = false,
        $careerId = 0,
        $promotionId = 0
    ) {
        $sysRepo = Container::getSysAnnouncementRepository();
        /** @var SysAnnouncement $announcement */
        $announcement = $sysRepo->find($id);
        if (null === $announcement) {
            return false;
        }

        $a_dateS = explode(' ', $date_start);
        $a_arraySD = explode('-', $a_dateS[0]);
        $a_arraySH = explode(':', $a_dateS[1]);
        $date_start_to_compare = array_merge($a_arraySD, $a_arraySH);

        $a_dateE = explode(' ', $date_end);
        $a_arrayED = explode('-', $a_dateE[0]);
        $a_arrayEH = explode(':', $a_dateE[1]);
        $date_end_to_compare = array_merge($a_arrayED, $a_arrayEH);

        $lang = is_null($lang) ? '' : $lang;

        if (!checkdate($date_start_to_compare[1], $date_start_to_compare[2], $date_start_to_compare[0])) {
            echo Display::return_message(get_lang('Invalid start date was given.'));

            return false;
        }

        if (($date_end_to_compare[1] ||
                $date_end_to_compare[2] ||
                $date_end_to_compare[0]) &&
            !checkdate($date_end_to_compare[1], $date_end_to_compare[2], $date_end_to_compare[0])
        ) {
            echo Display::return_message(get_lang('Invalid end date was given.'));

            return false;
        }

        if (0 == strlen(trim($title))) {
            echo Display::return_message(get_lang('Please enter a title'));

            return false;
        }

        $start = api_get_utc_datetime($date_start);
        $end = api_get_utc_datetime($date_end);

        //Fixing urls that are sent by email
        //$content = str_replace('src=\"/home/', 'src=\"'.api_get_path(WEB_PATH).'home/', $content);
        //$content = str_replace('file=/home/', 'file='.api_get_path(WEB_PATH).'home/', $content);
        $content = str_replace(
            'src=\"'.api_get_path(REL_HOME_PATH),
            'src=\"'.api_get_path(WEB_PATH).api_get_path(REL_HOME_PATH),
            $content
        );
        $content = str_replace(
            'file='.api_get_path(REL_HOME_PATH),
            'file='.api_get_path(WEB_PATH).api_get_path(REL_HOME_PATH),
            $content
        );

        $dateStart = new DateTime($start, new DateTimeZone('UTC'));
        $dateEnd = new DateTime($end, new DateTimeZone('UTC'));

        $announcement
            ->setLang($lang)
            ->setTitle($title)
            ->setContent($content)
            ->setDateStart($dateStart)
            ->setDateEnd($dateEnd)
            ->setRoles($visibility)
        ;

       $sysRepo->update($announcement);

        // Update visibility
        //$list = self::getVisibilityList();
        $table = Database::get_main_table(TABLE_MAIN_SYSTEM_ANNOUNCEMENTS);

        if (api_get_configuration_value('allow_careers_in_global_announcements') && !empty($careerId)) {
            $params = [];
            $params['career_id'] = (int) $careerId;
            $params['promotion_id'] = (int) $promotionId;
            Database::update(
                $table,
                $params,
                ['id = ? ' => $id]
            );
        }

        /*foreach ($list as $key => $title) {
            $value = isset($visibility[$key]) && $visibility[$key] ? 1 : 0;
            $sql = "UPDATE $table SET $key = '$value' WHERE id = $id";
            Database::query($sql);
        }*/

        if ($sendEmailTest) {
            self::send_system_announcement_by_email($announcement, true);
        } else {
            if (1 == $send_mail) {
                self::send_system_announcement_by_email($announcement);
            }
        }

        return true;
    }

    /**
     * Deletes an announcement.
     *
     * @param int $id The identifier of the announcement that should be
     *
     * @return bool True on success, false on failure
     */
    public static function delete_announcement($id)
    {
        $table = Database::get_main_table(TABLE_MAIN_SYSTEM_ANNOUNCEMENTS);
        $id = (int) $id;
        $sql = "DELETE FROM $table WHERE id =".$id;
        $res = Database::query($sql);
        if (false === $res) {
            return false;
        }
        self::deleteAnnouncementPicture($id);

        return true;
    }

    /**
     * Gets an announcement.
     *
     * @param int $id The identifier of the announcement that should be
     *
     * @return object Object of class StdClass or the required class, containing the query result row
     */
    public static function get_announcement($id)
    {
        $table = Database::get_main_table(TABLE_MAIN_SYSTEM_ANNOUNCEMENTS);
        $id = (int) $id;
        $sql = "SELECT * FROM ".$table." WHERE id = ".$id;
        $announcement = Database::fetch_object(Database::query($sql));

        return $announcement;
    }

    /**
     * Change the visibility of an announcement.
     *
     * @param int  $id
     * @param int  $user    For who should the visibility be changed
     * @param bool $visible
     *
     * @return bool True on success, false on failure
     */
    public static function set_visibility($id, $user, $visible)
    {
        $table = Database::get_main_table(TABLE_MAIN_SYSTEM_ANNOUNCEMENTS);
        $id = (int) $id;
        $list = array_keys(self::getVisibilityList());
        $user = trim($user);
        $visible = (int) $visible;
        if (!in_array($user, $list)) {
            return false;
        }

        $field = $user;
        $sql = "UPDATE $table SET ".$field." = '".$visible."'
                WHERE id='".$id."'";
        $res = Database::query($sql);

        if (false === $res) {
            return false;
        }

        return true;
    }

    /**
     * Send a system announcement by e-mail to all teachers/students depending on parameters.
     *
     * @return bool True if the message was sent or there was no destination matching.
     *              False on database or e-mail sending error.
     */
    public static function send_system_announcement_by_email(SysAnnouncement $announcement, bool $sendEmailTest = false)
    {
        $title = $announcement->getTitle();
        $content = $announcement->getContent();
        $language = $announcement->getLang();

        $content = str_replace(['\r\n', '\n', '\r'], '', $content);
        $now = api_get_utc_datetime();

        if ($sendEmailTest) {
            MessageManager::send_message_simple(api_get_user_id(), $title, $content);

            return true;
        }

        $urlJoin = '';
        $urlCondition = '';
        $user_table = Database::get_main_table(TABLE_MAIN_USER);
        if (api_is_multiple_url_enabled()) {
            $current_access_url_id = api_get_current_access_url_id();
            $url_rel_user = Database::get_main_table(TABLE_MAIN_ACCESS_URL_REL_USER);
            $urlJoin = " INNER JOIN $url_rel_user uu ON uu.user_id = u.id ";
            $urlCondition = " AND access_url_id = '".$current_access_url_id."' ";
        }

        $sql = "SELECT DISTINCT u.id as user_id FROM $user_table u $urlJoin
                WHERE status = '1' $urlCondition ";

        $announcement;
        $sql .= " AND roles IN () ";

        if (!isset($sql)) {
            return false;
        }

        if (!empty($language)) {
            //special condition because language was already treated for SQL insert before
            $sql .= " AND language = '".Database::escape_string($language)."' ";
        }

        // Sent to active users.
        $sql .= " AND email <>'' AND active = 1 ";

        // Expiration date
        $sql .= " AND (expiration_date = '' OR expiration_date IS NULL OR expiration_date > '$now') ";

        if ((empty($teacher) || '0' == $teacher) && (empty($student) || '0' == $student)) {
            return true;
        }

        $userListToFilter = [];
        // @todo check if other filters will apply for the career/promotion option.
        if (null !== $announcement->getCareer()) {
            $promotion = new Promotion();
            $promotionList = $promotion->get_all_promotions_by_career_id($announcement->getCareer()->getId());
            if (null !== $announcement->getPromotion()) {
                $promotionList = [];
                $promotionList[] = $promotion->get($announcement->getPromotion()->getId());
            }

            if (!empty($promotionList)) {
                foreach ($promotionList as $promotion) {
                    $sessionList = SessionManager::get_all_sessions_by_promotion($promotion['id']);
                    foreach ($sessionList as $session) {
                        if ($teacher) {
                            $users = SessionManager::get_users_by_session($session['id'], 2);
                            if (!empty($users)) {
                                $userListToFilter = array_merge($users, $userListToFilter);
                            }
                        }

                        if ($student) {
                            $users = SessionManager::get_users_by_session($session['id'], 0);
                            if (!empty($users)) {
                                $userListToFilter = array_merge($users, $userListToFilter);
                            }
                        }
                    }
                }
            }
        }

        if (!empty($userListToFilter)) {
            $userListToFilter = array_column($userListToFilter, 'user_id');
            $userListToFilterToString = implode("', '", $userListToFilter);
            $sql .= " AND (u.user_id IN ('$userListToFilterToString') ) ";
        }

        $result = Database::query($sql);
        if (false === $result) {
            return false;
        }

        $message_sent = false;
        while ($row = Database::fetch_array($result, 'ASSOC')) {
            MessageManager::send_message_simple($row['user_id'], $title, $content);
            $message_sent = true;
        }

        // Minor validation to clean up the attachment files in the announcement
        if (!empty($_FILES)) {
            $attachments = $_FILES;
            foreach ($attachments as $attachment) {
                unlink($attachment['tmp_name']);
            }
        }

        return $message_sent; //true if at least one e-mail was sent
    }

    /**
     * Displays announcements as an slideshow.
     *
     * @param string $visible see self::VISIBLE_* constants
     * @param int    $id      The identifier of the announcement to display
     */
    public static function getAnnouncements($visible, $id = null): array
    {
        $user_selected_language = Database::escape_string(api_get_language_isocode());
        $table = Database::get_main_table(TABLE_MAIN_SYSTEM_ANNOUNCEMENTS);

        $cut_size = 500;
        $now = api_get_utc_datetime();
        $sql = "SELECT * FROM $table
                WHERE
                    (lang = '$user_selected_language' OR lang = '') AND
                    ('$now' >= date_start AND '$now' <= date_end) ";

        $sql .= self::getVisibilityCondition($visible);

        if (isset($id) && !empty($id)) {
            $id = (int) $id;
            $sql .= " AND id = $id ";
        }

        if (api_is_multiple_url_enabled()) {
            $current_url_id = api_get_current_access_url_id();
            $sql .= " AND access_url_id IN ('1', '$current_url_id') ";
        }

        $checkCareers = true === api_get_configuration_value('allow_careers_in_global_announcements');

        $userId = api_get_user_id();

        $promotion = new Promotion();
        $sql .= ' ORDER BY date_start DESC';
        $result = Database::query($sql);
        $announcements = [];
        if (Database::num_rows($result) > 0) {
            while ($announcement = Database::fetch_object($result)) {
                if ($checkCareers && !empty($announcement->career_id)) {
                    $promotionList = [];
                    if (!empty($announcement->promotion_id)) {
                        $promotionList[] = $announcement->promotion_id;
                    } else {
                        $promotionList = $promotion->get_all_promotions_by_career_id($announcement->career_id);
                        if (!empty($promotionList)) {
                            $promotionList = array_column($promotionList, 'id');
                        }
                    }

                    $show = false;
                    foreach ($promotionList as $promotionId) {
                        $sessionList = SessionManager::get_all_sessions_by_promotion($promotionId);
                        foreach ($sessionList as $session) {
                            $sessionId = $session['id'];
                            // Check student
                            if (self::VISIBLE_STUDENT === $visible &&
                                SessionManager::isUserSubscribedAsStudent($sessionId, $userId)
                            ) {
                                $show = true;
                                break 2;
                            }

                            if (self::VISIBLE_TEACHER === $visible &&
                                SessionManager::user_is_general_coach($userId, $sessionId)
                            ) {
                                $show = true;
                                break 2;
                            }

                            // Check course coach
                            $coaches = SessionManager::getCoachesBySession($sessionId);

                            if (self::VISIBLE_TEACHER === $visible && in_array($userId, $coaches)) {
                                $show = true;
                                break 2;
                            }
                        }
                    }

                    if (false === $show) {
                        continue;
                    }
                }

                $announcementData = [
                    'id' => $announcement->id,
                    'title' => $announcement->title,
                    'content' => $announcement->content,
                    'readMore' => null,
                ];

                if (empty($id)) {
                    if (api_strlen(strip_tags($announcement->content)) > $cut_size) {
                        $announcementData['content'] = cut($announcement->content, $cut_size);
                        $announcementData['readMore'] = true;
                    }
                }

                $announcements[] = $announcementData;
            }
        }

        if (0 === count($announcements)) {
            return [];
        }

        return $announcements;
    }

    /**
     * Get the HTML code for an announcement.
     *
     * @param int    $announcementId The announcement ID
     * @param string $visibility     The announcement visibility
     */
    public static function getAnnouncement($announcementId, $visibility): array
    {
        $selectedUserLanguage = Database::escape_string(api_get_language_isocode());
        $announcementTable = Database::get_main_table(TABLE_MAIN_SYSTEM_ANNOUNCEMENTS);
        $now = api_get_utc_datetime();
        $announcementId = (int) $announcementId;

        $whereConditions = [
            "(lang = ? OR lang IS NULL OR lang = '') " => $selectedUserLanguage,
            "AND (? >= date_start AND ? <= date_end) " => [$now, $now],
            "AND id = ? " => $announcementId,
        ];

        $condition = self::getVisibilityCondition($visibility);
        $whereConditions[$condition] = 1;

        if (api_is_multiple_url_enabled()) {
            $whereConditions["AND access_url_id IN (1, ?) "] = api_get_current_access_url_id();
        }

        $announcement = Database::select(
            '*',
            $announcementTable,
            [
                'where' => $whereConditions,
                'order' => 'date_start',
            ],
            'first'
        );

        return $announcement;
    }

    /**
     * @return string
     */
    public static function getCurrentUserVisibility()
    {
        if (api_is_anonymous()) {
            return self::VISIBLE_GUEST;
        }

        if (api_is_student_boss()) {
            return self::VISIBLE_STUDENT_BOSS;
        }

        if (api_is_session_admin()) {
            return self::VISIBLE_SESSION_ADMIN;
        }

        if (api_is_drh()) {
            return self::VISIBLE_DRH;
        }

        if (api_is_teacher()) {
            return self::VISIBLE_TEACHER;
        } else {
            return self::VISIBLE_STUDENT;
        }
    }

    /**
     * Deletes the Announcement picture.
     *
     * @param int $announcementId
     */
    public static function deleteAnnouncementPicture($announcementId)
    {
        /*$store_path = api_get_path(SYS_UPLOAD_PATH).'announcements';

        // image name
        $announcementPicture = $store_path.'/announcement_'.$announcementId.'.png';
        $announcementPictureSmall = $store_path.'/announcement_'.$announcementId.'_100x100.png';

        if (file_exists($announcementPicture)) {
            unlink($announcementPicture);
        }
        if (file_exists($announcementPictureSmall)) {
            unlink($announcementPictureSmall);
        }*/
    }

    /**
     * get announcement picture.
     *
     * @param int $announcementId
     *
     * @return string|null
     */
    private static function getPictureAnnouncement($announcementId)
    {
        /*$store_path = api_get_path(SYS_UPLOAD_PATH).'announcements';
        $announcementPicture = $store_path.'/announcement_'.$announcementId.'.png';
        if (file_exists($announcementPicture)) {
            $web_path = api_get_path(WEB_UPLOAD_PATH).'announcements';
            $urlPicture = $web_path.'/announcement_'.$announcementId.'.png';

            return $urlPicture;
        }

        return null;*/
    }
}
