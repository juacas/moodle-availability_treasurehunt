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

use context_module;
use core\exception\moodle_exception;
use restore_treasurehunt_activity_task;

/**
 * Restricción por Treasurehunt condition
 *
 * Documentation: {@link https://moodledev.io/docs/apis/plugintypes/availability}
 *
 * @package    availability_treasurehunt
 * @copyright  2025 YOUR NAME <your@email.com>
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */
class condition extends \core_availability\condition {
    
    // Tipos de condición
    const TYPE_STAGES = 'stages';
    const TYPE_TIME = 'time'; 
    const TYPE_COMPLETION = 'completion';
    const TYPE_CURRENT_STAGE = 'current_stage';
    
    /** @var int ID de la actividad treasurehunt */
    protected $treasurehuntid;
    /** @var \stdClass record of the actvity */
    protected $treasurehunt = null;
    
    /** @var string Tipo de condición */
    protected $conditiontype;
    
    /** @var int Valor requerido */
    protected $requiredvalue;
    
    /** @var int ID del stage específico (para condición current_stage) */
    protected $stageid;
    
    /**
     * Constructor
     */
    public function __construct($structure) {
        $this->treasurehuntid = $structure->treasurehuntid;
        $this->conditiontype = $structure->conditiontype;
        $this->requiredvalue = isset($structure->requiredvalue) ? $structure->requiredvalue : 0;
        $this->stageid = isset($structure->stageid) ? $structure->stageid : 0;
    }
    
    /**
     * Guarda la condición en la estructura
     */
    public function save() {
        $data = (object) array(
            'type' => 'treasurehunt',
            'treasurehuntid' => $this->treasurehuntid,
            'conditiontype' => $this->conditiontype,
            'requiredvalue' => $this->requiredvalue
        );
        
        if ($this->conditiontype === self::TYPE_CURRENT_STAGE) {
            $data->stageid = $this->stageid;
        }
        
        return $data;
    }
    
    /**
     * Verifica si la condición se cumple
     * 
     */
    public function is_available($not, \core_availability\info $info, $grabthelot, $userid) {
        global $CFG;
        require_once($CFG->dirroot . '/mod/treasurehunt/locallib.php');

        global $DB;
        $course = $info->get_course();
        //$context = \context_course::instance($course->id);
       
        // Obtener intentos del usuario
        if ($this->treasurehunt === null ) {
            $this->treasurehunt = $DB->get_record('treasurehunt', ['id' => $this->treasurehuntid], '*', IGNORE_MISSING);
            if ($this->treasurehunt == false) { // Maybe, activity was deleted.
                return false;
            }
        }
        try {
            $userdata = treasurehunt_get_user_group_and_road($userid, $this->treasurehunt, false);
        } catch (moodle_exception $e) {
            // User is not in a group or in more than one group (for a teams treassurehunt).
            // Nevertheless, a incorrect situation.
            return false;
        }
        $groupid = $userdata->groupid;
        $roadid = $userdata->roadid;
        
        $available = false;
        
        switch ($this->conditiontype) {
            case self::TYPE_STAGES:
                // Number of stages solved.
                $lastsolved = treasurehunt_query_last_successful_attempt($userid, $groupid, $roadid);
                $current_stage = $lastsolved->position;
                $available = $current_stage >= $this->requiredvalue;
                break;
                
            case self::TYPE_TIME:
                // Time played.
                $playtime = treasurehunt_get_hunt_duration($cmid, $userid, $groupid);
                // Convert in minutes.
                $playtime = $playtime / 60000;
                $available = $playtime >= $this->requiredvalue;
                break;
                
            case self::TYPE_COMPLETION:
                // All stages.
                $available = treasurehunt_check_if_user_has_finished($userid, $groupid, $roadid);
                break;
                
            case self::TYPE_CURRENT_STAGE:
                // Get lastsolved stage.
                $lastsolved = treasurehunt_query_last_successful_attempt($userid, $groupid, $roadid);
                if ($lastsolved) {
                    $current_stage = $lastsolved->stageid;
                    $available = $current_stage == $this->stageid;
                }
                break;
        }
        
        if ($not) {
            $available = !$available;
        }
        
        return $available;
    }
    
    /**
     * Obtiene la descripción de la condición
     */
    public function get_description($full, $not, \core_availability\info $info) {
        global $DB;
       
        $treasurehunt = $DB->get_record('treasurehunt', array('id' => $this->treasurehuntid));
        $name = $treasurehunt ? $treasurehunt->name : get_string('missing_treasurehunt', 'availability_treasurehunt');
        
        switch ($this->conditiontype) {
            case self::TYPE_STAGES:
                $description = get_string('requires_stages', 'availability_treasurehunt', $this->requiredvalue);
                break;
                
            case self::TYPE_TIME:
                $description = get_string('requires_time', 'availability_treasurehunt', $this->requiredvalue);
                break;
                
            case self::TYPE_COMPLETION:
                $description = get_string('requires_completion', 'availability_treasurehunt');
                break;
                
            case self::TYPE_CURRENT_STAGE:
                $stage = $this->get_stage($this->stageid);
                $description = get_string('requires_current_stage', 'availability_treasurehunt',
                                $stage );
                break;
                
            default:
                $description = get_string('requires_treasurehunt', 'availability_treasurehunt');
        }
        // We cannot get the name at this point because it requires format_string which is not
        // allowed here. Instead, get it later with the callback function below.
        return $this->description_callback([$description . ' (' . $name . ')']);
    }
    /**
     * Gets the Stage name at display time.
     *
     * @param \course_modinfo $modinfo Modinfo
     * @param \context $context Context
     * @param string[] $params Parameters (just names)
     * @return string Text value
     */
    public static function get_description_callback_value(
            \course_modinfo $modinfo, \context $context, array $params): string {
        if (count($params) !== 1 ) {
            return '<!-- Invalid treasurehunt callback -->';
        }
        return format_text($params[0]);
    }
    /**
     * Obtiene el nombre de un stage
     */
    protected function get_stage_name($stageid) {
        global $DB;
        
        $stage = $DB->get_record('treasurehunt_stages', array('id' => $stageid));
        if ($stage) {
            return $stage->name ? $stage->name : get_string('stage', 'availability_treasurehunt') . ' #' . $stageid;
        }
        
        return get_string('missing_stage', 'availability_treasurehunt');
    }
    /**
     * Obtiene la posicion de un stage en su camino.
     */
    protected function get_stage($stageid) {
        return treasurehunt_get_stage($stageid);
    }
    /**
     * Obtiene información de debug
     */
    protected function get_debug_string() {
        return 'treasurehunt#' . $this->treasurehuntid . ' ' . $this->conditiontype . ':' . $this->requiredvalue;
    }

    public function update_after_restore($restoreid, $courseid, \base_logger $logger, $name) {
        global $DB;
        if (!$this->treasurehuntid) {
            return false;
        }
        $rec = \restore_dbops::get_backup_ids_record($restoreid, 'treasurehunt', $this->treasurehuntid);
        if (!$rec || !$rec->newitemid) {
            // If we are on the same course (e.g. duplicate) then we can just
            // use the existing one.
            if ($DB->record_exists('treasurehunt',
                    array('id' => $this->treasurehuntid, 'course' => $courseid))) {
                return false;
            }
            // Otherwise it's a warning.
            $this->treasurehuntid = -1;
            $logger->process('Restored item (' . $name .
                    ') has availability condition on treasurehunt that was not restored',
                    \backup::LOG_WARNING);
        } else {
            $this->treasurehuntid = (int)$rec->newitemid;
        }
        // Re-map Stage TODO.
        $rec = \restore_dbops::get_backup_ids_record($restoreid, 'treasurehunt_stages', $this->treasurehuntid);
        if (!$rec || !$rec->newitemid) {
            // If we are on the same course (e.g. duplicate) then we can just
            // use the existing one.
            if ($DB->record_exists('treasurehunt_stages',
                    array('id' => $this->stageid))) {
                return false;
            }
            // Otherwise it's a warning.
            $this->stageid = -1;
            $logger->process('Restored item (' . $name .
                    ') has availability condition on a stage that was not restored',
                    \backup::LOG_WARNING);
        } else {
            $this->stageid = (int)$rec->newitemid;
        }
        return true;
    }

    public function update_dependency_id($table, $oldid, $newid) {
        if ($table === 'treasurehunt' && (int)$this->treasurehuntid === (int)$oldid) {
            $this->treasurehuntid = $newid;
            return true;
        } else {
            return false;
        }
    }
    
    
    /**
     * Incluye JavaScript para el formulario
     */
    public function include_after_base_js() {
        global $PAGE;
        $PAGE->requires->yui_module('moodle-availability_treasurehunt-form', 'M.availability_treasurehunt.form.init');
    }
    
    /**
     * Obtiene las actividades treasurehunt disponibles
     */
    public static function get_treasurehunt_options($courseid) {
        global $DB;
        $modinfo = get_fast_modinfo($courseid);
        $cm_infos = $modinfo->get_instances_of('treasurehunt');
        $options = array();
        
        foreach ($cm_infos as $cm_info) {
            $options[$cm_info->instance] = $cm_info->name;
        }
        
        return $options;
    }
    
    /**
     * Obtiene los stages de un treasurehunt específico
     */
    public static function get_stages_options($treasurehuntid, $context) {
        global $DB;
        
        $options = array();
        $stages = treasurehunt_get_stages($treasurehuntid, $context);
        
        foreach ($stages as $stage) {
            $name = $stage->name ? $stage->name : get_string('stage', 'availability_treasurehunt') . ' #' . $stage->id;
            $options[$stage->id] = $stage->roadname . "/" . $name;
        }
        
        return $options;
    }
}
