<?php
header('Content-Type: application/json');

echo json_encode([
    'success' => true,
    'data' => [
        'units' => []
    ],
    'timestamp' => date('Y-m-d H:i:s')
]);