<?php
session_start();
include('config.php');

header('Content-Type: application/json');

$stmt = $mysql->prepare("SELECT * FROM history ORDER BY id DESC");
$stmt->execute();

$result = $stmt->get_result();
$data = [];

while ($row = $result->fetch_assoc()) {
    $data[] = $row;
}

echo json_encode($data);

$stmt->close();
$mysql->close();
?>
