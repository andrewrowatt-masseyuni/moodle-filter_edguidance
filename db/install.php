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
 * Install steps for the teacher guidance filter.
 *
 * @package    filter_edguidance
 * @copyright  2026 Andrew Rowatt <A.J.Rowatt@massey.ac.nz>
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */

/**
 * Switch the filter on site-wide.
 *
 * Without it no guidance is ever shown, to anyone, so an install that left it disabled would look
 * broken. Filters added this way go to the end of the filter order, which is where this one belongs:
 * the guidance it inserts has already been through the filters, and anything after it would run
 * over that text a second time.
 */
function xmldb_filter_edguidance_install() {
    global $CFG;

    require_once($CFG->libdir . '/filterlib.php');
    filter_set_global_state('edguidance', TEXTFILTER_ON);
}
