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

namespace local_byblos;

/**
 * Unit tests for the page model.
 *
 * @package    local_byblos
 * @copyright  2026 South African Theological Seminary
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 * @covers     \local_byblos\page
 */
final class page_test extends \advanced_testcase {

    /**
     * create() should insert a draft page owned by the given user.
     */
    public function test_create_default_status_is_draft(): void {
        $this->resetAfterTest();
        $user = $this->getDataGenerator()->create_user();

        $pageid = page::create((int) $user->id, 'My first page', 'Hello world');

        $rec = page::get($pageid);
        $this->assertNotNull($rec);
        $this->assertSame((int) $user->id, (int) $rec->userid);
        $this->assertSame('My first page', $rec->title);
        $this->assertSame('draft', $rec->status);
        $this->assertSame('single', $rec->layoutkey);
        $this->assertSame('clean', $rec->themekey);
    }

    /**
     * list_by_user() should return only the caller's own pages.
     */
    public function test_list_by_user_scopes_to_owner(): void {
        $this->resetAfterTest();
        $u1 = $this->getDataGenerator()->create_user();
        $u2 = $this->getDataGenerator()->create_user();

        page::create((int) $u1->id, 'U1 A');
        page::create((int) $u1->id, 'U1 B');
        page::create((int) $u2->id, 'U2 A');

        $u1pages = page::list_by_user((int) $u1->id);
        $u2pages = page::list_by_user((int) $u2->id);

        $this->assertCount(2, $u1pages);
        $this->assertCount(1, $u2pages);
    }

    /**
     * delete() should remove the row; subsequent get() returns null.
     */
    public function test_delete_removes_page(): void {
        $this->resetAfterTest();
        $user = $this->getDataGenerator()->create_user();
        $pageid = page::create((int) $user->id, 'Temp');

        $this->assertTrue(page::delete($pageid));
        $this->assertNull(page::get($pageid));
    }
}
