<?php
session_start();
include __DIR__ . '/../db.php';
include __DIR__ . '/../session_security.php';
include __DIR__ . '/access_control.php';
include __DIR__ . '/../log_helper.php';

// Check if tenant is logged in
if (!isset($_SESSION['tenantID'])) {
    header('Location: tenantlogin.php');
    exit;
}

$tenantID = (int) $_SESSION['tenantID'];

// Enforce access control for this module
enforceModuleAccess($tenantID, basename(__FILE__));

// Get accessible modules for navigation
$accessibleModules = getAccessibleModules($tenantID);
$isStaffUser = isset($_SESSION['userType']) && $_SESSION['userType'] === 'staff';

// Helper function to check if a module should be accessible
function canAccessModule($moduleFile, $accessibleModules) {
    return in_array($moduleFile, $accessibleModules);
}

// Try session slug first, then URL slug
$loginSlug = '';
if (isset($_SESSION['login_slug']) && trim((string) $_SESSION['login_slug']) !== '') {
    $loginSlug = trim((string) $_SESSION['login_slug']);
} elseif (isset($_GET['shop']) && trim((string) $_GET['shop']) !== '') {
    $loginSlug = trim((string) $_GET['shop']);
    $_SESSION['login_slug'] = $loginSlug;
}

// If still no slug, force login
if ($loginSlug === '') {
    session_unset();
    session_destroy();
    header('Location: tenantlogin.php');
    exit;
}

// Validate tenant + slug
$ownerStmt = mysqli_prepare($conn, "SELECT shopName FROM owners WHERE tenantID = ? AND login_slug = ? LIMIT 1");
mysqli_stmt_bind_param($ownerStmt, 'is', $tenantID, $loginSlug);
mysqli_stmt_execute($ownerStmt);
$ownerResult = mysqli_stmt_get_result($ownerStmt);
$owner = $ownerResult ? mysqli_fetch_assoc($ownerResult) : null;
mysqli_stmt_close($ownerStmt);

if (!$owner) {
    session_unset();
    session_destroy();
    header('Location: tenantlogin.php');
    exit;
}

// Re-store correct slug in session
$_SESSION['login_slug'] = $loginSlug;
$shopName = isset($owner['shopName']) && $owner['shopName'] !== '' ? $owner['shopName'] : 'AutoFix Pro';
$shopSlug = $loginSlug;
$shopQuery = urlencode($loginSlug);

// Keep URL consistent
$currentScript = basename($_SERVER['PHP_SELF']);
if ($_SERVER['REQUEST_METHOD'] === 'GET' && (!isset($_GET['shop']) || trim((string) $_GET['shop']) !== $loginSlug)) {
    header('Location: ' . $currentScript . '?shop=' . $shopQuery);
    exit;
}

if (isset($_GET['audit_action'])) {
    $auditAction = trim((string) $_GET['audit_action']);
    $auditAppointmentId = isset($_GET['appointment_id']) ? max(0, (int) $_GET['appointment_id']) : 0;

    if ($auditAction === 'open_create_modal') {
        log_event($conn, 'VIEW Create Appointment Modal', 'appointment', null, 'Opened create appointment modal');
    } elseif ($auditAction === 'open_review_modal' && $auditAppointmentId > 0) {
        log_event($conn, 'VIEW Review Appointment Modal', 'appointment', $auditAppointmentId, 'Opened review modal for appointment #' . $auditAppointmentId);
    }

    if (isset($_GET['audit_only']) && $_GET['audit_only'] === '1') {
        http_response_code(204);
        exit;
    }
}

if (empty($_SESSION['appointment_csrf'])) {
    $_SESSION['appointment_csrf'] = bin2hex(random_bytes(16));
}
$csrfToken = $_SESSION['appointment_csrf'];

// Get logged-in user information
$loggedInUserName = '';
$loggedInUserRole = '';
if ($_SESSION['userType'] === 'owner') {
    $loggedInUserName = isset($_SESSION['shopName']) ? $_SESSION['shopName'] : 'Shop Owner';
    $loggedInUserRole = 'Administrator';
} else {
    $loggedInUserName = (isset($_SESSION['firstName']) ? $_SESSION['firstName'] : '') . ' ' . (isset($_SESSION['lastName']) ? $_SESSION['lastName'] : '');
    $loggedInUserName = trim($loggedInUserName) ?: 'User';
    $loggedInUserRole = isset($_SESSION['userRole']) ? $_SESSION['userRole'] : 'Staff Member';
}

$allowedStatuses = ['Pending', 'Confirmed', 'In Progress', 'Completed', 'Cancelled'];
$allowedJobStatuses = ['Queued', 'In Progress', 'Diagnostics', 'Waiting for Parts', 'Quality Check', 'Ready for Pickup', 'Completed', 'Cancelled'];

// Sync repair job status to appointment status.
// When the job status changes, the appointment status follows this mapping.
$jobToAppointmentStatusMap = [
    'Queued' => 'Confirmed',
    'In Progress' => 'In Progress',
    'Diagnostics' => 'In Progress',
    'Waiting for Parts' => 'In Progress',
    'Quality Check' => 'In Progress',
    'Ready for Pickup' => 'In Progress',
    'Completed' => 'Completed',
    'Cancelled' => 'Cancelled',
];
$timeSlots = ['09:00', '10:30', '13:00', '14:30', '16:00', '17:30'];
$actionMessage = '';
$actionError = '';
$createError = '';
$showCreateForm = isset($_GET['create_appointment']);
$reviewAppointmentId = isset($_GET['review']) ? (int) $_GET['review'] : 0;
$showReviewModal = $reviewAppointmentId > 0;

$createForm = [
    'user_id' => 0,
    'vehicle_id' => 0,
    'service_ids' => [],
    'appointment_date' => date('Y-m-d'),
    'appointment_time' => '09:00',
    'status' => 'Pending',
    'notes' => '',
];

if (isset($_GET['appointment_created']) && $_GET['appointment_created'] === '1') {
    $actionMessage = 'Appointment created successfully.';
}

$reviewForm = [
    'appointment_id' => $reviewAppointmentId,
    'job_status' => 'Queued',
    'assigned_technician' => '',
    'bay_no' => '',
    'bay_no_custom' => '',
];

if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['save_review'])) {
    $postedToken = isset($_POST['csrf_token']) ? (string) $_POST['csrf_token'] : '';
    $reviewForm['appointment_id'] = isset($_POST['appointment_id']) ? (int) $_POST['appointment_id'] : 0;
    $appointmentStatus = isset($_POST['appointment_status']) ? trim((string) $_POST['appointment_status']) : '';
    $reviewForm['job_status'] = isset($_POST['job_status']) ? trim((string) $_POST['job_status']) : '';
    $reviewForm['assigned_technician'] = isset($_POST['assigned_technician']) ? trim((string) $_POST['assigned_technician']) : '';
    $reviewForm['bay_no'] = isset($_POST['bay_no']) ? trim((string) $_POST['bay_no']) : '';
    $reviewForm['bay_no_custom'] = isset($_POST['bay_no_custom']) ? trim((string) $_POST['bay_no_custom']) : '';

    $selectedBayNo = $reviewForm['bay_no'];
    if ($selectedBayNo === '__custom__') {
        $selectedBayNo = $reviewForm['bay_no_custom'];
    }

    // Appointment status is automatically synced from repair job status.
    // This prevents mismatches like repair_jobs.job_status = Completed while appointments.status is still Confirmed.
    if (in_array($reviewForm['job_status'], $allowedJobStatuses, true) && isset($jobToAppointmentStatusMap[$reviewForm['job_status']])) {
        $appointmentStatus = $jobToAppointmentStatusMap[$reviewForm['job_status']];
    }

    $reviewAppointmentId = $reviewForm['appointment_id'];

    if (!hash_equals($csrfToken, $postedToken)) {
        $actionError = 'Invalid request token. Please refresh and try again.';
    } elseif ($reviewForm['appointment_id'] <= 0) {
        $actionError = 'Invalid appointment selected for review.';
    } elseif (!in_array($appointmentStatus, $allowedStatuses, true)) {
        $actionError = 'Invalid synced appointment status selected.';
    } elseif (!in_array($reviewForm['job_status'], $allowedJobStatuses, true)) {
        $actionError = 'Invalid repair job status selected.';
    } elseif ($reviewForm['assigned_technician'] === '') {
        $actionError = 'Assigned technician is required.';
    } elseif ($selectedBayNo === '') {
        $actionError = 'Bay number is required.';
    } elseif (strlen($selectedBayNo) > 50) {
        $actionError = 'Bay number is too long.';
    }

    if ($actionError === '') {
        $validTechnicianStmt = mysqli_prepare(
            $conn,
            "SELECT username
             FROM roles
             WHERE tenantID = ?
               AND username = ?
               AND is_active = 1
               AND status = 'Active'
               AND LOWER(TRIM(role_name)) NOT IN (
                    'office staff',
                    'office admin',
                    'front desk',
                    'front desk staff',
                    'receptionist',
                    'cashier',
                    'billing staff',
                    'service advisor',
                    'manager',
                    'admin',
                    'administrator'
               )
             LIMIT 1"
        );

        if (!$validTechnicianStmt) {
            $actionError = 'Unable to validate technician.';
        } else {
            mysqli_stmt_bind_param($validTechnicianStmt, 'is', $tenantID, $reviewForm['assigned_technician']);
            mysqli_stmt_execute($validTechnicianStmt);
            $validTechResult = mysqli_stmt_get_result($validTechnicianStmt);
            if (!$validTechResult || !mysqli_fetch_assoc($validTechResult)) {
                $actionError = 'Selected technician is not valid or inactive.';
            }
            mysqli_stmt_close($validTechnicianStmt);
        }
    }

    if ($actionError === '') {
        $apptStmt = mysqli_prepare(
            $conn,
            'SELECT appointment_id, user_id, vehicle_id, total_amount FROM appointments WHERE appointment_id = ? AND tenantID = ? LIMIT 1'
        );

        if (!$apptStmt) {
            $actionError = 'Unable to validate appointment.';
        } else {
            mysqli_stmt_bind_param($apptStmt, 'ii', $reviewForm['appointment_id'], $tenantID);
            mysqli_stmt_execute($apptStmt);
            $apptResult = mysqli_stmt_get_result($apptStmt);
            $apptRow = $apptResult ? mysqli_fetch_assoc($apptResult) : null;
            mysqli_stmt_close($apptStmt);

            if (!$apptRow) {
                $actionError = 'Appointment record not found.';
            }
        }
    }

    if ($actionError === '') {
        $checkRepairStmt = mysqli_prepare(
            $conn,
            'SELECT repair_job_id FROM repair_jobs WHERE appointment_id = ? AND tenantID = ? LIMIT 1'
        );

        if (!$checkRepairStmt) {
            $actionError = 'Unable to validate repair job record.';
        } else {
            mysqli_stmt_bind_param($checkRepairStmt, 'ii', $reviewForm['appointment_id'], $tenantID);
            mysqli_stmt_execute($checkRepairStmt);
            $repairResult = mysqli_stmt_get_result($checkRepairStmt);
            $repairRow = $repairResult ? mysqli_fetch_assoc($repairResult) : null;
            mysqli_stmt_close($checkRepairStmt);

            if (mysqli_begin_transaction($conn)) {
                $saveOk = true;
                $repairJobId = 0;

                // Update appointment status first
                $updateApptStmt = mysqli_prepare(
                    $conn,
                    'UPDATE appointments SET status = ?, updated_at = NOW() WHERE appointment_id = ? AND tenantID = ? LIMIT 1'
                );
                if (!$updateApptStmt) {
                    $saveOk = false;
                    $actionError = 'Unable to update appointment status: ' . mysqli_error($conn);
                } else {
                    mysqli_stmt_bind_param($updateApptStmt, 'sii', $appointmentStatus, $reviewForm['appointment_id'], $tenantID);
                    if (!mysqli_stmt_execute($updateApptStmt)) {
                        $saveOk = false;
                        $actionError = 'Appointment status update failed: ' . mysqli_stmt_error($updateApptStmt);
                    } else {
                        log_event($conn, 'UPDATE Appointment', 'appointment', (int) $reviewForm['appointment_id'], 'Synced appointment status to ' . $appointmentStatus . ' from job status ' . $reviewForm['job_status']);
                    }
                    mysqli_stmt_close($updateApptStmt);
                }

                if ($saveOk && $repairRow) {
                    $grandTotal = (float) ($apptRow['total_amount'] ?? 0);
                    $updateRepairStmt = mysqli_prepare(
                        $conn,
                        'UPDATE repair_jobs
                         SET job_status = ?, assigned_technician = ?, bay_no = ?, grand_total = ?, updated_at = NOW()
                         WHERE repair_job_id = ? AND tenantID = ? LIMIT 1'
                    );

                    if (!$updateRepairStmt) {
                        $saveOk = false;
                        $actionError = 'Unable to update repair job details: ' . mysqli_error($conn);
                    } else {
                        $repairJobId = (int) $repairRow['repair_job_id'];
                        $bayValue = $selectedBayNo;
                        mysqli_stmt_bind_param(
                            $updateRepairStmt,
                            'sssdii',
                            $reviewForm['job_status'],
                            $reviewForm['assigned_technician'],
                            $bayValue,
                            $grandTotal,
                            $repairJobId,
                            $tenantID
                        );
                        if (!mysqli_stmt_execute($updateRepairStmt)) {
                            $saveOk = false;
                            $actionError = 'Repair job update failed: ' . mysqli_stmt_error($updateRepairStmt);
                        } else {
                            log_event($conn, 'UPDATE RepairJob', 'repair_job', $repairJobId, 'Updated job status to ' . $reviewForm['job_status']);
                        }
                        mysqli_stmt_close($updateRepairStmt);
                    }
                } elseif ($saveOk) {
                    $newJobOrderNo = generateRepairJobOrderNo($conn, $tenantID);
                    $grandTotal = (float) ($apptRow['total_amount'] ?? 0);
                    $bayValue = $selectedBayNo;
                    $insertRepairStmt = mysqli_prepare(
                        $conn,
                        'INSERT INTO repair_jobs
                        (tenantID, appointment_id, user_id, vehicle_id, job_order_no, bay_no, assigned_technician, job_status, priority, grand_total)
                         VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?, ?)'
                    );

                    if (!$insertRepairStmt) {
                        $saveOk = false;
                        $actionError = 'Unable to create repair job details: ' . mysqli_error($conn);
                    } else {
                        $defaultPriority = 'Normal';
                        mysqli_stmt_bind_param(
                            $insertRepairStmt,
                            'iiiisssssd',
                            $tenantID,
                            $reviewForm['appointment_id'],
                            $apptRow['user_id'],
                            $apptRow['vehicle_id'],
                            $newJobOrderNo,
                            $bayValue,
                            $reviewForm['assigned_technician'],
                            $reviewForm['job_status'],
                            $defaultPriority,
                            $grandTotal
                        );
                        if (!mysqli_stmt_execute($insertRepairStmt)) {
                            $saveOk = false;
                            $actionError = 'Repair job creation failed: ' . mysqli_stmt_error($insertRepairStmt);
                        } else {
                            $repairJobId = (int) mysqli_insert_id($conn);
                            log_event($conn, 'CREATE RepairJob', 'repair_job', $repairJobId, 'Created RepairJob for appointment ID: ' . (int) $reviewForm['appointment_id']);
                        }
                        mysqli_stmt_close($insertRepairStmt);
                    }
                }

                // Link appointment services to repair job (for new jobs or to ensure consistency)
                if ($saveOk && $repairJobId > 0) {
                    // Get appointment services
                    $apptServicesStmt = mysqli_prepare(
                        $conn,
                        'SELECT service_id, service_price, duration_minutes, notes FROM appointment_services WHERE appointment_id = ? AND tenantID = ?'
                    );
                    if ($apptServicesStmt) {
                        mysqli_stmt_bind_param($apptServicesStmt, 'ii', $reviewForm['appointment_id'], $tenantID);
                        mysqli_stmt_execute($apptServicesStmt);
                        $apptServicesResult = mysqli_stmt_get_result($apptServicesStmt);
                        
                        // Clear existing services if updating
                        if ($repairRow) {
                            $deleteServicesStmt = mysqli_prepare(
                                $conn,
                                'DELETE FROM repair_job_services WHERE repair_job_id = ? AND tenantID = ?'
                            );
                            if ($deleteServicesStmt) {
                                mysqli_stmt_bind_param($deleteServicesStmt, 'ii', $repairJobId, $tenantID);
                                if (mysqli_stmt_execute($deleteServicesStmt)) {
                                    log_event($conn, 'DELETE RepairJobService', 'repair_job_service', $repairJobId, 'Deleted RepairJobService records for repair job ID: ' . $repairJobId);
                                }
                                mysqli_stmt_close($deleteServicesStmt);
                            }
                        }

                        // Insert services
                        $insertJobServiceStmt = mysqli_prepare(
                            $conn,
                            'INSERT INTO repair_job_services (repair_job_id, tenantID, service_id, service_price, estimated_duration_minutes, service_status, remarks)
                             VALUES (?, ?, ?, ?, ?, "In Progress", ?)'
                        );
                        if ($insertJobServiceStmt) {
                            while ($apptServicesResult && $serviceRow = mysqli_fetch_assoc($apptServicesResult)) {
                                $serviceId = (int) ($serviceRow['service_id'] ?? 0);
                                if ($serviceId > 0) {
                                    $servicePrice = (float) ($serviceRow['service_price'] ?? 0);
                                    $estimatedDuration = isset($serviceRow['duration_minutes']) ? (int) $serviceRow['duration_minutes'] : null;
                                    $remarks = trim((string) ($serviceRow['notes'] ?? ''));
                                    $remarksValue = $remarks !== '' ? $remarks : null;

                                    mysqli_stmt_bind_param(
                                        $insertJobServiceStmt,
                                        'iiidis',
                                        $repairJobId,
                                        $tenantID,
                                        $serviceId,
                                        $servicePrice,
                                        $estimatedDuration,
                                        $remarksValue
                                    );

                                    if (!mysqli_stmt_execute($insertJobServiceStmt)) {
                                        $saveOk = false;
                                        $actionError = 'Failed to link services: ' . mysqli_stmt_error($insertJobServiceStmt);
                                        break;
                                    } else {
                                        log_event($conn, 'CREATE RepairJobService', 'repair_job_service', (int) $serviceId, 'Created RepairJobService for repair job ID: ' . $repairJobId);
                                    }
                                }
                            }
                            mysqli_stmt_close($insertJobServiceStmt);
                        }
                        mysqli_stmt_close($apptServicesStmt);
                    }
                }

                if ($saveOk && mysqli_commit($conn)) {
                    $actionMessage = 'Review details saved successfully.';
                    $showReviewModal = false;
                    $reviewAppointmentId = 0;
                    header('Location: appointmentadmin.php?shop=' . urlencode($loginSlug));
                    exit;
                } else {
                    if ($saveOk) {
                        $actionError = 'Transaction commit failed: ' . mysqli_error($conn);
                    }
                    mysqli_rollback($conn);
                    $showReviewModal = true;
                }
            } else {
                $actionError = 'Unable to start transaction: ' . mysqli_error($conn);
                $showReviewModal = true;
            }
        }
    }
}

if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['create_appointment_submit'])) {
    $showCreateForm = true;
    $postedToken = isset($_POST['csrf_token']) ? (string) $_POST['csrf_token'] : '';

    $createForm['user_id'] = isset($_POST['user_id']) ? (int) $_POST['user_id'] : 0;
    $createForm['vehicle_id'] = isset($_POST['vehicle_id']) ? (int) $_POST['vehicle_id'] : 0;
    $rawServiceIds = isset($_POST['service_ids']) && is_array($_POST['service_ids']) ? $_POST['service_ids'] : [];
    $createForm['service_ids'] = array_values(array_unique(array_filter(array_map('intval', $rawServiceIds), static fn ($id) => $id > 0)));
    $createForm['appointment_date'] = isset($_POST['appointment_date']) ? trim((string) $_POST['appointment_date']) : '';
    $createForm['appointment_time'] = isset($_POST['appointment_time']) ? trim((string) $_POST['appointment_time']) : '';
    $createForm['status'] = isset($_POST['status']) ? trim((string) $_POST['status']) : 'Pending';
    $createForm['notes'] = isset($_POST['notes']) ? trim((string) $_POST['notes']) : '';

    if (!hash_equals($csrfToken, $postedToken)) {
        $createError = 'Invalid request token. Please refresh and try again.';
    } elseif ($createForm['user_id'] <= 0 || $createForm['vehicle_id'] <= 0 || count($createForm['service_ids']) === 0) {
        $createError = 'Customer, vehicle, and at least one service are required.';
    } elseif (!in_array($createForm['status'], $allowedStatuses, true)) {
        $createError = 'Invalid appointment status selected.';
    }

    if ($createError === '') {
        $dateObj = DateTime::createFromFormat('Y-m-d', $createForm['appointment_date']);
        $timeObj = DateTime::createFromFormat('H:i', $createForm['appointment_time']);
        if (!$dateObj || $dateObj->format('Y-m-d') !== $createForm['appointment_date']) {
            $createError = 'Please provide a valid appointment date.';
        } elseif (!$timeObj || $timeObj->format('H:i') !== $createForm['appointment_time']) {
            $createError = 'Please provide a valid appointment time.';
        } elseif (!in_array($createForm['appointment_time'], $timeSlots, true)) {
            $createError = 'Please select an available appointment time slot.';
        }
    }

    if ($createError === '') {
        $customerStmt = mysqli_prepare($conn, "SELECT user_id FROM users WHERE user_id = ? AND tenantID = ? AND role = 'client' LIMIT 1");
        if (!$customerStmt) {
            $createError = 'Unable to validate customer.';
        } else {
            mysqli_stmt_bind_param($customerStmt, 'ii', $createForm['user_id'], $tenantID);
            mysqli_stmt_execute($customerStmt);
            $customerResult = mysqli_stmt_get_result($customerStmt);
            if (!$customerResult || !mysqli_fetch_assoc($customerResult)) {
                $createError = 'Selected customer was not found.';
            }
            mysqli_stmt_close($customerStmt);
        }
    }

    if ($createError === '') {
        $vehicleStmt = mysqli_prepare($conn, 'SELECT vehicle_id FROM vehicleinformation WHERE vehicle_id = ? AND user_id = ? AND tenantID = ? LIMIT 1');
        if (!$vehicleStmt) {
            $createError = 'Unable to validate vehicle.';
        } else {
            mysqli_stmt_bind_param($vehicleStmt, 'iii', $createForm['vehicle_id'], $createForm['user_id'], $tenantID);
            mysqli_stmt_execute($vehicleStmt);
            $vehicleResult = mysqli_stmt_get_result($vehicleStmt);
            if (!$vehicleResult || !mysqli_fetch_assoc($vehicleResult)) {
                $createError = 'Selected vehicle does not belong to the selected customer.';
            }
            mysqli_stmt_close($vehicleStmt);
        }
    }

    $selectedServices = [];
    $serviceTotalAmount = 0.0;
    if ($createError === '') {
        $serviceStmt = mysqli_prepare($conn, "SELECT service_id, price, duration_minutes, status FROM services WHERE service_id = ? AND tenantID = ? LIMIT 1");
        if (!$serviceStmt) {
            $createError = 'Unable to validate service.';
        } else {
            foreach ($createForm['service_ids'] as $serviceId) {
                mysqli_stmt_bind_param($serviceStmt, 'ii', $serviceId, $tenantID);
                mysqli_stmt_execute($serviceStmt);
                $serviceResult = mysqli_stmt_get_result($serviceStmt);
                $serviceRow = $serviceResult ? mysqli_fetch_assoc($serviceResult) : null;

                if (!$serviceRow) {
                    $createError = 'One or more selected services were not found.';
                    break;
                }
                if (isset($serviceRow['status']) && strtolower((string) $serviceRow['status']) !== 'active') {
                    $createError = 'One or more selected services are not active.';
                    break;
                }

                $servicePrice = (float) ($serviceRow['price'] ?? 0);
                $serviceDuration = (int) ($serviceRow['duration_minutes'] ?? 0);
                $selectedServices[] = [
                    'service_id' => (int) $serviceRow['service_id'],
                    'price' => $servicePrice,
                    'duration_minutes' => $serviceDuration,
                ];
                $serviceTotalAmount += $servicePrice;
            }
            mysqli_stmt_close($serviceStmt);
        }
    }

    if ($createError === '' && count($selectedServices) === 0) {
        $createError = 'Please select at least one valid service.';
    }

    if ($createError === '') {
        $notes = $createForm['notes'] !== '' ? $createForm['notes'] : null;
        $appointmentDate = $createForm['appointment_date'];
        $appointmentTime = $createForm['appointment_time'] . ':00';
        $totalAmount = $serviceTotalAmount;

        mysqli_begin_transaction($conn);
        $createOk = true;

        $insertAppointmentStmt = mysqli_prepare(
            $conn,
            'INSERT INTO appointments (tenantID, user_id, vehicle_id, appointment_date, appointment_time, status, notes, total_amount)
             VALUES (?, ?, ?, ?, ?, ?, ?, ?)'
        );
        if (!$insertAppointmentStmt) {
            $createOk = false;
            $createError = 'Unable to create appointment record.';
        } else {
            mysqli_stmt_bind_param(
                $insertAppointmentStmt,
                'iiissssd',
                $tenantID,
                $createForm['user_id'],
                $createForm['vehicle_id'],
                $appointmentDate,
                $appointmentTime,
                $createForm['status'],
                $notes,
                $totalAmount
            );

            if (!mysqli_stmt_execute($insertAppointmentStmt)) {
                $createOk = false;
                $createError = 'Unable to save appointment details.';
            } else {
                $newAppointmentId = (int) mysqli_insert_id($conn);
                log_event($conn, 'CREATE Appointment', 'appointment', $newAppointmentId, 'Created Appointment for user ID: ' . (int) $createForm['user_id']);
            }
            mysqli_stmt_close($insertAppointmentStmt);
        }

        if ($createOk) {
            $insertServiceStmt = mysqli_prepare(
                $conn,
                'INSERT INTO appointment_services (appointment_id, tenantID, service_id, service_price, duration_minutes, notes)
                 VALUES (?, ?, ?, ?, ?, ?)'
            );
            if (!$insertServiceStmt) {
                $createOk = false;
                $createError = 'Appointment was created, but service link could not be saved.';
            } else {
                foreach ($selectedServices as $serviceItem) {
                    $serviceId = (int) $serviceItem['service_id'];
                    $servicePrice = (float) $serviceItem['price'];
                    $serviceDuration = (int) $serviceItem['duration_minutes'];

                    mysqli_stmt_bind_param(
                        $insertServiceStmt,
                        'iiidis',
                        $newAppointmentId,
                        $tenantID,
                        $serviceId,
                        $servicePrice,
                        $serviceDuration,
                        $notes
                    );

                    if (!mysqli_stmt_execute($insertServiceStmt)) {
                        $createOk = false;
                        $createError = 'Appointment service details could not be saved.';
                        break;
                    } else {
                        log_event($conn, 'CREATE AppointmentService', 'appointment_service', (int) $serviceId, 'Created AppointmentService for appointment ID: ' . $newAppointmentId);
                    }
                }
                mysqli_stmt_close($insertServiceStmt);
            }
        }

        if ($createOk) {
            mysqli_commit($conn);
            header('Location: appointmentadmin.php?shop=' . urlencode($loginSlug) . '&appointment_created=1');
            exit;
        }

        mysqli_rollback($conn);
    }
}

if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['update_status'])) {
    $postedToken = isset($_POST['csrf_token']) ? (string) $_POST['csrf_token'] : '';
    $appointmentID = isset($_POST['appointment_id']) ? (int) $_POST['appointment_id'] : 0;
    $newStatus = isset($_POST['status']) ? (string) $_POST['status'] : '';

    if (!hash_equals($csrfToken, $postedToken)) {
        $actionError = 'Invalid request token. Please refresh and try again.';
    } elseif ($appointmentID <= 0 || !in_array($newStatus, $allowedStatuses, true)) {
        $actionError = 'Invalid status update request.';
    } else {
        $updateStmt = mysqli_prepare(
            $conn,
            "UPDATE appointments SET status = ?, updated_at = NOW() WHERE appointment_id = ? AND tenantID = ? LIMIT 1"
        );
        mysqli_stmt_bind_param($updateStmt, 'sii', $newStatus, $appointmentID, $tenantID);
        $statusUpdated = mysqli_stmt_execute($updateStmt);

        if ($statusUpdated && mysqli_stmt_affected_rows($updateStmt) >= 0) {
            log_event($conn, 'UPDATE Appointment', 'appointment', $appointmentID, 'Updated status to ' . $newStatus);
            $actionMessage = 'Appointment status updated successfully.';
        } else {
            $actionError = 'Unable to update appointment status right now.';
        }
        mysqli_stmt_close($updateStmt);
    }
}

$search = isset($_GET['search']) ? trim((string) $_GET['search']) : '';
$statusFilter = isset($_GET['status']) ? trim((string) $_GET['status']) : 'Pending';

// Appointment list table should only show active/non-cancelled appointments.
// Cancelled appointments are shown in Appointment History instead.
$listStatuses = ['Pending', 'Confirmed', 'In Progress', 'Completed', 'Cancelled'];
if (!in_array($statusFilter, array_merge(['All'], $listStatuses), true)) {
    $statusFilter = 'Pending';
}

function h($value)
{
    return htmlspecialchars((string) $value, ENT_QUOTES, 'UTF-8');
}

function generateRepairJobOrderNo(mysqli $conn, int $tenantID): string
{
    // Get the next sequential number for this tenant
    $countStmt = mysqli_prepare($conn, 'SELECT COUNT(*) as total FROM repair_jobs WHERE tenantID = ?');
    if (!$countStmt) {
        return 'RR-' . str_pad((string) random_int(1, 99999), 5, '0', STR_PAD_LEFT);
    }
    
    mysqli_stmt_bind_param($countStmt, 'i', $tenantID);
    mysqli_stmt_execute($countStmt);
    $countResult = mysqli_stmt_get_result($countStmt);
    $countRow = mysqli_fetch_assoc($countResult);
    mysqli_stmt_close($countStmt);
    
    $nextNumber = ((int) ($countRow['total'] ?? 0)) + 1;
    return 'RR-' . str_pad((string) $nextNumber, 5, '0', STR_PAD_LEFT);
}

$customerOptions = [];
$customerOptionsStmt = mysqli_prepare(
    $conn,
    "SELECT user_id, fullName, email
     FROM users
     WHERE tenantID = ? AND role = 'client'
     ORDER BY fullName ASC"
);
if ($customerOptionsStmt) {
    mysqli_stmt_bind_param($customerOptionsStmt, 'i', $tenantID);
    mysqli_stmt_execute($customerOptionsStmt);
    $customerOptionsResult = mysqli_stmt_get_result($customerOptionsStmt);
    while ($customerOptionsResult && $customerRow = mysqli_fetch_assoc($customerOptionsResult)) {
        $customerOptions[] = $customerRow;
    }
    mysqli_stmt_close($customerOptionsStmt);
}

$vehicleOptions = [];
$vehicleOptionsStmt = mysqli_prepare(
    $conn,
    "SELECT v.vehicle_id, v.user_id, v.year_model, v.brand, v.model, v.plate_number, u.fullName
     FROM vehicleinformation v
     LEFT JOIN users u ON u.user_id = v.user_id AND u.tenantID = v.tenantID
     WHERE v.tenantID = ?
     ORDER BY u.fullName ASC, v.brand ASC, v.model ASC"
);
if ($vehicleOptionsStmt) {
    mysqli_stmt_bind_param($vehicleOptionsStmt, 'i', $tenantID);
    mysqli_stmt_execute($vehicleOptionsStmt);
    $vehicleOptionsResult = mysqli_stmt_get_result($vehicleOptionsStmt);
    while ($vehicleOptionsResult && $vehicleRow = mysqli_fetch_assoc($vehicleOptionsResult)) {
        $vehicleOptions[] = $vehicleRow;
    }
    mysqli_stmt_close($vehicleOptionsStmt);
}

$serviceOptions = [];
$serviceOptionsStmt = mysqli_prepare(
    $conn,
    "SELECT service_id, service_name, price, duration_minutes
     FROM services
     WHERE tenantID = ? AND status = 'active'
     ORDER BY service_name ASC"
);
if ($serviceOptionsStmt) {
    mysqli_stmt_bind_param($serviceOptionsStmt, 'i', $tenantID);
    mysqli_stmt_execute($serviceOptionsStmt);
    $serviceOptionsResult = mysqli_stmt_get_result($serviceOptionsStmt);
    while ($serviceOptionsResult && $serviceRow = mysqli_fetch_assoc($serviceOptionsResult)) {
        $serviceOptions[] = $serviceRow;
    }
    mysqli_stmt_close($serviceOptionsStmt);
}

$reviewDetails = null;
if ($showReviewModal && $reviewAppointmentId > 0) {
    $reviewStmt = mysqli_prepare(
        $conn,
        "SELECT
            a.appointment_id,
            a.user_id,
            a.vehicle_id,
            a.appointment_date,
            a.appointment_time,
            a.status AS appt_status,
            a.notes AS appt_notes,
            a.total_amount,
            COALESCE(u.fullName, CONCAT('User #', a.user_id)) AS customer_name,
            COALESCE(u.email, '') AS customer_email,
            COALESCE(u.contactNumber, '') AS customer_phone,
            CONCAT(IFNULL(v.year_model, ''), ' ', IFNULL(v.brand, ''), ' ', IFNULL(v.model, '')) AS vehicle_name,
            IFNULL(v.plate_number, '') AS plate_number,
            COALESCE(GROUP_CONCAT(DISTINCT s.service_name ORDER BY s.service_name SEPARATOR ', '), '') AS services_list,
            rj.repair_job_id,
            rj.job_order_no,
            rj.job_status,
            rj.assigned_technician,
            rj.bay_no,
            rj.priority,
            rj.concern,
            rj.diagnosis_notes,
            rj.progress_notes,
            rj.check_in_time,
            rj.work_started_at,
            rj.estimated_finish_at,
            rj.labor_total,
            rj.parts_total,
            rj.grand_total
         FROM appointments a
         LEFT JOIN users u ON u.user_id = a.user_id
         LEFT JOIN vehicleinformation v ON v.vehicle_id = a.vehicle_id AND v.tenantID = a.tenantID
         LEFT JOIN appointment_services aps ON aps.appointment_id = a.appointment_id AND aps.tenantID = a.tenantID
         LEFT JOIN services s ON s.service_id = aps.service_id AND s.tenantID = a.tenantID
         LEFT JOIN repair_jobs rj ON rj.appointment_id = a.appointment_id AND rj.tenantID = a.tenantID
         WHERE a.appointment_id = ? AND a.tenantID = ?
         GROUP BY a.appointment_id, u.user_id, u.fullName, u.email, u.contactNumber, v.vehicle_id, v.year_model, v.brand, v.model, v.plate_number, rj.repair_job_id, rj.job_order_no, rj.job_status, rj.assigned_technician, rj.bay_no, rj.priority, rj.concern, rj.diagnosis_notes, rj.progress_notes, rj.check_in_time, rj.work_started_at, rj.estimated_finish_at, rj.labor_total, rj.parts_total, rj.grand_total
         LIMIT 1"
    );

    if ($reviewStmt) {
        mysqli_stmt_bind_param($reviewStmt, 'ii', $reviewAppointmentId, $tenantID);
        mysqli_stmt_execute($reviewStmt);
        $reviewResult = mysqli_stmt_get_result($reviewStmt);
        $reviewDetails = $reviewResult ? mysqli_fetch_assoc($reviewResult) : null;
        mysqli_stmt_close($reviewStmt);

        if ($reviewDetails) {
            if ($reviewForm['job_status'] === 'Queued' && !empty($reviewDetails['job_status'])) {
                $reviewForm['job_status'] = (string) $reviewDetails['job_status'];
            }
            if ($reviewForm['assigned_technician'] === '' && !empty($reviewDetails['assigned_technician'])) {
                $reviewForm['assigned_technician'] = (string) $reviewDetails['assigned_technician'];
            }
            if ($reviewForm['bay_no'] === '' && !empty($reviewDetails['bay_no'])) {
                $reviewForm['bay_no'] = (string) $reviewDetails['bay_no'];
            }
        } else {
            $showReviewModal = false;
            $reviewAppointmentId = 0;
            if ($actionError === '') {
                $actionError = 'Unable to load review details for that appointment.';
            }
        }
    }
}

$allBayNumbers = ['Bay 1', 'Bay 2', 'Bay 3'];
$occupiedBays = [];
$occupiedBayStmt = mysqli_prepare(
    $conn,
    "SELECT DISTINCT bay_no
     FROM repair_jobs
     WHERE tenantID = ?
       AND bay_no IS NOT NULL
       AND bay_no <> ''
       AND job_status IN ('Queued', 'In Progress', 'Diagnostics', 'Waiting for Parts', 'Quality Check', 'Ready for Pickup')"
);
if ($occupiedBayStmt) {
    mysqli_stmt_bind_param($occupiedBayStmt, 'i', $tenantID);
    mysqli_stmt_execute($occupiedBayStmt);
    $occupiedResult = mysqli_stmt_get_result($occupiedBayStmt);
    while ($occupiedResult && $occupiedRow = mysqli_fetch_assoc($occupiedResult)) {
        $occupiedBays[] = trim((string) $occupiedRow['bay_no']);
    }
    mysqli_stmt_close($occupiedBayStmt);
}

$availableBays = array_values(array_filter($allBayNumbers, static fn ($bay) => !in_array($bay, $occupiedBays, true)));
$showCustomBayInput = false;
if ($reviewForm['bay_no'] !== '' && !in_array($reviewForm['bay_no'], $allBayNumbers, true)) {
    $reviewForm['bay_no_custom'] = $reviewForm['bay_no'];
    $reviewForm['bay_no'] = '__custom__';
    $showCustomBayInput = true;
}

if ($reviewForm['bay_no'] !== '' && $reviewForm['bay_no'] !== '__custom__' && !in_array($reviewForm['bay_no'], $availableBays, true)) {
    $availableBays[] = $reviewForm['bay_no'];
}
sort($availableBays);

$technicianOptions = [];
$technicianStmt = mysqli_prepare(
    $conn,
    "SELECT role_id, username, first_name, last_name, role_name
     FROM roles
     WHERE tenantID = ?
       AND is_active = 1
       AND status = 'Active'
       AND LOWER(TRIM(role_name)) NOT IN (
            'office staff',
            'office admin',
            'front desk',
            'front desk staff',
            'receptionist',
            'cashier',
            'billing staff',
            'service advisor',
            'manager',
            'admin',
            'administrator'
       )
     ORDER BY first_name ASC, last_name ASC, username ASC"
);
if ($technicianStmt) {
    mysqli_stmt_bind_param($technicianStmt, 'i', $tenantID);
    mysqli_stmt_execute($technicianStmt);
    $technicianResult = mysqli_stmt_get_result($technicianStmt);
    while ($technicianResult && $techRow = mysqli_fetch_assoc($technicianResult)) {
        $technicianOptions[] = $techRow;
    }
    mysqli_stmt_close($technicianStmt);
}

$todayLoad = 0;
$todayCompleted = 0;
$pendingCount = 0;
$weekCount = 0;
$nextAvailable = 'No upcoming schedule';

$statsSql = "
    SELECT
        SUM(CASE WHEN appointment_date = CURDATE() THEN 1 ELSE 0 END) AS today_load,
        SUM(CASE WHEN appointment_date = CURDATE() AND status = 'Completed' THEN 1 ELSE 0 END) AS today_completed,
        SUM(CASE WHEN status = 'Pending' THEN 1 ELSE 0 END) AS pending_count,
        SUM(CASE WHEN YEARWEEK(appointment_date, 1) = YEARWEEK(CURDATE(), 1) THEN 1 ELSE 0 END) AS weekly_count
    FROM appointments
    WHERE tenantID = $tenantID
";
$statsResult = mysqli_query($conn, $statsSql);
if ($statsResult && ($statsRow = mysqli_fetch_assoc($statsResult))) {
    $todayLoad = (int) ($statsRow['today_load'] ?? 0);
    $todayCompleted = (int) ($statsRow['today_completed'] ?? 0);
    $pendingCount = (int) ($statsRow['pending_count'] ?? 0);
    $weekCount = (int) ($statsRow['weekly_count'] ?? 0);
}

$nextSql = "
    SELECT appointment_date, appointment_time
    FROM appointments
    WHERE tenantID = $tenantID
      AND status IN ('Pending', 'Confirmed', 'In Progress')
      AND (appointment_date > CURDATE() OR (appointment_date = CURDATE() AND appointment_time >= CURTIME()))
    ORDER BY appointment_date ASC, appointment_time ASC
    LIMIT 1
";
$nextResult = mysqli_query($conn, $nextSql);
if ($nextResult && ($nextRow = mysqli_fetch_assoc($nextResult))) {
    $nextAvailable = date('M d, Y', strtotime((string) $nextRow['appointment_date'])) . ' ' . date('h:i A', strtotime((string) $nextRow['appointment_time']));
}

$whereParts = ["a.tenantID = $tenantID"];
if ($statusFilter !== 'All') {
    $safeStatus = mysqli_real_escape_string($conn, $statusFilter);
    $whereParts[] = "a.status = '$safeStatus'";
}
if ($search !== '') {
    $safeSearch = mysqli_real_escape_string($conn, $search);
    $whereParts[] = "(
        u.fullName LIKE '%$safeSearch%'
        OR CONCAT(IFNULL(v.year_model, ''), ' ', IFNULL(v.brand, ''), ' ', IFNULL(v.model, '')) LIKE '%$safeSearch%'
        OR IFNULL(v.plate_number, '') LIKE '%$safeSearch%'
        OR IFNULL(s.service_name, '') LIKE '%$safeSearch%'
        OR IFNULL(a.notes, '') LIKE '%$safeSearch%'
    )";
}

$appointmentsSql = "
    SELECT
        a.appointment_id,
        a.user_id,
        a.vehicle_id,
        a.appointment_date,
        a.appointment_time,
        a.status,
        a.notes,
        a.total_amount,
        a.created_at,
        COALESCE(u.fullName, CONCAT('User #', a.user_id)) AS customer_name,
        v.year_model,
        v.brand,
        v.model,
        v.plate_number,
        COALESCE(GROUP_CONCAT(DISTINCT s.service_name ORDER BY s.service_name SEPARATOR ', '), 'No service linked') AS requested_services
    FROM appointments a
    LEFT JOIN users u ON u.user_id = a.user_id
    LEFT JOIN vehicleinformation v ON v.vehicle_id = a.vehicle_id AND v.tenantID = a.tenantID
    LEFT JOIN appointment_services aps ON aps.appointment_id = a.appointment_id AND aps.tenantID = a.tenantID
    LEFT JOIN services s ON s.service_id = aps.service_id AND s.tenantID = a.tenantID
    WHERE " . implode(' AND ', $whereParts) . "
    GROUP BY
        a.appointment_id,
        a.user_id,
        a.vehicle_id,
        a.appointment_date,
        a.appointment_time,
        a.status,
        a.notes,
        a.total_amount,
        a.created_at,
        u.fullName,
        v.year_model,
        v.brand,
        v.model,
        v.plate_number
    ORDER BY a.appointment_date ASC, a.appointment_time ASC
    LIMIT 200
";

$appointments = [];
$listResult = mysqli_query($conn, $appointmentsSql);
if ($listResult) {
    while ($row = mysqli_fetch_assoc($listResult)) {
        $appointments[] = $row;
    }
}

$upcomingSql = "
    SELECT
        a.appointment_id,
        a.appointment_date,
        a.appointment_time,
        COALESCE(u.fullName, CONCAT('User #', a.user_id)) AS customer_name,
        CONCAT(IFNULL(v.brand, ''), ' ', IFNULL(v.model, '')) AS vehicle_name,
        COALESCE(GROUP_CONCAT(DISTINCT s.service_name ORDER BY s.service_name SEPARATOR ', '), 'No service linked') AS requested_services
    FROM appointments a
    LEFT JOIN users u ON u.user_id = a.user_id
    LEFT JOIN vehicleinformation v ON v.vehicle_id = a.vehicle_id AND v.tenantID = a.tenantID
    LEFT JOIN appointment_services aps ON aps.appointment_id = a.appointment_id AND aps.tenantID = a.tenantID
    LEFT JOIN services s ON s.service_id = aps.service_id AND s.tenantID = a.tenantID
    WHERE a.tenantID = $tenantID
      AND a.status IN ('Confirmed', 'In Progress')
      AND (a.appointment_date > CURDATE() OR (a.appointment_date = CURDATE() AND a.appointment_time >= CURTIME()))
      AND NOT EXISTS (
            SELECT 1
            FROM repair_jobs rj
            WHERE rj.appointment_id = a.appointment_id
              AND rj.tenantID = a.tenantID
              AND rj.job_status = 'Completed'
       )
    GROUP BY
        a.appointment_id,
        a.appointment_date,
        a.appointment_time,
        u.fullName,
        a.user_id,
        v.brand,
        v.model
    ORDER BY a.appointment_date ASC, a.appointment_time ASC
    LIMIT 5
";

$upcomingAppointments = [];
$upcomingResult = mysqli_query($conn, $upcomingSql);
if ($upcomingResult) {
    while ($row = mysqli_fetch_assoc($upcomingResult)) {
        $upcomingAppointments[] = $row;
    }
}

$historyRange = isset($_GET['history_range']) ? trim((string) $_GET['history_range']) : 'all';
$allowedHistoryRanges = ['all', 'today', 'week', 'month', 'range'];
if (!in_array($historyRange, $allowedHistoryRanges, true)) {
    $historyRange = 'all';
}
$historySearch = isset($_GET['history_search']) ? trim((string) $_GET['history_search']) : '';
// Optional custom date range filter (YYYY-MM-DD)
$historyDateFrom = isset($_GET['date_from']) ? trim((string) $_GET['date_from']) : '';
$historyDateTo = isset($_GET['date_to']) ? trim((string) $_GET['date_to']) : '';
// Validate date inputs
$validDateFrom = false;
$validDateTo = false;
if ($historyDateFrom !== '') {
    $d = DateTime::createFromFormat('Y-m-d', $historyDateFrom);
    $validDateFrom = $d && $d->format('Y-m-d') === $historyDateFrom;
}
if ($historyDateTo !== '') {
    $d2 = DateTime::createFromFormat('Y-m-d', $historyDateTo);
    $validDateTo = $d2 && $d2->format('Y-m-d') === $historyDateTo;
}
// Pagination variables
$itemsPerPage = 5;
$currentPage = isset($_GET['history_page']) ? (int) $_GET['history_page'] : 1;
if ($currentPage < 1) {
    $currentPage = 1;
}
$offset = ($currentPage - 1) * $itemsPerPage;

$historyDateFilterSql = '';
// If a valid custom range is provided, use it (and prefer it over the preset ranges)
if ($validDateFrom && $validDateTo) {
    // ensure from <= to
    if ($historyDateFrom > $historyDateTo) {
        // swap
        [$historyDateFrom, $historyDateTo] = [$historyDateTo, $historyDateFrom];
    }
    $safeFrom = mysqli_real_escape_string($conn, $historyDateFrom);
    $safeTo = mysqli_real_escape_string($conn, $historyDateTo);
    $historyDateFilterSql = " AND a.appointment_date BETWEEN '$safeFrom' AND '$safeTo'";
    // mark active range
    $historyRange = 'range';
} elseif ($historyRange === 'today') {
    $historyDateFilterSql = ' AND a.appointment_date = CURDATE()';
} elseif ($historyRange === 'week') {
    $historyDateFilterSql = ' AND YEARWEEK(a.appointment_date, 1) = YEARWEEK(CURDATE(), 1)';
} elseif ($historyRange === 'month') {
    $historyDateFilterSql = " AND DATE_FORMAT(a.appointment_date, '%Y-%m') = DATE_FORMAT(CURDATE(), '%Y-%m')";
}

$completedHistory = [];
$totalHistoryRecords = 0;

// First, get total count for pagination
$countHistoryStmt = mysqli_prepare(
    $conn,
    "SELECT COUNT(DISTINCT a.appointment_id) as total
     FROM appointments a
         LEFT JOIN repair_jobs rj ON rj.appointment_id = a.appointment_id AND rj.tenantID = a.tenantID
     LEFT JOIN users u ON u.user_id = a.user_id
     LEFT JOIN vehicleinformation v ON v.vehicle_id = a.vehicle_id AND v.tenantID = a.tenantID
     LEFT JOIN appointment_services aps ON aps.appointment_id = a.appointment_id AND aps.tenantID = a.tenantID
     LEFT JOIN services s ON s.service_id = aps.service_id AND s.tenantID = a.tenantID
     WHERE a.tenantID = ?
             AND (rj.job_status IN ('Completed', 'Cancelled') OR a.status IN ('Completed', 'Cancelled'))
       AND (
            ? = ''
            OR u.fullName LIKE CONCAT('%', ?, '%')
            OR CONCAT(IFNULL(v.year_model, ''), ' ', IFNULL(v.brand, ''), ' ', IFNULL(v.model, '')) LIKE CONCAT('%', ?, '%')
            OR IFNULL(v.plate_number, '') LIKE CONCAT('%', ?, '%')
            OR IFNULL(s.service_name, '') LIKE CONCAT('%', ?, '%')
                        OR IFNULL(a.notes, '') LIKE CONCAT('%', ?, '%')
                        OR IFNULL(rj.concern, '') LIKE CONCAT('%', ?, '%')
                        OR IFNULL(rj.diagnosis_notes, '') LIKE CONCAT('%', ?, '%')
                        OR IFNULL(rj.progress_notes, '') LIKE CONCAT('%', ?, '%')
       )"
    . $historyDateFilterSql
);
if ($countHistoryStmt) {
    mysqli_stmt_bind_param(
        $countHistoryStmt,
        'isssssssss',
        $tenantID,
        $historySearch,
        $historySearch,
        $historySearch,
        $historySearch,
        $historySearch,
        $historySearch,
        $historySearch,
        $historySearch,
        $historySearch
    );
    mysqli_stmt_execute($countHistoryStmt);
    $countResult = mysqli_stmt_get_result($countHistoryStmt);
    if ($countResult && $countRow = mysqli_fetch_assoc($countResult)) {
        $totalHistoryRecords = (int) $countRow['total'];
    }
    mysqli_stmt_close($countHistoryStmt);
}

// Calculate total pages
$totalHistoryPages = ceil($totalHistoryRecords / $itemsPerPage);
if ($currentPage > $totalHistoryPages && $totalHistoryPages > 0) {
    $currentPage = $totalHistoryPages;
    $offset = ($currentPage - 1) * $itemsPerPage;
}

// Now fetch paginated results
$historyStmt = mysqli_prepare(
    $conn,
    "SELECT
        a.appointment_id,
        a.appointment_date,
        a.appointment_time,
        a.status,
        a.notes,
        a.total_amount,
                rj.job_status,
        COALESCE(u.fullName, CONCAT('User #', a.user_id)) AS customer_name,
        v.year_model,
        v.brand,
        v.model,
        v.plate_number,
        COALESCE(GROUP_CONCAT(DISTINCT s.service_name ORDER BY s.service_name SEPARATOR ', '), 'No service linked') AS requested_services
     FROM appointments a
         LEFT JOIN repair_jobs rj ON rj.appointment_id = a.appointment_id AND rj.tenantID = a.tenantID
     LEFT JOIN users u ON u.user_id = a.user_id
     LEFT JOIN vehicleinformation v ON v.vehicle_id = a.vehicle_id AND v.tenantID = a.tenantID
     LEFT JOIN appointment_services aps ON aps.appointment_id = a.appointment_id AND aps.tenantID = a.tenantID
     LEFT JOIN services s ON s.service_id = aps.service_id AND s.tenantID = a.tenantID
     WHERE a.tenantID = ?
             AND (rj.job_status IN ('Completed', 'Cancelled') OR a.status IN ('Completed', 'Cancelled'))
       AND (
            ? = ''
            OR u.fullName LIKE CONCAT('%', ?, '%')
            OR CONCAT(IFNULL(v.year_model, ''), ' ', IFNULL(v.brand, ''), ' ', IFNULL(v.model, '')) LIKE CONCAT('%', ?, '%')
            OR IFNULL(v.plate_number, '') LIKE CONCAT('%', ?, '%')
            OR IFNULL(s.service_name, '') LIKE CONCAT('%', ?, '%')
                        OR IFNULL(a.notes, '') LIKE CONCAT('%', ?, '%')
                        OR IFNULL(rj.concern, '') LIKE CONCAT('%', ?, '%')
                        OR IFNULL(rj.diagnosis_notes, '') LIKE CONCAT('%', ?, '%')
                        OR IFNULL(rj.progress_notes, '') LIKE CONCAT('%', ?, '%')
       )"
    . $historyDateFilterSql .
    " GROUP BY
        a.appointment_id,
        a.appointment_date,
        a.appointment_time,
        a.status,
        a.notes,
        a.total_amount,
                rj.job_status,
        u.fullName,
        a.user_id,
        v.year_model,
        v.brand,
        v.model,
        v.plate_number
      ORDER BY a.appointment_date DESC, a.appointment_time DESC
      LIMIT ? OFFSET ?"
);
if ($historyStmt) {
    mysqli_stmt_bind_param(
        $historyStmt,
        'isssssssssii',
        $tenantID,
        $historySearch,
        $historySearch,
        $historySearch,
        $historySearch,
        $historySearch,
        $historySearch,
        $historySearch,
        $historySearch,
        $historySearch,
        $itemsPerPage,
        $offset
    );
    mysqli_stmt_execute($historyStmt);
    $historyResult = mysqli_stmt_get_result($historyStmt);
    while ($historyResult && $historyRow = mysqli_fetch_assoc($historyResult)) {
        $completedHistory[] = $historyRow;
    }
    mysqli_stmt_close($historyStmt);
}
?>
<!DOCTYPE html>
<html lang="en">

<head>
    <meta charset="utf-8" />
    <meta content="width=device-width, initial-scale=1.0" name="viewport" />
    <meta http-equiv="Cache-Control" content="no-cache, no-store, must-revalidate" />
    <meta http-equiv="Pragma" content="no-cache" />
    <meta http-equiv="Expires" content="0" />
    <title><?php echo h($shopName); ?> | Appointment Management</title>
    <script src="https://cdn.tailwindcss.com?plugins=forms,container-queries"></script>
    <link href="https://fonts.googleapis.com/css2?family=Inter:wght@400;500;600;700&amp;display=swap"
        rel="stylesheet" />
    <link href="https://fonts.googleapis.com/css2?family=Material+Symbols+Outlined:wght@100..700,0..1&amp;display=swap"
        rel="stylesheet" />
    <link
        href="https://fonts.googleapis.com/css2?family=Material+Symbols+Outlined:wght,FILL@100..700,0..1&amp;display=swap"
        rel="stylesheet" />
    <script id="tailwind-config">
        tailwind.config = {
            darkMode: "class",
            theme: {
                extend: {
                    colors: {
                        "primary": "#1152d4",
                        "background-light": "#f6f6f8",
                        "background-dark": "#101622",
                    },
                    fontFamily: {
                        "display": ["Inter", "sans-serif"]
                    },
                    borderRadius: { "DEFAULT": "0.25rem", "lg": "0.5rem", "xl": "0.75rem", "full": "9999px" },
                },
            },
        }
    </script>
    <style>
        body {
            font-family: 'Inter', sans-serif;
        }

        .material-symbols-outlined {
            font-variation-settings: 'FILL' 0, 'wght' 400, 'GRAD' 0, 'opsz' 24;
        }
    </style>
</head>

<body class="bg-white text-black antialiased">
    <!-- Mobile Menu Toggle -->
    <div class="md:hidden fixed top-0 left-0 right-0 bg-white dark:bg-slate-900 border-b border-slate-200 dark:border-slate-800 px-4 py-3 z-50 flex items-center justify-between">
        <button id="sidebarToggle" type="button" class="inline-flex items-center justify-center w-10 h-10 rounded-lg hover:bg-slate-100 dark:hover:bg-slate-800 transition-colors">
            <span class="material-symbols-outlined">menu</span>
        </button>
        <h2 class="text-lg font-bold truncate flex-1 ml-3"><?php echo h($shopName); ?></h2>
    </div>
    <div id="sidebarOverlay" class="hidden fixed inset-0 bg-black/50 z-30 md:hidden"></div>
    <div class="flex h-screen overflow-hidden pt-16 md:pt-0">
    <aside id="sidebar" class="fixed md:static md:flex left-0 top-0 h-screen md:h-screen w-64 md:w-64 flex-shrink-0 border-r border-slate-200 dark:border-slate-800 bg-white dark:bg-slate-900 flex flex-col overflow-y-auto z-40 -translate-x-full md:translate-x-0 transition-transform duration-300 ease-in-out md:transition-none pt-16 md:pt-0">
        <div class="p-6 flex-1">
            <div class="flex items-center gap-3 mb-8">
                <div class="bg-primary rounded-lg p-2 text-white">
                    <span class="material-symbols-outlined">directions_car</span>
                </div>
                <div>
                    <h1 class="text-lg font-bold leading-none"><?php echo h($shopName); ?></h1>
                    <p class="text-xs text-slate-500 dark:text-slate-400 mt-1">Your Repair Shop</p>
                </div>
            </div>
            <nav class="space-y-1">
                <?php if (canAccessModule('dashboardadmin.php', $accessibleModules)): ?>
                <a class="flex items-center gap-3 px-3 py-2.5 rounded-lg text-slate-600 hover:bg-slate-100 transition-colors font-medium" href="dashboardadmin.php?shop=<?php echo $shopQuery; ?>"><span class="material-symbols-outlined text-[22px]">dashboard</span>Dashboard</a>
                <?php endif; ?>
                <?php if (canAccessModule('repairjobsadmin.php', $accessibleModules)): ?>
                <a class="flex items-center gap-3 px-3 py-2.5 rounded-lg text-slate-600 hover:bg-slate-100 transition-colors font-medium" href="repairjobsadmin.php?shop=<?php echo $shopQuery; ?>"><span class="material-symbols-outlined text-[22px]">build</span>Repair Jobs</a>
                <?php endif; ?>
                <?php if (canAccessModule('vehicleadmin.php', $accessibleModules)): ?>
                <a class="flex items-center gap-3 px-3 py-2.5 rounded-lg text-slate-600 hover:bg-slate-100 transition-colors" href="vehicleadmin.php?shop=<?php echo $shopQuery; ?>"><span class="material-symbols-outlined text-[22px]">directions_car</span>Vehicles</a>
                <?php endif; ?>
                <?php if (canAccessModule('appointmentadmin.php', $accessibleModules)): ?>
                <a class="flex items-center gap-3 px-3 py-2.5 rounded-lg bg-primary/10 text-primary font-medium" href="appointmentadmin.php?shop=<?php echo $shopQuery; ?>"><span class="material-symbols-outlined text-[22px]">event</span>Appointments</a>
                <?php endif; ?>
                <?php if (canAccessModule('reportsadmin.php', $accessibleModules)): ?>
                <a class="flex items-center gap-3 px-3 py-2.5 rounded-lg text-slate-600 hover:bg-slate-100 transition-colors" href="reportsadmin.php?shop=<?php echo $shopQuery; ?>"><span class="material-symbols-outlined text-[22px]">description</span>Reports</a>
                <?php endif; ?>
                <?php if (canAccessModule('inventoryadmin.php', $accessibleModules)): ?>
                <a class="flex items-center gap-3 px-3 py-2.5 rounded-lg text-slate-600 hover:bg-slate-100 transition-colors" href="inventoryadmin.php?shop=<?php echo $shopQuery; ?>"><span class="material-symbols-outlined text-[22px]">inventory_2</span>Inventory</a>
                <?php endif; ?>
                <?php if (canAccessModule('customeradmin.php', $accessibleModules)): ?>
                <a class="flex items-center gap-3 px-3 py-2.5 rounded-lg text-slate-600 hover:bg-slate-100 transition-colors" href="customeradmin.php?shop=<?php echo $shopQuery; ?>"><span class="material-symbols-outlined text-[22px]">group</span>Customers</a>
                <?php endif; ?>
                <?php if (canAccessModule('paymentsadmin.php', $accessibleModules)): ?>
                <a class="flex items-center gap-3 px-3 py-2.5 rounded-lg text-slate-600 hover:bg-slate-100 transition-colors" href="paymentsadmin.php?shop=<?php echo $shopQuery; ?>"><span class="material-symbols-outlined text-[22px]">payments</span>Payments</a>
                <?php endif; ?>
                <div class="pt-4 mt-4 border-t border-slate-100">
                    <div class="relative group">
                        <button class="flex items-center gap-3 px-3 py-2.5 rounded-lg text-slate-600 hover:bg-slate-100 transition-colors w-full text-left settings-dropdown-btn" data-dropdown="settings">
                            <span class="material-symbols-outlined text-[22px]">settings</span>
                            <span>Settings</span>
                            <span class="material-symbols-outlined text-[16px] ml-auto">expand_more</span>
                        </button>
                        <div class="absolute left-0 top-full mt-1 w-full bg-white border border-slate-200 rounded-lg shadow-lg hidden z-50 settings-dropdown" data-dropdown="settings">
                            <?php if (canAccessModule('accountbillingadmin.php', $accessibleModules)): ?>
                            <a class="flex items-center gap-3 px-3 py-2.5 rounded-t-lg text-slate-600 hover:bg-blue-50 transition-colors text-sm"
                                href="accountbillingadmin.php?shop=<?php echo $shopQuery; ?>">
                                <span class="material-symbols-outlined text-[18px]">receipt_long</span>
                                Account Billing
                            </a>
                            <?php endif; ?>
                            <a class="flex items-center gap-3 px-3 py-2.5 text-slate-600 hover:bg-blue-50 transition-colors text-sm border-t border-slate-100"
                                href="websitecustomadmin.php?shop=<?php echo $shopQuery; ?>">
                                <span class="material-symbols-outlined text-[18px]">palette</span>
                                Website Customizer
                            </a>
                            <?php if (canAccessModule('settingsadmin.php', $accessibleModules)): ?>
                            <a class="flex items-center gap-3 px-3 py-2.5 rounded-b-lg text-slate-600 hover:bg-blue-50 transition-colors text-sm border-t border-slate-100"
                                href="settingsadmin.php?shop=<?php echo $shopQuery; ?>">
                                <span class="material-symbols-outlined text-[18px]">settings</span>
                                Settings
                            </a>
                            <?php endif; ?>
                        </div>
                    </div>
                </div>
            </nav>
        </div>
        <div class="p-4 border-t border-slate-200 dark:border-slate-800">
            <div class="flex items-center gap-3">
                <div class="size-10 rounded-full bg-slate-200 dark:bg-slate-700 flex items-center justify-center overflow-hidden">
                    <span class="material-symbols-outlined text-slate-500">person</span>
                </div>
                <div class="flex-1 min-w-0">
                    <p class="text-sm font-semibold truncate"><?php echo h($loggedInUserName); ?></p>
                    <p class="text-xs text-slate-500 truncate"><?php echo h($loggedInUserRole); ?></p>
                </div>
                <form id="logoutForm" method="post" action="../logout/logout.php" class="inline">
                    <input type="hidden" name="action" value="confirm" />
                    <input type="hidden" name="shop" value="<?php echo h($shopSlug); ?>" />
                    <button type="submit" class="text-slate-400 hover:text-error transition-colors" title="Logout">
                        <span class="material-symbols-outlined text-xl">logout</span>
                    </button>
                </form>
            </div>
        </div>
    </aside>

    <main class="flex-1 overflow-y-auto">
        <header class="sticky top-0 z-40 w-full border-b border-slate-200 dark:border-slate-800 bg-white/80 dark:bg-slate-900/80 backdrop-blur-md flex items-center justify-between px-8 h-16">
            <h2 class="text-lg font-black text-slate-900 dark:text-white tracking-tight">Appointment Management</h2>
            <div class="flex items-center gap-4">
                <button class="p-2 text-slate-500 hover:text-primary transition-all">
                    <span class="material-symbols-outlined">notifications</span>
                </button>
                <button class="p-2 text-slate-500 hover:text-primary transition-all">
                    <span class="material-symbols-outlined">help_outline</span>
                </button>
            </div>
        </header>

        <div class="p-8 space-y-6">
            <?php if ($actionMessage !== ''): ?>
                <div class="rounded-lg border border-green-200 bg-green-50 text-green-700 px-4 py-3 text-sm font-medium"><?php echo h($actionMessage); ?></div>
            <?php endif; ?>
            <?php if ($actionError !== ''): ?>
                <div class="rounded-lg border border-red-200 bg-red-50 text-red-700 px-4 py-3 text-sm font-medium"><?php echo h($actionError); ?></div>
            <?php endif; ?>

            <?php if ($showCreateForm): ?>
                <div class="fixed inset-0 z-50 flex items-center justify-center p-4">
                    <a href="appointmentadmin.php?shop=<?php echo h($shopQuery); ?>" class="absolute inset-0 bg-slate-900/60 backdrop-blur-[1px]"></a>
                    <section class="relative w-full max-w-5xl max-h-[92vh] overflow-y-auto bg-white rounded-2xl border border-slate-200 shadow-2xl">
                        <div class="sticky top-0 z-10 bg-white border-b border-slate-100 px-5 py-4 flex items-center justify-between gap-3">
                            <h3 class="font-bold text-slate-900">Create Appointment</h3>
                            <a href="appointmentadmin.php?shop=<?php echo h($shopQuery); ?>" class="inline-flex items-center justify-center w-8 h-8 rounded-full text-slate-500 hover:text-slate-700 hover:bg-slate-100" aria-label="Close create appointment modal">
                                <span class="material-symbols-outlined text-lg">close</span>
                            </a>
                        </div>
                        <div class="p-5">
                            <?php if ($createError !== ''): ?>
                                <div class="mb-4 rounded-lg border border-red-200 bg-red-50 px-4 py-3 text-sm text-red-700"><?php echo h($createError); ?></div>
                            <?php endif; ?>
                            <form method="post" class="grid grid-cols-1 md:grid-cols-3 gap-4">
                                <input type="hidden" name="csrf_token" value="<?php echo h($csrfToken); ?>" />
                                <input type="hidden" name="create_appointment_submit" value="1" />

                                <div>
                                    <label class="text-xs font-bold uppercase text-slate-500">Customer</label>
                                    <select id="create_user_id" name="user_id" class="mt-1 w-full rounded-lg border-slate-300 text-sm" required>
                                        <option value="">Select customer</option>
                                        <?php foreach ($customerOptions as $customerOption): ?>
                                            <option value="<?php echo (int) $customerOption['user_id']; ?>" <?php echo (int) $createForm['user_id'] === (int) $customerOption['user_id'] ? 'selected' : ''; ?>>
                                                <?php echo h($customerOption['fullName'] ?: ('User #' . (int) $customerOption['user_id'])); ?>
                                                <?php if (!empty($customerOption['email'])): ?>
                                                    (<?php echo h($customerOption['email']); ?>)
                                                <?php endif; ?>
                                            </option>
                                        <?php endforeach; ?>
                                    </select>
                                </div>

                                <div>
                                    <label class="text-xs font-bold uppercase text-slate-500">Vehicle</label>
                                    <select id="create_vehicle_id" name="vehicle_id" class="mt-1 w-full rounded-lg border-slate-300 text-sm" required>
                                        <option value="">Select vehicle</option>
                                        <?php foreach ($vehicleOptions as $vehicleOption): ?>
                                            <?php
                                                $vehicleLabel = trim(((string) ($vehicleOption['year_model'] ?? '')) . ' ' . ((string) ($vehicleOption['brand'] ?? '')) . ' ' . ((string) ($vehicleOption['model'] ?? '')));
                                                $plateLabel = trim((string) ($vehicleOption['plate_number'] ?? ''));
                                                $ownerLabel = trim((string) ($vehicleOption['fullName'] ?? ''));
                                            ?>
                                            <option
                                                value="<?php echo (int) $vehicleOption['vehicle_id']; ?>"
                                                data-user-id="<?php echo (int) $vehicleOption['user_id']; ?>"
                                                <?php echo (int) $createForm['vehicle_id'] === (int) $vehicleOption['vehicle_id'] ? 'selected' : ''; ?>>
                                                <?php echo h($vehicleLabel !== '' ? $vehicleLabel : ('Vehicle #' . (int) $vehicleOption['vehicle_id'])); ?>
                                                <?php if ($plateLabel !== ''): ?>
                                                    - <?php echo h($plateLabel); ?>
                                                <?php endif; ?>
                                                <?php if ($ownerLabel !== ''): ?>
                                                    (<?php echo h($ownerLabel); ?>)
                                                <?php endif; ?>
                                            </option>
                                        <?php endforeach; ?>
                                    </select>
                                </div>

                                <div class="md:col-span-3">
                                    <label class="text-xs font-bold uppercase text-slate-500">Service</label>
                                    <div class="mt-1 rounded-lg border border-slate-200 p-3 max-h-52 overflow-y-auto">
                                        <div class="grid grid-cols-1 md:grid-cols-2 xl:grid-cols-3 gap-x-4 gap-y-2">
                                        <?php foreach ($serviceOptions as $serviceOption): ?>
                                            <?php $serviceId = (int) $serviceOption['service_id']; ?>
                                            <label class="flex items-start gap-2 text-sm text-slate-700">
                                                <input
                                                    type="checkbox"
                                                    name="service_ids[]"
                                                    value="<?php echo $serviceId; ?>"
                                                    class="rounded border-slate-300 text-blue-600 mt-0.5"
                                                    <?php echo in_array($serviceId, $createForm['service_ids'], true) ? 'checked' : ''; ?>>
                                                <span>
                                                    <span class="font-semibold"><?php echo h($serviceOption['service_name']); ?></span>
                                                    <span class="text-slate-500"> - ₱<?php echo h(number_format((float) ($serviceOption['price'] ?? 0), 2)); ?></span>
                                                    <?php if ((int) ($serviceOption['duration_minutes'] ?? 0) > 0): ?>
                                                        <span class="text-slate-500">(<?php echo (int) $serviceOption['duration_minutes']; ?> mins)</span>
                                                    <?php endif; ?>
                                                </span>
                                            </label>
                                        <?php endforeach; ?>
                                        </div>
                                    </div>
                                    <p class="mt-2 text-xs text-slate-500">You can select multiple services for one appointment.</p>
                                </div>

                                <div>
                                    <label class="text-xs font-bold uppercase text-slate-500">Appointment Date</label>
                                    <input type="date" name="appointment_date" value="<?php echo h($createForm['appointment_date']); ?>" class="mt-1 w-full rounded-lg border-slate-300 text-sm" required />
                                </div>

                                <div>
                                    <label class="text-xs font-bold uppercase text-slate-500">Appointment Time</label>
                                    <input type="hidden" id="appointment_time_input" name="appointment_time" value="<?php echo h($createForm['appointment_time']); ?>" required />
                                    <div id="time_slots_grid" class="mt-2 grid grid-cols-2 md:grid-cols-3 gap-3">
                                        <?php foreach ($timeSlots as $slot): ?>
                                            <?php $slotSelected = $createForm['appointment_time'] === $slot; ?>
                                            <button
                                                type="button"
                                                data-time-slot="<?php echo h($slot); ?>"
                                                class="time-slot-btn h-12 rounded-xl border text-sm font-bold transition-colors <?php echo $slotSelected ? 'bg-slate-800 text-white border-slate-800' : 'bg-white text-slate-700 border-slate-200 hover:border-slate-400'; ?>">
                                                <?php echo h(date('h:i A', strtotime($slot . ':00'))); ?>
                                            </button>
                                        <?php endforeach; ?>
                                    </div>
                                    <p class="mt-2 text-xs text-slate-500">Choose one available slot.</p>
                                </div>

                                <div>
                                    <label class="text-xs font-bold uppercase text-slate-500">Status</label>
                                    <select name="status" class="mt-1 w-full rounded-lg border-slate-300 text-sm" required>
                                        <?php foreach ($allowedStatuses as $statusOption): ?>
                                            <option value="<?php echo h($statusOption); ?>" <?php echo $createForm['status'] === $statusOption ? 'selected' : ''; ?>><?php echo h($statusOption); ?></option>
                                        <?php endforeach; ?>
                                    </select>
                                </div>

                                <div class="md:col-span-3">
                                    <label class="text-xs font-bold uppercase text-slate-500">Notes</label>
                                    <textarea name="notes" rows="3" class="mt-1 w-full rounded-lg border-slate-300 text-sm" placeholder="Optional notes for this appointment"><?php echo h($createForm['notes']); ?></textarea>
                                </div>

                                <div class="md:col-span-3 flex justify-end gap-2">
                                    <a href="appointmentadmin.php?shop=<?php echo h($shopQuery); ?>" class="px-4 py-2.5 border border-slate-300 rounded-lg text-sm text-slate-600">Cancel</a>
                                    <button type="submit" class="px-4 py-2.5 bg-blue-600 text-white rounded-lg text-sm font-semibold">Create Appointment</button>
                                </div>
                            </form>
                        </div>
                    </section>
                </div>
            <?php endif; ?>

            <?php if ($showReviewModal && $reviewDetails): ?>
                <div class="fixed inset-0 z-50 flex items-center justify-center p-4">
                    <a href="appointmentadmin.php?shop=<?php echo h($shopQuery); ?>" class="absolute inset-0 bg-slate-900/60 backdrop-blur-[1px]"></a>
                    <section class="relative w-full max-w-4xl max-h-[90vh] overflow-y-auto bg-white rounded-2xl border border-slate-200 shadow-2xl">
                        <div class="sticky top-0 z-10 px-5 py-4 border-b border-slate-100 bg-white flex items-center justify-between">
                            <div>
                                <h3 class="font-bold text-slate-900">Review Appointment #<?php echo (int) $reviewDetails['appointment_id']; ?></h3>
                                <?php if (!empty($reviewDetails['job_order_no'])): ?>
                                    <p class="text-xs text-slate-500 mt-1">Job Order: <?php echo h($reviewDetails['job_order_no']); ?></p>
                                <?php endif; ?>
                            </div>
                            <a href="appointmentadmin.php?shop=<?php echo h($shopQuery); ?>" class="inline-flex items-center justify-center w-8 h-8 rounded-full text-slate-500 hover:text-slate-700 hover:bg-slate-100" aria-label="Close review modal">
                                <span class="material-symbols-outlined text-lg">close</span>
                            </a>
                        </div>

                        <div class="space-y-4 p-5">
                            <!-- Customer & Vehicle Section -->
                            <div class="border border-slate-200 rounded-lg p-4 bg-slate-50">
                                <h4 class="font-bold text-xs uppercase text-slate-600 mb-3">Appointment Details</h4>
                                <div class="grid grid-cols-1 md:grid-cols-2 gap-3 text-sm">
                                    <div>
                                        <p class="text-xs text-slate-500 font-semibold">Customer</p>
                                        <p class="text-slate-900 font-medium"><?php echo h($reviewDetails['customer_name']); ?></p>
                                        <?php if (!empty($reviewDetails['customer_email'])): ?>
                                            <p class="text-xs text-slate-600"><?php echo h($reviewDetails['customer_email']); ?></p>
                                        <?php endif; ?>
                                        <?php if (!empty($reviewDetails['customer_phone'])): ?>
                                            <p class="text-xs text-slate-600"><?php echo h($reviewDetails['customer_phone']); ?></p>
                                        <?php endif; ?>
                                    </div>
                                    <div>
                                        <p class="text-xs text-slate-500 font-semibold">Vehicle</p>
                                        <p class="text-slate-900 font-medium"><?php echo h(trim((string) $reviewDetails['vehicle_name']) !== '' ? $reviewDetails['vehicle_name'] : 'Vehicle'); ?></p>
                                        <?php if (!empty($reviewDetails['plate_number'])): ?>
                                            <p class="text-xs text-slate-600">Plate: <?php echo h($reviewDetails['plate_number']); ?></p>
                                        <?php endif; ?>
                                    </div>
                                    <div>
                                        <p class="text-xs text-slate-500 font-semibold">Date & Time</p>
                                        <p class="text-slate-900 font-medium"><?php echo h(date('M d, Y', strtotime((string) $reviewDetails['appointment_date']))); ?></p>
                                        <p class="text-xs text-slate-600"><?php echo h(date('h:i A', strtotime((string) $reviewDetails['appointment_time']))); ?></p>
                                    </div>
                                    <div>
                                        <p class="text-xs text-slate-500 font-semibold">Appointment Status</p>
                                        <p class="text-slate-900 font-medium"><?php echo h($reviewDetails['appt_status']); ?></p>
                                        <p class="text-xs text-slate-600">Total: ₱<?php echo number_format((float) ($reviewDetails['total_amount'] ?? 0), 2); ?></p>
                                    </div>
                                </div>
                                <?php if (!empty($reviewDetails['services_list'])): ?>
                                    <div class="mt-3 pt-3 border-t border-slate-200">
                                        <p class="text-xs text-slate-500 font-semibold mb-1">Services</p>
                                        <p class="text-sm text-slate-700"><?php echo h($reviewDetails['services_list']); ?></p>
                                    </div>
                                <?php endif; ?>
                                <?php if (!empty($reviewDetails['appt_notes'])): ?>
                                    <div class="mt-3 pt-3 border-t border-slate-200">
                                        <p class="text-xs text-slate-500 font-semibold mb-1">Appointment Notes</p>
                                        <p class="text-sm text-slate-700"><?php echo h($reviewDetails['appt_notes']); ?></p>
                                    </div>
                                <?php endif; ?>
                            </div>

                            <!-- Repair Job Details Section (if exists) -->
                            <?php if (!empty($reviewDetails['repair_job_id'])): ?>
                                <div class="border border-slate-200 rounded-lg p-4 bg-blue-50">
                                    <h4 class="font-bold text-xs uppercase text-slate-600 mb-3">Repair Job Details</h4>
                                    <div class="grid grid-cols-1 md:grid-cols-2 gap-3 text-sm">
                                        <div>
                                            <p class="text-xs text-slate-500 font-semibold">Priority</p>
                                            <p class="text-slate-900 font-medium"><?php echo h($reviewDetails['priority'] ?? 'Not set'); ?></p>
                                        </div>
                                        <div>
                                            <p class="text-xs text-slate-500 font-semibold">Check-in Time</p>
                                            <p class="text-slate-900 font-medium"><?php echo !empty($reviewDetails['check_in_time']) ? h(date('M d, Y h:i A', strtotime((string) $reviewDetails['check_in_time']))) : 'Not checked in'; ?></p>
                                        </div>
                                        <div>
                                            <p class="text-xs text-slate-500 font-semibold">Work Started</p>
                                            <p class="text-slate-900 font-medium"><?php echo !empty($reviewDetails['work_started_at']) ? h(date('M d, Y h:i A', strtotime((string) $reviewDetails['work_started_at']))) : 'Not started'; ?></p>
                                        </div>
                                        <div>
                                            <p class="text-xs text-slate-500 font-semibold">Estimated Finish</p>
                                            <p class="text-slate-900 font-medium"><?php echo !empty($reviewDetails['estimated_finish_at']) ? h(date('M d, Y h:i A', strtotime((string) $reviewDetails['estimated_finish_at']))) : 'Not estimated'; ?></p>
                                        </div>
                                    </div>
                                    <?php if (!empty($reviewDetails['concern']) || !empty($reviewDetails['diagnosis_notes']) || !empty($reviewDetails['progress_notes'])): ?>
                                        <div class="mt-3 pt-3 border-t border-slate-200 space-y-2">
                                            <?php if (!empty($reviewDetails['concern'])): ?>
                                                <div>
                                                    <p class="text-xs text-slate-500 font-semibold">Concern</p>
                                                    <p class="text-sm text-slate-700"><?php echo h($reviewDetails['concern']); ?></p>
                                                </div>
                                            <?php endif; ?>
                                            <?php if (!empty($reviewDetails['diagnosis_notes'])): ?>
                                                <div>
                                                    <p class="text-xs text-slate-500 font-semibold">Diagnosis</p>
                                                    <p class="text-sm text-slate-700"><?php echo h($reviewDetails['diagnosis_notes']); ?></p>
                                                </div>
                                            <?php endif; ?>
                                            <?php if (!empty($reviewDetails['progress_notes'])): ?>
                                                <div>
                                                    <p class="text-xs text-slate-500 font-semibold">Progress</p>
                                                    <p class="text-sm text-slate-700"><?php echo h($reviewDetails['progress_notes']); ?></p>
                                                </div>
                                            <?php endif; ?>
                                        </div>
                                    <?php endif; ?>
                                    <?php if ((float) ($reviewDetails['labor_total'] ?? 0) > 0 || (float) ($reviewDetails['parts_total'] ?? 0) > 0): ?>
                                        <div class="mt-3 pt-3 border-t border-slate-200 grid grid-cols-3 gap-2 text-sm">
                                            <div>
                                                <p class="text-xs text-slate-500">Labor</p>
                                                <p class="font-semibold text-slate-900">₱<?php echo number_format((float) ($reviewDetails['labor_total'] ?? 0), 2); ?></p>
                                            </div>
                                            <div>
                                                <p class="text-xs text-slate-500">Parts</p>
                                                <p class="font-semibold text-slate-900">₱<?php echo number_format((float) ($reviewDetails['parts_total'] ?? 0), 2); ?></p>
                                            </div>
                                            <div>
                                                <p class="text-xs text-slate-500">Total</p>
                                                <p class="font-bold text-blue-700">₱<?php echo number_format((float) ($reviewDetails['grand_total'] ?? 0), 2); ?></p>
                                            </div>
                                        </div>
                                    <?php endif; ?>
                                </div>
                            <?php endif; ?>

                            <!-- Edit Form Section -->
                            <div class="border border-slate-200 rounded-lg p-4 bg-white">
                                <h4 class="font-bold text-xs uppercase text-slate-600 mb-4">Update Appointment & Job Details</h4>
                                <form method="post" class="grid grid-cols-1 md:grid-cols-3 gap-4">
                            <input type="hidden" name="csrf_token" value="<?php echo h($csrfToken); ?>" />
                            <input type="hidden" name="save_review" value="1" />
                            <input type="hidden" name="appointment_id" value="<?php echo (int) $reviewDetails['appointment_id']; ?>" />

                            <div>
                                <label class="text-xs font-bold uppercase text-slate-500">Appointment Status</label>
                                <select name="appointment_status" class="mt-1 w-full rounded-lg border-slate-300 text-sm bg-slate-50" required>
                                    <?php foreach ($allowedStatuses as $statusOption): ?>
                                        <option value="<?php echo h($statusOption); ?>" <?php echo $reviewDetails['appt_status'] === $statusOption ? 'selected' : ''; ?>><?php echo h($statusOption); ?></option>
                                    <?php endforeach; ?>
                                </select>
                                <p class="mt-1 text-[11px] text-slate-500">This will auto-sync based on the selected job status after saving.</p>
                            </div>

                            <div>
                                <label class="text-xs font-bold uppercase text-slate-500">Job Status</label>
                                <select name="job_status" class="mt-1 w-full rounded-lg border-slate-300 text-sm" required>
                                    <?php foreach ($allowedJobStatuses as $jobStatusOption): ?>
                                        <option value="<?php echo h($jobStatusOption); ?>" <?php echo $reviewForm['job_status'] === $jobStatusOption ? 'selected' : ''; ?>><?php echo h($jobStatusOption); ?></option>
                                    <?php endforeach; ?>
                                </select>
                            </div>

                            <div>
                                <label class="text-xs font-bold uppercase text-slate-500">Bay Number</label>
                                <select name="bay_no" id="bay_no_select" class="mt-1 w-full rounded-lg border-slate-300 text-sm" required>
                                    <option value="">Select bay</option>
                                    <?php foreach ($availableBays as $bayOption): ?>
                                        <option value="<?php echo h($bayOption); ?>" <?php echo $reviewForm['bay_no'] === $bayOption ? 'selected' : ''; ?>><?php echo h($bayOption); ?></option>
                                    <?php endforeach; ?>
                                    <option value="__custom__" <?php echo $reviewForm['bay_no'] === '__custom__' ? 'selected' : ''; ?>>Add New Bay...</option>
                                </select>
                                <div id="custom_bay_wrapper" class="mt-3 <?php echo $reviewForm['bay_no'] === '__custom__' ? '' : 'hidden'; ?>">
                                    <label class="text-xs font-bold uppercase text-slate-500">Custom Bay Number</label>
                                    <input
                                        type="text"
                                        name="bay_no_custom"
                                        id="bay_no_custom"
                                        value="<?php echo h($reviewForm['bay_no_custom']); ?>"
                                        placeholder="e.g. Bay 9"
                                        class="mt-1 w-full rounded-lg border-slate-300 text-sm">
                                    <p class="mt-1 text-xs text-slate-500">Use this when you need to add a bay that is not in the preset list.</p>
                                </div>
                            </div>

                            <div class="md:col-span-2">
                                <label class="text-xs font-bold uppercase text-slate-500">Technician Assigned</label>
                                <select name="assigned_technician" class="mt-1 w-full rounded-lg border-slate-300 text-sm" required>
                                    <option value="">Select technician</option>
                                    <?php foreach ($technicianOptions as $technicianOption): ?>
                                        <?php
                                            $technicianFullName = trim(((string) ($technicianOption['first_name'] ?? '')) . ' ' . ((string) ($technicianOption['last_name'] ?? '')));
                                            $technicianLabel = $technicianFullName !== '' ? $technicianFullName : (string) ($technicianOption['username'] ?? '');
                                            $technicianRole = trim((string) ($technicianOption['role_name'] ?? ''));
                                        ?>
                                        <option value="<?php echo h($technicianOption['username'] ?? ''); ?>" <?php echo ($reviewForm['assigned_technician'] ?? '') === ($technicianOption['username'] ?? '') ? 'selected' : ''; ?>>
                                            <?php echo h(($technicianRole !== '' ? $technicianRole : 'Technician') . ' - ' . ($technicianLabel !== '' ? $technicianLabel : 'Unnamed Staff')); ?>
                                        </option>
                                    <?php endforeach; ?>
                                </select>
                            </div>

                                <div class="md:col-span-3 flex justify-end gap-2 pt-2 border-t border-slate-100">
                                    <a href="appointmentadmin.php?shop=<?php echo h($shopQuery); ?>" class="px-4 py-2.5 border border-slate-300 rounded-lg text-sm text-slate-600">Cancel</a>
                                    <button type="submit" class="px-4 py-2.5 bg-blue-600 text-white rounded-lg text-sm font-semibold">Save Changes</button>
                                </div>
                                </form>
                            </div>
                        </div>
                    </section>
                </div>
            <?php endif; ?>

            <div class="grid grid-cols-1 md:grid-cols-2 lg:grid-cols-4 gap-6">
                <!-- Today's Load -->
                <div class="bg-white border border-slate-200 rounded-xl p-5 shadow-sm">
                    <div class="flex justify-between items-start mb-4">
                        <div class="p-2 bg-blue-50 rounded-lg text-blue-600">
                            <span class="material-symbols-outlined text-[20px]">today</span>
                        </div>
                    </div>
                    <p class="text-slate-500 text-xs font-bold uppercase tracking-wider mb-1">Today's Load</p>
                    <h3 class="text-2xl font-black text-black"><?php echo h($todayLoad); ?></h3>
                    <p class="text-xs text-slate-400 mt-1">Appointments for today</p>
                </div>
                <!-- Completed Today -->
                <div class="bg-white border border-slate-200 rounded-xl p-5 shadow-sm">
                    <div class="flex justify-between items-start mb-4">
                        <div class="p-2 bg-green-50 rounded-lg text-green-600">
                            <span class="material-symbols-outlined text-[20px]">check_circle</span>
                        </div>
                    </div>
                    <p class="text-slate-500 text-xs font-bold uppercase tracking-wider mb-1">Completed Today</p>
                    <h3 class="text-2xl font-black text-black"><?php echo h($todayCompleted); ?></h3>
                    <p class="text-xs text-slate-400 mt-1">Finished service visits</p>
                </div>
                <!-- Pending Bookings -->
                <div class="bg-white border border-slate-200 rounded-xl p-5 shadow-sm">
                    <div class="flex justify-between items-start mb-4">
                        <div class="p-2 bg-amber-50 rounded-lg text-amber-600">
                            <span class="material-symbols-outlined text-[20px]">schedule</span>
                        </div>
                    </div>
                    <p class="text-slate-500 text-xs font-bold uppercase tracking-wider mb-1">Pending Bookings</p>
                    <h3 class="text-2xl font-black text-black"><?php echo h($pendingCount); ?></h3>
                    <p class="text-xs text-slate-400 mt-1">Need action</p>
                </div>
                <!-- Next Available -->
                <div class="bg-white border border-slate-200 rounded-xl p-5 shadow-sm">
                    <div class="flex justify-between items-start mb-4">
                        <div class="p-2 bg-purple-50 rounded-lg text-purple-600">
                            <span class="material-symbols-outlined text-[20px]">calendar_month</span>
                        </div>
                    </div>
                    <p class="text-slate-500 text-xs font-bold uppercase tracking-wider mb-1">Next Available</p>
                    <h3 class="text-sm font-bold text-black mt-2"><?php echo h($nextAvailable); ?></h3>
                    <p class="text-xs text-slate-400 mt-1">This week total: <?php echo h($weekCount); ?></p>
                </div>
            </div>

            <section class="bg-white rounded-xl border border-slate-200 shadow-sm overflow-hidden">
                <div class="p-5 border-b border-slate-100">
                    <div class="flex flex-wrap items-center justify-between gap-3 mb-4">
                        <h3 class="font-bold text-black text-lg">Appointments List</h3>
                        <a href="appointmentadmin.php?shop=<?php echo h($shopQuery); ?>&create_appointment=1" onclick="new Image().src='appointmentadmin.php?shop=<?php echo h($shopQuery); ?>&audit_action=open_create_modal&audit_only=1';" class="inline-flex items-center gap-2 px-4 py-2.5 bg-blue-600 text-white rounded-lg text-sm font-semibold hover:bg-blue-700 transition-colors">
                            <span class="material-symbols-outlined text-base">add</span>
                            Create Appointment
                        </a>
                    </div>
                    <form method="get" class="grid grid-cols-1 md:grid-cols-4 gap-3 items-end">
                        <input type="hidden" name="shop" value="<?php echo h($loginSlug); ?>">
                        <div class="md:col-span-2">
                            <label class="text-xs font-bold uppercase text-slate-500">Search</label>
                            <input name="search" value="<?php echo h($search); ?>" placeholder="Customer, vehicle, plate, service, notes" class="mt-1 w-full rounded-lg border-slate-300 text-sm" />
                        </div>
                        <div>
                            <label class="text-xs font-bold uppercase text-slate-500">Status</label>
                            <select name="status" class="mt-1 w-full rounded-lg border-slate-300 text-sm">
                                <?php foreach (array_merge(['All'], $listStatuses) as $status): ?>
                                    <option value="<?php echo h($status); ?>" <?php echo $statusFilter === $status ? 'selected' : ''; ?>><?php echo h($status); ?></option>
                                <?php endforeach; ?>
                            </select>
                        </div>
                        <div class="flex gap-2">
                            <button class="w-full px-4 py-2.5 bg-blue-600 text-white rounded-lg text-sm font-semibold hover:bg-blue-700 transition-colors" type="submit">Apply</button>
                            <a href="appointmentadmin.php?shop=<?php echo $shopQuery; ?>" class="px-4 py-2.5 border border-slate-300 rounded-lg text-sm text-slate-600 hover:bg-slate-50 transition-colors">Reset</a>
                        </div>
                    </form>
                </div>

                <div class="overflow-x-auto">
                    <table class="w-full text-left text-sm">
                        <thead class="bg-gray-100 text-black uppercase text-xs">
                            <tr>
                                <th class="px-5 py-3">Customer</th>
                                <th class="px-5 py-3">Vehicle</th>
                                <th class="px-5 py-3">Services</th>
                                <th class="px-5 py-3">Date / Time</th>
                                <th class="px-5 py-3">Created At</th>
                                <th class="px-5 py-3">Status</th>
                                <th class="px-5 py-3">Amount</th>
                                <th class="px-5 py-3 text-right">Actions</th>
                            </tr>
                        </thead>
                        <tbody class="divide-y divide-gray-200">
                            <?php if (count($appointments) === 0): ?>
                                <tr>
                                    <td colspan="8" class="px-5 py-10 text-center text-slate-500">No appointments found for this filter.</td>
                                </tr>
                            <?php else: ?>
                                <?php foreach ($appointments as $row): ?>
                                    <?php
                                        $vehicleText = trim(((string) $row['year_model']) . ' ' . ((string) $row['brand']) . ' ' . ((string) $row['model']));
                                        if ($vehicleText === '') {
                                            $vehicleText = 'Vehicle #' . (int) $row['vehicle_id'];
                                        }
                                        $plate = trim((string) ($row['plate_number'] ?? ''));
                                        $status = (string) ($row['status'] ?? 'Pending');
                                        $badge = 'bg-slate-100 text-slate-700';
                                        if ($status === 'Pending') {
                                            $badge = 'bg-amber-100 text-amber-700';
                                        } elseif ($status === 'Confirmed') {
                                            $badge = 'bg-blue-100 text-blue-700';
                                        } elseif ($status === 'In Progress') {
                                            $badge = 'bg-indigo-100 text-indigo-700';
                                        } elseif ($status === 'Completed') {
                                            $badge = 'bg-green-100 text-green-700';
                                        } elseif ($status === 'Cancelled') {
                                            $badge = 'bg-red-100 text-red-700';
                                        }
                                    ?>
                                    <tr class="hover:bg-gray-50">
                                        <td class="px-5 py-4">
                                            <div class="font-semibold text-black"><?php echo h($row['customer_name']); ?></div>
                                            <div class="text-xs text-slate-500">Appointment #<?php echo h($row['appointment_id']); ?></div>
                                        </td>
                                        <td class="px-5 py-4">
                                            <div class="text-black"><?php echo h($vehicleText); ?></div>
                                            <div class="text-xs text-slate-500"><?php echo $plate !== '' ? h($plate) : 'No plate'; ?></div>
                                        </td>
                                        <td class="px-5 py-4 text-black max-w-xs"><?php echo h($row['requested_services']); ?></td>
                                        <td class="px-5 py-4">
                                            <div class="font-semibold text-black"><?php echo h(date('M d, Y', strtotime((string) $row['appointment_date']))); ?></div>
                                            <div class="text-xs text-slate-500"><?php echo h(date('h:i A', strtotime((string) $row['appointment_time']))); ?></div>
                                        </td>
                                        <td class="px-5 py-4">
                                            <?php if (!empty($row['created_at'])): ?>
                                                <div class="font-semibold text-black"><?php echo h(date('M d, Y', strtotime((string) $row['created_at']))); ?></div>
                                                <div class="text-xs text-slate-500"><?php echo h(date('h:i A', strtotime((string) $row['created_at']))); ?></div>
                                            <?php else: ?>
                                                <span class="text-xs text-slate-400">N/A</span>
                                            <?php endif; ?>
                                        </td>
                                        <td class="px-5 py-4">
                                            <span class="px-2.5 py-1.5 rounded-full text-xs font-bold <?php echo h($badge); ?>"><?php echo h($status); ?></span>
                                        </td>
                                        <td class="px-5 py-4 font-semibold text-black">
                                            <?php
                                                $amount = $row['total_amount'];
                                                echo $amount !== null ? '₱' . number_format((float) $amount, 2) : 'N/A';
                                            ?>
                                        </td>
                                        <td class="px-5 py-4">
                                            <div class="flex items-center justify-end">
                                                <a href="appointmentadmin.php?<?php echo h(http_build_query(array_filter([
                                                    'shop' => $loginSlug,
                                                    'search' => $search,
                                                    'status' => $statusFilter,
                                                    'history_range' => $historyRange,
                                                    'history_search' => $historySearch,
                                                    'review' => (int) $row['appointment_id'],
                                                ], static fn ($value) => $value !== ''))); ?>" onclick="new Image().src='appointmentadmin.php?shop=<?php echo h($shopQuery); ?>&audit_action=open_review_modal&audit_only=1&appointment_id=<?php echo (int) $row['appointment_id']; ?>';" class="inline-flex items-center gap-1 px-3 py-1.5 bg-slate-800 text-white rounded text-xs font-semibold hover:bg-slate-900">
                                                    <span class="material-symbols-outlined text-sm">rate_review</span>
                                                    Review
                                                </a>
                                            </div>
                                        </td>
                                    </tr>
                                <?php endforeach; ?>
                            <?php endif; ?>
                        </tbody>
                    </table>
                </div>
            </section>

            <section class="bg-white rounded-xl border border-slate-200 shadow-sm overflow-hidden">
                <div class="p-5 border-b border-slate-100 flex items-center justify-between">
                    <h3 class="font-bold">Upcoming Confirmed Queue</h3>
                    <span class="text-xs text-slate-500">Next 5 appointments</span>
                </div>
                <div class="divide-y divide-slate-100">
                    <?php if (count($upcomingAppointments) === 0): ?>
                        <div class="px-5 py-8 text-sm text-slate-500">No confirmed appointments in queue.</div>
                    <?php else: ?>
                        <?php foreach ($upcomingAppointments as $item): ?>
                            <div class="px-5 py-4">
                                <div class="flex items-center justify-between gap-4">
                                    <div>
                                        <div class="font-semibold text-slate-900"><?php echo h($item['customer_name']); ?> - <?php echo h(trim((string) $item['vehicle_name'])); ?></div>
                                        <div class="text-xs text-slate-500 mt-1"><?php echo h($item['requested_services']); ?></div>
                                    </div>
                                    <div class="text-right shrink-0">
                                        <div class="text-xs font-bold text-blue-700"><?php echo h(date('M d, Y', strtotime((string) $item['appointment_date']))); ?></div>
                                        <div class="text-xs text-slate-500"><?php echo h(date('h:i A', strtotime((string) $item['appointment_time']))); ?></div>
                                    </div>
                                </div>
                            </div>
                        <?php endforeach; ?>
                    <?php endif; ?>
                </div>
            </section>

            <section class="bg-white rounded-xl border border-slate-200 shadow-sm overflow-hidden">
                <div class="p-5 border-b border-slate-100 flex flex-wrap items-center justify-between gap-3">
                    <h3 class="font-bold text-slate-900">Appointment History (Completed & Cancelled)</h3>
                    <span class="text-xs text-slate-500"><?php echo number_format($totalHistoryRecords); ?> total record(s) - Page <?php echo $currentPage; ?> of <?php echo max(1, $totalHistoryPages); ?></span>
                </div>

                <div class="px-5 py-4 border-b border-slate-100 bg-slate-50/40">
                    <div class="flex flex-wrap items-center gap-2 mb-3">
                        <?php
                            $historyBaseParams = [
                                'shop' => $loginSlug,
                                'search' => $search,
                                'status' => $statusFilter,
                                'history_search' => $historySearch,
                            ];
                            $historyLabels = [
                                'all' => 'All History',
                                'today' => 'Today',
                                'week' => 'This Week',
                                'month' => 'This Month',
                                'range' => 'Custom Range',
                            ];
                        ?>
                        <?php foreach ($historyLabels as $rangeValue => $rangeLabel): ?>
                            <?php
                                $isActiveRange = $historyRange === $rangeValue;
                                $rangeUrl = 'appointmentadmin.php?' . http_build_query(array_filter(array_merge($historyBaseParams, ['history_range' => $rangeValue]), static fn ($value) => $value !== ''));
                            ?>
                            <a
                                href="<?php echo h($rangeUrl); ?>"
                                class="px-3 py-1.5 rounded-lg text-xs font-semibold border <?php echo $isActiveRange ? 'bg-blue-600 text-white border-blue-600' : 'bg-white text-slate-600 border-slate-300 hover:bg-slate-100'; ?>">
                                <?php echo h($rangeLabel); ?>
                            </a>
                        <?php endforeach; ?>
                    </div>
                    <form method="get" class="flex flex-wrap items-center gap-2" id="historyFilterForm">
                        <input type="hidden" name="shop" value="<?php echo h($loginSlug); ?>">
                        <input type="hidden" name="search" value="<?php echo h($search); ?>">
                        <input type="hidden" name="status" value="<?php echo h($statusFilter); ?>">
                        <input type="hidden" name="history_range" value="<?php echo h($historyRange); ?>" id="history_range_input">
                        <input
                            type="text"
                            name="history_search"
                            value="<?php echo h($historySearch); ?>"
                            placeholder="Search completed or cancelled history..."
                            class="w-full md:w-96 rounded-lg border-slate-300 text-sm">
                        <div class="flex items-center gap-2">
                            <label class="text-xs text-slate-600">From</label>
                            <input type="date" name="date_from" id="date_from" value="<?php echo h($historyDateFrom); ?>" class="rounded-lg border-slate-300 text-sm">
                            <label class="text-xs text-slate-600">To</label>
                            <input type="date" name="date_to" id="date_to" value="<?php echo h($historyDateTo); ?>" class="rounded-lg border-slate-300 text-sm">
                        </div>
                        <button type="submit" class="px-4 py-2 bg-blue-600 text-white rounded-lg text-sm font-semibold">Apply</button>
                        <a
                            href="appointmentadmin.php?<?php echo h(http_build_query(array_filter([
                                'shop' => $loginSlug,
                                'search' => $search,
                                'status' => $statusFilter,
                                'history_range' => 'all',
                            ], static fn ($value) => $value !== ''))); ?>"
                            class="px-4 py-2 border border-slate-300 rounded-lg text-sm text-slate-600">Reset</a>
                    </form>
                </div>

                <div class="overflow-x-auto">
                    <table class="w-full text-left text-sm">
                        <thead class="bg-slate-50 text-slate-500 uppercase text-xs">
                            <tr>
                                <th class="px-5 py-3">Appointment</th>
                                <th class="px-5 py-3">Customer</th>
                                <th class="px-5 py-3">Vehicle</th>
                                <th class="px-5 py-3">Services</th>
                                <th class="px-5 py-3">Date / Time</th>
                                <th class="px-5 py-3">Amount</th>
                            </tr>
                        </thead>
                        <tbody class="divide-y divide-slate-100">
                            <?php if (count($completedHistory) === 0): ?>
                                <tr>
                                    <td colspan="6" class="px-5 py-10 text-center text-slate-500">No completed or cancelled appointments found for this history filter.</td>
                                </tr>
                            <?php else: ?>
                                <?php foreach ($completedHistory as $historyItem): ?>
                                    <?php
                                        $historyVehicle = trim(((string) ($historyItem['year_model'] ?? '')) . ' ' . ((string) ($historyItem['brand'] ?? '')) . ' ' . ((string) ($historyItem['model'] ?? '')));
                                        if ($historyVehicle === '') {
                                            $historyVehicle = 'Vehicle record';
                                        }
                                        $historyAppointmentStatus = (string) ($historyItem['status'] ?? '');
                                        $historyJobStatus = (string) ($historyItem['job_status'] ?? '');
                                        $historyDisplayStatus = ($historyAppointmentStatus === 'Cancelled' || $historyJobStatus === 'Cancelled') ? 'Cancelled' : 'Completed';
                                        $historyStatusClass = $historyDisplayStatus === 'Cancelled' ? 'text-red-600' : 'text-emerald-600';
                                    ?>
                                    <tr class="hover:bg-slate-50">
                                        <td class="px-5 py-4">
                                            <div class="font-semibold text-slate-900">#<?php echo (int) $historyItem['appointment_id']; ?></div>
                                            <div class="text-xs font-semibold <?php echo h($historyStatusClass); ?>"><?php echo h($historyDisplayStatus); ?></div>
                                        </td>
                                        <td class="px-5 py-4 text-slate-700"><?php echo h($historyItem['customer_name']); ?></td>
                                        <td class="px-5 py-4">
                                            <div class="text-slate-700"><?php echo h($historyVehicle); ?></div>
                                            <div class="text-xs text-slate-500"><?php echo !empty($historyItem['plate_number']) ? h($historyItem['plate_number']) : 'No plate'; ?></div>
                                        </td>
                                        <td class="px-5 py-4 text-slate-700 max-w-xs"><?php echo h($historyItem['requested_services']); ?></td>
                                        <td class="px-5 py-4">
                                            <div class="font-semibold"><?php echo h(date('M d, Y', strtotime((string) $historyItem['appointment_date']))); ?></div>
                                            <div class="text-xs text-slate-500"><?php echo h(date('h:i A', strtotime((string) $historyItem['appointment_time']))); ?></div>
                                        </td>
                                        <td class="px-5 py-4 font-semibold text-slate-900"><?php echo '₱' . number_format((float) ($historyItem['total_amount'] ?? 0), 2); ?></td>
                                    </tr>
                                <?php endforeach; ?>
                            <?php endif; ?>
                        </tbody>
                    </table>
                </div>

                <!-- Pagination Controls -->
                <?php if ($totalHistoryPages > 1): ?>
                <div class="px-5 py-4 border-t border-slate-100 bg-slate-50/40 flex flex-wrap items-center justify-between gap-4">
                    <div class="text-xs text-slate-600">
                        Showing <?php echo (($currentPage - 1) * $itemsPerPage) + 1; ?> to <?php echo min($currentPage * $itemsPerPage, $totalHistoryRecords); ?> of <?php echo $totalHistoryRecords; ?> records
                    </div>
                    <div class="flex items-center gap-2">
                        <?php
                            $baseParams = [
                                'shop' => $loginSlug,
                                'search' => $search,
                                'status' => $statusFilter,
                                'history_range' => $historyRange,
                                'history_search' => $historySearch,
                            ];
                            $prevPage = $currentPage - 1;
                            $nextPage = $currentPage + 1;
                            $prevParams = array_filter(array_merge($baseParams, ['history_page' => $prevPage]), static fn ($value) => $value !== '');
                            $nextParams = array_filter(array_merge($baseParams, ['history_page' => $nextPage]), static fn ($value) => $value !== '');
                        ?>
                        
                        <?php if ($currentPage > 1): ?>
                            <a href="appointmentadmin.php?<?php echo h(http_build_query($prevParams)); ?>" class="px-3 py-2 border border-slate-300 rounded-lg text-sm font-medium text-slate-600 hover:bg-slate-100 transition-colors">
                                <span class="material-symbols-outlined text-lg">chevron_left</span>
                            </a>
                        <?php else: ?>
                            <button disabled class="px-3 py-2 border border-slate-200 rounded-lg text-sm font-medium text-slate-400 cursor-not-allowed opacity-50">
                                <span class="material-symbols-outlined text-lg">chevron_left</span>
                            </button>
                        <?php endif; ?>

                        <div class="flex items-center gap-1">
                            <?php
                                $startPage = max(1, $currentPage - 2);
                                $endPage = min($totalHistoryPages, $currentPage + 2);
                                
                                if ($startPage > 1): ?>
                                    <a href="appointmentadmin.php?<?php echo h(http_build_query(array_filter(array_merge($baseParams, ['history_page' => 1]), static fn ($value) => $value !== ''))); ?>" class="px-2.5 py-1.5 rounded text-sm text-slate-600 hover:bg-slate-100">1</a>
                                    <?php if ($startPage > 2): ?>
                                        <span class="text-slate-400">...</span>
                                    <?php endif; ?>
                                <?php endif; ?>

                                <?php for ($page = $startPage; $page <= $endPage; $page++): ?>
                                    <?php if ($page === $currentPage): ?>
                                        <button disabled class="px-2.5 py-1.5 rounded text-sm font-semibold bg-blue-600 text-white"><?php echo $page; ?></button>
                                    <?php else: ?>
                                        <a href="appointmentadmin.php?<?php echo h(http_build_query(array_filter(array_merge($baseParams, ['history_page' => $page]), static fn ($value) => $value !== ''))); ?>" class="px-2.5 py-1.5 rounded text-sm text-slate-600 hover:bg-slate-100"><?php echo $page; ?></a>
                                    <?php endif; ?>
                                <?php endfor; ?>

                                <?php if ($endPage < $totalHistoryPages): ?>
                                    <?php if ($endPage < $totalHistoryPages - 1): ?>
                                        <span class="text-slate-400">...</span>
                                    <?php endif; ?>
                                    <a href="appointmentadmin.php?<?php echo h(http_build_query(array_filter(array_merge($baseParams, ['history_page' => $totalHistoryPages]), static fn ($value) => $value !== ''))); ?>" class="px-2.5 py-1.5 rounded text-sm text-slate-600 hover:bg-slate-100"><?php echo $totalHistoryPages; ?></a>
                                <?php endif; ?>
                        </div>

                        <?php if ($currentPage < $totalHistoryPages): ?>
                            <a href="appointmentadmin.php?<?php echo h(http_build_query($nextParams)); ?>" class="px-3 py-2 border border-slate-300 rounded-lg text-sm font-medium text-slate-600 hover:bg-slate-100 transition-colors">
                                <span class="material-symbols-outlined text-lg">chevron_right</span>
                            </a>
                        <?php else: ?>
                            <button disabled class="px-3 py-2 border border-slate-200 rounded-lg text-sm font-medium text-slate-400 cursor-not-allowed opacity-50">
                                <span class="material-symbols-outlined text-lg">chevron_right</span>
                            </button>
                        <?php endif; ?>
                    </div>
                </div>
                <?php endif; ?>
            </section>
        </div>
    </main>
    </div>
</body>

<script>
    const createUserSelect = document.getElementById('create_user_id');
    const createVehicleSelect = document.getElementById('create_vehicle_id');
    const createDateInput = document.querySelector('input[name="appointment_date"]');
    const appointmentTimeInput = document.getElementById('appointment_time_input');
    const timeSlotButtons = document.querySelectorAll('.time-slot-btn');

    function filterVehiclesByCustomer() {
        if (!createUserSelect || !createVehicleSelect) {
            return;
        }

        const selectedUserId = createUserSelect.value;
        const options = createVehicleSelect.querySelectorAll('option[data-user-id]');
        let hasVisibleOption = false;

        options.forEach((option) => {
            const matches = selectedUserId !== '' && option.getAttribute('data-user-id') === selectedUserId;
            option.hidden = !matches;
            if (!matches && option.selected) {
                option.selected = false;
            }
            if (matches) {
                hasVisibleOption = true;
            }
        });

        if (!hasVisibleOption) {
            createVehicleSelect.value = '';
        }
    }

    if (createUserSelect && createVehicleSelect) {
        filterVehiclesByCustomer();
        createUserSelect.addEventListener('change', filterVehiclesByCustomer);
    }

    function setSelectedTimeSlot(slot) {
        if (!appointmentTimeInput) {
            return;
        }

        appointmentTimeInput.value = slot;
        timeSlotButtons.forEach((button) => {
            const isSelected = button.getAttribute('data-time-slot') === slot;
            button.classList.toggle('bg-slate-800', isSelected);
            button.classList.toggle('text-white', isSelected);
            button.classList.toggle('border-slate-800', isSelected);
            button.classList.toggle('bg-white', !isSelected);
            button.classList.toggle('text-slate-700', !isSelected);
            button.classList.toggle('border-slate-200', !isSelected);
        });
    }

    function updateSlotAvailability() {
        if (!createDateInput || !timeSlotButtons.length) {
            return;
        }

        const selectedDate = createDateInput.value;
        const now = new Date();
        const today = now.toISOString().slice(0, 10);
        const currentMinutes = now.getHours() * 60 + now.getMinutes();

        timeSlotButtons.forEach((button) => {
            const slot = button.getAttribute('data-time-slot') || '';
            const parts = slot.split(':');
            const slotMinutes = (parseInt(parts[0] || '0', 10) * 60) + parseInt(parts[1] || '0', 10);
            const disableSlot = selectedDate === today && slotMinutes <= currentMinutes;

            button.disabled = disableSlot;
            button.classList.toggle('opacity-50', disableSlot);
            button.classList.toggle('cursor-not-allowed', disableSlot);

            if (disableSlot && appointmentTimeInput && appointmentTimeInput.value === slot) {
                appointmentTimeInput.value = '';
            }
        });

        if (appointmentTimeInput && appointmentTimeInput.value === '') {
            const firstEnabled = Array.from(timeSlotButtons).find((button) => !button.disabled);
            if (firstEnabled) {
                setSelectedTimeSlot(firstEnabled.getAttribute('data-time-slot') || '');
            }
        }
    }

    timeSlotButtons.forEach((button) => {
        button.addEventListener('click', () => {
            if (button.disabled) {
                return;
            }
            setSelectedTimeSlot(button.getAttribute('data-time-slot') || '');
        });
    });

    if (createDateInput) {
        createDateInput.addEventListener('change', updateSlotAvailability);
    }

    if (appointmentTimeInput && appointmentTimeInput.value) {
        setSelectedTimeSlot(appointmentTimeInput.value);
    }
    updateSlotAvailability();

    const bayNoSelect = document.getElementById('bay_no_select');
    const customBayWrapper = document.getElementById('custom_bay_wrapper');
    const customBayInput = document.getElementById('bay_no_custom');

    function toggleCustomBayField() {
        if (!bayNoSelect || !customBayWrapper || !customBayInput) {
            return;
        }

        const isCustom = bayNoSelect.value === '__custom__';
        customBayWrapper.classList.toggle('hidden', !isCustom);
        customBayInput.required = isCustom;

        if (!isCustom) {
            customBayInput.value = '';
        }
    }

    if (bayNoSelect) {
        bayNoSelect.addEventListener('change', toggleCustomBayField);
        toggleCustomBayField();
    }

    // Dropdown menu click handler
    document.querySelectorAll('.settings-dropdown-btn').forEach(button => {
        button.addEventListener('click', function(e) {
            e.preventDefault();
            const dropdown = document.querySelector('[data-dropdown="settings"].settings-dropdown');
            if (dropdown) {
                dropdown.classList.toggle('hidden');
            }
        });
    });

    // Close dropdown when clicking outside
    document.addEventListener('click', function(e) {
        const dropdownBtn = document.querySelector('.settings-dropdown-btn');
        const dropdown = document.querySelector('[data-dropdown="settings"].settings-dropdown');
        if (dropdown && dropdownBtn && !dropdownBtn.contains(e.target) && !dropdown.contains(e.target)) {
            dropdown.classList.add('hidden');
        }
    });

    // History date range quick behavior: when user fills dates, mark history_range as 'range'
    const historyForm = document.getElementById('historyFilterForm');
    if (historyForm) {
        const dateFrom = document.getElementById('date_from');
        const dateTo = document.getElementById('date_to');
        const rangeInput = document.getElementById('history_range_input');
        historyForm.addEventListener('submit', function() {
            if (dateFrom && dateTo && dateFrom.value !== '' && dateTo.value !== '') {
                if (rangeInput) rangeInput.value = 'range';
            }
        });
    }
</script>

<script>
(function() {
    const sidebarToggle = document.getElementById('sidebarToggle');
    const sidebar = document.getElementById('sidebar');
    const sidebarOverlay = document.getElementById('sidebarOverlay');
    const navLinks = document.querySelectorAll('aside a');

    if (sidebarToggle) {
        sidebarToggle.addEventListener('click', function() {
            sidebar.classList.toggle('-translate-x-full');
            sidebarOverlay.classList.toggle('hidden');
        });
    }

    if (sidebarOverlay) {
        sidebarOverlay.addEventListener('click', function() {
            sidebar.classList.add('-translate-x-full');
            sidebarOverlay.classList.add('hidden');
        });
    }

    navLinks.forEach(link => {
        link.addEventListener('click', function() {
            if (window.innerWidth < 768) {
                sidebar.classList.add('-translate-x-full');
                sidebarOverlay.classList.add('hidden');
            }
        });
    });

    window.addEventListener('resize', function() {
        if (window.innerWidth >= 768) {
            sidebar.classList.remove('-translate-x-full');
            sidebarOverlay.classList.add('hidden');
        }
    });
})();
</script>

</html>