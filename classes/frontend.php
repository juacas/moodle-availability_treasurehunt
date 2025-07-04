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

namespace availability_treasurehunt;
defined('MOODLE_INTERNAL') || die();
/**
 * Restricción por Treasurehunt frontend
 *
 * @package    availability_treasurehunt
 * @copyright  2025 YOUR NAME <your@email.com>
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */
namespace availability_treasurehunt;

defined('MOODLE_INTERNAL') || die();

class frontend extends \core_availability\frontend {
    
    /**
     * Obtiene las cadenas de JavaScript
     */
    protected function get_javascript_strings() {
        return array(
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
            'error_selectstage'
        );
    }
    
    /**
     * Obtiene los parámetros de JavaScript
     */
    protected function get_javascript_init_params($course, \cm_info $cm = null, \section_info $section = null) {
        $treasurehunts = condition::get_treasurehunt_options($course->id);
        return [$cm->id, self::convert_associative_array_for_js($treasurehunts, 'id', 'display')];
    }
    
    /**
     * Permite usar en módulos de curso
     */
    protected function allow_add($course, \cm_info $cm = null, \section_info $section = null) {
        global $DB;
        
        // Solo permitir si hay actividades treasurehunt en el curso
        return $DB->record_exists('treasurehunt', array('course' => $course->id));
    }
}
