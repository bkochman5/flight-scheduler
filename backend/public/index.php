<?php
declare(strict_types=1);

header('Access-Control-Allow-Origin: http://localhost:5173');
header('Access-Control-Allow-Methods: GET, POST, OPTIONS');
header('Access-Control-Allow-Headers: Content-Type');

if ($_SERVER['REQUEST_METHOD'] === 'OPTIONS') {
    http_response_code(204);
    exit;
}

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

/**
 * Seat ranges (simple demo version)
 */
$seatRanges = [
    'first' => range(1, 5),
    'business' => range(6, 15),
    'economy' => range(16, 35),
];

$initialState = [
    "101" => [
        "first" => ["booked" => [], "waitlist" => []],
        "business" => ["booked" => [], "waitlist" => []],
        "economy" => ["booked" => [], "waitlist" => []],
    ],
    "202" => [
        "first" => ["booked" => [], "waitlist" => []],
        "business" => ["booked" => [], "waitlist" => []],
        "economy" => ["booked" => [], "waitlist" => []],
    ],
];

function loadState(string $filePath): array {
    if (!file_exists($filePath)) return [];
    $json = file_get_contents($filePath);
    $data = json_decode($json, true);
    return is_array($data) ? $data : [];
}

function saveState(string $filePath, array $state): void {
    @mkdir(dirname($filePath), 0777, true);
    file_put_contents($filePath, json_encode($state, JSON_PRETTY_PRINT));
}

function isValidClass(string $class): bool {
    return in_array($class, ['first', 'business', 'economy'], true);
}

function findFlightByNumber(array $flights, int $flightNumber): ?array {
    foreach ($flights as $f) {
        if ((int)($f['flightNumber'] ?? -1) === $flightNumber) return $f;
    }
    return null;
}

/* ---------- Sorting + searching (your algorithms) ---------- */
function mergeSortFlights(array $flights, string $by): array {
    $n = count($flights);
    if ($n <= 1) return $flights;

    $mid = intdiv($n, 2);
    $left = array_slice($flights, 0, $mid);
    $right = array_slice($flights, $mid);

    $leftSorted = mergeSortFlights($left, $by);
    $rightSorted = mergeSortFlights($right, $by);

    return mergeFlights($leftSorted, $rightSorted, $by);
}

function mergeFlights(array $left, array $right, string $by): array {
    $result = [];
    $i = 0; $j = 0;

    while ($i < count($left) && $j < count($right)) {
        $a = $left[$i][$by];
        $b = $right[$j][$by];

        if ($a <= $b) { $result[] = $left[$i]; $i++; }
        else { $result[] = $right[$j]; $j++; }
    }

    while ($i < count($left)) { $result[] = $left[$i]; $i++; }
    while ($j < count($right)) { $result[] = $right[$j]; $j++; }

    return $result;
}

function binarySearchFlightByNumber(array $sortedFlights, int $target): ?array {
    $low = 0;
    $high = count($sortedFlights) - 1;

    while ($low <= $high) {
        $mid = intdiv($low + $high, 2);
        $midVal = (int)$sortedFlights[$mid]['flightNumber'];

        if ($midVal === $target) return $sortedFlights[$mid];
        if ($midVal < $target) $low = $mid + 1;
        else $high = $mid - 1;
    }
    return null;
}

/* -------------------- Router -------------------- */
$path = parse_url($_SERVER['REQUEST_URI'], PHP_URL_PATH);

/* Debug endpoints */
if ($path === '/debug/statepath') {
    echo json_encode([
        "stateFile" => $stateFile,
        "exists" => file_exists($stateFile),
        "cwd" => getcwd(),
        "dir" => __DIR__,
    ]);
    exit;
}

if ($path === '/debug/state') {
    $state = loadState($stateFile);
    echo json_encode([
        "keys" => array_keys($state),
        "state101_exists" => isset($state["101"]),
        "state202_exists" => isset($state["202"]),
        "state101" => $state["101"] ?? null,
    ], JSON_PRETTY_PRINT);
    exit;
}

if ($path === '/' || $path === '') {
    echo json_encode([
        'message' => 'Flight Scheduler API',
        'endpoints' => [
            '/health', '/version', '/flights',
            '/flights/{id}/info',
            '/flights/{id}/book (POST)',
            '/flights/{id}/cancel (POST)',
            '/passengers/status?name=...',
            '/reset (POST)'
        ],
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

if ($path === '/reset' && $_SERVER['REQUEST_METHOD'] === 'POST') {
    saveState($stateFile, $initialState);
    echo json_encode(["status" => "reset"]);
    exit;
}

if ($path === '/flights') {
    echo json_encode($flights);
    exit;
}

/* Single flight meta */
if (preg_match('#^/flights/(\d+)$#', $path, $m) && $_SERVER['REQUEST_METHOD'] === 'GET') {
    $flightNumber = (int)$m[1];
    $flight = findFlightByNumber($flights, $flightNumber);

    if (!$flight) {
        http_response_code(404);
        echo json_encode(['error' => 'Flight not found', 'flightNumber' => $flightNumber]);
        exit;
    }

    echo json_encode($flight);
    exit;
}

/* Flight info (seat map) */
if (preg_match('#^/flights/(\d+)/info$#', $path, $m) && $_SERVER['REQUEST_METHOD'] === 'GET') {
    $flightNumber = (int)$m[1];
    $flightKey = (string)$flightNumber;

    $flightMeta = findFlightByNumber($flights, $flightNumber);
    $state = loadState($stateFile);

    if ($flightMeta === null || !isset($state[$flightKey])) {
        http_response_code(404);
        echo json_encode(['error' => 'Flight not found or state missing']);
        exit;
    }

    $classesOut = [];
    foreach (['first', 'business', 'economy'] as $class) {
        $booked = $state[$flightKey][$class]['booked'] ?? [];
        $waitlist = $state[$flightKey][$class]['waitlist'] ?? [];

        $seatMap = [];
        foreach ($seatRanges[$class] as $seatNumber) {
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

/* Book seat */
if (preg_match('#^/flights/(\d+)/book$#', $path, $m) && $_SERVER['REQUEST_METHOD'] === 'POST') {
    $flightNumber = (int)$m[1];
    $flightKey = (string)$flightNumber;

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

    if (!findFlightByNumber($flights, $flightNumber)) {
        http_response_code(404);
        echo json_encode(['error' => 'Flight not found', 'flightNumber' => $flightNumber]);
        exit;
    }

    $state = loadState($stateFile);
    if (!isset($state[$flightKey])) {
        // create state if missing
        $state[$flightKey] = [
            'first' => ['booked' => [], 'waitlist' => []],
            'business' => ['booked' => [], 'waitlist' => []],
            'economy' => ['booked' => [], 'waitlist' => []],
        ];
    }

    $booked = $state[$flightKey][$class]['booked'] ?? [];
    $waitlist = $state[$flightKey][$class]['waitlist'] ?? [];

    // prevent duplicates in this class (booked map values + waitlist)
    if (in_array($passengerName, array_values($booked), true) || in_array($passengerName, $waitlist, true)) {
        http_response_code(409);
        echo json_encode(['error' => 'Passenger already exists in this class']);
        exit;
    }

    // find first free seat in range
    $assignedSeat = null;
    foreach ($seatRanges[$class] as $seatNumber) {
        if (!isset($booked[(string)$seatNumber])) {
            $assignedSeat = $seatNumber;
            break;
        }
    }

    if ($assignedSeat !== null) {
        $booked[(string)$assignedSeat] = $passengerName;
        $state[$flightKey][$class]['booked'] = $booked;
        saveState($stateFile, $state);

        echo json_encode([
            'status' => 'booked',
            'class' => $class,
            'passenger' => $passengerName,
            'seatNumber' => $assignedSeat,
        ]);
        exit;
    }

    // no free seats -> waitlist FIFO
    $waitlist[] = $passengerName;
    $state[$flightKey][$class]['waitlist'] = $waitlist;
    saveState($stateFile, $state);

    echo json_encode([
        'status' => 'waitlisted',
        'class' => $class,
        'passenger' => $passengerName,
        'position' => count($waitlist),
    ]);
    exit;
}

/* Cancel booking */
if (preg_match('#^/flights/(\d+)/cancel$#', $path, $m) && $_SERVER['REQUEST_METHOD'] === 'POST') {
    $flightNumber = (int)$m[1];
    $flightKey = (string)$flightNumber;

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
    if (!isset($state[$flightKey][$class])) {
        http_response_code(404);
        echo json_encode(['error' => 'Flight/class state not found']);
        exit;
    }

    $booked = $state[$flightKey][$class]['booked'] ?? [];
    $waitlist = $state[$flightKey][$class]['waitlist'] ?? [];

    // find seat occupied by passenger
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
    if (count($waitlist) > 0) {
        $movedFromWaitlist = array_shift($waitlist);
        $booked[(string)$seatToFree] = $movedFromWaitlist;
    }

    $state[$flightKey][$class]['booked'] = $booked;
    $state[$flightKey][$class]['waitlist'] = $waitlist;
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

/* Passenger status (search across all flights + classes) */
if ($path === '/passengers/status' && $_SERVER['REQUEST_METHOD'] === 'GET') {
    $name = $_GET['name'] ?? null;

    if (!$name) {
        http_response_code(400);
        echo json_encode(['error' => 'Query param "name" is required']);
        exit;
    }

    $state = loadState($stateFile);

    foreach ($state as $flightKey => $flightState) {
        foreach (['first','business','economy'] as $class) {
            $booked = $flightState[$class]['booked'] ?? [];
            $waitlist = $flightState[$class]['waitlist'] ?? [];

            foreach ($booked as $seatNumberStr => $passenger) {
                if ($passenger === $name) {
                    echo json_encode([
                        'name' => $name,
                        'status' => 'booked',
                        'flightNumber' => (int)$flightKey,
                        'class' => $class,
                        'seatNumber' => (int)$seatNumberStr,
                    ]);
                    exit;
                }
            }

            $waitIndex = array_search($name, $waitlist, true);
            if ($waitIndex !== false) {
                echo json_encode([
                    'name' => $name,
                    'status' => 'waitlisted',
                    'flightNumber' => (int)$flightKey,
                    'class' => $class,
                    'position' => $waitIndex + 1,
                ]);
                exit;
            }
        }
    }

    http_response_code(404);
    echo json_encode([
        'name' => $name,
        'status' => 'not_found',
        'message' => 'Passenger not found',
    ]);
    exit;
}

/* Sorted flights */
if ($path === '/flights/sorted' && $_SERVER['REQUEST_METHOD'] === 'GET') {
    $by = $_GET['by'] ?? 'flightNumber';

    if (!in_array($by, ['flightNumber', 'departureDate'], true)) {
        http_response_code(400);
        echo json_encode(['error' => 'Invalid "by" parameter', 'allowed' => ['flightNumber','departureDate']]);
        exit;
    }

    $sorted = mergeSortFlights($flights, $by);
    echo json_encode(['sortedBy' => $by, 'flights' => $sorted]);
    exit;
}

/* Search flight (binary search after merge sort) */
if ($path === '/flights/search' && $_SERVER['REQUEST_METHOD'] === 'GET') {
    $flightNumberStr = $_GET['flightNumber'] ?? null;

    if ($flightNumberStr === null || $flightNumberStr === '') {
        http_response_code(400);
        echo json_encode(['error' => 'Query param "flightNumber" is required']);
        exit;
    }

    $target = (int)$flightNumberStr;
    $sorted = mergeSortFlights($flights, 'flightNumber');
    $found = binarySearchFlightByNumber($sorted, $target);

    if ($found === null) {
        http_response_code(404);
        echo json_encode(['error' => 'Flight not found', 'flightNumber' => $target]);
        exit;
    }

    echo json_encode(['flight' => $found]);
    exit;
}

http_response_code(404);
echo json_encode(['error' => 'Not Found', 'path' => $path]);
