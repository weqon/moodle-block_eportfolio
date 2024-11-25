<?php
// This file is part of Moodle - http://moodle.org/
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
// along with Moodle.  If not, see <http://www.gnu.org/licenses/>.

/**
 * Locallib for block eportfolio
 *
 * @package     block_eportfolio
 * @copyright   2024 weQon UG <support@weqon.net>
 * @license     https://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */

/**
 * Renders HTML to display shared eportfolios
 *
 * @param string $shareoption
 * @param int $courseid
 * @param int $userid
 * @return string
 */
function get_shared_eportfolios($shareoption, $courseid, $userid = '') {
    global $DB, $USER, $OUTPUT;

    // Only display eportfolios for grading if current user is enrolled as grading teacher.
    if ($shareoption === 'grade' && empty($userid)) {
        $config = get_config('local_eportfolio');
        $gradingroles = explode(',', $config->gradingteacher);
        $coursecontext = context_course::instance($courseid);
        $isgradingteacher = false;

        foreach ($gradingroles as $gr) {
            $check = check_gradingteacher_role($gr, $coursecontext->id);
            if ($check) {
                $isgradingteacher = true;
            }
        }

        if (!$isgradingteacher) {
            return false;
        }
    }

    $sql = "SELECT * FROM {local_eportfolio_share} WHERE shareoption = :shareoption AND courseid = :courseid";

    $params = [
            'shareoption' => (string) $shareoption,
            'courseid' => (int) $courseid,
    ];

    if (!empty($userid)) {
        $sql .= " AND usermodified = :usermodified";
        $params['usermodified'] = (int) $userid;
    } else if ($shareoption != 'template') {
        $sql .= " AND usermodified <> :usermodified";
        $params['usermodified'] = (int) $USER->id;
    }

    $eportfoliosshare = $DB->get_records_sql($sql, $params);

    // Check, if there is a cm for the eportfolio mod.
    $cm = get_eportfolio_cm($courseid);

    if (!empty($eportfoliosshare)) {

        // Enddate reached?
        $currentdate = time();
        $enddate = false;

        $datareturn = [];

        foreach ($eportfoliosshare as $es) {
            if ($es->enddate != 0 && $es->enddate < $currentdate) {
                $enddate = true;
            }

            $eligible = false;

            if ($shareoption === 'grade' && !empty($userid)) {
                // We are diyplaying shared for grading for the specific user id.
                $eligible = true;
            } else if (!empty($userid)) {
                // We are diyplaying shared ePortfolios for the specific user id.
                $eligible = true;
            } else {
                $eligible = check_eligible($courseid, $es->fullcourse, $es->roles, $es->enrolled, $es->coursegroups);
            }

            if (!$enddate && $eligible) {

                $data = new stdClass();

                $viewurl = new moodle_url('/local/eportfolio/view.php',
                        ['id' => $es->id, 'course' => $courseid, 'tocourse' => '1']);

                $data->icon = $OUTPUT->pix_icon('i/search', '');

                // If a course module exists, link to the mod view.
                if ($shareoption === 'grade' && !empty($cm)) {
                    $viewurl = new moodle_url('/mod/eportfolio/grade.php',
                            ['id' => $cm, 'eportid' => $es->id]);

                    $data->icon = $OUTPUT->pix_icon('e/table', '');
                }

                $data->id = $es->id;
                $data->title = (!empty($es->title)) ? $es->title : get_h5p_title($es->h5pid);
                $data->fileidcontext = $es->fileidcontext;
                $data->viewurl = $viewurl->out(false);

                $datareturn[] = $data;

            }

        }

        return $datareturn;
    }
}

/**
 * Check gradingteacher role.
 *
 * @param int $roleid
 * @param int $coursecontextid
 * @return bool
 */
function check_gradingteacher_role($roleid, $coursecontextid) {
    global $DB, $USER;

    // Just return course where the user has the specified role assigned.
    $sql = "SELECT * FROM {role_assignments} WHERE contextid = :contextid AND userid = :userid AND roleid = :roleid";
    $params = [
            'contextid' => (int) $coursecontextid,
            'userid' => (int) $USER->id,
            'roleid' => (int) $roleid,
    ];

    $getrole = $DB->get_record_sql($sql, $params);

    if (empty($getrole)) {
        return false;
    } else {
        return true;
    }
}

/**
 * Check, if a ePortfolio was shared for specific user, role or course group.
 *
 * @param int $courseid
 * @param int $fullcourse
 * @param array $roles
 * @param array $enrolled
 * @param array $coursegroups
 * @return bool
 */
function check_eligible($courseid, $fullcourse, $roles, $enrolled, $coursegroups) {
    global $DB, $USER;

    // Siteadmins should always be eligible.
    if (is_siteadmin()) {
        return true;
    }

    $coursecontext = context_course::instance($courseid);

    // First, check, if I am eligible to view this eportfolio.
    $eligible = false;

    if ($fullcourse == '1' && !$eligible) {
        return true;
    }

    if (!empty($roles) && !$eligible) {
        $roles = explode(', ', $roles);

        foreach ($roles as $ro) {
            $isenrolled = $DB->get_record('role_assignments',
                    ['contextid' => $coursecontext->id, 'roleid' => $ro, 'userid' => $USER->id]);

            if (!empty($isenrolled)) {
                $eligible = true;
            }
        }
    }

    if (!empty($enrolled) && !$eligible) {
        $enrolledusers = explode(', ', $enrolled);

        if (in_array($USER->id, $enrolledusers)) {
            $eligible = true;
        }
    }

    if (!empty($coursegroups) && !$eligible) {
        $groups = explode(', ', $coursegroups);

        foreach ($groups as $gr) {
            $coursegroups = groups_get_all_groups($courseid, $USER->id);

            if (in_array($gr, $coursegroups)) {
                $eligible = true;
            }
        }
    }

    return $eligible;
}

/**
 * Get the H5P file title.
 *
 * @param int $id
 * @return void
 */
function get_h5p_title($id) {
    global $DB;

    $h5pfile = $DB->get_record('h5p', ['id' => $id]);

    if (!empty($h5pfile)) {
        $json = $h5pfile->jsoncontent;
        $jsondecode = json_decode($json);

        if (isset($jsondecode->metadata)) {
            if ($jsondecode->metadata->title) {
                $title = $jsondecode->metadata->title;
            }
        } else {
            $title = $jsondecode->title;
        }

        if (!empty($title)) {
            return $title;
        }
    }
}

/**
 * Get course module for the ePortfolio activity.
 *
 * @param int $courseid
 * @return false|void
 */
function get_eportfolio_cm($courseid) {
    global $DB;

    // First check, if the eportfolio activity is available and enabled.
    $activityplugin = \core_plugin_manager::instance()->get_plugin_info('mod_eportfolio');

    if (!$activityplugin || !$activityplugin->is_enabled()) {
        return false;
    }

    // Only one instance per course is allowed.
    // Get the cm ID for the eportfolio activity for the current course.
    $sql = "SELECT cm.id
        FROM {modules} m
        JOIN {course_modules} cm
        ON m.id = cm.module
        WHERE cm.course = :cmcourse AND m.name = :mname";

    $params = [
            'cmcourse' => (int) $courseid,
            'mname' => 'eportfolio',
    ];

    $coursemodule = $DB->get_record_sql($sql, $params);

    if ($coursemodule) {
        // At last but not least, let's do an availability check.
        $modinfo = get_fast_modinfo($courseid);
        $cm = $modinfo->get_cm($coursemodule->id);

        if ($cm->uservisible) {
            // User can access the activity.
            return $coursemodule->id;
        } else if ($cm->availableinfo) {
            // User cannot access the activity.
            // But on the course page they will see a why they can't access it.
            return false;
        } else {
            // User cannot access the activity.
            return false;

        }
    }
}
