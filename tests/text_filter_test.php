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

use local_edguidance\dismissed;
use local_edguidance\guidance;
use local_edguidance\local\card_injector;
use local_edguidance\token;

/**
 * Tests for the teacher guidance filter.
 *
 * @package    filter_edguidance
 * @copyright  2026 Andrew Rowatt <A.J.Rowatt@massey.ac.nz>
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 * @covers     \filter_edguidance\text_filter
 */
final class text_filter_test extends \advanced_testcase {
    /**
     * Start each test with nothing cached.
     */
    protected function setUp(): void {
        parent::setUp();
        guidance::reset_cache();
        dismissed::reset_cache();
        card_injector::reset();
    }

    /**
     * A book holding one guidance block.
     *
     * @param array $block Overrides for the block.
     * @return array [course, module context, row]
     */
    private function make_book(array $block = []): array {
        $course = $this->getDataGenerator()->create_course();
        $book = $this->getDataGenerator()->create_module('book', ['course' => $course->id]);
        $row = $this->getDataGenerator()->get_plugin_generator('local_edguidance')->create_block(array_merge([
            'cmid' => $book->cmid,
            'guidance' => '<p>Unique guidance words.</p>',
        ], $block));

        return [$course, \context_module::instance($book->cmid), $row];
    }

    /**
     * Run the filter.
     *
     * @param \context $context The text's context.
     * @param string $text The text.
     * @return string
     */
    private function filter(\context $context, string $text): string {
        return (new text_filter($context, []))->filter($text);
    }

    /**
     * A teacher sees the guidance where the token was.
     */
    public function test_teacher_sees_the_block(): void {
        $this->resetAfterTest();
        [$course, $context, $row] = $this->make_book();
        $this->setUser($this->getDataGenerator()->create_and_enrol($course, 'teacher'));

        $html = $this->filter($context, '<p>Before</p>' . token::html($row->embedkey) . '<p>After</p>');

        $this->assertMatchesRegularExpression('~Before.*Unique guidance words\..*After~s', $html);
        $this->assertStringContainsString('data-guidanceid="' . $row->id . '"', $html);
    }

    /**
     * A student sees the text around it, and nothing of the guidance.
     */
    public function test_student_sees_nothing_of_it(): void {
        $this->resetAfterTest();
        [$course, $context, $row] = $this->make_book();
        $this->setUser($this->getDataGenerator()->create_and_enrol($course, 'student'));

        $html = $this->filter($context, '<p>Before</p>' . token::html($row->embedkey) . '<p>After</p>');

        $this->assertSame('<p>Before</p><p>After</p>', $html);
    }

    /**
     * Text that was only guidance becomes nothing for a student, so no empty box is drawn.
     */
    public function test_token_only_text_becomes_empty(): void {
        $this->resetAfterTest();
        [$course, $context, $row] = $this->make_book();
        $this->setUser($this->getDataGenerator()->create_and_enrol($course, 'student'));

        $padded = '<p>&nbsp;</p><div class="edguidance-embed" data-edguidance="' . $row->embedkey . '">&nbsp;</div>';

        $this->assertSame('', $this->filter($context, $padded));
    }

    /**
     * ...but an embedded video with no text around it is not "nothing".
     */
    public function test_media_is_not_blank(): void {
        $this->resetAfterTest();
        [$course, $context, $row] = $this->make_book();
        $this->setUser($this->getDataGenerator()->create_and_enrol($course, 'student'));

        $text = '<iframe src="https://example.com/video"></iframe>' . token::html($row->embedkey);

        $this->assertSame('<iframe src="https://example.com/video"></iframe>', $this->filter($context, $text));
    }

    /**
     * A token copied in from another activity: a notice for editors, nothing for anyone else.
     */
    public function test_unknown_key(): void {
        $this->resetAfterTest();
        [$course, $context] = $this->make_book();
        $text = '<p>Text</p>' . token::html('0123456789abcdef');

        $this->setUser($this->getDataGenerator()->create_and_enrol($course, 'editingteacher'));
        $this->assertStringContainsString('edguidance-notfound', $this->filter($context, $text));

        $this->setUser($this->getDataGenerator()->create_and_enrol($course, 'teacher'));
        $this->assertSame('<p>Text</p>', $this->filter($context, $text));
    }

    /**
     * Keys are scoped to their activity, so a real key used in another activity does not resolve.
     */
    public function test_keys_are_scoped_to_their_activity(): void {
        $this->resetAfterTest();
        [$course, , $row] = $this->make_book();
        $other = $this->getDataGenerator()->create_module('page', ['course' => $course->id]);
        $this->setUser($this->getDataGenerator()->create_and_enrol($course, 'teacher'));

        $html = $this->filter(\context_module::instance($other->cmid), token::html($row->embedkey) . '<p>x</p>');

        $this->assertStringNotContainsString('Unique guidance words.', $html);
    }

    /**
     * Outside an activity a token is simply removed, even for an administrator.
     */
    public function test_non_module_contexts_are_stripped(): void {
        $this->resetAfterTest();
        [$course, , $row] = $this->make_book();
        $this->setAdminUser();

        $html = $this->filter(\context_course::instance($course->id), '<p>Summary</p>' . token::html($row->embedkey));

        $this->assertSame('<p>Summary</p>', $html);
    }

    /**
     * Filtering already-filtered text changes nothing, as mod_lesson's double pass needs.
     */
    public function test_filtering_twice_is_a_no_op(): void {
        $this->resetAfterTest();
        [$course, $context, $row] = $this->make_book();
        $this->setUser($this->getDataGenerator()->create_and_enrol($course, 'teacher'));

        $once = $this->filter($context, token::html($row->embedkey));

        $this->assertSame($once, $this->filter($context, $once));
        $this->assertSame(1, substr_count($once, 'data-guidanceid='));
    }

    /**
     * Guidance that itself contains a token does not render a block within a block.
     */
    public function test_no_guidance_inside_guidance(): void {
        $this->resetAfterTest();
        $course = $this->getDataGenerator()->create_course();
        $book = $this->getDataGenerator()->create_module('book', ['course' => $course->id]);
        $generator = $this->getDataGenerator()->get_plugin_generator('local_edguidance');
        $inner = $generator->create_block(['cmid' => $book->cmid, 'guidance' => '<p>Inner words.</p>']);
        $outer = $generator->create_block([
            'cmid' => $book->cmid,
            'guidance' => '<p>Outer words.</p>' . token::html($inner->embedkey),
        ]);
        filter_set_global_state('edguidance', TEXTFILTER_ON);
        $this->setUser($this->getDataGenerator()->create_and_enrol($course, 'teacher'));

        $html = format_text(token::html($outer->embedkey), FORMAT_HTML, [
            'context' => \context_module::instance($book->cmid),
            'noclean' => true,
        ]);

        $this->assertStringContainsString('Outer words.', $html);
        $this->assertStringNotContainsString('Inner words.', $html);
    }

    /**
     * A block already placed in the course page card is not shown a second time in the description.
     */
    public function test_blocks_already_in_the_card_are_skipped(): void {
        $this->resetAfterTest();
        $course = $this->getDataGenerator()->create_course();
        $key = token::new_key();
        $page = $this->getDataGenerator()->create_module('page', [
            'course' => $course->id,
            'intro' => token::html($key),
            'showdescription' => 0,
        ]);
        $row = $this->getDataGenerator()->get_plugin_generator('local_edguidance')->create_block([
            'cmid' => $page->cmid,
            'embedkey' => $key,
            'introorder' => 1,
        ]);
        $this->setUser($this->getDataGenerator()->create_and_enrol($course, 'teacher'));

        card_injector::prime((int)$course->id);

        $html = $this->filter(\context_module::instance($page->cmid), '<p>Page</p>' . token::html($key));
        $this->assertStringNotContainsString('data-guidanceid="' . $row->id . '"', $html);
    }

    /**
     * Through format_text(), with the filter on as installed: the path every host takes.
     *
     * With noclean, as book chapters, lesson pages and activity descriptions are all formatted.
     */
    public function test_end_to_end_through_format_text(): void {
        $this->resetAfterTest();
        [$course, $context, $row] = $this->make_book();
        $this->assertSame(TEXTFILTER_ON, (int)filter_get_global_states()['edguidance']->active);

        $this->setUser($this->getDataGenerator()->create_and_enrol($course, 'teacher'));
        $this->assertStringContainsString(
            'Unique guidance words.',
            format_text(token::html($row->embedkey), FORMAT_HTML, ['context' => $context, 'noclean' => true])
        );
    }

    /**
     * Text that is cleaned before filtering loses the token, and with it the guidance.
     *
     * HTMLPurifier strips data-* attributes. This fails closed - nobody sees guidance, and there is
     * none in the text to leak - and is the documented behaviour under $CFG->forceclean.
     */
    public function test_cleaned_text_fails_closed(): void {
        $this->resetAfterTest();
        [$course, $context, $row] = $this->make_book();
        $this->setUser($this->getDataGenerator()->create_and_enrol($course, 'teacher'));

        $html = format_text(token::html($row->embedkey), FORMAT_HTML, ['context' => $context]);

        $this->assertStringNotContainsString('Unique guidance words.', $html);
        $this->assertStringNotContainsString($row->embedkey, $html);
    }
}
