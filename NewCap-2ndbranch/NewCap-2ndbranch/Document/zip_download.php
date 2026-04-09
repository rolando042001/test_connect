<?php
session_start();
header('Content-Type: application/json');
require '../Backend/db.php';
require '../Backend/rbac.php';
require_role('admin');

if (!isset($_POST['file_ids']) || $_POST['file_ids'] === '') {
    echo json_encode(['status' => 'error', 'message' => 'No file IDs provided.']);
    exit;
}

$tempDir = __DIR__ . '/../uploads/temp/';
if (!is_dir($tempDir)) mkdir($tempDir, 0777, true);

$zipName = 'Eufile_Batch_' . time() . '.zip';
$zipPath = $tempDir . $zipName;

$zip = new ZipArchive();
if ($zip->open($zipPath, ZipArchive::CREATE) !== TRUE) {
    echo json_encode(['status' => 'error', 'message' => 'Could not create archive on server.']);
    exit;
}

$ids = array_filter(array_map('intval', explode(',', $_POST['file_ids'])));
$added = 0;
foreach ($ids as $id) {
    $stmt = $conn->prepare("SELECT storage_name, display_name FROM files WHERE id = ?");
    $stmt->bind_param("i", $id);
    $stmt->execute();
    $file = $stmt->get_result()->fetch_assoc();
    $stmt->close();
    if (!$file) continue;
    $disk = __DIR__ . '/../uploads/vault/' . $file['storage_name'];
    if (is_file($disk)) {
        $zip->addFile($disk, $file['display_name']);
        $added++;
    }
}
$zip->close();

if ($added === 0) {
    @unlink($zipPath);
    echo json_encode(['status' => 'error', 'message' => 'None of the requested files were found on disk.']);
    exit;
}

echo json_encode(['status' => 'success', 'download_url' => 'uploads/temp/' . $zipName, 'count' => $added]);
