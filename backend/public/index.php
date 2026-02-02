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

if ($path === '/' || $path === '') {
    echo json_encode([
        'message' => 'Flight Scheduler API',
        'endpoints' => ['/health', '/version', '/flights'],
    ]);
    exit;
}

if ($path === '/health') {
    echo json_encode(['status' => 'ok']);
    exit;
}

if ($path === '/version') {
    echo json_encode(['version' => '1.0.0']);
    exit;
}

if (preg_match('#^/flights/(\d+)$#', $path, $matches)) {
    $flightNumber = (int)$matches[1];

    foreach ($flights as $flight) {
        if ($flight['flightNumber'] === $flightNumber) {
            echo json_encode($flight);
            exit;

        }
    }
    
    http_response_code(404);
    echo json_encode([
        'error' => 'Flight not found',
        'flightNumber' => $flightNumber,
    ]);
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
