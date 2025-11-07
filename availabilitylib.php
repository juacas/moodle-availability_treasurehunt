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
 * Those that have the availability/treasurehunt restriction applied are marked with
 * $info->locked=true.
 *
 * @param int $courseid Course ID
 * @param int $stageid Treasurehunt stage ID
 * @return array[stdClass] Array of objects with cm_info and locked status.
 */
function availability_treasurehunt_get_activities_with_stage_restriction($courseid, $stageid) {
    // Get course information.
    $fastmodinfo = get_fast_modinfo($courseid);

    $matchingactivities = [];

    // Iterate over all course activities.
    foreach ($fastmodinfo->get_cms() as $cminfo) {
        // Mark each activity as controlled by availability_treasurehunt or not.
        $modinfo = (object) [
            'locked'=> false,
            'cm_info'=> $cminfo,
        ];
        // Discard activities invisible to the user.
        if (!$cminfo->uservisible) {
            continue;
        }
        // Check if the activity has availability restrictions.
        if ($cminfo->availability) {
            // Decode the availability JSON.
            $availability = json_decode($cminfo->availability, false);

            if ($availability && isset($availability->c)) {
                // Search for treasurehunt restriction in the conditions.
                [$found, $section] = availability_treasurehunt_check_stage_restriction($availability, $stageid);
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
 * Auxiliary function to check if a stageid is present in the restrictions
 *
 * @param object $availability Conditions structure for availability
 * @param integer $stageid stage id to check.
 * @return array(bool, &object) true if found, conditions section
 */
function availability_treasurehunt_check_stage_restriction($availability, $stageid) {
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
    $conditionsection = &availability_treasurehunt_get_treasurehunt_availability_section($availability);
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
function &availability_treasurehunt_get_treasurehunt_availability_section($availability) {
    if (!isset($availability->c)) {
        $nullreference = null;
        return $nullreference;
    }
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
 * @return [stdClass, condition]  availability structure, condition structure.
 */
function availability_treasurehunt_add_restriction($cm, $stage, $treasurehuntid, $replace = false) {
    $currentavailability = $cm->availability; // phpcs:ignore PHP6602
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
    [$found, $trsection] = availability_treasurehunt_check_stage_restriction($availability, $stage->id);

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
    $condition = new availability_treasurehunt\condition($newrestriction);
    return [$availability, $condition];
}
/**
 * Change a treasurehunt restriction to a new stageid. For restores.
 *
 * @param course_modinfo $cm course module.
 * @param stdClass $oldstage stage record.
 * @param int $oldtreasurehuntid Treasurehunt ID.
 * @param stdClass $newstage stage record.
 * @param int $newtreasurehuntid Treasurehunt ID.
 * @return [condition, mixed]  availability structure, condition structure.
 */
function availability_treasurehunt_get_updated_restriction(
    $cm,
    $oldstageid,
    $oldtreasurehuntid,
    $newstageid,
    $newtreasurehuntid
) {

    $availability = $cm->availability ? json_decode($cm->availability, false) : null;
    // Check if there is treasurehunt restriction into treasurehunt section.
    [$found, $trsection] = availability_treasurehunt_check_stage_restriction($availability, $oldstageid);
    $updatedcondition = null;
    if ($found === true) {
        // Update the existing restriction.
        foreach ($trsection->c as $condition) {
            // Check if it matches with $newrestriction.
            if (
                ($condition->type ?? '') === 'treasurehunt' &&
                ($condition->stageid ?? '') == $oldstageid &&
                ($condition->conditiontype ?? '') == 'current_stage' &&
                ($condition->treasurehuntid ?? '') == $oldtreasurehuntid
            ) {
                // Update to new values.
                $condition->stageid = $newstageid;
                $condition->treasurehuntid = $newtreasurehuntid;
                $condition->requiredvalue = 0;
                $condition->conditiontype = 'current_stage';
                $updatedcondition = new availability_treasurehunt\condition($condition);
                break;
            }
        }
    }
    return [$availability, $updatedcondition];
}

/**
 * Removes a specific treasurehunt restriction
 *
 * @param course_modinfo $cm to unlock.
 * @param stdClass $stage stage record to unlock.
 * @return stdClass|null availability structure.
 */
function availability_treasurehunt_remove_restriction($cm, $stage) {
    if (empty($cm->availability)) { // phpcs:ignore PHP6602
        return null;
    }

    $availability = json_decode($cm->availability, false); // phpcs:ignore PHP6602
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
    $trconditions = availability_treasurehunt_get_treasurehunt_availability_section($availability);
    if ($trconditions !== null) {
        // Filter the restrictions to remove the one that matches stageid.
        $conditions = availability_treasurehunt_filter_restrictions($trconditions->c, $stage->id);
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
function availability_treasurehunt_update_activity_availability($cm, $newavailability) {
    global $DB;
    // Clear cache.
    $courseid = $cm->get_course()->id;
    try {
        // Update using the DB.
        $DB->set_field('course_modules', 'availability', $newavailability != null ? json_encode($newavailability) : null, ['id' => $cm->id]);
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
function availability_treasurehunt_filter_restrictions($conditions, $stageid) {
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

/**
 * Get intro text from module instance.
 * Search for <span class="treasurehunt-return-link">...</span>.
 * Create the mark at the end of the text for the return link if it is not found.
 * Regenerate the return-link inside the <span>.
 *
 * @param cm_info $cminfo
 * @param availability_treasurehunt\condition $condition
 * @return void
 */
function availability_treasurehunt_add_return_link(cm_info $cminfo, $condition, $add = false, $delete = false) {
    global $DB;
    // Get treasurehunt instance from condition.
    $treasurehunt = $condition->get_treasurehunt_instance();
    // Get module instance from $modinfo->instance and $modinfo->modulename.
    // Get raw description text, search for extra text and reformat.
    $intro = $cminfo->content;

    if ($delete) {
        $add = false;
        $returnlinkhtml = '';
    } else {
        $treasurehuntcmid = get_coursemodule_from_instance(
            'treasurehunt',
            $treasurehunt->id,
            $treasurehunt->course,
            false,
            MUST_EXIST
        )->id;

        $returnurl = new moodle_url('/mod/treasurehunt/view.php', ['id' => $treasurehuntcmid]);
        $treasurehunttitle = format_string($treasurehunt->name);

        $returnlinktext = new lang_string(
            'returnlinktext',
            'availability_treasurehunt',
            [
                  'returnlink' => $returnurl->out(false),
                  'treasurehuntname' => $treasurehunttitle,
              ]
        );
        $returnlinkhtml = '<span class="treasurehunt-return-link">' . $returnlinktext . '</span>';
    }
    // Search for existing return link span.
    if (strpos($intro, 'class="treasurehunt-return-link"') !== false) {
        // Replace existing return link.
        $newintro = preg_replace(
            '/<span class="treasurehunt-return-link">.*?<\/span>/s',
            $returnlinkhtml,
            $intro
        );
    } else if ($add) {
        // Append return link at the end.
        $newintro = $intro . '<br>' . $returnlinkhtml;
    } else {
        // No changes.
        $newintro = $intro;
    }

    // Update intro in activity.
    if ($newintro == $intro) {
        // No changes made, avoid updating module.
        return;
    }
    $DB->set_field(
        $cminfo->modname,
        'intro',
        $newintro,
        ['id' => $cminfo->instance]
    );
    // Invalidate course cache.
    rebuild_course_cache($cminfo->course, clearonly: true);
}

