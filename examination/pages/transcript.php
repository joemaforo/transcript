<?php
// Include Moodle configuration and libraries
require_once('../../config.php');

// Initialize the page
$PAGE->set_url(new moodle_url('/examination/pages/transcript.php'));
$PAGE->set_title('Student Transcript');
$PAGE->set_heading('Transcript');

// Get the user's ID
$userid = $USER->id;

// Fetch the programs from the database
$programs = $DB->get_records('programmes');

// Check balance from account_statement
$balance = $DB->get_field('account_statement', 'balance', array('userid' => $userid));

// Handle the form submission for program selection
if (isset($_POST['programid'])) {
    $programid = $_POST['programid'];
    // Fetch courses and GPA calculation for the selected program
    $courses = $DB->get_records('course', array('programid' => $programid));
    // GPA calculation logic here
}

// Start output buffering
ob_start();
?>

<html>
<head>
    <title>Student Transcript</title>
</head>
<body>
    <h1>Student Transcript</h1>
    <form method='post'>
        <label for='program'>Select Program: </label>
        <select name='programid' id='program'>
            <option value=''>Select a program</option>
            <?php foreach ($programs as $program): ?>
                <option value='<?php echo $program->id; ?>'><?php echo $program->name; ?></option>
            <?php endforeach; ?>
        </select>
        <input type='submit' value='Get Transcript'>
    </form>
    <p>Your account balance: <?php echo $balance; ?></p>
    <?php if (isset($courses)): ?>
        <h2>Courses for Selected Program</h2>
        <ul>
            <?php foreach ($courses as $course): ?>
                <li><?php echo $course->name; ?> - GPA: <?php // GPA Logic here ?></li>
            <?php endforeach; ?>
        </ul>
    <?php endif; ?>
    <form action='pdf_download.php' method='post'>
        <input type='hidden' name='userid' value='<?php echo $userid; ?>'>
        <input type='hidden' name='programid' value='<?php echo (isset($programid) ? $programid : ''); ?>'>
        <input type='submit' value='Download Transcript as PDF'>
    </form>
</body>
</html>
<?php
// End output buffering and flush output
ob_end_flush();
?>