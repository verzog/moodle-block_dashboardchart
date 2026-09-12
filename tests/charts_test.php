<?php
// This file is part of Moodle - https://moodle.org/
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
// along with Moodle.  If not, see <https://www.gnu.org/licenses/>.

/**
 * Tests for the block_dashboardchart chart builders.
 *
 * @package    block_dashboardchart
 * @copyright  2026 Vernon Spain
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */

namespace block_dashboardchart;

use PHPUnit\Framework\Attributes\CoversClass;

/**
 * Verify each chart builder executes on every supported database.
 *
 * The SQL in these builders is the main cross-database risk, so each test
 * runs the query and renders the chart to prove it works on both MySQL and
 * PostgreSQL.
 *
 * @package    block_dashboardchart
 * @copyright  2026 Vernon Spain
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */
#[CoversClass(\block_dashboardchart::class)]
final class charts_test extends \advanced_testcase {
    /**
     * Instantiate the block so its chart builders can be called.
     *
     * @return \block_base The dashboardchart block instance.
     */
    protected function make_block(): \block_base {
        return block_instance('dashboardchart');
    }

    /**
     * The enrolment-by-country chart counts users grouped by country.
     *
     * @return void
     */
    public function test_make_enrollment_table(): void {
        $this->resetAfterTest();
        $generator = $this->getDataGenerator();
        $generator->create_user(['country' => 'AU']);
        $generator->create_user(['country' => 'AU']);
        $generator->create_user(['country' => 'NZ']);

        $result = $this->make_block()->make_enrollment_table(5);

        $this->assertIsString($result);
        $this->assertNotEmpty($result);
    }

    /**
     * The category chart counts courses per category.
     *
     * @return void
     */
    public function test_make_category_course_table(): void {
        $this->resetAfterTest();
        $this->getDataGenerator()->create_category(['name' => 'Science']);

        $result = $this->make_block()->make_category_course_table(5);

        $this->assertIsString($result);
        $this->assertNotEmpty($result);
    }

    /**
     * The students-per-course chart counts student role assignments per course.
     *
     * @return void
     */
    public function test_make_course_student_table(): void {
        $this->resetAfterTest();
        $generator = $this->getDataGenerator();
        $course = $generator->create_course();
        $user = $generator->create_user();
        $generator->enrol_user($user->id, $course->id, 'student');

        $result = $this->make_block()->make_course_student_table(5);

        $this->assertIsString($result);
        $this->assertNotEmpty($result);
    }

    /**
     * The most-active-courses chart joins logs, roles, courses and contexts.
     *
     * @return void
     */
    public function test_make_most_active_courses_table(): void {
        $this->resetAfterTest();

        // Primarily asserts the multi-join query runs on every database.
        $result = $this->make_block()->make_most_active_courses_table(5);

        $this->assertIsString($result);
    }

    /**
     * The logins chart buckets log activity into recent local days.
     *
     * @return void
     */
    public function test_make_login_table(): void {
        $this->resetAfterTest();

        // Primarily asserts the per-day calendar-bucketed query runs on every
        // database; it should return one data point per requested day.
        $result = $this->make_block()->make_login_table(5);

        $this->assertIsString($result);
        $this->assertNotEmpty($result);
    }
}
