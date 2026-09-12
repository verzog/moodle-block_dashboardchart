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
 * Edit form for the dashboardchart block.
 *
 * @package    block_dashboardchart
 * @copyright  2022 Brain Station 23 Ltd.
 * @copyright  2026 Vernon Spain
 * @author     Brain Station 23 Ltd.
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */

/**
 * Block instance configuration form.
 *
 * @package    block_dashboardchart
 * @copyright  2022 Brain Station 23 Ltd.
 * @copyright  2026 Vernon Spain
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */
class block_dashboardchart_edit_form extends block_edit_form {
    /**
     * Adds configuration fields to the block instance edit form.
     *
     * @param MoodleQuickForm $mform The form being built.
     * @return void
     */
    protected function specific_definition($mform) {
        global $USER;

        // Section header title according to language file.
        $mform->addElement('header', 'config_header', get_string('blocksettings', 'block'));

        // A heading message with a default value.
        $mform->addElement('text', 'config_msg', get_string('blockstring', 'block_dashboardchart'));
        $mform->setDefault('config_msg', get_string('pluginname', 'block_dashboardchart'));
        $mform->setType('config_msg', PARAM_TEXT);

        // Graph type.
        $graphposition = [];
        $graphposition['horizontal'] = get_string('horizontal', 'block_dashboardchart');
        $graphposition['vertical'] = get_string('vertical', 'block_dashboardchart');
        $graphposition['pie'] = get_string('pie', 'block_dashboardchart');
        $graphposition['line'] = get_string('line', 'block_dashboardchart');
        $mform->addElement('select', 'config_graphtype', get_string('graphtype', 'block_dashboardchart'), $graphposition);
        $mform->setDefault('config_graphtype', 'vertical');

        $dashboardcharttype = [];
        $dashboardcharttype[""] = get_string('dashboardcharttype:select', 'block_dashboardchart');
        $dashboardcharttype["coursewiseenrollment"] = get_string('dashboardcharttype:coursewiseenrollment', 'block_dashboardchart');
        $dashboardcharttype["category"] = get_string('dashboardcharttype:category', 'block_dashboardchart');
        $dashboardcharttype["active_courses"] = get_string('dashboardcharttype:active_courses', 'block_dashboardchart');
        if (is_siteadmin($USER->id)) {
            $dashboardcharttype["login"] = get_string('dashboardcharttype:login', 'block_dashboardchart');
            $dashboardcharttype["enrollmentbyCountry"] = get_string('dashboardcharttype:enrollment', 'block_dashboardchart');
        }
        $mform->addElement(
            'select',
            'config_dashboardcharttype',
            get_string('dashboardcharttype', 'block_dashboardchart'),
            $dashboardcharttype
        );

        $datalimitoptions = [];
        $datalimitoptions[""] = get_string('dashboardcharttype:select', 'block_dashboardchart');
        $datalimitoptions[5] = get_string('datalimitoption:top5', 'block_dashboardchart');
        $datalimitoptions[10] = get_string('datalimitoption:top10', 'block_dashboardchart');
        $datalimitoptions[20] = get_string('datalimitoption:top20', 'block_dashboardchart');
        $datalimitoptions[1] = get_string('datalimitoption:all', 'block_dashboardchart');

        $mform->addElement(
            'select',
            'config_datalimit',
            get_string('datalimitlabel', 'block_dashboardchart'),
            $datalimitoptions
        );
        $mform->setDefault('config_datalimit', 5);
    }
}
