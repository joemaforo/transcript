<?php
// This file is part of the examination block for Moodle.
//
// Moodle is free software: you can redistribute it and/or modify
// it under the terms of the GNU General Public License as published by
// the Free Software Foundation, either version 3 of the License, or
// (at your option) any later version.

require_once(__DIR__ . '/../../config.php');
require_once($CFG->libdir . '/pdflib.php');

// Check user is logged in
require_login();

global $USER, $DB, $OUTPUT, $PAGE;

$PAGE->set_context(context_system::instance());
$PAGE->set_url(new moodle_url('/blocks/examination/pages/transcript.php'));
$PAGE->set_title('Student Transcript');
$PAGE->set_heading('Student Transcript');

// Get current user's username
$username = $USER->username;

// Check student balance from account_statement table
$balance = $DB->get_field_sql(
    'SELECT SUM(amountlcy) FROM {account_statement} WHERE customerno = ?',
    [$username]
);

// If balance > 1, student cannot view transcript
if ($balance > 1) {
    echo $OUTPUT->header();
    echo html_writer::div(
        'Your account has an outstanding balance. Please clear your balance to view your transcript.',
        'alert alert-warning'
    );
    echo $OUTPUT->footer();
    exit();
}

// Get student's bio data
$biodata = $DB->get_record('user_bio_data', ['username' => $username]);

if (!$biodata) {
    echo $OUTPUT->header();
    echo html_writer::div('Student record not found.', 'alert alert-danger');
    echo $OUTPUT->footer();
    exit();
}

// Get all programmes for this student from transcript and transcript_decision tables
$programmes_sql = "
    SELECT DISTINCT td.programme
    FROM {transcript_decision} td
    WHERE td.username = ?
    ORDER BY td.programme ASC
";
$programmes = $DB->get_records_sql($programmes_sql, [$username]);

$selected_programme = optional_param('programme', null, PARAM_TEXT);

// If no programme selected, use the first one or the one from biodata
if (!$selected_programme && !empty($programmes)) {
    $selected_programme = reset($programmes)->programme;
} else if (!$selected_programme) {
    $selected_programme = $biodata->programme;
}

// Get faculty information
$faculty = $DB->get_record('faculty', ['programmecode' => $selected_programme]);

// Render page header
echo $OUTPUT->header();

// Add custom CSS for fixed column widths
$css = <<<CSS
<style>
    .transcript-table {
        width: 100%;
        border-collapse: collapse;
        table-layout: fixed;
        margin-bottom: 20px;
    }
    
    .transcript-table th,
    .transcript-table td {
        border: 1px solid #999;
        padding: 8px;
        word-wrap: break-word;
        overflow-wrap: break-word;
        white-space: normal;
    }
    
    .transcript-table th {
        background-color: #f0f0f0;
        font-weight: bold;
        text-align: center;
    }
    
    .col-code {
        width: 80px;
        text-align: center;
    }
    
    .col-description {
        width: 350px;
        text-align: left;
    }
    
    .col-credits {
        width: 80px;
        text-align: center;
    }
    
    .col-grade {
        width: 80px;
        text-align: center;
    }
    
    .col-points {
        width: 80px;
        text-align: center;
    }
    
    .col-gpa {
        width: 80px;
        text-align: center;
    }
    
    .transcript-container {
        margin: 20px auto;
        max-width: 1000px;
        padding: 20px;
    }
    
    .semester-header {
        margin-top: 30px;
        margin-bottom: 10px;
        font-size: 14px;
        font-weight: bold;
    }
    
    .student-info {
        margin-bottom: 10px;
        font-size: 12px;
    }
    
    .totals-row {
        background-color: #e8e8e8;
        font-weight: bold;
    }
    
    .cumulative-row {
        background-color: #f5f5f5;
        font-weight: bold;
    }
</style>
CSS;

echo $css;

// Display programme selection dropdown if student has multiple programmes
if (count($programmes) > 1) {
    echo html_writer::start_div('programme-selection', ['style' => 'margin-bottom: 20px;']);
    echo html_writer::start_form(new moodle_url('/blocks/examination/pages/transcript.php'), 'get');
    echo html_writer::label('Select Programme: ', 'programme_select');
    echo html_writer::select(
        array_combine(
            array_map(function($p) { return $p->programme; }, array_values($programmes)),
            array_map(function($p) { return $p->programme; }, array_values($programmes))
        ),
        'programme',
        $selected_programme,
        false,
        ['id' => 'programme_select', 'onchange' => 'this.form.submit();']
    );
    echo html_writer::end_form();
    echo html_writer::end_div();
}

// Get transcript data
$transcript_sql = "
    SELECT t.*, td.semestercredithours, td.semestergradepoint, td.semestergradepointaverage,
           td.cumulativecredithours, td.cumulativegradepoint, td.cumulativegradepointaverage,
           td.part, td.semester, td.enrollment, td.academicyear
    FROM {transcript} t
    JOIN {transcript_decision} td ON t.username = td.username AND t.programme = td.programme 
        AND t.part = td.part AND t.semester = td.semester AND t.academicyear = td.academicyear
    WHERE t.username = ? AND t.programme = ?
    ORDER BY t.academicyear ASC, t.part ASC, t.semester ASC, t.coursecode ASC
";
$transcripts = $DB->get_records_sql($transcript_sql, [$username, $selected_programme]);

// Group transcripts by semester
$semesters = [];
foreach ($transcripts as $transcript) {
    $sem_key = $transcript->part . '-' . $transcript->semester . '-' . $transcript->academicyear . '-' . $transcript->enrollment;
    
    if (!isset($semesters[$sem_key])) {
        $semesters[$sem_key] = [
            'part' => $transcript->part,
            'semester' => $transcript->semester,
            'academicyear' => $transcript->academicyear,
            'enrollment' => $transcript->enrollment,
            'courses' => [],
            'semestercredithours' => $transcript->semestercredithours,
            'semestergradepoint' => $transcript->semestergradepoint,
            'semestergradepointaverage' => $transcript->semestergradepointaverage,
            'cumulativecredithours' => $transcript->cumulativecredithours,
            'cumulativegradepoint' => $transcript->cumulativegradepoint,
            'cumulativegradepointaverage' => $transcript->cumulativegradepointaverage,
        ];
    }
    
    $semesters[$sem_key]['courses'][] = $transcript;
}

// Start transcript display
echo html_writer::start_div('transcript-container');

// Display university logo and tagline (centered)
echo html_writer::start_div('', ['style' => 'text-align: center; margin-bottom: 20px;']);
echo html_writer::tag('img', '', ['src' => $CFG->wwwroot . '/pix/logo.png', 'alt' => 'University Logo', 'style' => 'height: 80px; margin-bottom: 10px;']);
echo html_writer::tag('p', 'University Tagline', ['style' => 'margin: 0; font-weight: bold;']);
echo html_writer::end_div();

echo html_writer::empty_tag('hr');

// Student Information
echo html_writer::div(
    'STUDENT NAME: ' . ucwords(strtolower($biodata->title . ' ' . $USER->firstname . ' ' . $USER->lastname)) . 
    '&nbsp;&nbsp;&nbsp;&nbsp;STUDENT NUMBER: ' . $username,
    'student-info'
);

echo html_writer::div(
    'DATE OF BIRTH: ' . userdate($biodata->dateofbirth, '%d/%m/%Y') . 
    '&nbsp;&nbsp;&nbsp;&nbsp;PLACE OF BIRTH: ' . $biodata->placeofbirth,
    'student-info'
);

echo html_writer::div('PROGRAMME: ' . $selected_programme, 'student-info');

if ($faculty) {
    echo html_writer::div('FACULTY: ' . $faculty->facultyname, 'student-info');
}

echo html_writer::empty_tag('hr');

// Display transcript table for each semester
$last_cumulative_gpa = null;

foreach ($semesters as $sem_key => $semester_data) {
    // Determine month based on enrollment
    $month = ($semester_data['enrollment'] == 1) ? 'June' : 'December';
    
    // Construct semester header
    $part_text = convert_number_to_ordinal($semester_data['part']) . ' Year';
    $sem_text = convert_number_to_ordinal($semester_data['semester']) . ' Semester';
    
    echo html_writer::div(
        $part_text . ' ' . $sem_text . ' ' . $month . ' ' . $semester_data['academicyear'],
        'semester-header'
    );
    
    // Create transcript table with fixed column widths
    echo html_writer::start_tag('table', ['class' => 'transcript-table']);
    
    // Table header
    echo html_writer::start_tag('thead');
    echo html_writer::start_tag('tr');
    echo html_writer::tag('th', 'Code', ['class' => 'col-code']);
    echo html_writer::tag('th', 'Description', ['class' => 'col-description']);
    echo html_writer::tag('th', 'Credit Hours', ['class' => 'col-credits']);
    echo html_writer::tag('th', 'Letter Grade', ['class' => 'col-grade']);
    echo html_writer::tag('th', 'Grade Points', ['class' => 'col-points']);
    echo html_writer::tag('th', 'GPA', ['class' => 'col-gpa']);
    echo html_writer::end_tag('tr');
    echo html_writer::end_tag('thead');
    
    // Table body - courses
    echo html_writer::start_tag('tbody');
    foreach ($semester_data['courses'] as $course) {
        echo html_writer::start_tag('tr');
        echo html_writer::tag('td', $course->coursecode, ['class' => 'col-code']);
        echo html_writer::tag('td', $course->coursedescription, ['class' => 'col-description']);
        echo html_writer::tag('td', $course->credithours, ['class' => 'col-credits']);
        echo html_writer::tag('td', $course->lettergrade, ['class' => 'col-grade']);
        echo html_writer::tag('td', $course->gradepoint, ['class' => 'col-points']);
        echo html_writer::tag('td', '', ['class' => 'col-gpa']);
        echo html_writer::end_tag('tr');
    }
    
    // Semester totals row
    echo html_writer::start_tag('tr', ['class' => 'totals-row']);
    echo html_writer::tag('td', 'Semester Totals', ['colspan' => '2', 'class' => 'col-description']);
    echo html_writer::tag('td', $semester_data['semestercredithours'], ['class' => 'col-credits']);
    echo html_writer::tag('td', '', ['class' => 'col-grade']);
    echo html_writer::tag('td', $semester_data['semestergradepoint'], ['class' => 'col-points']);
    echo html_writer::tag('td', $semester_data['semestergradepointaverage'], ['class' => 'col-gpa']);
    echo html_writer::end_tag('tr');
    
    // Cumulative totals row
    echo html_writer::start_tag('tr', ['class' => 'cumulative-row']);
    echo html_writer::tag('td', 'Cumulative Totals', ['colspan' => '2', 'class' => 'col-description']);
    echo html_writer::tag('td', $semester_data['cumulativecredithours'], ['class' => 'col-credits']);
    echo html_writer::tag('td', '', ['class' => 'col-grade']);
    echo html_writer::tag('td', $semester_data['cumulativegradepoint'], ['class' => 'col-points']);
    echo html_writer::tag('td', $semester_data['cumulativegradepointaverage'], ['class' => 'col-gpa']);
    echo html_writer::end_tag('tr');
    
    echo html_writer::end_tag('tbody');
    echo html_writer::end_tag('table');
    
    $last_cumulative_gpa = $semester_data['cumulativegradepointaverage'];
}

// Display overall cumulative GPA
if ($last_cumulative_gpa !== null) {
    echo html_writer::tag('h3', 'Overall Cumulative GPA: ' . $last_cumulative_gpa);
}

echo html_writer::end_div();

// PDF Download Button
echo html_writer::start_div('', ['style' => 'text-align: center; margin-top: 30px; margin-bottom: 30px;']);
echo html_writer::link(
    new moodle_url('/blocks/examination/pages/transcript.php', ['action' => 'download_pdf', 'programme' => $selected_programme]),
    'Download Transcript as PDF',
    ['class' => 'btn btn-primary']
);
echo html_writer::end_div();

// Handle PDF download
$action = optional_param('action', null, PARAM_ALPHA);
if ($action === 'download_pdf') {
    generate_transcript_pdf($username, $selected_programme, $biodata, $faculty, $semesters, $last_cumulative_gpa);
    exit();
}

echo $OUTPUT->footer();

/**
 * Helper function to convert number to ordinal (1st, 2nd, 3rd, etc.)
 */
function convert_number_to_ordinal($num) {
    $num = intval($num);
    if ($num == 1) return '1st';
    if ($num == 2) return '2nd';
    if ($num == 3) return '3rd';
    return $num . 'th';
}

/**
 * Generate and download transcript as PDF
 */
function generate_transcript_pdf($username, $programme, $biodata, $faculty, $semesters, $last_cumulative_gpa) {
    global $CFG, $USER;
    
    $pdf = new pdf();
    $pdf->set_title('Student Transcript');
    
    // Add content to PDF
    $pdf->add_page();
    
    // Set font
    $pdf->SetFont('helvetica', '', 12);
    
    // University logo and tagline
    $pdf->SetXY(0, 10);
    $pdf->SetFont('helvetica', 'B', 14);
    $pdf->SetTextColor(0, 0, 0);
    $pdf->Cell(0, 10, 'UNIVERSITY', 0, 1, 'C');
    $pdf->SetFont('helvetica', '', 10);
    $pdf->Cell(0, 5, 'University Tagline', 0, 1, 'C');
    
    // Student information
    $pdf->SetFont('helvetica', '', 10);
    $pdf->Ln(10);
    $pdf->Cell(0, 5, 'STUDENT NAME: ' . ucwords(strtolower($biodata->title . ' ' . $USER->firstname . ' ' . $USER->lastname)) . '    STUDENT NUMBER: ' . $username, 0, 1);
    $pdf->Cell(0, 5, 'DATE OF BIRTH: ' . userdate($biodata->dateofbirth, '%d/%m/%Y') . '    PLACE OF BIRTH: ' . $biodata->placeofbirth, 0, 1);
    $pdf->Cell(0, 5, 'PROGRAMME: ' . $programme, 0, 1);
    if ($faculty) {
        $pdf->Cell(0, 5, 'FACULTY: ' . $faculty->facultyname, 0, 1);
    }
    
    $pdf->Ln(5);
    
    // Add semester tables
    foreach ($semesters as $sem_key => $semester_data) {
        $month = ($semester_data['enrollment'] == 1) ? 'June' : 'December';
        $part_text = convert_number_to_ordinal($semester_data['part']) . ' Year';
        $sem_text = convert_number_to_ordinal($semester_data['semester']) . ' Semester';
        
        $pdf->SetFont('helvetica', 'B', 11);
        $pdf->Cell(0, 7, $part_text . ' ' . $sem_text . ' ' . $month . ' ' . $semester_data['academicyear'], 0, 1);
        
        $pdf->SetFont('helvetica', '', 9);
        
        // Table headers with fixed column widths
        $pdf->SetFillColor(200, 200, 200);
        $pdf->Cell(20, 5, 'Code', 1, 0, 'C', true);
        $pdf->Cell(80, 5, 'Description', 1, 0, 'L', true);
        $pdf->Cell(18, 5, 'Credits', 1, 0, 'C', true);
        $pdf->Cell(15, 5, 'Grade', 1, 0, 'C', true);
        $pdf->Cell(18, 5, 'Points', 1, 0, 'C', true);
        $pdf->Cell(15, 5, 'GPA', 1, 1, 'C', true);
        
        // Course rows
        $pdf->SetFillColor(255, 255, 255);
        foreach ($semester_data['courses'] as $course) {
            $pdf->Cell(20, 5, substr($course->coursecode, 0, 10), 1, 0, 'C');
            $pdf->Cell(80, 5, substr($course->coursedescription, 0, 30), 1, 0, 'L');
            $pdf->Cell(18, 5, $course->credithours, 1, 0, 'C');
            $pdf->Cell(15, 5, $course->lettergrade, 1, 0, 'C');
            $pdf->Cell(18, 5, $course->gradepoint, 1, 0, 'C');
            $pdf->Cell(15, 5, '', 1, 1, 'C');
        }
        
        // Semester totals
        $pdf->SetFillColor(220, 220, 220);
        $pdf->Cell(100, 5, 'Semester Totals', 1, 0, 'R');
        $pdf->Cell(18, 5, $semester_data['semestercredithours'], 1, 0, 'C');
        $pdf->Cell(15, 5, '', 1, 0, 'C');
        $pdf->Cell(18, 5, $semester_data['semestergradepoint'], 1, 0, 'C');
        $pdf->Cell(15, 5, $semester_data['semestergradepointaverage'], 1, 1, 'C');
        
        // Cumulative totals
        $pdf->SetFillColor(240, 240, 240);
        $pdf->Cell(100, 5, 'Cumulative Totals', 1, 0, 'R');
        $pdf->Cell(18, 5, $semester_data['cumulativecredithours'], 1, 0, 'C');
        $pdf->Cell(15, 5, '', 1, 0, 'C');
        $pdf->Cell(18, 5, $semester_data['cumulativegradepoint'], 1, 0, 'C');
        $pdf->Cell(15, 5, $semester_data['cumulativegradepointaverage'], 1, 1, 'C');
        
        $pdf->Ln(5);
    }
    
    // Overall cumulative GPA
    if ($last_cumulative_gpa !== null) {
        $pdf->SetFont('helvetica', 'B', 10);
        $pdf->Cell(0, 7, 'Overall Cumulative GPA: ' . $last_cumulative_gpa, 0, 1);
    }
    
    // Output PDF
    $filename = 'Transcript_' . $username . '_' . date('Ymd') . '.pdf';
    $pdf->Output('D', $filename);
}
?>
