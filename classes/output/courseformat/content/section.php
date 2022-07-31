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

namespace format_flexsections\output\courseformat\content;

use stdClass;

/**
 * Contains the section controls output class.
 *
 * @package   format_flexsections
 * @copyright 2022 Marina Glancy
 * @license   http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */
class section extends \core_courseformat\output\local\content\section {

    /** @var \format_flexsections the course format */
    protected $format;

    /** @var int subsection level */
    protected $level = 1;

    /**
     * Template name
     *
     * @param \renderer_base $renderer
     * @return string
     */
    public function get_template_name(\renderer_base $renderer): string {
        return 'format_flexsections/local/content/section';
    }

    /**
     * Data exporter
     *
     * @param \renderer_base $output
     * @return stdClass
     */
    public function export_for_template(\renderer_base $output): stdClass {
        $format = $this->format;

        $data = parent::export_for_template($output);

        // For sections that are displayed as a link do not print list of cms or controls.
        $showaslink = $this->section->collapsed == FORMAT_FLEXSECTIONS_COLLAPSED
            && $this->format->get_viewed_section() != $this->section->section;

        $data->showaslink = $showaslink;
        if ($showaslink) {
            $data->cmlist = [];
            $data->cmcontrols = '';
        }

        // Add subsections.
        if (!$showaslink) {
            $data->subsections = $this->section->section ? $this->get_subsections($output) : [];
            $data->level = $this->level;
        }

        if (!$this->section->section || $this->section->section == $this->format->get_viewed_section()) {
            $data->contentcollapsed = false;
            $data->collapsemenu = true;
        } else {
            $data->collapsemenu = false;
        }
        if ($this->section->parent == 0) {
            $data->type_class = "parent-section";
            $data->progress = $this->count_progress($this->section->id);
        } else {
            $data->type_class = "child-section";
            $data->progress = 0;
        }

        return $data;
    }

    /**
     * Subsections (recursive)
     *
     * @param \renderer_base $output
     * @return array
     */
    protected function get_subsections(\renderer_base $output): array {
        $modinfo = $this->format->get_modinfo();
        $data = [];
        foreach ($modinfo->get_section_info_all() as $section) {
            if ($section->parent == $this->section->section) {
                if ($this->format->is_section_visible($section)) {
                    $instance = new static($this->format, $section);
                    $instance->level++;
                    $d = (array)($instance->export_for_template($output)) +
                        $this->default_section_properties();
                    $data[] = (object)$d;
                }
            }
        }
        return $data;
    }

    /**
     * Since we display sections nested the values from the parent can propagate in templates
     *
     * @return array
     */
    protected function default_section_properties(): array {
        return [
            'collapsemenu' => false, 'summary' => [],
            'insertafter' => false, 'numsections' => false,
            'availability' => [], 'restrictionlock' => false, 'hasavailability' => false,
            'isstealth' => false, 'ishidden' => false, 'notavailable' => false, 'hiddenfromstudents' => false,
            'controlmenu' => [], 'cmcontrols' => '',
            'singleheader' => [], 'header' => [],
            'cmsummary' => [], 'onlysummary' => false, 'cmlist' => [],
        ];
    }
    
    
    public function count_progress($sectionid) {
        global $DB, $USER, $COURSE;
        $courseid = $COURSE->id;
        $value = $DB->get_field('course_format_options', 'value', array('sectionid' => $sectionid, 'name' => 'parent'));
        if ($value) {
            return 0; //If its child section return 0 as we are not showing it anywhere
        } else {
            $parent_child_arr = array();
            $arr = $this->get_child($sectionid, $courseid, $parent_child_arr);
            $total_number_activity = 0;
            $user_completed = 0;
            foreach ($arr as &$value) {
                $total_number_activity += $DB->count_records('course_modules', array('section' => $value, 'visible' => 1, 'completion' => 1, 'deletioninprogress' => 0));
                $sql = "SELECT count(cmc.id) as count FROM `mdl_course_modules` as cm left join mdl_course_modules_completion as cmc on cm.id = cmc.coursemoduleid WHERE section = $value and userid = $USER->id";
                $user_completed += $DB->get_record_sql($sql)->count;
            }
            return $this->get_percentage($user_completed, $total_number_activity);
        }
    }

    public function get_child($sectionid, $courseid, $parent_child_arr) {
        global $DB;
        $parent_child_arr[] = $sectionid;
        $sectionnum = $DB->get_field('course_sections', 'section', array('id' => $sectionid));
        if ($sectionnum != 0) {

            $sql = "select id,sectionid from {course_format_options} where courseid = $courseid and value = $sectionnum and " . $DB->sql_compare_text('name') . "= 'parent'";
            $child_sections = $DB->get_records_sql($sql);
            if (!empty($child_sections)) {
                foreach ($child_sections as $key => $value) {
                    $parent_child_arr = $this->get_child($value->sectionid, $courseid, $parent_child_arr);
                }
            }
        }
        return $parent_child_arr;
    }

    public function get_percentage($of, $from) {
        if ($from == 0)
            $percent = 0;
        else {
            $percent = $of * 100 / $from;
            $percent = number_format($percent);
        }
        return $percent;
    }

}
