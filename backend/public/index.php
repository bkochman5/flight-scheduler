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

function isValidClass(string $class): bool {
    return in_array($class, ['first', 'business', 'economy'], true);
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
    $class = $_POST['class'] ?? 'economy';

    if (!$passengerName) {
        http_response_code(400);
        echo json_encode(['error' => 'Passenger name required']);
        exit;
    }
    if (!isValidClass($class)) {
        http_response_code(400);
        echo json_encode(['error' => 'Invalid class', 'allowed' => ['first','business','economy']]);
        exit;
    }

    $state = loadState($stateFile);

    if (!isset($state['101']['classes'][$class])) {
        http_response_code(404);
        echo json_encode(['error' => 'Class not found in state', 'class' => $class]);
        exit;
    }

    $classState = $state['101']['classes'][$class];
    $seats = $classState['seats'];
    $booked = $classState['booked'];
    $waitlist = $classState['waitlist'];

    // Prevent duplicates across this class
    if (in_array($passengerName, array_values($booked), true) || in_array($passengerName, $waitlist, true)) {
        http_response_code(409);
        echo json_encode(['error' => 'Passenger already exists in this class']);
        exit;
    }

    // Find first free seat
    $assignedSeat = null;
    foreach ($seats as $seatNumber) {
        if (!isset($booked[(string)$seatNumber])) {
            $assignedSeat = $seatNumber;
            break;
        }
    }

    if ($assignedSeat !== null) {
        $booked[(string)$assignedSeat] = $passengerName;
        $state['101']['classes'][$class]['booked'] = $booked;
        saveState($stateFile, $state);

        echo json_encode([
            'status' => 'booked',
            'class' => $class,
            'passenger' => $passengerName,
            'seatNumber' => $assignedSeat,
        ]);
        exit;
    }

    // No free seats -> waitlist
    $waitlist[] = $passengerName;
    $state['101']['classes'][$class]['waitlist'] = $waitlist;
    saveState($stateFile, $state);

    echo json_encode([
        'status' => 'waitlisted',
        'class' => $class,
        'passenger' => $passengerName,
        'position' => count($waitlist),
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
    $class = $_POST['class'] ?? 'economy';

    if (!$passengerName) {
        http_response_code(400);
        echo json_encode(['error' => 'Passenger name required']);
        exit;
    }
    if (!isValidClass($class)) {
        http_response_code(400);
        echo json_encode(['error' => 'Invalid class', 'allowed' => ['first','business','economy']]);
        exit;
    }

    $state = loadState($stateFile);

    if (!isset($state['101']['classes'][$class])) {
        http_response_code(404);
        echo json_encode(['error' => 'Class not found in state', 'class' => $class]);
        exit;
    }

    $classState = $state['101']['classes'][$class];
    $seats = $classState['seats'];
    $booked = $classState['booked'];
    $waitlist = $classState['waitlist'];

    // Find which seat the passenger occupies
    $seatToFree = null;
    foreach ($booked as $seatNumberStr => $name) {
        if ($name === $passengerName) {
            $seatToFree = (int)$seatNumberStr;
            break;
        }
    }

    if ($seatToFree === null) {
        http_response_code(404);
        echo json_encode(['error' => 'Passenger not found in booked list', 'class' => $class]);
        exit;
    }

    unset($booked[(string)$seatToFree]);

    $movedFromWaitlist = null;

    // Auto-move FIFO: assign next waiting passenger to the freed seat
    if (count($waitlist) > 0) {
        $movedFromWaitlist = array_shift($waitlist);
        $booked[(string)$seatToFree] = $movedFromWaitlist;
    }

    $state['101']['classes'][$class]['booked'] = $booked;
    $state['101']['classes'][$class]['waitlist'] = $waitlist;
    saveState($stateFile, $state);

    echo json_encode([
        'status' => 'cancelled',
        'class' => $class,
        'cancelledPassenger' => $passengerName,
        'freedSeat' => $seatToFree,
        'movedFromWaitlist' => $movedFromWaitlist,
    ]);
    exit;
}


if ($path === '/passengers/status' && $_SERVER['REQUEST_METHOD'] === 'GET') {
    $name = $_GET['name'] ?? null;

    if (!$name) {
        http_response_code(400);
        echo json_encode(['error' => 'Query param "name" is required']);
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

    $bookedIndex = array_search($name, $booked, true);
    if ($bookedIndex !== false) {
        echo json_encode([
            'name' => $name,
            'status' => 'booked',
            'flightNumber' => 101,
            'seatNumber' => $bookedIndex + 1,
        ]);
        exit;
    }

    $waitIndex = array_search($name, $waitlist, true);
    if ($waitIndex !== false) {
        echo json_encode([
            'name' => $name,
            'status' => 'waitlisted',
            'flightNumber' => 101,
            'position' => $waitIndex + 1,
        ]);
        exit;
    }

    http_response_code(404);
    echo json_encode([
        'name' => $name,
        'status' => 'not_found',
        'message' => 'Passenger not found in booking or waitlist',
    ]);
    exit;
}

if ($path === '/flights/101/info' && $_SERVER['REQUEST_METHOD'] === 'GET') {
    $state = loadState($stateFile);

    $flightMeta = null;
    foreach ($flights as $f) {
        if ($f['flightNumber'] === 101) { $flightMeta = $f; break; }
    }
    if ($flightMeta === null || !isset($state['101']['classes'])) {
        http_response_code(404);
        echo json_encode(['error' => 'Flight not found or state missing']);
        exit;
    }

    $classesOut = [];

    foreach (['first','business','economy'] as $class) {
        $classState = $state['101']['classes'][$class];
        $seats = $classState['seats'];
        $booked = $classState['booked'];
        $waitlist = $classState['waitlist'];

        $seatMap = [];
        foreach ($seats as $seatNumber) {
            $seatMap[] = [
                'seatNumber' => $seatNumber,
                'class' => $class,
                'passenger' => $booked[(string)$seatNumber] ?? null,
            ];
        }

        $classesOut[$class] = [
            'seats' => $seatMap,
            'waitlist' => $waitlist,
        ];
    }

    echo json_encode([
        'flight' => [
            'flightNumber' => $flightMeta['flightNumber'],
            'departureAirport' => $flightMeta['departureAirport'],
            'departureDate' => $flightMeta['departureDate'],
            'arrivalAirport' => $flightMeta['arrivalAirport'],
        ],
        'classes' => $classesOut,
    ]);
    exit;
}



http_response_code(404);
echo json_encode([
    'error' => 'Not Found',
    'path' => $path,
]);
