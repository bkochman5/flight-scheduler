<?php
declare(strict_types=1);

header('Content-Type: application/json; charset=utf-8');

$flights = [
    [
        'flightNumber' => 101,
        'departureAirport' => 'LHR',
        'arrivalAirport' => 'JFK',
        'departureDate' => '2026-09-01',
    ],
    [
        'flightNumber' => 202,
        'departureAirport' => 'CDG',
        'arrivalAirport' => 'FCO',
        'departureDate' => '2026-09-02',
    ],
];

$path = parse_url($_SERVER['REQUEST_URI'], PHP_URL_PATH);

if ($path === '/health') {
    echo json_encode(['status' => 'ok']);
    exit;
}

if ($path === '/flights') {
    echo json_encode($flights);
    exit;
}

http_response_code(404);
echo json_encode([
    'error' => 'Not Found',
    'path' => $path,
]);
