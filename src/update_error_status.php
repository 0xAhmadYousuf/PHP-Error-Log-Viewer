<?php
header('Content-Type: application/json');

if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    http_response_code(405);
    echo json_encode(['success' => false, 'error' => 'Method not allowed']);
    exit;
}

$input = json_decode(file_get_contents('php://input'), true);

if (!$input) {
    http_response_code(400);
    echo json_encode(['success' => false, 'error' => 'Invalid JSON']);
    exit;
}

$jsonFile = 'solved_errors.json';
$data = [];

if (file_exists($jsonFile)) {
    $data = json_decode(file_get_contents($jsonFile), true) ?: [];
}

$data['last_updated'] = date('Y-m-d H:i:s');

if ($input['type'] === 'file') {
    $data['files'][$input['path']] = [
        'solved' => $input['solved'],
        'solved_by' => $input['solved_by'] ?? '',
        'timestamp' => $input['timestamp'] ?? date('c')
    ];
} elseif ($input['type'] === 'error') {
    $data['errors'][$input['hash']] = [
        'solved' => $input['solved'],
        'solved_by' => $input['solved_by'] ?? '',
        'timestamp' => $input['timestamp'] ?? date('c')
    ];
}

if (file_put_contents($jsonFile, json_encode($data, JSON_PRETTY_PRINT))) {
    echo json_encode(['success' => true]);
} else {
    http_response_code(500);
    echo json_encode(['success' => false, 'error' => 'Failed to save data']);
}
?>
