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
 * Function to interoperate with availability API.
 * Developed for availability_treasurehunt in mind.
 * Maybe more.
 *
 * @package    availability_treasurehunt
 * @copyright  2025 Juan Pablo de Castro <juan.pablo.de.castro@gmail.com>
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */

/**
 * Obtiene la lista de actividades del curso.
 * para la stageid especificada, ya sea sola o combinada mediante AND con otras restricciones.
 * Las que tienen aplicada la restricción availability/treasurehunt se marcan con locked=true.
 *
 * @param int $courseid ID del curso
 * @param int $stageid ID de la etapa del treasurehunt
 * @return array Array de objetos con información de las actividades
 */
function treasurehunt_get_activities_with_stage_restriction($courseid, $stageid) {
    // Obtener información del curso.
    $modinfo = get_fast_modinfo($courseid);

    $matchingactivities = [];

    // Iterar sobre todas las actividades del curso.
    foreach ($modinfo->get_cms() as $cm) {
        $modinfo = treasurehunt_get_activity_info_from_cm($cm);
        // Verificar si la actividad tiene restricciones de disponibilidad.
        if ($cm->availability) {
            // Decodificar el JSON de availability.
            $availability = json_decode($cm->availability, false);

            if ($availability && isset($availability->c)) {
                // Buscar la restricción treasurehunt en las condiciones.
                [$found, $section] = treasurehunt_check_stage_restriction($availability, $stageid);
                if ($found) {
                    $modinfo->locked = true;
                }
            }
        }
        $matchingactivities[] = $modinfo;
    }

    return $matchingactivities;
}
/**
 * Obtiene información de una actividad desde el objeto cm_info
 *
 * @param cm_info $cm Objeto cm_info de Moodle
 * @return object Objeto con información completa de la actividad
 */
function treasurehunt_get_activity_info_from_cm($cm) {
    $activityinfo = new stdClass();
    $activityinfo->cmid = $cm->id;
    $activityinfo->course = $cm->course;
    $activityinfo->module = $cm->module;
    $activityinfo->instance = $cm->instance;
    $activityinfo->modulename = $cm->modname;
    $activityinfo->name = $cm->name;
    $activityinfo->availability = $cm->availability;
    $activityinfo->url = $cm->url;
    $activityinfo->visible = $cm->visible;
    $activityinfo->uservisible = $cm->uservisible;
    $activityinfo->locked = false;

    return $activityinfo;
}
/**
 * Función auxiliar para verificar si una stageid está presente en las restricciones
 *
 * @param object $availability Conditions structure for availability
 * @param integer $stageid stage id to check.
 * @return array(bool, &object) true if found, conditions section
 */
function treasurehunt_check_stage_restriction($availability, $stageid) {
    // Search only condition at root. This is a very common scenario.
    if (isset($availability->c[0])) {
        if (
            $availability->op =="&" &&
            count($availability->c) == 1 &&
            isset($availability->c[0]->type) &&
            $availability->c[0]->type == "treasurehunt" &&
            $availability->c[0]->stageid == $stageid
            ) {
                return [ true, null];
            }
    }
    // Search for a group of availability_treasurehunt section.
    $conditionsection = &treasurehunt_get_treasurehunt_availability_section($availability);
    if ($stageid) {
        // Search conditions.
        foreach (($conditionsection->c ?? []) as $trcondition) {
            // Check if it match with $newrestriction.
            if (($trcondition->type ?? '') === 'treasurehunt') {
                if (
                    ($trcondition->stageid ?? '') == $stageid
                    && ($trcondition->conditiontype ?? '') == 'current_stage'
                ) {
                    return [true, &$conditionsection];
                }
            }
        }
    }
    return [false, &$conditionsection];
}
/**
 * Find treasurehunt section in availability structure.
 * Apply an heuristical algorithm for getting the first suitable place for conditions.
 * Treasurehunt section is an array of availability_treasurehunt conditions combined by "or" operand.
 * @param stdClass $availability structure of availability.
 * @return stdClass|null treasurehunt section Reference or null
 */
function &treasurehunt_get_treasurehunt_availability_section($availability) {
    // Search terasurehunt section.
    foreach ($availability->c as $conditionsection) {
        // Check if there is an "or" section at root.
        if (isset($conditionsection->op) && $conditionsection->op == '|') {
            return $conditionsection;
        }
    }
    $nullreference = null;
    return $nullreference;
}
/**
 * Añade una restricción treasurehunt a las restricciones existentes
 *
 * @param course_modinfo $cm course module.
 * @param stdClass $stageid record etapa.
 * @param bool $replace Si reemplazar todas las restricciones existentes.
 * @return stdClass  availability structure.
 */
function treasurehunt_add_restriction($cm, $stage, $treasurehuntid, $replace = false) {
    $currentavailability = $cm->availability;
    $availability = null;
    // Create an availability structure from json or from scratch.
    if ($replace == false && empty($currentavailability) === false) {
        // Parse availability json.
        $availability = json_decode($currentavailability, false);
    }

    if (!$availability || !isset($availability->c)) {
        // If structure is not valid, create a seed structure.
        $availability = (object) [
            'op' => '&',
            'c' => [],
            'showc' => [],
        ];
    }
    // Check if there is treasurehunt restriction into treasurehunt section.
    [$found, $trsection] = treasurehunt_check_stage_restriction($availability, $stage->id);

    if ($found === false) {
        // Añadir la nueva restricción.
        if ($trsection === null) {

            // Place treasureavailabilities in a labeled section.
            // Note: label is not an official field.
            $trsection = (object) [
                'op' => '|',
                'c' => [],
                'showc' => [],
            ];
            $availability->c[] = $trsection;
            $availability->showc = array_fill(0, count($availability->c), true);
        }
        // Restriction to add.
        $newrestriction = (object) [
            'treasurehuntid' => $treasurehuntid,
            'type' => 'treasurehunt',
            'conditiontype' => 'current_stage',
            'requiredvalue' => 0,
            'stageid' => $stage->id,
        ];
        $trsection->c[] = $newrestriction;
        $trsection->showc[] = true;
    }
    return $availability;
}

/**
 * Elimina una restricción treasurehunt específica
 *
 * @param course_modinfo $cm to unlock.
 * @param stdClass $stage stage record to unlock.
 * @return stdClass|null availability structure.
 */
function treasurehunt_remove_restriction($cm, $stage) {
    if (empty($cm->availability)) {
        return null;
    }

    $availability = json_decode($cm->availability, false);
     // Search if only condition at root.
    if (isset($availability->c[0])) {
        if (
            $availability->op =="&" &&
            count($availability->c) == 1 &&
            isset($availability->c[0]->type) &&
            $availability->c[0]->type == "treasurehunt" &&
            $availability->c[0]->stageid == $stage->id
            ) {
                // Empty condition list.
                $availability->c = [];
                $availability->showc = [];
                return $availability;
            }
    }

    // Find treasurehutn section.
    $trconditions = treasurehunt_get_treasurehunt_availability_section($availability);
    if ($trconditions !== null) {
        // Filtrar las restricciones para eliminar la que coincida con stageid.
        $conditions = treasurehunt_filter_restrictions($trconditions->c, $stage->id);
        $trconditions->c = $conditions;
        // Calculate showc array.
        $trconditions->showc = array_fill(0, count($trconditions->c), true);
    }
    return $availability;
}
/**
 * Update availability field and purge caches.
 * @param mixed $cm
 * @param stdClass $newavailability availability structure.
 * @return bool
 */
function treasurehunt_update_activity_availability($cm, $newavailability) {
    global $DB;
    // Clear cache.
    $courseid = $cm->get_course()->id;
    try {
        // Actualizar usando la DB.
        $DB->set_field('course_modules', 'availability', json_encode($newavailability), ['id' => $cm->id]);
        // Invalidar caché del curso.
        rebuild_course_cache($courseid, true);

        return true;
    } catch (Exception $e) {
        debugging('Error updating availability: ' . $e->getMessage(), DEBUG_DEVELOPER);
        return false;
    }
}


/**
 * Filtra recursivamente las restricciones para eliminar la treasurehunt específica
 *
 * @param array $conditions Array de condiciones
 * @param int $stageid ID de la etapa a eliminar
 * @return array Array filtrado de condiciones
 */
function treasurehunt_filter_restrictions($conditions, $stageid) {
    $filtered = [];

    foreach ($conditions as $condition) {
        // Si es una restricción treasurehunt con el stageid específico, la saltamos.
        if (
            $condition->type === 'treasurehunt' &&
            $condition->stageid == $stageid
        ) {
            continue;
        }
        $filtered[] = $condition;
    }

    return $filtered;
}
