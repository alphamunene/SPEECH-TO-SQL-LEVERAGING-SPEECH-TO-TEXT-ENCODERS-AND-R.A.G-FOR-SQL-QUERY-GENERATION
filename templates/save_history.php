<?php
session_start();
include('config.php'); // should define $mysql = new mysqli(...);

header('Content-Type: application/json');

// You might want to check login, optional
if (!isset($_SESSION['email'])) {
    echo json_encode(["status" => "error", "message" => "Not logged in"]);
    exit();
}

$data = json_decode(file_get_contents("php://input"), true);

$query = $data['query'] ?? '';
$result = json_encode($data['result'] ?? []);

$stmt = $mysql->prepare("
    INSERT INTO history (query, result, created_at)
    VALUES (?, ?, NOW())
");

$stmt->bind_param("ss", $query, $result);

if ($stmt->execute()) {
    echo json_encode(["status" => "success"]);
} else {
    echo json_encode(["status" => "error", "message" => $stmt->error]);
}

$stmt->close();
$mysql->close();
?>
