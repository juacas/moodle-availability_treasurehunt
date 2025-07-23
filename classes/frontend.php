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
 * Restricción por Treasurehunt frontend
 *
 * @package    availability_treasurehunt
 * @copyright  2025 YOUR NAME <your@email.com>
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */
namespace availability_treasurehunt;
/**
 * User interface for configuring conditions.
 */
class frontend extends \core_availability\frontend {

    /**
     * Obtiene las cadenas de JavaScript
     */
    protected function get_javascript_strings() {
        return [
            'select_treasurehunt',
            'condition_type',
            'stages_completed',
            'time_played',
            'full_completion',
            'current_stage',
            'minimum_stages',
            'minimum_time',
            'select_stage',
            'error_selecttreasurehunt',
            'error_setcondition',
            'error_selectstage',
        ];
    }

    /**
     * Get the JavaScript parameters.
     * @param \stdClass $course Cuourse object.
     * @param \cm_info|null $cm Course module information.
     * @param \section_info|null $section Section information.
     * @return array Array of JavaScript initialization parameters.
     */
    protected function get_javascript_init_params($course, ?\cm_info $cm = null, ?\section_info $section = null) {
        $treasurehunts = condition::get_treasurehunt_options($course->id);
        return [$cm ? $cm->id : null, self::convert_associative_array_for_js($treasurehunts, 'id', 'display')];
    }

    /**
     * Allows the condition to be used in course modules.
     * @param \stdClass $course Course object.
     * @param \cm_info|null $cm Course module information.
     * @param \section_info|null $section Section information.
     * @return bool True if the condition can be added, false otherwise.
     */
    protected function allow_add($course, ?\cm_info $cm = null, ?\section_info $section = null) {
        global $DB;
        // If there are no treasurehunt activities, do not allow adding the condition.
        return $DB->record_exists('treasurehunt', ['course' => $course->id]);
    }
}
