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
 * TODO describe file ajax
 *
 * @package    availability_treasurehunt
 * @copyright  2025 YOUR NAME <your@email.com>
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */

require('../../../config.php');
require_once($CFG->libdir . '/adminlib.php');
require_once($CFG->dirroot . '/mod/treasurehunt/locallib.php');
$action = required_param('action', PARAM_TEXT);
$treasurehuntid = required_param('treasurehuntid', PARAM_INT);
$id = $treasurehuntid;
$cm = get_coursemodule_from_instance('treasurehunt', $treasurehuntid, 0, false, MUST_EXIST);

$course = $DB->get_record('course', ['id' => $cm->course], '*', MUST_EXIST);
require_login($course, false, $cm);
$context = context_module::instance($cm->id);
$PAGE->set_context(null);
$treasurehuntid = $cm->instance;

switch ($action) {
    case 'get_stages':
        $stages = \availability_treasurehunt\condition::get_stages_options($treasurehuntid, $context);
        header('Content-Type: application/json');
        echo json_encode($stages);
        break;

    default:
        throw new moodle_exception('invalidaction');
}
