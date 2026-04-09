<?php
// test_connect — database bootstrap
//
// All endpoints in this folder include this file. It opens the mysqli
// connection, sets a sane reporting mode, and registers a fatal-error
// handler that returns an ASCII "ERROR" body so the ESP32 firmware
// (which parses responses with String.toInt() / String.indexOf()) never
// receives an HTML error dump it can't parse.

$servername = "localhost";
$username   = "root";
$password   = "";
$dbname     = "test_connect";

// Make mysqli throw on errors so prepare()/execute() failures don't
// silently return false.
mysqli_report(MYSQLI_REPORT_ERROR | MYSQLI_REPORT_STRICT);

try {
    $conn = new mysqli($servername, $username, $password, $dbname);
    $conn->set_charset("utf8mb4");
} catch (Throwable $e) {
    // Log the real error, return a short ASCII body the ESP32 sketch
    // can recognise as a failure.
    error_log("test_connect db.php: " . $e->getMessage());
    http_response_code(500);
    header('Content-Type: text/plain');
    echo "ERROR";
    exit;
}

// Convert any later mysqli/runtime exception in this request into the
// same ASCII "ERROR" response so the ESP32 firmware never sees HTML.
set_exception_handler(function (Throwable $e) {
    error_log("test_connect handler: " . $e->getMessage());
    http_response_code(500);
    if (!headers_sent()) header('Content-Type: text/plain');
    echo "ERROR";
});
