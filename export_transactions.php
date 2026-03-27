<?php
session_start();

// Database connection
include 'db.php';
include 'queries.php';

// --- CSV GENERATION START ---
$filename = "transactions_" . date('YmdHis') . ".csv";

// Clear any previous output to prevent file corruption
if (ob_get_length()) ob_end_clean();

// Set headers to force download
header('Content-Type: text/csv; charset=utf-8');
header('Content-Disposition: attachment; filename="' . $filename . '";');

// Open the output stream
$output = fopen('php://output', 'w');

// 1. Optional: Add UTF-8 BOM for Excel compatibility (Fixes weird characters in Excel)
fprintf($output, chr(0xEF).chr(0xBB).chr(0xBF));

// 2. Write the Column Headers
fputcsv($output, array('Member', 'Details', 'Amount', 'Date', 'By', 'Receipt URL'));

// 3. Write the Data Rows
foreach ($all_transactions as $tr) {
    fputcsv($output, [
        trim(($tr['FIRST_NAME'] ?? '') . ' ' . ($tr['LAST_NAME'] ?? '')),
        $tr['LABEL'] ?? '',
        $tr['AMOUNT'] ?? '',
        $tr['CREATED_AT'] ?? '',
        trim(($tr['BY_FIRST_NAME'] ?? '') . ' ' . ($tr['BY_LAST_NAME'] ?? '')),
        $tr['RECEIPT_PATH'] ?? ''
    ]);
}

// Close the stream
fclose($output);
exit;
