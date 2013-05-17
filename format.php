<?php
// format.php - course format featuring single activity
//              included from view.php

// if we are not redirected before this point this means we want to manage orphaned activities
// i.e. display section 1

if ($section == 1) {
    echo $OUTPUT->box(get_string('orphanedwarning', 'format_singleactivity'));
    $courserenderer = $PAGE->get_renderer('core', 'course');
    echo $courserenderer->course_section_cm_list($course, course_get_format($course)->get_section(1), 1);
}
