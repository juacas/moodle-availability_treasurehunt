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
 * External services for availability_treasurehunt plugin.
 *
 * @package    availability_treasurehunt
 * @copyright  2025 Juan Pablo de Castro <juan.pablo.de.castro@gmail.com>
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */

defined('MOODLE_INTERNAL') || die();

$functions = [
    'availability_treasurehunt_get_stages' => [
        'classname' => 'availability_treasurehunt\external\get_stages',
        'methodname' => 'get_treasurehunt_stages',
        'description' => 'Get available stages for a treasure hunt activity',
        'type' => 'read',
        'ajax' => true,
        'capabilities' => 'mod/treasurehunt:view',
        'services' => [MOODLE_OFFICIAL_MOBILE_SERVICE],
    ],
];

$services = [
    'Treasure Hunt Availability Service' => [
        'functions' => [
            'availability_treasurehunt_get_stages',
        ],
        'restrictedusers' => 0,
        'enabled' => 1,
        'shortname' => 'treasurehunt_availability',
        'downloadfiles' => 0,
        'uploadfiles' => 0,
    ],
];
