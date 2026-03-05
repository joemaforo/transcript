<?php
// Assuming necessary database connection code here

// Fetching records from database
$records = fetchRecords(); // This should be your fetch function for records

// Rendering HTML table
echo '<table style="table-layout: fixed; width: 100%;">
';
echo '<colgroup>
';
echo '<col style="width: 20%;" />'; // Fixed width for each column

echo '<col style="width: 40%;" />'; // Adjust as per your design

echo '<col style="width: 40%;" />';
echo '</colgroup>
';

echo '<tr>
';
echo '<th>Semester</th>
';
echo '<th>Description</th>
';
echo '<th>Other Column</th>
';
echo '</tr>
';

foreach ($records as $record) {
    echo '<tr>
';
    echo '<td>' . htmlspecialchars($record['semester']) . '</td>
';
    echo '<td style="overflow: hidden;">' . htmlspecialchars($record['description']) . '</td>
'; // Ensuring overflow is handled
    echo '<td>' . htmlspecialchars($record['other_column']) . '</td>
';
    echo '</tr>
';
}

echo '</table>';