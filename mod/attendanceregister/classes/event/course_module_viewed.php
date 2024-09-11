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
 * The course_module_viewed event.
 *
 * @package    mod_attendanceregister
 * @copyright  2015 CINECA
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */

namespace mod_attendanceregister\event;

/**
 * The course_module_viewed event class.
 *
 * @property-read array $other {
 *      Extra information about event.
 *
 *      - PUT INFO HERE
 * }
 *
 * @since     Moodle 2.7
 * @copyright 2015 CINECA
 * @license   http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 **/
class course_module_viewed extends \core\event\course_module_viewed {
    protected function init() {
        $this->data['crud'] = 'r'; // Meaning Create, Read, Update, Delete.
        $this->data['edulevel'] = self::LEVEL_PARTICIPATING;
        $this->data['objecttable'] = 'attendanceregister';
    }

    public function get_legacy_logdata() {
        // Override if you are migrating an add_to_log() call.
        return [$this->courseid, 'attendanceregister', 'view',
            'view.php?id='. $this->contextinstanceid, $this->objectid, $this->contextinstanceid];
    }
}
