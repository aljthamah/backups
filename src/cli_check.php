<?php
// This script is executed from the command line by the installer
// to check for extensions in the CLI environment, which might be
// different from the web server's PHP environment (e.g., FPM vs CLI).

header('Content-Type: application/json');

echo json_encode([
    'ssh2_loaded' => extension_loaded('ssh2'),
]);
