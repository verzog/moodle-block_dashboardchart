<?php
// This file is part of Moodle - https://moodle.org/
//
// Moodle is free software: you can redistribute it and/or modify
// it under the terms of the GNU General Public License as published by
// the Free Software Foundation, either version 3 of the License, or
// (at your option) any later version.
//
// Moodle is distributed in the hope that it will be useful,
// but WITHOUT ANY WARRANTY; without even the implied warranty of
// MERCHANTABILITY or FITNESS FOR A PARTICULAR PURPOSE.  See the
// GNU General Public License for more details.
//
// You should have received a copy of the GNU General Public License
// along with Moodle.  If not, see <https://www.gnu.org/licenses/>.

/**
 * Block class for the dashboardchart block.
 *
 * @package    block_dashboardchart
 * @copyright  2022 Brain Station 23 Ltd.
 * @copyright  2026 Vernon Spain
 * @author     Brain Station 23 Ltd.
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */

/**
 * Dashboard chart block.
 *
 * @package    block_dashboardchart
 * @copyright  2022 Brain Station 23 Ltd.
 * @copyright  2026 Vernon Spain
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */
class block_dashboardchart extends block_base {
    /**
     * Allow the block to have a configuration page.
     *
     * @return boolean
     */
    public function has_config() {
        return true;
    }

    /**
     * Init function for the plugin name.
     *
     * @return void
     * @throws coding_exception
     */
    public function init() {
        $this->title = get_string('pluginname', 'block_dashboardchart');
    }

    /**
     * Get content function.
     *
     * @return stdClass|null
     */
    public function get_content() {
        if ($this->content !== null) {
            return $this->content;
        }

        if (isset($this->config->dashboardcharttype) && !empty($this->config->msg)) {
            $this->title = $this->config->msg;
        }

        $this->content = new stdClass();
        $this->content->text = $this->make_custom_content();

        return $this->content;
    }

    /**
     * Update title.
     *
     * @return void
     */
    public function specialization() {
        if (isset($this->config)) {
            if (!empty($this->config->msg)) {
                $this->title = $this->config->msg;
            } else {
                $this->config->msg = get_string('dashboardchart', 'block_dashboardchart');
            }
        }
    }

    /**
     * Allow the block to have multiple instances.
     *
     * @return bool
     */
    public function instance_allow_multiple() {
        return true;
    }

    /**
     * Make custom content for the block.
     *
     * @return string
     */
    public function make_custom_content() {
        $datalimit = $this->config->datalimit ?? 5;

        if (isset($this->config->dashboardcharttype)) {
            if ($this->config->dashboardcharttype == 'coursewiseenrollment') {
                return $this->make_course_student_table($datalimit);
            } else if ($this->config->dashboardcharttype == 'category') {
                return $this->make_category_course_table($datalimit);
            } else if ($this->config->dashboardcharttype == 'login') {
                return $this->make_login_table($datalimit);
            } else if ($this->config->dashboardcharttype == 'loginusers') {
                return $this->make_login_user_table();
            } else if ($this->config->dashboardcharttype == 'active_courses') {
                return $this->make_most_active_courses_table($datalimit);
            }
        }

        return $this->make_enrollment_table($datalimit);
    }

    /**
     * Make the enrolment leaderboard by country.
     *
     * @param int $datalimit
     * @return string
     */
    public function make_enrollment_table($datalimit) {
        global $DB;
        $sql = "SELECT country, COUNT(country) AS newusers
                  FROM {user}
                 WHERE country <> ''
              GROUP BY country
              ORDER BY COUNT(country) DESC";

        $rows = $DB->get_records_sql($sql, null, 0, $datalimit);
        $series = [];
        $labels = [];
        foreach ($rows as $row) {
            if (empty($row->country)) {
                continue;
            }
            $series[] = $row->newusers;
            $labels[] = get_string($row->country, 'countries');
        }

        return $this->display_graph(
            $series,
            $labels,
            get_string('country_title', 'block_dashboardchart'),
            get_string('country_desc', 'block_dashboardchart')
        );
    }

    /**
     * Get the most active courses by log activity.
     *
     * @param int $datalimit
     * @return string
     * @throws dml_exception
     */
    public function make_most_active_courses_table($datalimit) {
        global $DB;

        $params = [
            'studentrole' => 5,
            'coursecontext' => CONTEXT_COURSE,
            'siteid' => SITEID,
        ];
        $sql = "SELECT c.shortname, COUNT(l.userid) AS views
                  FROM {logstore_standard_log} l
                  JOIN {user} u ON l.userid = u.id
                  JOIN {role_assignments} r ON r.userid = u.id AND r.roleid = :studentrole
                  JOIN {course} c ON c.id = l.courseid
                  JOIN {context} ct ON ct.contextlevel = :coursecontext AND ct.instanceid = l.courseid
                 WHERE c.id <> :siteid
              GROUP BY c.shortname
              ORDER BY COUNT(l.userid) DESC";

        $records = $DB->get_records_sql($sql, $params, 0, $datalimit);

        $series = [];
        $labels = [];

        foreach ($records as $data) {
            $series[] = $data->views;
            $labels[] = $data->shortname;
        }

        return $this->display_graph(
            $series,
            $labels,
            get_string('mostactive', 'block_dashboardchart'),
            get_string('mostactive_desc', 'block_dashboardchart')
        );
    }

    /**
     * Distinct active users per day for the most recent days.
     *
     * @param int $datalimit
     * @return string
     * @throws dml_exception
     */
    public function make_login_table($datalimit) {
        global $DB;

        // Count the distinct users active on each of the most recent local
        // days. The day boundaries are derived with calendar maths
        // (usergetmidnight) rather than by adding DAYSECS, so the buckets stay
        // correct across daylight saving changes, and the query uses only a
        // portable integer range so it runs on both MySQL and PostgreSQL.
        $numdays = ($datalimit > 1) ? (int) $datalimit : 30;

        $series = [];
        $labels = [];

        $sql = "SELECT COUNT(DISTINCT userid)
                  FROM {logstore_standard_log}
                 WHERE timecreated >= :dayfrom AND timecreated < :dayto";

        $probe = time();
        for ($i = 0; $i < $numdays; $i++) {
            $daystart = usergetmidnight($probe);
            // Probe a time safely inside the next calendar day, then snap to
            // its local midnight so the day end is daylight-saving correct.
            $dayend = usergetmidnight($daystart + DAYSECS + (2 * HOURSECS));

            $logins = $DB->get_field_sql($sql, ['dayfrom' => $daystart, 'dayto' => $dayend]);

            $series[] = (int) $logins;
            $labels[] = userdate($daystart, get_string('strftimedaydate', 'langconfig'));

            // Step to the previous day; one second before midnight lands in it.
            $probe = $daystart - 1;
        }

        // Present the oldest day first so the chart reads left to right.
        $series = array_reverse($series);
        $labels = array_reverse($labels);

        return $this->display_graph(
            $series,
            $labels,
            get_string('logins', 'block_dashboardchart'),
            get_string('date', 'block_dashboardchart')
        );
    }

    /**
     * Show the two users with the most logins and the two with the fewest.
     *
     * Only users who have logged in at least once are considered. Login
     * counts identify individuals, so this chart is offered to site admins
     * only (see edit_form.php).
     *
     * @return string
     */
    public function make_login_user_table() {
        $data = $this->get_login_user_data();

        return $this->display_graph(
            $data['series'],
            $data['labels'],
            get_string('loginusers', 'block_dashboardchart'),
            get_string('username', 'block_dashboardchart')
        );
    }

    /**
     * Gather the two users with the most logins and the two with the fewest.
     *
     * Results are ordered highest first, so the chart reads from the most to
     * the least active. Only users with at least one login appear.
     *
     * @return array Two parallel arrays keyed 'labels' (full names) and 'series' (login counts).
     * @throws dml_exception
     */
    public function get_login_user_data() {
        global $DB;

        $countsql = "SELECT l.userid, COUNT(l.id) AS logins
                       FROM {logstore_standard_log} l
                      WHERE l.action = :action AND l.target = :target
                   GROUP BY l.userid";
        $params = ['action' => 'loggedin', 'target' => 'user'];

        // Two users with the most logins, then two with the fewest. The
        // secondary sort on userid keeps the result stable across databases.
        $top = $DB->get_records_sql($countsql . ' ORDER BY COUNT(l.id) DESC, l.userid ASC', $params, 0, 2);
        $bottom = $DB->get_records_sql($countsql . ' ORDER BY COUNT(l.id) ASC, l.userid ASC', $params, 0, 2);

        // Present the highest first, then append the lowest users, skipping any
        // already shown when the site has four or fewer users with logins.
        $ordered = $top;
        foreach (array_reverse($bottom, true) as $userid => $row) {
            if (!isset($ordered[$userid])) {
                $ordered[$userid] = $row;
            }
        }

        $series = [];
        $labels = [];
        if (!empty($ordered)) {
            // Load the display names in one query (see CLAUDE.md 5.1).
            $namefields = \core_user\fields::for_name()->get_sql('', true)->selects;
            $users = $DB->get_records_list('user', 'id', array_keys($ordered), '', 'id' . $namefields);

            foreach ($ordered as $userid => $row) {
                if (!isset($users[$userid])) {
                    continue;
                }
                $series[] = (int) $row->logins;
                $labels[] = fullname($users[$userid]);
            }
        }

        return ['labels' => $labels, 'series' => $series];
    }

    /**
     * Get the number of courses in each category.
     *
     * @param int $datalimit
     * @return string
     * @throws dml_exception
     */
    public function make_category_course_table($datalimit) {
        global $DB;

        $sql = "SELECT name, coursecount
                  FROM {course_categories}
              ORDER BY coursecount DESC";

        $records = $DB->get_records_sql($sql, null, 0, $datalimit);

        $series = [];
        $labels = [];

        foreach ($records as $data) {
            $series[] = $data->coursecount;
            $labels[] = $data->name;
        }

        return $this->display_graph(
            $series,
            $labels,
            get_string('courseno', 'block_dashboardchart'),
            get_string('categoryname', 'block_dashboardchart')
        );
    }

    /**
     * Get the number of students enrolled in each course.
     *
     * @param int $datalimit
     * @return string
     * @throws dml_exception
     */
    public function make_course_student_table($datalimit) {
        global $DB;

        $sql = "SELECT c.fullname AS coursename, COUNT(u.username) AS studentcount
                  FROM {role_assignments} r
                  JOIN {user} u ON r.userid = u.id
                  JOIN {role} rn ON r.roleid = rn.id
                  JOIN {context} ctx ON r.contextid = ctx.id
                  JOIN {course} c ON ctx.instanceid = c.id
                 WHERE rn.shortname = 'student'
              GROUP BY c.fullname, rn.shortname
              ORDER BY COUNT(u.username) DESC";

        $records = $DB->get_records_sql($sql, null, 0, $datalimit);

        $series = [];
        $labels = [];

        foreach ($records as $data) {
            $series[] = $data->studentcount;
            $labels[] = $data->coursename;
        }

        return $this->display_graph(
            $series,
            $labels,
            get_string('studentpercourse', 'block_dashboardchart'),
            get_string('mostactive_desc', 'block_dashboardchart')
        );
    }

    /**
     * Display graph.
     *
     * @param float[] $seriesvalue
     * @param array $labels
     * @param string $title
     * @param string $labelx
     * @return string
     */
    public function display_graph($seriesvalue, $labels, $title, $labelx) {
        global $OUTPUT, $CFG;

        $config = get_config('block_dashboardchart');

        $chart = new \core\chart_bar();
        $series = new \core\chart_series($title, $seriesvalue);

        $chartcolour = empty($config->barcolor) ? '#2385E5' : $config->barcolor;

        if (isset($this->config->graphtype)) {
            if ($this->config->graphtype == 'horizontal') {
                $chart->set_horizontal(true);
                $CFG->chart_colorset = [$chartcolour];
            } else if ($this->config->graphtype == 'pie') {
                $chart = new \core\chart_pie();
            } else if ($this->config->graphtype == 'line') {
                $CFG->chart_colorset = [$chartcolour];
                $chart = new \core\chart_line();
                $chart->set_smooth(true);
            } else {
                $CFG->chart_colorset = [$chartcolour];
            }
        } else {
            $CFG->chart_colorset = [$chartcolour];
        }
        $chart->set_labels($labels);
        $chart->add_series($series);
        $yaxis = $chart->get_yaxis(0, true);
        $yaxis->set_min(0);
        $chart->get_xaxis(0, true)->set_label($labelx);

        return $OUTPUT->render($chart);
    }
}
