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
 * Tests for the block_dashboardchart privacy provider.
 *
 * @package    block_dashboardchart
 * @copyright  2026 Vernon Spain
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */

namespace block_dashboardchart\privacy;

use PHPUnit\Framework\Attributes\CoversClass;
use core_privacy\local\request\approved_contextlist;
use core_privacy\local\request\writer;

/**
 * Verify the provider reports and exports a configured block's data.
 *
 * @package    block_dashboardchart
 * @copyright  2026 Vernon Spain
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */
#[CoversClass(provider::class)]
final class provider_test extends \advanced_testcase {
    /**
     * Create a configured dashboardchart block on a user's dashboard.
     *
     * @param int $userid The owner of the dashboard the block sits on.
     * @return \context_block The block's context.
     */
    protected function create_user_block(int $userid): \context_block {
        global $DB;
        $usercontext = \context_user::instance($userid);
        $instance = (object) [
            'blockname' => 'dashboardchart',
            'parentcontextid' => $usercontext->id,
            'showinsubcontexts' => 0,
            'requiredbytheme' => 0,
            'pagetypepattern' => 'my-index',
            'subpagepattern' => null,
            'defaultregion' => 'content',
            'defaultweight' => 0,
            'configdata' => '',
            'timecreated' => time(),
            'timemodified' => time(),
        ];
        $instance->id = $DB->insert_record('block_instances', $instance);
        $blockcontext = \context_block::instance($instance->id);

        $block = block_instance('dashboardchart', $instance);
        $block->instance_config_save((object) [
            'msg' => 'My heading',
            'graphtype' => 'pie',
            'dashboardcharttype' => 'category',
            'datalimit' => 5,
        ]);

        return $blockcontext;
    }

    /**
     * The owner's contexts include their configured block context.
     *
     * @return void
     */
    public function test_get_contexts_for_userid(): void {
        $this->resetAfterTest();
        $user = $this->getDataGenerator()->create_user();
        $blockcontext = $this->create_user_block($user->id);

        $contextlist = provider::get_contexts_for_userid($user->id);

        // Context ids come back from the database as strings, so compare loosely.
        $this->assertContainsEquals($blockcontext->id, $contextlist->get_contextids());
    }

    /**
     * The export includes the user-provided block configuration.
     *
     * @return void
     */
    public function test_export_user_data_includes_configuration(): void {
        $this->resetAfterTest();
        $user = $this->getDataGenerator()->create_user();
        $blockcontext = $this->create_user_block($user->id);

        $approved = new approved_contextlist($user, 'block_dashboardchart', [$blockcontext->id]);
        provider::export_user_data($approved);

        $writer = writer::with_context($blockcontext);
        $this->assertTrue($writer->has_any_data());
        $data = $writer->get_data([]);
        $this->assertEquals('My heading', $data->heading);
        $this->assertEquals('pie', $data->graphtype);
        $this->assertEquals('category', $data->charttype);
        $this->assertEquals(5, $data->datalimit);
    }
}
