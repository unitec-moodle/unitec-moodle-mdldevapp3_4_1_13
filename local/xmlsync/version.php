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
 * XML Import tasks for Unitec New Zealand Ltd
 *
 * @package    local_xmlsync
 * @author     David Thompson <david.thompson@catalyst.net.nz>
 * @copyright  2021 Catalyst IT
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */

defined('MOODLE_INTERNAL') || die;

$plugin->version   = 2022053001;
$plugin->release   = '0.1.0';
$plugin->maturity  = MATURITY_ALPHA;
$plugin->requires  = 2021051700; // Moodle 3.11 release and upwards.
$plugin->component = 'local_xmlsync';
