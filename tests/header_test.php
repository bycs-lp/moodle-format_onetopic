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

namespace format_onetopic;

use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\Attributes\Group;

/**
 * Tests for the generated tab CSS.
 *
 * @package   format_onetopic
 * @copyright 2026 ISB Bayern
 * @license   http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */
#[CoversClass(header::class)]
final class header_test extends \advanced_testcase {
    /**
     * Create a course with one section per entry using the given tabstyles and return the generated CSS.
     *
     * @param array $sectionstyles Tabstyles structure (type => rule => value) for each section.
     * @return string The CSS passed to the template.
     */
    private function get_css_for_tabstyles(array $sectionstyles): string {
        global $PAGE;

        $this->resetAfterTest();
        $this->setAdminUser();
        set_config('enablecustomstyles', 1, 'format_onetopic');

        $course = $this->getDataGenerator()->create_course(['format' => 'onetopic', 'numsections' => count($sectionstyles)]);
        $format = course_get_format($course);
        foreach ($sectionstyles as $number => $styles) {
            $section = get_fast_modinfo($course)->get_section_info($number + 1);
            $format->update_section_format_options(['id' => $section->id, 'tabstyles' => json_encode($styles)]);
        }
        rebuild_course_cache($course->id, true);

        $PAGE->set_url('/course/view.php', ['id' => $course->id]);
        $PAGE->set_course($course);
        $format = course_get_format($course);
        $header = new header($format);
        $data = $header->export_for_template($PAGE->get_renderer('format_onetopic'));

        return $data->cssstyles;
    }

    /**
     * Create a course with three sections using the given "others" rules and return the generated CSS.
     *
     * @param array $others Value of the "others" rule for each of the three sections.
     * @return string The CSS passed to the template.
     */
    private function get_generated_css(array $others): string {
        $sectionstyles = [];
        foreach ($others as $value) {
            $sectionstyles[] = ['default' => ['others' => $value]];
        }

        return $this->get_css_for_tabstyles($sectionstyles);
    }

    /**
     * Markup split over several sections must not end up in the generated CSS.
     */
    public function test_cssstyles_cannot_break_out_of_style_element_violation(): void {
        $css = $this->get_generated_css(['</style ', '><img src=x onerror=alert(1)//', '>']);

        $this->assertStringNotContainsString('<', $css);
        $this->assertStringNotContainsString('</style', $css);
    }

    /**
     * Braces in a value must not close the generated rule.
     */
    public function test_cssstyles_braces_cannot_leave_rule_violation(): void {
        $others = [
            'x;}:root a[href*="sesskey="]{background:url(//extern.example/probe)}',
            'a{color:red',
            'color: blue',
        ];
        $css = $this->get_generated_css($others);

        $this->assertStringNotContainsString('}:root', $css);
        $this->assertStringNotContainsString('a{color', $css);
        $this->assertStringContainsString('x;:root a[href*="sesskey="]background:url(//extern.example/probe);', $css);
        $this->assertStringContainsString('color: blue;', $css);
    }

    /**
     * Property names other than plain CSS names must be dropped.
     */
    public function test_cssstyles_invalid_property_name_is_dropped_violation(): void {
        $css = $this->get_css_for_tabstyles([
            ['default' => ['</style ' => 'x', 'color;}body{x' => 'y', 'color' => 'red']],
            ['default' => ['others' => '><img src=x>']],
        ]);

        $this->assertStringNotContainsString('/style', $css);
        $this->assertStringNotContainsString('}body{', $css);
        $this->assertStringContainsString('color:red;', $css);
    }

    /**
     * The unit appended to a value must be sanitised as well.
     */
    public function test_cssstyles_unit_is_sanitised_violation(): void {
        $css = $this->get_css_for_tabstyles([
            ['default' => ['font-size' => '12', 'unit-font-size' => 'px}a{color:red']],
        ]);

        $this->assertStringContainsString('font-size:12pxacolor:red;', $css);
        $this->assertStringNotContainsString('}a{', $css);
    }

    /**
     * Values that are not scalars must be skipped.
     */
    public function test_cssstyles_non_scalar_values_are_skipped_violation(): void {
        $css = $this->get_css_for_tabstyles([
            ['default' => ['color' => null, 'border-color' => ['a' => 'b'], 'font-weight' => 'bold']],
        ]);

        $this->assertStringNotContainsString('border-color', $css);
        $this->assertStringNotContainsString('{color:', $css);
        $this->assertStringContainsString('font-weight:bold;', $css);
    }

    /**
     * Regular rules must still be generated.
     */
    #[Group('baseline')]
    public function test_cssstyles_regular_rules_are_kept_verification(): void {
        $css = $this->get_generated_css(['color: red', 'text-decoration: underline', 'margin-top: 1px']);

        $this->assertStringContainsString('color: red;', $css);
        $this->assertStringContainsString('text-decoration: underline;', $css);
        $this->assertStringContainsString('margin-top: 1px;', $css);
    }
}
