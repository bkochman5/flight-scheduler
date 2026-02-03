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

$stateFile = __DIR__ . '/../data/state.json';

function loadState(string $filePath): array {
    if (!file_exists($filePath)) {
        return [];
    }
    $json = file_get_contents($filePath);
    $data = json_decode($json, true);
    return is_array($data) ? $data : [];
}

function saveState(string $filePath, array $state): void {
    file_put_contents($filePath, json_encode($state, JSON_PRETTY_PRINT));
}


// $flightState = [
//     'flightNumber' => 101,
//     'economySeats' => 2,
//     'booked' => [],
//     'waitlist' => [],
// ];


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

if ($path === '/flights/101/book' && $_SERVER['REQUEST_METHOD'] === 'POST') {
    $passengerName = $_POST['name'] ?? null;

    if (!$passengerName) {
        http_response_code(400);
        echo json_encode(['error' => 'Passenger name required']);
        exit;
    }

    $state = loadState($stateFile);

    if (!isset($state['101'])) {
        $state['101'] = [
            'economySeats' => 2,
            'booked' => [],
            'waitlist' => [],
        ];
    }

    // Prevent duplicates 
    if (in_array($passengerName, $state['101']['booked'], true) || in_array($passengerName, $state['101']['waitlist'], true)) {
        http_response_code(409);
        echo json_encode(['error' => 'Passenger already exists in booking or waitlist']);
        exit;
    }

    if (count($state['101']['booked']) < $state['101']['economySeats']) {
        $state['101']['booked'][] = $passengerName;
        saveState($stateFile, $state);

        echo json_encode([
            'status' => 'booked',
            'passenger' => $passengerName,
            'seatNumber' => count($state['101']['booked']),
        ]);
        exit;
    }

    $state['101']['waitlist'][] = $passengerName;
    saveState($stateFile, $state);

    echo json_encode([
        'status' => 'waitlisted',
        'passenger' => $passengerName,
        'position' => count($state['101']['waitlist']),
    ]);
    exit;
}

if ($path === '/flights/101/state' && $_SERVER['REQUEST_METHOD'] === 'GET') {
    $state = loadState($stateFile);

    echo json_encode([
        'flightNumber' => 101,
        'state' => $state['101'] ?? null,
    ]);
    exit;
}

if ($path === '/flights/101/cancel' && $_SERVER['REQUEST_METHOD'] === 'POST') {
    $passengerName = $_POST['name'] ?? null;

    if (!$passengerName) {
        http_response_code(400);
        echo json_encode(['error' => 'Passenger name required']);
        exit;
    }

    $state = loadState($stateFile);

    if (!isset($state['101'])) {
        http_response_code(404);
        echo json_encode(['error' => 'Flight state not found']);
        exit;
    }

    $booked = $state['101']['booked'];
    $waitlist = $state['101']['waitlist'];

    // Find passenger in booked
    $index = array_search($passengerName, $booked, true);

    if ($index === false) {
        http_response_code(404);
        echo json_encode(['error' => 'Passenger not found in booked list']);
        exit;
    }

    // Remove passenger from booked
    array_splice($booked, $index, 1);

    $movedFromWaitlist = null;

    // Auto-move next passenger from waitlist if someone is waiting
    if (count($waitlist) > 0) {
        $movedFromWaitlist = array_shift($waitlist); // FIFO
        $booked[] = $movedFromWaitlist;
    }

    // Save back
    $state['101']['booked'] = $booked;
    $state['101']['waitlist'] = $waitlist;
    saveState($stateFile, $state);

    echo json_encode([
        'status' => 'cancelled',
        'cancelledPassenger' => $passengerName,
        'movedFromWaitlist' => $movedFromWaitlist,
        'booked' => $booked,
        'waitlist' => $waitlist,
    ]);
    exit;
}


http_response_code(404);
echo json_encode([
    'error' => 'Not Found',
    'path' => $path,
]);
