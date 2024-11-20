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
 * @copyright   2024 weQon UG <support@weqon.net>
 * @license     https://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */

/**
 * Block eportfolio is defined here.
 *
 * @package     block_eportfolio
 * @copyright   2024 weQon UG <support@weqon.net>
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
        global $USER, $DB, $COURSE, $OUTPUT;

        require_once(__DIR__ . '/locallib.php');

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
        $sql = "SELECT cd.*
        FROM {customfield_data} cd
        JOIN {customfield_field} cf ON cf.id = cd.fieldid
        WHERE cf.shortname = :shortname AND cd.instanceid = :instanceid";

        $params = [
                'shortname' => 'eportfolio_course',
                'instanceid' => $COURSE->id,
        ];

        $customfielddata = $DB->get_record_sql($sql, $params);

        if (empty($customfielddata) || !$customfielddata->intvalue) {
            // Check, if the course was marked as ePortfolio course.
            $this->content->text .= get_string('message:noeportfoliocourse', 'block_eportfolio');
            return $this->content;
        } else if (!$DB->get_records('local_eportfolio_share', ['courseid' => $COURSE->id])) {
            // Check, if there are any ePortfolios available for this course.
            $this->content->text .= get_string('message:noeportfoliosshared', 'block_eportfolio');
            return $this->content;
        }

        $mysharedeportfolios = get_shared_eportfolios('share', $COURSE->id, $USER->id);
        $mysharedeportfoliosgrade = get_shared_eportfolios('grade', $COURSE->id, $USER->id);
        $sharedeportfolios = get_shared_eportfolios('share', $COURSE->id);
        $sharedeportfoliosgrade = get_shared_eportfolios('grade', $COURSE->id);
        $sharedeportfoliostemplate = get_shared_eportfolios('template', $COURSE->id);

        if (!empty($mysharedeportfolios)) {
            $templatedata = new \stdClass();
            $templatedata->header = get_string('header:mysharedeportfolios', 'block_eportfolio');
            $templatedata->eportfolios = $mysharedeportfolios;

            $this->content->text .= $OUTPUT->render_from_template('block_eportfolio/view_eportfolios', $templatedata);
        }

        if (!empty($mysharedeportfoliosgrade)) {
            $templatedata = new \stdClass();
            $templatedata->header = get_string('header:mysharedeportfoliosgrade', 'block_eportfolio');
            $templatedata->eportfolios = $mysharedeportfoliosgrade;

            $this->content->text .= $OUTPUT->render_from_template('block_eportfolio/view_eportfolios', $templatedata);
        }

        if (!empty($sharedeportfolios)) {
            $templatedata = new \stdClass();
            $templatedata->header = get_string('header:sharedeportfolios', 'block_eportfolio');
            $templatedata->eportfolios = $sharedeportfolios;

            $this->content->text .= $OUTPUT->render_from_template('block_eportfolio/view_eportfolios', $templatedata);
        }

        if (!empty($sharedeportfoliosgrade)) {
            $templatedata = new \stdClass();
            $templatedata->header = get_string('header:sharedeportfoliosgrade', 'block_eportfolio');
            $templatedata->eportfolios = $sharedeportfoliosgrade;

            $this->content->text .= $OUTPUT->render_from_template('block_eportfolio/view_eportfolios', $templatedata);
        }

        if (!empty($sharedeportfoliostemplate)) {
            $templatedata = new \stdClass();
            $templatedata->header = get_string('header:sharedtemplates', 'block_eportfolio');
            $templatedata->eportfolios = $sharedeportfoliostemplate;

            $this->content->text .= $OUTPUT->render_from_template('block_eportfolio/view_eportfolios', $templatedata);
        }

        return $this->content;
    }

    /**
     * Defines configuration data.
     *
     * The function is called immediately after init().
     */
    public function specialization() {
        return false;
    }

    /**
     * Sets the applicable formats for the block.
     *
     * @return string[] Array of pages and permissions.
     */
    public function applicable_formats() {
        return [
                'course-view' => true,
                'mod*' => true,
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
