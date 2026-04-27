<?php
/**
 * Client Application Helper Functions
 */

function getTenantDetails($conn, $tenantID)
{
    if (!$conn || !is_numeric($tenantID)) {
        return null;
    }

    $query = "SELECT * FROM owners WHERE tenantID = '" . mysqli_real_escape_string($conn, (string)$tenantID) . "' LIMIT 1";
    $result = mysqli_query($conn, $query);

    if (!$result || mysqli_num_rows($result) === 0) {
        return null;
    }

    return mysqli_fetch_assoc($result);
}

function loadSubscriptionPlans($conn)
{
    $plans = [];

    if (!subscriptionPlansTableExists($conn)) {
        return $plans;
    }

    $sql = "SELECT plan_id, plan_code, plan_name, monthly_price, plan_features, is_active 
            FROM subscription_plans 
            WHERE is_active = 1 
            ORDER BY monthly_price ASC, plan_name ASC";

    $result = mysqli_query($conn, $sql);
    if (!$result) {
        return $plans;
    }

    while ($row = mysqli_fetch_assoc($result)) {
        $planName = trim((string) ($row['plan_name'] ?? ''));
        if ($planName === '') {
            continue;
        }

        $planCode = strtolower(trim((string) ($row['plan_code'] ?? '')));
        if ($planCode === '') {
            $planCode = normalizePlanKey($planName);
        }

        // Parse plan features - try JSON first, fall back to newline/comma separated
        $features = [];
        $rawFeatures = trim((string) ($row['plan_features'] ?? ''));
        if (!empty($rawFeatures)) {
            $decoded = json_decode($rawFeatures, true);
            if (is_array($decoded)) {
                $features = $decoded;
            } else {
                // Fall back to splitting by newline or comma
                $features = preg_split('/[\r\n,]+/', $rawFeatures);
                $features = array_map('trim', $features);
                $features = array_filter($features);
            }
        }

        $plans[] = [
            'plan_id' => (int) ($row['plan_id'] ?? 0),
            'plan_code' => $planCode,
            'plan_name' => $planName,
            'monthly_price' => (float) ($row['monthly_price'] ?? 0),
            'plan_features' => $features,
            'is_active' => (int) ($row['is_active'] ?? 0)
        ];
    }

    return $plans;
}

function getPlanByCode($conn, $planCode)
{
    if (!$conn || empty($planCode)) {
        return null;
    }

    $normalizedCode = strtolower(trim((string)$planCode));
    
    if (!subscriptionPlansTableExists($conn)) {
        return null;
    }

    $sql = "SELECT plan_id, plan_code, plan_name, monthly_price, plan_features, is_active 
            FROM subscription_plans 
            WHERE LOWER(plan_code) = '" . mysqli_real_escape_string($conn, $normalizedCode) . "' 
            AND is_active = 1 
            LIMIT 1";

    $result = mysqli_query($conn, $sql);
    if (!$result || mysqli_num_rows($result) === 0) {
        return null;
    }

    $row = mysqli_fetch_assoc($result);
    
    // Parse plan features
    $features = [];
    $rawFeatures = trim((string) ($row['plan_features'] ?? ''));
    if (!empty($rawFeatures)) {
        $decoded = json_decode($rawFeatures, true);
        if (is_array($decoded)) {
            $features = $decoded;
        } else {
            $features = preg_split('/[\r\n,]+/', $rawFeatures);
            $features = array_map('trim', $features);
            $features = array_filter($features);
        }
    }

    return [
        'plan_id' => (int) ($row['plan_id'] ?? 0),
        'plan_code' => strtolower(trim((string) ($row['plan_code'] ?? ''))),
        'plan_name' => trim((string) ($row['plan_name'] ?? '')),
        'monthly_price' => (float) ($row['monthly_price'] ?? 0),
        'plan_features' => $features,
        'is_active' => (int) ($row['is_active'] ?? 0)
    ];
}

function subscriptionPlansTableExists($conn)
{
    $check = mysqli_query($conn, "SHOW TABLES LIKE 'subscription_plans'");
    return $check && mysqli_num_rows($check) > 0;
}

function normalizePlanKey($value)
{
    $normalized = strtolower(trim((string) $value));
    $normalized = preg_replace('/[^a-z0-9]+/', '-', $normalized);
    $normalized = trim((string) $normalized, '-');
    return $normalized === '' ? 'plan' : $normalized;
}
?>
