<?php
// Single endpoint CRUD for table `vehicleinformation`.

declare(strict_types=1);

header('Content-Type: application/json; charset=utf-8');
header('Access-Control-Allow-Origin: *');
header('Access-Control-Allow-Methods: GET, POST, OPTIONS');
header('Access-Control-Allow-Headers: Content-Type, Authorization');

if ($_SERVER['REQUEST_METHOD'] === 'OPTIONS') {
    http_response_code(200);
    echo json_encode(['status' => 'success']);
    exit;
}

function respond(int $code, array $payload): void
{
    http_response_code($code);
    echo json_encode($payload);
    exit;
}

function envOrDefault(string $key, string $default = ''): string
{
    $value = getenv($key);
    return $value !== false ? (string)$value : $default;
}

function readInput(): array
{
    $input = $_POST;

    $raw = file_get_contents('php://input');
    if ($raw !== false && trim($raw) !== '') {
        $json = json_decode($raw, true);
        if (is_array($json)) {
            $input = array_merge($input, $json);
        }
    }

    if ($_SERVER['REQUEST_METHOD'] === 'GET') {
        $input = array_merge($input, $_GET);
    }

    return $input;
}

function cleanString($value): ?string
{
    if ($value === null) {
        return null;
    }

    $trimmed = trim((string)$value);
    return $trimmed === '' ? null : $trimmed;
}

function cleanInt($value): ?int
{
    if ($value === null || $value === '') {
        return null;
    }

    if (is_numeric($value)) {
        return (int)$value;
    }

    return null;
}

function cleanYear($value): ?string
{
    $text = preg_replace('/[^0-9]/', '', (string)$value);
    if (strlen($text) !== 4) {
        return null;
    }

    return $text;
}

function validateEnum(?string $value, array $allowed, string $field): string
{
    if ($value === null) {
        return $allowed[0];
    }

    if (!in_array($value, $allowed, true)) {
        respond(422, [
            'status' => 'error',
            'message' => "Invalid {$field} value.",
        ]);
    }

    return $value;
}

$host = envOrDefault('DB_HOST', envOrDefault('MYSQL_HOST', '127.0.0.1'));
$db = envOrDefault('DB_NAME', envOrDefault('MYSQL_DATABASE', 'rapidrepairs'));
$user = envOrDefault('DB_USER', envOrDefault('MYSQL_USER', 'root'));
$pass = envOrDefault('DB_PASS', envOrDefault('MYSQL_PASSWORD', ''));
$port = envOrDefault('DB_PORT', '3306');

try {
    $pdo = new PDO(
        "mysql:host={$host};port={$port};dbname={$db};charset=utf8mb4",
        $user,
        $pass,
        [
            PDO::ATTR_ERRMODE => PDO::ERRMODE_EXCEPTION,
            PDO::ATTR_DEFAULT_FETCH_MODE => PDO::FETCH_ASSOC,
        ]
    );
} catch (Throwable $e) {
    respond(500, [
        'status' => 'error',
        'message' => 'Database connection failed.',
    ]);
}

$input = readInput();
$method = $_SERVER['REQUEST_METHOD'];
$action = strtolower((string)($input['action'] ?? ($method === 'GET' ? 'list' : 'create')));

$allowedFuel = ['Gasoline', 'Diesel', 'Electric', 'Hybrid'];
$allowedTransmission = ['Manual', 'Automatic', 'CVT', 'DCT', 'AMT'];
$allowedStatus = ['Active', 'Inactive'];

try {
    if ($action === 'list') {
        $tenantID = cleanInt($input['tenantID'] ?? null);
        $userId = cleanInt($input['user_id'] ?? null);

        if ($tenantID === null || $userId === null) {
            respond(422, [
                'status' => 'error',
                'message' => 'tenantID and user_id are required.',
            ]);
        }

        $stmt = $pdo->prepare(
            'SELECT vehicle_id, tenantID, user_id, brand, model, year_model, fuel_type, transmission_type, engine_number, mileage_km, vin_number, plate_number, color, status, date_added
             FROM vehicleinformation
             WHERE tenantID = :tenantID AND user_id = :user_id
             ORDER BY vehicle_id DESC'
        );
        $stmt->execute([
            ':tenantID' => $tenantID,
            ':user_id' => $userId,
        ]);

        respond(200, [
            'status' => 'success',
            'vehicles' => $stmt->fetchAll(),
        ]);
    }

    if ($action === 'create') {
        $tenantID = cleanInt($input['tenantID'] ?? null);
        $userId = cleanInt($input['user_id'] ?? null);
        $brand = cleanString($input['brand'] ?? null);
        $model = cleanString($input['model'] ?? null);
        $yearModel = cleanYear($input['year_model'] ?? null);
        $fuelType = validateEnum(cleanString($input['fuel_type'] ?? null), $allowedFuel, 'fuel_type');
        $transmissionType = validateEnum(cleanString($input['transmission_type'] ?? null), $allowedTransmission, 'transmission_type');
        $engineNumber = cleanString($input['engine_number'] ?? null);
        $mileageKm = cleanInt($input['mileage_km'] ?? null);
        $vinNumber = cleanString($input['vin_number'] ?? null);
        $plateNumber = cleanString($input['plate_number'] ?? null);
        $color = cleanString($input['color'] ?? null);
        $status = validateEnum(cleanString($input['status'] ?? null), $allowedStatus, 'status');
        $dateAdded = cleanString($input['date_added'] ?? null);

        if ($tenantID === null || $userId === null || $brand === null || $model === null || $plateNumber === null) {
            respond(422, [
                'status' => 'error',
                'message' => 'tenantID, user_id, brand, model, and plate_number are required.',
            ]);
        }

        $sql = 'INSERT INTO vehicleinformation (tenantID, user_id, brand, model, year_model, fuel_type, transmission_type, engine_number, mileage_km, vin_number, plate_number, color, status, date_added)
                VALUES (:tenantID, :user_id, :brand, :model, :year_model, :fuel_type, :transmission_type, :engine_number, :mileage_km, :vin_number, :plate_number, :color, :status, COALESCE(:date_added, NOW()))';

        $stmt = $pdo->prepare($sql);
        $stmt->execute([
            ':tenantID' => $tenantID,
            ':user_id' => $userId,
            ':brand' => $brand,
            ':model' => $model,
            ':year_model' => $yearModel,
            ':fuel_type' => $fuelType,
            ':transmission_type' => $transmissionType,
            ':engine_number' => $engineNumber,
            ':mileage_km' => $mileageKm,
            ':vin_number' => $vinNumber,
            ':plate_number' => $plateNumber,
            ':color' => $color,
            ':status' => $status,
            ':date_added' => $dateAdded,
        ]);

        $vehicleId = (int)$pdo->lastInsertId();

        $fetchStmt = $pdo->prepare(
            'SELECT vehicle_id, tenantID, user_id, brand, model, year_model, fuel_type, transmission_type, engine_number, mileage_km, vin_number, plate_number, color, status, date_added
             FROM vehicleinformation
             WHERE vehicle_id = :vehicle_id LIMIT 1'
        );
        $fetchStmt->execute([':vehicle_id' => $vehicleId]);

        respond(201, [
            'status' => 'success',
            'vehicle' => $fetchStmt->fetch(),
        ]);
    }

    if ($action === 'update') {
        $vehicleId = cleanInt($input['vehicle_id'] ?? null);
        $tenantID = cleanInt($input['tenantID'] ?? null);
        $userId = cleanInt($input['user_id'] ?? null);

        if ($vehicleId === null || $tenantID === null || $userId === null) {
            respond(422, [
                'status' => 'error',
                'message' => 'vehicle_id, tenantID, and user_id are required.',
            ]);
        }

        $fieldMap = [
            'brand' => cleanString($input['brand'] ?? null),
            'model' => cleanString($input['model'] ?? null),
            'year_model' => array_key_exists('year_model', $input) ? cleanYear($input['year_model']) : null,
            'fuel_type' => array_key_exists('fuel_type', $input) ? validateEnum(cleanString($input['fuel_type']), $allowedFuel, 'fuel_type') : null,
            'transmission_type' => array_key_exists('transmission_type', $input) ? validateEnum(cleanString($input['transmission_type']), $allowedTransmission, 'transmission_type') : null,
            'engine_number' => array_key_exists('engine_number', $input) ? cleanString($input['engine_number']) : null,
            'mileage_km' => array_key_exists('mileage_km', $input) ? cleanInt($input['mileage_km']) : null,
            'vin_number' => array_key_exists('vin_number', $input) ? cleanString($input['vin_number']) : null,
            'plate_number' => array_key_exists('plate_number', $input) ? cleanString($input['plate_number']) : null,
            'color' => array_key_exists('color', $input) ? cleanString($input['color']) : null,
            'status' => array_key_exists('status', $input) ? validateEnum(cleanString($input['status']), $allowedStatus, 'status') : null,
            'date_added' => array_key_exists('date_added', $input) ? cleanString($input['date_added']) : null,
        ];

        $updates = [];
        $params = [
            ':vehicle_id' => $vehicleId,
            ':tenantID' => $tenantID,
            ':user_id' => $userId,
        ];

        foreach ($fieldMap as $column => $value) {
            if (!array_key_exists($column, $input) && !in_array($column, ['year_model', 'fuel_type', 'transmission_type', 'engine_number', 'mileage_km', 'vin_number', 'plate_number', 'color', 'status', 'date_added'], true)) {
                continue;
            }

            if (array_key_exists($column, $input)) {
                $updates[] = "{$column} = :{$column}";
                $params[":{$column}"] = $value;
            }
        }

        // Backward compatibility for updates array keys, ensure columns included when key exists in request.
        foreach (['year_model', 'fuel_type', 'transmission_type', 'engine_number', 'mileage_km', 'vin_number', 'plate_number', 'color', 'status', 'date_added'] as $column) {
            if (array_key_exists($column, $input) && !in_array("{$column} = :{$column}", $updates, true)) {
                $updates[] = "{$column} = :{$column}";
                $params[":{$column}"] = $fieldMap[$column];
            }
        }

        if (count($updates) === 0) {
            respond(422, [
                'status' => 'error',
                'message' => 'No fields provided for update.',
            ]);
        }

        $sql = 'UPDATE vehicleinformation SET ' . implode(', ', $updates) . ' WHERE vehicle_id = :vehicle_id AND tenantID = :tenantID AND user_id = :user_id';
        $stmt = $pdo->prepare($sql);
        $stmt->execute($params);

        if ($stmt->rowCount() === 0) {
            respond(404, [
                'status' => 'error',
                'message' => 'Vehicle not found or no changes made.',
            ]);
        }

        $fetchStmt = $pdo->prepare(
            'SELECT vehicle_id, tenantID, user_id, brand, model, year_model, fuel_type, transmission_type, engine_number, mileage_km, vin_number, plate_number, color, status, date_added
             FROM vehicleinformation
             WHERE vehicle_id = :vehicle_id LIMIT 1'
        );
        $fetchStmt->execute([':vehicle_id' => $vehicleId]);

        respond(200, [
            'status' => 'success',
            'vehicle' => $fetchStmt->fetch(),
        ]);
    }

    if ($action === 'delete') {
        $vehicleId = cleanInt($input['vehicle_id'] ?? null);
        $tenantID = cleanInt($input['tenantID'] ?? null);
        $userId = cleanInt($input['user_id'] ?? null);

        if ($vehicleId === null || $tenantID === null || $userId === null) {
            respond(422, [
                'status' => 'error',
                'message' => 'vehicle_id, tenantID, and user_id are required.',
            ]);
        }

        $stmt = $pdo->prepare(
            'DELETE FROM vehicleinformation WHERE vehicle_id = :vehicle_id AND tenantID = :tenantID AND user_id = :user_id'
        );
        $stmt->execute([
            ':vehicle_id' => $vehicleId,
            ':tenantID' => $tenantID,
            ':user_id' => $userId,
        ]);

        if ($stmt->rowCount() === 0) {
            respond(404, [
                'status' => 'error',
                'message' => 'Vehicle not found.',
            ]);
        }

        respond(200, [
            'status' => 'success',
            'message' => 'Vehicle deleted.',
        ]);
    }

    respond(405, [
        'status' => 'error',
        'message' => 'Unsupported action.',
    ]);
} catch (Throwable $e) {
    respond(500, [
        'status' => 'error',
        'message' => 'Server error: ' . $e->getMessage(),
    ]);
}
