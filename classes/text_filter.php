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

namespace filter_edguidance;

use local_edguidance\guidance;
use local_edguidance\local\card_injector;
use local_edguidance\output\block;
use local_edguidance\token;

/**
 * Replaces teacher guidance tokens with the guidance, for teachers, and with nothing for anyone else.
 *
 * A token is an empty <div data-edguidance="KEY"> that carries no guidance text; the text lives in
 * local_edguidance. This filter is therefore the one place the text reaches a page, and it only
 * does so for someone holding local/edguidance:view in the activity - or, for a section summary, in
 * the course. Everywhere the filter does not run - search indexing, raw web service text, a course
 * with this filter switched off - there was never any guidance in the text to leak.
 *
 * Output depends on who is looking. That is safe because Moodle 4.5 has no text cache: filters run
 * per request, per user (filter_activitynames relies on the same).
 *
 * @package    filter_edguidance
 * @copyright  2026 Andrew Rowatt <A.J.Rowatt@massey.ac.nz>
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */
class text_filter extends \core_filters\text_filter {
    /**
     * @var string Tags that count as content even with no text, when deciding whether anything is left.
     *
     * Wider than html_is_blank()'s list, which does not know about iframes or video: judging an
     * embedded video "blank" would delete it.
     */
    protected const CONTENT_TAGS = '<img><iframe><video><audio><object><embed><applet><svg><math><canvas>' .
        '<picture><input><select><textarea><button><hr><table>';

    /**
     * Filter some text.
     *
     * @param string $text The text.
     * @param array $options Filter options.
     * @return string
     */
    public function filter($text, array $options = []) {
        // Cheap enough for every piece of text on every page; nothing below runs without a token.
        if (!is_string($text) || stripos($text, 'data-edguidance') === false) {
            return $text;
        }

        // The plugin that holds the guidance has gone. The tokens carry no text, so leaving them is
        // harmless, and without local_edguidance there is nothing to render them with.
        if (!get_config('local_edguidance', 'version') || !class_exists(token::class)) {
            return $text;
        }

        $filtered = token::replace($text, fn(string $key) => $this->replace($key));

        return $this->anything_left($filtered) ? $filtered : '';
    }

    /**
     * What one token becomes.
     *
     * @param string $key The token's key.
     * @return string HTML, or ''.
     */
    protected function replace(string $key): string {
        // Guidance embedded inside guidance: nothing, or it would nest for as long as someone cared to.
        if (guidance::is_rendering() || !has_capability('local/edguidance:view', $this->context)) {
            return '';
        }

        // Anywhere a block cannot belong: nothing. A token there was copied in from somewhere else.
        $blocks = $this->blocks();
        if ($blocks === null) {
            return '';
        }

        $row = $blocks[$key] ?? null;

        if (!$row) {
            // Tokens are scoped to their activity, or a section's to its course, so one pasted in
            // from elsewhere does not resolve. Worth telling someone who can fix it; everyone else
            // sees nothing.
            if (!has_capability('local/edguidance:manage', $this->context)) {
                return '';
            }
            return \html_writer::div(
                get_string('notfound', 'filter_edguidance'),
                'alert alert-warning edguidance-notfound',
                ['role' => 'note']
            );
        }

        // Already shown in this activity's course page card; see card_injector.
        if (card_injector::was_emitted((int)$row->id)) {
            return '';
        }

        return block::render_row($row);
    }

    /**
     * The blocks text in this context can show, keyed by key, or null where no block can belong.
     *
     * An activity's text shows that activity's blocks. Text in a course context is a section
     * summary - or the course summary - and shows any of the course's section blocks: a filter is
     * told the context but not which section, so a section's key is scoped to its course.
     * local_edguidance keeps each section's copy of a block its own.
     *
     * @return array<string, \stdClass>|null
     */
    protected function blocks(): ?array {
        if ($this->context instanceof \context_module) {
            $courseid = (int)$this->context->get_course_context()->instanceid;
            return guidance::for_cm($courseid, (int)$this->context->instanceid);
        }

        if ($this->context instanceof \context_course && (int)$this->context->instanceid !== (int)SITEID) {
            return guidance::for_sections((int)$this->context->instanceid);
        }

        return null;
    }

    /**
     * Whether text still has something in it once the guidance is gone.
     *
     * A description that was nothing but guidance becomes '' for a student rather than an empty
     * paragraph, so there is no blank description box on their course page.
     *
     * @param string $text The filtered text.
     * @return bool
     */
    protected function anything_left(string $text): bool {
        $visible = strip_tags($text, self::CONTENT_TAGS);
        $visible = html_entity_decode($visible, ENT_QUOTES | ENT_HTML5, 'UTF-8');

        return preg_replace('/[\s\x{00A0}]+/u', '', $visible) !== '';
    }
}
