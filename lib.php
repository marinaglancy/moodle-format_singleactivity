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
 * This file contains main class for the course format SCORM
 *
 * @package    format_singleactivity
 * @copyright  2012 Marina Glancy
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */

defined('MOODLE_INTERNAL') || die();
require_once($CFG->dirroot. '/course/format/lib.php');

/**
 * Main class for the singleactivity course format
 *
 * @package    format_singleactivity
 * @copyright  2012 Marina Glancy
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */
class format_singleactivity extends format_base {

    /**
     * The URL to use for the specified course
     *
     * @param int|stdClass $section Section object from database or just field course_sections.section
     *     if null the course view page is returned
     * @param array $options options for view URL. At the moment core uses:
     *     'navigation' (bool) if true and section has no separate page, the function returns null
     *     'sr' (int) used by multipage formats to specify to which section to return
     * @return null|moodle_url
     */
    public function get_view_url($section, $options = array()) {
        $sectionnum = $section;
        if (is_object($sectionnum)) {
            $sectionnum = $section->section;
        }
        if ($sectionnum == 1 && !empty(get_fast_modinfo($this->courseid)->sections[1])) {
            return new moodle_url('/course/view.php', array('id' => $this->courseid, 'section' => 1));
        }
        if (!empty($options['navigation']) && $section !== null) {
            return null;
        }
        return new moodle_url('/course/view.php', array('id' => $this->courseid));
    }

    /**
     * Loads all of the course sections into the navigation
     *
     * @param global_navigation $navigation
     * @param navigation_node $node The course node within the navigation
     */
    public function extend_course_navigation($navigation, navigation_node $node) {
        global $OUTPUT;
        $activity = $this->get_activity();
        if ($activity && $activity->uservisible) {
            $activitynode = $this->navigation_add_activity($node, $activity);
            $node->action = $activitynode->action;
            $activitynode->display = false;
        }
        // Singleactivity course format does not extend course navigation
        if (has_capability('moodle/course:manageactivities', context_course::instance($this->courseid))) {
            $modinfo = get_fast_modinfo($this->courseid);
            if (!empty($modinfo->sections[1])) {
                $section1 = $modinfo->get_section_info(1);
                // show orphaned activities
                $icon = new pix_icon('orphaned', '', 'format_singleactivity');
                $orphanednode = $node->add(get_string('orphaned', 'format_singleactivity'),
                        $this->get_view_url(1), navigation_node::TYPE_SECTION, null, $section1->id, $icon);
                $orphanednode->nodetype = navigation_node::NODETYPE_BRANCH;
                $orphanednode->add_class('error');
                foreach ($modinfo->sections[1] as $cmid) {
                    $this->navigation_add_activity($orphanednode, $modinfo->cms[$cmid], $icon);
                }
            }
        }
    }

    /**
     * Adds a course module to the navigation node
     *
     * @param navigation_node $node
     * @param cm_info $cm
     * @param pix_icon $icon if specified overwrites module icon
     * @return null|navigation_node
     */
    protected function navigation_add_activity(navigation_node $node, $cm, $icon = null) {
        if (!$cm->uservisible || $cm->modname === 'label') {
            return null;
        }
        $activityname = format_string($cm->name, true, array('context' => context_module::instance($cm->id)));
        $action = $cm->get_url();
        if (!$icon) {
            if ($cm->icon) {
                $icon = new pix_icon($cm->icon, $cm->modfullname, $cm->iconcomponent);
            } else {
                $icon = new pix_icon('icon', $cm->modfullname, $cm->modname);
            }
        }
        $activitynode = $node->add($activityname, $action, navigation_node::TYPE_ACTIVITY, null, $cm->id, $icon);
        if (global_navigation::module_extends_navigation($cm->modname)) {
            $activitynode->nodetype = navigation_node::NODETYPE_BRANCH;
        } else {
            $activitynode->nodetype = navigation_node::NODETYPE_LEAF;
        }
        return $activitynode;
    }

    /**
     * Returns the list of blocks to be automatically added for the newly created course
     *
     * @return array of default blocks, must contain two keys BLOCK_POS_LEFT and BLOCK_POS_RIGHT
     *     each of values is an array of block names (for left and right side columns)
     */
    public function get_default_blocks() {
        return array(
            BLOCK_POS_LEFT => array(),
            BLOCK_POS_RIGHT => array()
        );
    }

    /**
     * Definitions of the additional options that this course format uses for course
     *
     * Singleactivity course format uses one option 'activitytype'
     *
     * @param bool $foreditform
     * @return array of options
     */
    public function course_format_options($foreditform = false) {
        static $courseformatoptions = false;
        if ($courseformatoptions === false) {
            $config = get_config('format_singleactivity');
            $courseformatoptions = array(
                'activitytype' => array(
                    'default' => $config->activitytype,
                    'type' => PARAM_TEXT,
                ),
            );
        }
        if ($foreditform && !isset($courseformatoptions['activitytype']['label'])) {
            $availabletypes = get_module_types_names();
            $courseformatoptionsedit = array(
                'activitytype' => array(
                    'label' => new lang_string('activitytype', 'format_singleactivity'),
                    'help' => 'activitytype',
                    'help_component' => 'format_singleactivity',
                    'element_type' => 'select',
                    'element_attributes' => array($availabletypes),
                ),
            );
            $courseformatoptions = array_merge_recursive($courseformatoptions, $courseformatoptionsedit);
        }
        return $courseformatoptions;
    }

    /**
     * Make sure that current active activity is in section 0
     *
     * All other activities are in section 1 that will be displayed as 'Orphaned'
     */
    public function reorder_activities() {
        course_create_sections_if_missing($this->courseid, array(0, 1));
        $section0 = $this->get_section(0);
        $section1 = $this->get_section(1);
        if (!$section0->visible) {
            set_section_visible($this->courseid, 0, true);
        }
        if ($section1->visible) {
            set_section_visible($this->courseid, 1, false);
        }

        $modinfo = get_fast_modinfo($this->courseid);
        $activitytype = $this->get_activitytype();
        $activity = null;
        if (!empty($activitytype)) {
            foreach ($modinfo->sections as $sectionnum => $cmlist) {
                foreach ($cmlist as $cmid) {
                    if ($modinfo->cms[$cmid]->modname === $activitytype) {
                        $activity = $modinfo->cms[$cmid];
                        break 2;
                    }
                }
            }
        }
        if ($activity && $activity->sectionnum != 0) {
            moveto_module($activity, $section0);
        }
        foreach ($modinfo->cms as $id => $cm) {
            if ((!$activity || $id != $activity->id) && $cm->sectionnum != 1) {
                moveto_module($cm, $section1);
            }
        }
    }

    /**
     * Returns the name of activity type used for this course
     *
     * @return string|null
     */
    protected function get_activitytype() {
        $options = $this->get_format_options();
        $availabletypes = get_module_types_names();
        if (!empty($options['activitytype']) &&
                array_key_exists($options['activitytype'], $availabletypes)) {
            return $options['activitytype'];
        } else {
            return null;
        }
    }

    /**
     * Returns the current activity if exists
     *
     * @return null|cm_info
     */
    protected function get_activity() {
        $this->reorder_activities();
        $modinfo = get_fast_modinfo($this->courseid);
        if (isset($modinfo->sections[0][0])) {
            return $modinfo->cms[$modinfo->sections[0][0]];
        }
        return null;
    }

    /**
     * Allows course format to execute code on moodle_page::set_course()
     *
     * If user is on course view page and there is no module added to the course
     * and the user has 'moodle/course:manageactivities' capability, redirect to create module
     * form. This function is executed before the output starts
     *
     * @param moodle_page $page instance of page calling set_course
     */
    public function page_set_course(moodle_page $page) {
        global $PAGE;
        $page->add_body_class('format-'. $this->get_format());
        if ($PAGE == $page && $page->has_set_url() &&
                $page->url->compare(new moodle_url('/course/view.php'), URL_MATCH_BASE)) {
            $edit = optional_param('edit', -1, PARAM_BOOL);
            if (($edit == 0 || $edit == 1) && confirm_sesskey()) {
                // This is a request to turn editing mode on or off, do not redirect here, let /course/view.php process request, it will redirect itself
                return;
            }
            $this->reorder_activities();
            $cursection = optional_param('section', null, PARAM_INT);
            if ($cursection == 1 && has_capability('moodle/course:manageactivities', context_course::instance($this->courseid))) {
                // display orphaned activities
                return;
            }
            if (!$this->get_activitytype()) {
                if (has_capability('moodle/course:update', context_course::instance($this->courseid))) {
                    // teacher is redirected to edit course page
                    $url = new moodle_url('/course/edit.php', array('id' => $this->courseid));
                    redirect($url, get_string('erroractivitytype', 'format_singleactivity'));
                } else {
                    // student just receives an error
                    redirect(new moodle_url('/'), get_string('errornotsetup', 'format_singleactivity'));
                }
            }
            $cm = $this->get_activity();
            if ($cm === null) {
                if (has_capability('moodle/course:manageactivities', context_course::instance($this->courseid))) {
                    // teacher is redirected to create a new activity
                    $url = new moodle_url('/course/modedit.php',
                            array('course' => $this->courseid, 'section' => 0, 'add' => $this->get_activitytype()));
                    redirect($url);
                } else {
                    // student just receives an error
                    redirect(new moodle_url('/'), get_string('errornotsetup', 'format_singleactivity'));
                }
            } else if (!$cm->uservisible) {
                // activity is set but not visible to current user, print error
                redirect(new moodle_url('/'), get_string('activityiscurrentlyhidden'));
            } else {
                redirect($cm->get_url());
            }
        }
    }

    /**
     * Allows course format to execute code on moodle_page::set_cm()
     *
     * If we are inside the main module for this course, remove extra node level
     * from navigation: substitute course node with activity node, move all children
     *
     * @param moodle_page $page instance of page calling set_cm
     */
    public function page_set_cm(moodle_page $page) {
        global $PAGE;
        parent::page_set_cm($page);
        if ($PAGE == $page && ($cm = $this->get_activity()) &&
                $cm->uservisible &&
                ($cm->id === $page->cm->id) &&
                ($activitynode = $page->navigation->find($cm->id, navigation_node::TYPE_ACTIVITY)) &&
                ($node = $page->navigation->find($page->course->id, navigation_node::TYPE_COURSE))) {
            // substitute course node with activity node, move all children
            $node->action = $activitynode->action;
            $node->type = $activitynode->type;
            $node->id = $activitynode->id;
            $node->key = $activitynode->key;
            $node->isactive = $node->isactive || $activitynode->isactive;
            $node->icon = null;
            if ($activitynode->children->count()) {
                foreach ($activitynode->children as &$child) {
                    $child->remove();
                    $node->add_node($child);
                }
            } else {
                $node->search_for_active_node();
            }
            $activitynode->remove();
        }
    }
}
