function logActivity($conn, $userId, $action, $itemName, $itemType) {
    $sql = "INSERT INTO audit_logs (user_id, action, item_name, item_type) VALUES (?, ?, ?, ?)";
    $stmt = $conn->prepare($sql);
    $stmt->bind_param("isss", $userId, $action, $itemName, $itemType);
    $stmt->execute();
}