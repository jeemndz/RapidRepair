<?php
/**
 * paymongo_webhook.php
 * PayMongo webhook endpoint for mobile app payments.
 *
 * Recommended webhook events:
 * - checkout_session.payment.paid
 * - checkout_session.payment.failed
 * - payment.paid
 * - payment.failed
 */

include __DIR__ . "/../db.php";

header("Content-Type: application/json");

$PAYMONGO_WEBHOOK_SECRET = "whsk_yiWLH6JVZmVGkXsHKX9fTATp"; // Add your webhook signing secret later.

function webhookLog($message, $context = null)
{
    $line = "[" . date("Y-m-d H:i:s") . "] " . $message;
    if ($context !== null) {
        $line .= " | " . json_encode($context);
    }
    file_put_contents(__DIR__ . "/paymongo_webhookAPI.log", $line . PHP_EOL, FILE_APPEND);
}

function jsonResponse($statusCode, $data)
{
    http_response_code($statusCode);
    echo json_encode($data);
    exit;
}

function verifyPaymongoSignature($payload, $signatureHeader, $secret)
{
    // Keep disabled while webhook secret is blank.
    if ($secret === '') {
        return true;
    }

    if ($signatureHeader === '') {
        return false;
    }

    $parts = [];
    foreach (explode(',', $signatureHeader) as $segment) {
        $kv = explode('=', trim($segment), 2);
        if (count($kv) === 2) {
            $parts[$kv[0]] = $kv[1];
        }
    }

    if (!isset($parts['t']) || !isset($parts['te'])) {
        return false;
    }

    $signedPayload = $parts['t'] . '.' . $payload;
    $expected = hash_hmac('sha256', $signedPayload, $secret);

    return hash_equals($expected, $parts['te']);
}

function getNested($array, $path, $default = null)
{
    $current = $array;
    foreach ($path as $key) {
        if (!is_array($current) || !array_key_exists($key, $current)) {
            return $default;
        }
        $current = $current[$key];
    }
    return $current;
}

function mapPaymentMethod($method)
{
    $method = strtolower((string)$method);

    if ($method === 'card') return 'Card';
    if ($method === 'gcash') return 'GCash';
    if ($method === 'paymaya' || $method === 'maya') return 'GCash';
    if ($method === 'dob' || $method === 'bank_transfer' || $method === 'online_banking') return 'Bank Transfer';

    return 'GCash';
}

function markPaymentPaid($conn, $paymentId, $tenantID, $userId, $amountPaid, $paymongoPaymentId, $paymongoStatus, $paymongoMethod, $checkoutId = null)
{
    $amountPaid = max(0, (float)$amountPaid);

    if ($amountPaid <= 0 || $paymentId <= 0) {
        return false;
    }

    // Lock row to avoid double-counting duplicate webhook deliveries.
    $conn->begin_transaction();

    try {
        $selectSql = "\n            SELECT payment_id, grand_total, paymentAmount, amountPaid, paymongo_payment_id\n            FROM payments\n            WHERE payment_id = ?\n        ";

        $types = "i";
        $params = [$paymentId];

        if ($tenantID > 0) {
            $selectSql .= " AND tenantID = ?";
            $types .= "i";
            $params[] = $tenantID;
        }

        if ($userId > 0) {
            $selectSql .= " AND user_id = ?";
            $types .= "i";
            $params[] = $userId;
        }

        $selectSql .= " LIMIT 1 FOR UPDATE";

        $stmt = $conn->prepare($selectSql);
        $stmt->bind_param($types, ...$params);
        $stmt->execute();
        $result = $stmt->get_result();
        $payment = $result->fetch_assoc();
        $stmt->close();

        if (!$payment) {
            $conn->rollback();
            webhookLog("Payment row not found", ["payment_id" => $paymentId]);
            return false;
        }

        // Idempotency: if the same PayMongo payment was already saved, do not add again.
        if (!empty($payment['paymongo_payment_id']) && $payment['paymongo_payment_id'] === $paymongoPaymentId) {
            $conn->commit();
            webhookLog("Duplicate webhook ignored", ["payment_id" => $paymentId, "paymongo_payment_id" => $paymongoPaymentId]);
            return true;
        }

        $grandTotal = (float)($payment['grand_total'] ?? 0);
        if ($grandTotal <= 0) {
            $grandTotal = (float)($payment['paymentAmount'] ?? 0);
        }

        $currentPaid = (float)($payment['amountPaid'] ?? 0);
        $newPaid = min($grandTotal, $currentPaid + $amountPaid);
        $newBalance = max(0, $grandTotal - $newPaid);
        $paymentStatus = $newBalance <= 0 ? 'Paid' : 'Partial';
        $dbPaymentMethod = mapPaymentMethod($paymongoMethod);

        $updateSql = "\n            UPDATE payments\n            SET\n                amountPaid = ?,\n                balance = ?,\n                paymentStatus = ?,\n                paymentMethod = ?,\n                paymentDate = NOW(),\n                paymongo_payment_id = ?,\n                paymongo_status = ?,\n                paymongo_method = ?,\n                referenceNumber = COALESCE(NULLIF(referenceNumber, ''), ?),\n                gcashReferenceNumber = CASE\n                    WHEN ? = 'gcash' THEN ?\n                    ELSE gcashReferenceNumber\n                END,\n                updated_at = NOW()\n        ";

        if ($checkoutId !== null && $checkoutId !== '') {
            $updateSql .= ", paymongo_checkout_id = ?";
        }

        $updateSql .= " WHERE payment_id = ?";

        $reference = $paymongoPaymentId;
        $lowerMethod = strtolower((string)$paymongoMethod);

        if ($checkoutId !== null && $checkoutId !== '') {
            $stmt = $conn->prepare($updateSql);
            $stmt->bind_param(
                "ddsssssssssi",
                $newPaid,
                $newBalance,
                $paymentStatus,
                $dbPaymentMethod,
                $paymongoPaymentId,
                $paymongoStatus,
                $paymongoMethod,
                $reference,
                $lowerMethod,
                $reference,
                $checkoutId,
                $paymentId
            );
        } else {
            $stmt = $conn->prepare($updateSql);
            $stmt->bind_param(
                "ddssssssssi",
                $newPaid,
                $newBalance,
                $paymentStatus,
                $dbPaymentMethod,
                $paymongoPaymentId,
                $paymongoStatus,
                $paymongoMethod,
                $reference,
                $lowerMethod,
                $reference,
                $paymentId
            );
        }

        $stmt->execute();
        $stmt->close();

        $conn->commit();
        return true;
    } catch (Throwable $e) {
        $conn->rollback();
        webhookLog("Payment update failed", ["error" => $e->getMessage()]);
        return false;
    }
}

function markPaymentFailed($conn, $paymentId, $tenantID, $userId, $paymongoPaymentId, $paymongoMethod, $checkoutId = null)
{
    if ($paymentId <= 0) return false;

    $sql = "\n        UPDATE payments\n        SET\n            paymentStatus = 'Failed',\n            paymongo_status = 'failed',\n            paymongo_payment_id = COALESCE(NULLIF(?, ''), paymongo_payment_id),\n            paymongo_method = COALESCE(NULLIF(?, ''), paymongo_method),\n            updated_at = NOW()\n    ";

    if ($checkoutId !== null && $checkoutId !== '') {
        $sql .= ", paymongo_checkout_id = ?";
    }

    $sql .= " WHERE payment_id = ?";

    $types = "ss";
    $params = [$paymongoPaymentId, $paymongoMethod];

    if ($checkoutId !== null && $checkoutId !== '') {
        $types .= "s";
        $params[] = $checkoutId;
    }

    $types .= "i";
    $params[] = $paymentId;

    if ($tenantID > 0) {
        $sql .= " AND tenantID = ?";
        $types .= "i";
        $params[] = $tenantID;
    }

    if ($userId > 0) {
        $sql .= " AND user_id = ?";
        $types .= "i";
        $params[] = $userId;
    }

    $stmt = $conn->prepare($sql);
    $stmt->bind_param($types, ...$params);
    $success = $stmt->execute();
    $stmt->close();

    return $success;
}

$payload = file_get_contents("php://input");
$signatureHeader = $_SERVER['HTTP_PAYMONGO_SIGNATURE'] ?? '';

webhookLog("Webhook received", ["signature" => $signatureHeader, "payload" => $payload]);

if (!verifyPaymongoSignature($payload, $signatureHeader, $PAYMONGO_WEBHOOK_SECRET)) {
    webhookLog("Invalid webhook signature");
    jsonResponse(401, ["status" => "error", "message" => "Invalid signature"]);
}

$event = json_decode($payload, true);

if (!is_array($event)) {
    webhookLog("Invalid JSON payload");
    jsonResponse(400, ["status" => "error", "message" => "Invalid JSON"]);
}

$eventType = getNested($event, ['data', 'attributes', 'type'], '');
$resource = getNested($event, ['data', 'attributes', 'data'], []);
$resourceId = getNested($resource, ['id'], '');
$attributes = getNested($resource, ['attributes'], []);
$metadata = getNested($attributes, ['metadata'], []);

webhookLog("Event parsed", ["event_type" => $eventType, "resource_id" => $resourceId]);

if ($eventType === 'checkout_session.payment.paid') {
    $paymentId = (int)($metadata['payment_id'] ?? 0);
    $tenantID = (int)($metadata['tenantID'] ?? 0);
    $userId = (int)($metadata['user_id'] ?? 0);
    $checkoutId = $resourceId;

    $payments = getNested($attributes, ['payments'], []);
    $firstPayment = is_array($payments) && isset($payments[0]) ? $payments[0] : [];

    $paymongoPaymentId = getNested($firstPayment, ['id'], '');
    $paymentAttributes = getNested($firstPayment, ['attributes'], []);

    $amount = ((float)getNested($paymentAttributes, ['amount'], 0)) / 100;
    $method = getNested($paymentAttributes, ['source', 'type'], '');

    if ($method === '') {
        $method = getNested($paymentAttributes, ['payment_method_details', 'type'], '');
    }

    $updated = markPaymentPaid(
        $conn,
        $paymentId,
        $tenantID,
        $userId,
        $amount,
        $paymongoPaymentId,
        'paid',
        $method,
        $checkoutId
    );

    jsonResponse(200, ["status" => $updated ? "success" : "ignored"]);
}

if ($eventType === 'checkout_session.payment.failed') {
    $paymentId = (int)($metadata['payment_id'] ?? 0);
    $tenantID = (int)($metadata['tenantID'] ?? 0);
    $userId = (int)($metadata['user_id'] ?? 0);
    $checkoutId = $resourceId;

    $updated = markPaymentFailed($conn, $paymentId, $tenantID, $userId, '', '', $checkoutId);

    jsonResponse(200, ["status" => $updated ? "success" : "ignored"]);
}

// Backup support if PayMongo sends direct payment events.
if ($eventType === 'payment.paid') {
    $paymentId = (int)($metadata['payment_id'] ?? 0);
    $tenantID = (int)($metadata['tenantID'] ?? 0);
    $userId = (int)($metadata['user_id'] ?? 0);
    $paymongoPaymentId = $resourceId;
    $amount = ((float)getNested($attributes, ['amount'], 0)) / 100;
    $method = getNested($attributes, ['source', 'type'], '');

    $updated = markPaymentPaid(
        $conn,
        $paymentId,
        $tenantID,
        $userId,
        $amount,
        $paymongoPaymentId,
        'paid',
        $method,
        null
    );

    jsonResponse(200, ["status" => $updated ? "success" : "ignored"]);
}

if ($eventType === 'payment.failed') {
    $paymentId = (int)($metadata['payment_id'] ?? 0);
    $tenantID = (int)($metadata['tenantID'] ?? 0);
    $userId = (int)($metadata['user_id'] ?? 0);
    $paymongoPaymentId = $resourceId;
    $method = getNested($attributes, ['source', 'type'], '');

    $updated = markPaymentFailed($conn, $paymentId, $tenantID, $userId, $paymongoPaymentId, $method, null);

    jsonResponse(200, ["status" => $updated ? "success" : "ignored"]);
}

webhookLog("Unhandled event ignored", ["event_type" => $eventType]);
jsonResponse(200, ["status" => "ignored", "event_type" => $eventType]);
