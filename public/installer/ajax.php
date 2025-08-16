<?php
header('Content-Type: application/json');

// This file provides AJAX functionality for the installer.

$action = $_GET['action'] ?? null;
$response = ['status' => 'error', 'message' => 'Invalid action specified.'];

if ($action === 'run_checks') {
    require_once __DIR__ . '/checks.php';

    $html_content = render_checks_table();

    // We also need the raw boolean status to enable/disable the 'next' button
    $checks = get_server_checks();
    $all_ok = !in_array(false, array_column($checks, 'status'), true);

    $response = [
        'status' => 'success',
        'html' => $html_content,
        'all_ok' => $all_ok,
    ];
}

echo json_encode($response);
exit;
