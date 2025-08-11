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
 * Gets the list of course activities
 * for the specified stageid, either alone or combined via AND with other restrictions.
 * Those that have the availability/treasurehunt restriction applied are marked with locked=true.
 *
 * @param int $courseid Course ID
 * @param int $stageid Treasurehunt stage ID
 * @return array Array of objects with activity information
 */
function treasurehunt_get_activities_with_stage_restriction($courseid, $stageid) {
    // Get course information.
    $modinfo = get_fast_modinfo($courseid);

    $matchingactivities = [];

    // Iterate over all course activities.
    foreach ($modinfo->get_cms() as $cm) {
        $modinfo = treasurehunt_get_activity_info_from_cm($cm);
        // Check if the activity has availability restrictions.
        if ($cm->availability) {
            // Decode the availability JSON.
            $availability = json_decode($cm->availability, false);

            if ($availability && isset($availability->c)) {
                // Search for treasurehunt restriction in the conditions.
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
 * Get information of an activity from the cm_info object.
 *
 * @param cm_info $cm Moodle cm_info object
 * @return object Object with complete activity information
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
 * Auxiliary function to check if a stageid is present in the restrictions
 *
 * @param object $availability Conditions structure for availability
 * @param integer $stageid stage id to check.
 * @return array(bool, &object) true if found, conditions section
 */
function treasurehunt_check_stage_restriction($availability, $stageid) {
    // Search only condition at root. This is a very common scenario.
    if (isset($availability->c[0])) {
        if (
            $availability->op == "&" &&
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
            // Check if it matches with $newrestriction.
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
 * Apply a heuristic algorithm for getting the first suitable place for conditions.
 * Treasurehunt section is an array of availability_treasurehunt conditions combined by "or" operand.
 * @param stdClass $availability structure of availability.
 * @return stdClass|null treasurehunt section Reference or null
 */
function &treasurehunt_get_treasurehunt_availability_section($availability) {
    // Search treasurehunt section.
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
 * Adds a treasurehunt restriction to the existing restrictions.
 *
 * @param course_modinfo $cm course module.
 * @param stdClass $stage stage record.
 * @param int $treasurehuntid Treasurehunt ID.
 * @param bool $replace Whether to replace all existing restrictions.
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
        // Add the new restriction.
        if ($trsection === null) {
            // Place treasurehunt availabilities in a labeled section.
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
 * Removes a specific treasurehunt restriction
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
            $availability->op == "&" &&
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

    // Find treasurehunt section.
    $trconditions = treasurehunt_get_treasurehunt_availability_section($availability);
    if ($trconditions !== null) {
        // Filter the restrictions to remove the one that matches stageid.
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
        // Update using the DB.
        $DB->set_field('course_modules', 'availability', json_encode($newavailability), ['id' => $cm->id]);
        // Invalidate course cache.
        rebuild_course_cache($courseid, true);

        return true;
    } catch (Exception $e) {
        debugging('Error updating availability: ' . $e->getMessage(), DEBUG_DEVELOPER);
        return false;
    }
}


/**
 * Recursively filters the restrictions to remove the specific treasurehunt one
 *
 * @param array $conditions Array of conditions
 * @param int $stageid Stage ID to remove
 * @return array Filtered array of conditions
 */
function treasurehunt_filter_restrictions($conditions, $stageid) {
    $filtered = [];

    foreach ($conditions as $condition) {
        // If it's a treasurehunt restriction with the specific stageid, skip it.
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
