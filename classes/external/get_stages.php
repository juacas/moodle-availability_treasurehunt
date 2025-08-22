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

namespace availability_treasurehunt\external;

use context_module;
use core_external\external_api;
use core_external\external_function_parameters;
use core_external\external_multiple_structure;
use core_external\external_single_structure;
use core_external\external_value;
use invalid_parameter_exception;
use moodle_exception;
use require_login_exception;

defined('MOODLE_INTERNAL') || die();
global $CFG;
require_once($CFG->dirroot . '/mod/treasurehunt/externalcompatibility.php');
/**
 * External service for getting treasure hunt stages.
 *
 * @package    availability_treasurehunt
 * @copyright  2025 Juan Pablo de Castro <juan.pablo.de.castro@gmail.com>
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */
class get_stages extends external_api {
    /**
     * Describes the parameters for get_stages.
     *
     * @return external_function_parameters
     */
    public static function get_treasurehunt_stages_parameters() {
        return new external_function_parameters([
            'treasurehuntid' => new external_value(PARAM_INT, 'Treasure hunt activity ID'),
        ]);
    }

    /**
     * Get available stages for a treasure hunt activity.
     *
     * @param int $treasurehuntid The treasure hunt activity ID
     * @return array List of available stages
     * @throws invalid_parameter_exception
     * @throws moodle_exception
     * @throws require_login_exception
     */
    public static function get_treasurehunt_stages($treasurehuntid) {
        global $DB;

        // Validate parameters.
        $params = self::validate_parameters(self::get_treasurehunt_stages_parameters(), [
            'treasurehuntid' => $treasurehuntid,
        ]);
        $treasurehuntid = $params['treasurehuntid'];

        // Get course module and validate access.
        $cm = get_coursemodule_from_instance('treasurehunt', $treasurehuntid, 0, false, MUST_EXIST);
        $course = $DB->get_record('course', ['id' => $cm->course], '*', MUST_EXIST);

        // Require login and check permissions.
        require_login($course, false, $cm);
        $context = context_module::instance($cm->id);

        // Check if user can view the activity.
        require_capability('mod/treasurehunt:view', $context); // phpcs:ignore PHP6602

        // Get stages using the existing method.
        $stages = \availability_treasurehunt\condition::get_stages_options($treasurehuntid, $context); // phpcs:ignore PHP6602
        // Map stages to return structure.
        $response = array_map(
            fn($id, $name) => ['id' => $id, 'name' => $name],
            array_keys($stages),
            $stages
        );
        return $response;
    }

    /**
     * Describes the get_stages return value.
     *
     * @return external_multiple_structure
     */
    public static function get_treasurehunt_stages_returns() {
        return new external_multiple_structure(
            new external_single_structure([
                'id' => new external_value(PARAM_INT, 'Stage ID'),
                'name' => new external_value(PARAM_TEXT, 'Stage name'),
            ])
        );
    }
}
