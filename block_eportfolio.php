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
 * Block eportfolio is defined here.
 *
 * @package     block_eportfolio
 * @copyright   2023 weQon UG <support@weqon.net>
 * @license     https://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */

defined('MOODLE_INTERNAL') || die();

require($CFG->dirroot . '/mod/eportfolio/locallib.php');


/**
 * Block eportfolio is defined here.
 *
 * @package     block_eportfolio
 * @copyright   2023 weQon UG <support@weqon.net>
 * @license     https://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */
class block_eportfolio extends block_base {

    /**
     * Initializes class member variables.
     */
    public function init() {
        // Needed by Moodle to differentiate between blocks.
        $this->title = get_string('pluginname', 'block_eportfolio');
    }

    /**
     * Returns the block config.
     *
     * @return bool
     */
    public function has_config() {
        return false;
    }

    /**
     * Returns the block contents.
     *
     * @return stdClass The block contents.
     */
    public function get_content() {
        global $USER, $DB, $COURSE;

        if ($this->content !== null) {
            return $this->content;
        }

        if (empty($this->instance)) {
            $this->content = '';
            return $this->content;
        }

        $this->content = new stdClass();
        $this->content->text = '';

        // Check, if the current course is marked as ePortfolio course.
        $customfielddata = $DB->get_record('customfield_data', ['instanceid' => $COURSE->id]);

        if ($customfielddata->intvalue) {

            // Check if there are any ePortfolios available for this course.
            if ($DB->get_records('local_eportfolio_share', ['courseid' => $COURSE->id])) {

                $urlview = '/local/eportfolio/view.php';
                $urlmod = '/local/eportfolio/view.php'; // Default value.
                $coursemodulecontext = '';

                // Check, if there is a cm for the eportfolio mod.
                if ($cm = get_eportfolio_cm($COURSE->id)) {
                    // If no cm was found, all eportfolios will be for view only.
                    $urlmod = '/mod/eportfolio/view.php';
                    $coursemodulecontext = context_module::instance($cm);
                }

                $usercontext = context_user::instance($USER->id);
                $courseocntext = context_course::instance($COURSE->id);

                $mysharedeportfolios = get_my_shared_eportfolios($courseocntext, 'share', $COURSE->id);
                $mysharedeportfoliosgrade = get_my_shared_eportfolios($coursemodulecontext, 'grade', $COURSE->id);
                $sharedeportfolios = get_shared_eportfolios('share', $COURSE->id);
                $sharedeportfoliosgrade = get_shared_eportfolios('grade', $COURSE->id);

                // ToDo: Use mustache templates instead.
                if (!empty($mysharedeportfolios)) {
                    $this->content->text .= html_writer::tag('h6', get_string('header:mysharedeportfolios', 'block_eportfolio'),
                            ['class' => 'mb-3']);

                    foreach ($mysharedeportfolios as $eport) {
                        $this->content->text .= html_writer::start_tag('p', ['class' => 'pl-2']);
                        $this->content->text .= html_writer::tag('i', '', ['class' => 'fa fa-search mr-1']);
                        $this->content->text .= html_writer::link(new moodle_url($urlview,
                                ['id' => $eport['id'], 'course' => $COURSE->id, 'tocourse' => '1']),
                                $eport['filename']);
                        $this->content->text .= html_writer::end_tag('p');

                    }
                }
                if (!empty($mysharedeportfoliosgrade)) {
                    $this->content->text .= html_writer::tag('h6',
                            get_string('header:mysharedeportfoliosgrade', 'block_eportfolio'), ['class' => 'mb-3']);

                    foreach ($mysharedeportfoliosgrade as $eport) {

                        if ($cm) {
                            $params = [
                                    'id' => $cm,
                            ];
                        } else {
                            $params = [
                                    'id' => $eport['id'],
                                    'courseid' => $eport['courseid'],
                                    'tocourse' => '1',
                            ];

                        }

                        $this->content->text .= html_writer::start_tag('p', ['class' => 'pl-2']);
                        $this->content->text .= html_writer::tag('i', '', ['class' => 'fa fa-table mr-1']);
                        $this->content->text .= html_writer::link(new moodle_url($urlmod, $params), $eport['filename']);
                        $this->content->text .= html_writer::end_tag('p');
                    }
                }
                if (!empty($sharedeportfolios)) {
                    $this->content->text .= html_writer::tag('h6', get_string('header:sharedeportfolios', 'block_eportfolio'),
                            ['class' => 'mb-3']);

                    foreach ($sharedeportfolios as $eport) {

                        $this->content->text .= html_writer::start_tag('p', ['class' => 'pl-2']);
                        $this->content->text .= html_writer::tag('i', '', ['class' => 'fa fa-search mr-1']);
                        $this->content->text .= html_writer::link(new moodle_url($urlview,
                                ['id' => $eport['id'], 'course' => $COURSE->id, 'userid' => $eport['userid'],
                                        'tocourse' => '1']), $eport['filename']);
                        $this->content->text .= html_writer::end_tag('p');
                    }
                }
                if (!empty($sharedeportfoliosgrade)) {
                    $this->content->text .= html_writer::tag('h6',
                            get_string('header:sharedeportfoliosgrade', 'block_eportfolio'), ['class' => 'mb-3']);

                    foreach ($sharedeportfoliosgrade as $eport) {

                        if ($cm) {
                            $params = [
                                    'id' => $cm,
                                    'fileid' => $eport['fileidcontext'],
                                    'userid' => $eport['userid'],
                                    'action' => 'grade',
                            ];
                        } else {
                            $params = [
                                    'id' => $eport['id'],
                                    'courseid' => $eport['courseid'],
                                    'tocourse' => '1',
                            ];

                        }

                        $this->content->text .= html_writer::start_tag('p', ['class' => 'pl-2']);
                        $this->content->text .= html_writer::tag('i', '', ['class' => 'fa fa-table mr-1']);
                        $this->content->text .= html_writer::link(new moodle_url($urlmod, $params), $eport['filename']);
                        $this->content->text .= html_writer::end_tag('p');
                    }
                }

            } else {
                $this->content->text .= get_string('message:noeportfoliosshared', 'block_eportfolio');
            }

        } else {
            $this->content->text .= get_string('message:noeportfoliocourse', 'block_eportfolio');
        }

        return $this->content;
    }

    /**
     * Defines configuration data.
     *
     * The function is called immediately after init().
     */
    public function specialization() {

        // Load user defined title and make sure it's never empty.
        if (empty($this->config->title)) {
            $this->title = get_string('pluginname', 'block_eportfolio');
        } else {
            $this->title = $this->config->title;
        }
    }

    /**
     * Sets the applicable formats for the block.
     *
     * @return string[] Array of pages and permissions.
     */
    public function applicable_formats() {
        return [
                'course-view' => true,
        ];
    }

    /**
     * Multiple instances allowed for the block.
     *
     * @return bool
     */
    public function instance_allow_multiple() {
        return false;
    }
}
