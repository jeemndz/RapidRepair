<?php
/**
 * Test Suite for appointmentcrud.php API
 * Tests all endpoints and scenarios
 * 
 * Usage: php test_appointment.php
 * or navigate to http://localhost:8000/test_appointment.php in a browser
 */

// Configure these values for your environment
define('API_URL', 'http://localhost:8000/appointmentcrud.php');
define('TENANT_ID', 1);
define('TEST_USER_ID', 1);
define('TEST_VEHICLE_ID', 1);
define('TEST_SERVICE_IDS', [1, 2]); // Adjust based on your services

class AppointmentAPITester
{
    private $baseUrl;
    private $testResults = [];
    private $createdAppointmentId = null;

    public function __construct($baseUrl)
    {
        $this->baseUrl = rtrim($baseUrl, '/');
    }

    /**
     * Make HTTP request to API
     */
    private function makeRequest($method = 'GET', $params = [], $postData = null)
    {
        $url = $this->baseUrl;

        if ($method === 'GET' && !empty($params)) {
            $url .= '?' . http_build_query($params);
        }

        $options = [
            'http' => [
                'method' => $method,
                'header' => "Content-Type: application/json\r\n",
                'timeout' => 10
            ]
        ];

        if ($method === 'POST' && $postData !== null) {
            $jsonData = json_encode($postData);
            $options['http']['content'] = $jsonData;
            $options['http']['header'] .= 'Content-Length: ' . strlen($jsonData) . "\r\n";
        }

        $context = stream_context_create($options);

        try {
            $response = file_get_contents($url, false, $context);
            return json_decode($response, true);
        } catch (Exception $e) {
            return ['status' => 'error', 'message' => 'Request failed: ' . $e->getMessage()];
        }
    }

    /**
     * Test: Create appointment via POST
     */
    public function testCreateAppointmentPOST()
    {
        echo "\n=== TEST: Create Appointment (POST) ===\n";

        $postData = [
            'action' => 'create',
            'tenantID' => TENANT_ID,
            'user_id' => TEST_USER_ID,
            'vehicle_id' => TEST_VEHICLE_ID,
            'appointment_date' => date('Y-m-d', strtotime('+1 day')),
            'appointment_time' => '10:00',
            'notes' => 'Test appointment via POST',
            'service_ids' => TEST_SERVICE_IDS,
            'total_amount' => 100.00
        ];

        echo "POST Data: " . json_encode($postData, JSON_PRETTY_PRINT) . "\n";

        $response = $this->makeRequest('POST', [], $postData);

        echo "Response: " . json_encode($response, JSON_PRETTY_PRINT) . "\n";

        if (isset($response['data']['appointment_id'])) {
            $this->createdAppointmentId = $response['data']['appointment_id'];
            $this->logResult('CREATE_APPOINTMENT_POST', $response['status'] === 'success');
        } else {
            $this->logResult('CREATE_APPOINTMENT_POST', false);
        }

        return $response;
    }

    /**
     * Test: Create appointment via GET (Azure workaround)
     */
    public function testCreateAppointmentGET()
    {
        echo "\n=== TEST: Create Appointment (GET) ===\n";

        $params = [
            'action' => 'create',
            'tenantID' => TENANT_ID,
            'user_id' => TEST_USER_ID,
            'vehicle_id' => TEST_VEHICLE_ID,
            'appointment_date' => date('Y-m-d', strtotime('+2 days')),
            'appointment_time' => '14:30',
            'notes' => 'Test appointment via GET',
            'service_ids' => json_encode(TEST_SERVICE_IDS),
            'total_amount' => 150.00
        ];

        echo "URL Params: " . json_encode($params, JSON_PRETTY_PRINT) . "\n";

        $response = $this->makeRequest('GET', $params);

        echo "Response: " . json_encode($response, JSON_PRETTY_PRINT) . "\n";

        $this->logResult('CREATE_APPOINTMENT_GET', $response['status'] === 'success');

        return $response;
    }

    /**
     * Test: List all appointments
     */
    public function testListAppointments()
    {
        echo "\n=== TEST: List All Appointments ===\n";

        $params = [
            'action' => 'list',
            'tenantID' => TENANT_ID,
            'limit' => 50,
            'offset' => 0
        ];

        echo "Params: " . json_encode($params, JSON_PRETTY_PRINT) . "\n";

        $response = $this->makeRequest('GET', $params);

        echo "Response: " . json_encode($response, JSON_PRETTY_PRINT) . "\n";

        $this->logResult('LIST_APPOINTMENTS', $response['status'] === 'success' && isset($response['data']));

        return $response;
    }

    /**
     * Test: List appointments for specific user
     */
    public function testListUserAppointments()
    {
        echo "\n=== TEST: List User Appointments ===\n";

        $params = [
            'action' => 'list',
            'tenantID' => TENANT_ID,
            'user_id' => TEST_USER_ID,
            'limit' => 50,
            'offset' => 0
        ];

        echo "Params: " . json_encode($params, JSON_PRETTY_PRINT) . "\n";

        $response = $this->makeRequest('GET', $params);

        echo "Response: " . json_encode($response, JSON_PRETTY_PRINT) . "\n";

        $this->logResult('LIST_USER_APPOINTMENTS', $response['status'] === 'success' && isset($response['data']));

        return $response;
    }

    /**
     * Test: Update appointment status
     */
    public function testUpdateAppointment()
    {
        echo "\n=== TEST: Update Appointment Status ===\n";

        if (!$this->createdAppointmentId) {
            echo "⚠️  Skipping: No appointment ID available. Run create test first.\n";
            $this->logResult('UPDATE_APPOINTMENT', false);
            return null;
        }

        $postData = [
            'action' => 'update',
            'tenantID' => TENANT_ID,
            'appointment_id' => $this->createdAppointmentId,
            'status' => 'Confirmed'
        ];

        echo "POST Data: " . json_encode($postData, JSON_PRETTY_PRINT) . "\n";

        $response = $this->makeRequest('POST', [], $postData);

        echo "Response: " . json_encode($response, JSON_PRETTY_PRINT) . "\n";

        $this->logResult('UPDATE_APPOINTMENT', $response['status'] === 'success');

        return $response;
    }

    /**
     * Test: Delete appointment
     */
    public function testDeleteAppointment()
    {
        echo "\n=== TEST: Delete Appointment ===\n";

        if (!$this->createdAppointmentId) {
            echo "⚠️  Skipping: No appointment ID available. Run create test first.\n";
            $this->logResult('DELETE_APPOINTMENT', false);
            return null;
        }

        $params = [
            'action' => 'delete',
            'tenantID' => TENANT_ID,
            'appointment_id' => $this->createdAppointmentId
        ];

        echo "Params: " . json_encode($params, JSON_PRETTY_PRINT) . "\n";

        $response = $this->makeRequest('GET', $params);

        echo "Response: " . json_encode($response, JSON_PRETTY_PRINT) . "\n";

        $this->logResult('DELETE_APPOINTMENT', $response['status'] === 'success');

        return $response;
    }

    /**
     * Test: Create with invalid date format
     */
    public function testCreateWithInvalidDate()
    {
        echo "\n=== TEST: Create with Invalid Date (Error Case) ===\n";

        $postData = [
            'action' => 'create',
            'tenantID' => TENANT_ID,
            'user_id' => TEST_USER_ID,
            'vehicle_id' => TEST_VEHICLE_ID,
            'appointment_date' => 'invalid-date',
            'appointment_time' => '10:00',
            'service_ids' => TEST_SERVICE_IDS,
            'total_amount' => 100.00
        ];

        echo "POST Data: " . json_encode($postData, JSON_PRETTY_PRINT) . "\n";

        $response = $this->makeRequest('POST', [], $postData);

        echo "Response: " . json_encode($response, JSON_PRETTY_PRINT) . "\n";

        $this->logResult('CREATE_INVALID_DATE', $response['status'] === 'error');

        return $response;
    }

    /**
     * Test: Create with invalid time format
     */
    public function testCreateWithInvalidTime()
    {
        echo "\n=== TEST: Create with Invalid Time (Error Case) ===\n";

        $postData = [
            'action' => 'create',
            'tenantID' => TENANT_ID,
            'user_id' => TEST_USER_ID,
            'vehicle_id' => TEST_VEHICLE_ID,
            'appointment_date' => date('Y-m-d', strtotime('+1 day')),
            'appointment_time' => 'invalid-time',
            'service_ids' => TEST_SERVICE_IDS,
            'total_amount' => 100.00
        ];

        echo "POST Data: " . json_encode($postData, JSON_PRETTY_PRINT) . "\n";

        $response = $this->makeRequest('POST', [], $postData);

        echo "Response: " . json_encode($response, JSON_PRETTY_PRINT) . "\n";

        $this->logResult('CREATE_INVALID_TIME', $response['status'] === 'error');

        return $response;
    }

    /**
     * Test: Create with missing required fields
     */
    public function testCreateWithMissingFields()
    {
        echo "\n=== TEST: Create with Missing Fields (Error Case) ===\n";

        $postData = [
            'action' => 'create',
            'tenantID' => TENANT_ID,
            'user_id' => TEST_USER_ID,
            // Missing vehicle_id
            'appointment_date' => date('Y-m-d', strtotime('+1 day')),
            'appointment_time' => '10:00',
            'service_ids' => TEST_SERVICE_IDS,
            'total_amount' => 100.00
        ];

        echo "POST Data: " . json_encode($postData, JSON_PRETTY_PRINT) . "\n";

        $response = $this->makeRequest('POST', [], $postData);

        echo "Response: " . json_encode($response, JSON_PRETTY_PRINT) . "\n";

        $this->logResult('CREATE_MISSING_FIELDS', $response['status'] === 'error');

        return $response;
    }

    /**
     * Test: Create with empty service IDs
     */
    public function testCreateWithNoServices()
    {
        echo "\n=== TEST: Create with No Services (Error Case) ===\n";

        $postData = [
            'action' => 'create',
            'tenantID' => TENANT_ID,
            'user_id' => TEST_USER_ID,
            'vehicle_id' => TEST_VEHICLE_ID,
            'appointment_date' => date('Y-m-d', strtotime('+1 day')),
            'appointment_time' => '10:00',
            'service_ids' => [],
            'total_amount' => 0.00
        ];

        echo "POST Data: " . json_encode($postData, JSON_PRETTY_PRINT) . "\n";

        $response = $this->makeRequest('POST', [], $postData);

        echo "Response: " . json_encode($response, JSON_PRETTY_PRINT) . "\n";

        $this->logResult('CREATE_NO_SERVICES', $response['status'] === 'error');

        return $response;
    }

    /**
     * Test: Delete non-existent appointment
     */
    public function testDeleteNonExistent()
    {
        echo "\n=== TEST: Delete Non-existent Appointment (Error Case) ===\n";

        $params = [
            'action' => 'delete',
            'tenantID' => TENANT_ID,
            'appointment_id' => 999999
        ];

        echo "Params: " . json_encode($params, JSON_PRETTY_PRINT) . "\n";

        $response = $this->makeRequest('GET', $params);

        echo "Response: " . json_encode($response, JSON_PRETTY_PRINT) . "\n";

        $this->logResult('DELETE_NON_EXISTENT', $response['status'] === 'error');

        return $response;
    }

    /**
     * Test: Invalid action
     */
    public function testInvalidAction()
    {
        echo "\n=== TEST: Invalid Action (Error Case) ===\n";

        $params = [
            'action' => 'invalid_action',
            'tenantID' => TENANT_ID
        ];

        echo "Params: " . json_encode($params, JSON_PRETTY_PRINT) . "\n";

        $response = $this->makeRequest('GET', $params);

        echo "Response: " . json_encode($response, JSON_PRETTY_PRINT) . "\n";

        $this->logResult('INVALID_ACTION', $response['status'] === 'error');

        return $response;
    }

    /**
     * Log test result
     */
    private function logResult($testName, $passed)
    {
        $status = $passed ? '✅ PASS' : '❌ FAIL';
        $this->testResults[$testName] = $passed;
        echo "\n$status: $testName\n";
    }

    /**
     * Print test summary
     */
    public function printSummary()
    {
        echo "\n\n";
        echo "========================================\n";
        echo "         TEST SUMMARY REPORT\n";
        echo "========================================\n";

        $passed = array_sum(array_map(fn($v) => $v ? 1 : 0, $this->testResults));
        $total = count($this->testResults);
        $percentage = ($total > 0) ? round(($passed / $total) * 100, 2) : 0;

        foreach ($this->testResults as $test => $result) {
            $status = $result ? '✅' : '❌';
            echo "$status $test\n";
        }

        echo "\n----------------------------------------\n";
        echo "Total: $passed/$total tests passed ($percentage%)\n";
        echo "========================================\n\n";
    }

    /**
     * Run all tests
     */
    public function runAllTests()
    {
        echo "Starting Appointment API Test Suite...\n";
        echo "API URL: {$this->baseUrl}\n";
        echo "Tenant ID: " . TENANT_ID . "\n";
        echo "Test User ID: " . TEST_USER_ID . "\n";
        echo "Test Vehicle ID: " . TEST_VEHICLE_ID . "\n";

        // Success cases
        $this->testCreateAppointmentPOST();
        $this->testListAppointments();
        $this->testListUserAppointments();
        $this->testUpdateAppointment();
        $this->testDeleteAppointment();

        // Error cases
        $this->testCreateWithInvalidDate();
        $this->testCreateWithInvalidTime();
        $this->testCreateWithMissingFields();
        $this->testCreateWithNoServices();
        $this->testDeleteNonExistent();
        $this->testInvalidAction();

        $this->printSummary();
    }
}

// Run tests
if (php_sapi_name() === 'cli') {
    // CLI mode
    $tester = new AppointmentAPITester(API_URL);
    $tester->runAllTests();
} else {
    // Browser mode
    header('Content-Type: text/html; charset=utf-8');
    ?>
    <!DOCTYPE html>
    <html>
    <head>
        <title>Appointment API Test Suite</title>
        <style>
            body {
                font-family: 'Segoe UI', Tahoma, Geneva, Verdana, sans-serif;
                margin: 20px;
                background-color: #f5f5f5;
            }
            .container {
                max-width: 1200px;
                margin: 0 auto;
                background: white;
                padding: 20px;
                border-radius: 8px;
                box-shadow: 0 2px 4px rgba(0,0,0,0.1);
            }
            h1 {
                color: #333;
                border-bottom: 3px solid #007bff;
                padding-bottom: 10px;
            }
            .control-panel {
                margin-bottom: 20px;
                padding: 15px;
                background: #f9f9f9;
                border-left: 4px solid #007bff;
                border-radius: 4px;
            }
            .button-group {
                display: flex;
                gap: 10px;
                flex-wrap: wrap;
            }
            button {
                padding: 10px 15px;
                background: #007bff;
                color: white;
                border: none;
                border-radius: 4px;
                cursor: pointer;
                font-size: 14px;
            }
            button:hover {
                background: #0056b3;
            }
            button.success {
                background: #28a745;
            }
            button.success:hover {
                background: #218838;
            }
            button.danger {
                background: #dc3545;
            }
            button.danger:hover {
                background: #c82333;
            }
            .config {
                display: grid;
                grid-template-columns: repeat(auto-fit, minmax(200px, 1fr));
                gap: 15px;
                margin-bottom: 20px;
            }
            .config-item {
                display: flex;
                flex-direction: column;
            }
            .config-item label {
                font-weight: bold;
                margin-bottom: 5px;
                font-size: 12px;
                color: #666;
            }
            .config-item input {
                padding: 8px;
                border: 1px solid #ddd;
                border-radius: 4px;
            }
            pre {
                background: #f4f4f4;
                padding: 15px;
                border-radius: 4px;
                overflow-x: auto;
                border-left: 4px solid #007bff;
                margin-top: 10px;
            }
            .pass {
                color: #28a745;
                font-weight: bold;
            }
            .fail {
                color: #dc3545;
                font-weight: bold;
            }
        </style>
    </head>
    <body>
        <div class="container">
            <h1>🧪 Appointment API Test Suite</h1>
            
            <div class="control-panel">
                <h3>Configuration</h3>
                <div class="config">
                    <div class="config-item">
                        <label>API URL:</label>
                        <input type="text" id="apiUrl" value="<?php echo API_URL; ?>">
                    </div>
                    <div class="config-item">
                        <label>Tenant ID:</label>
                        <input type="number" id="tenantId" value="<?php echo TENANT_ID; ?>">
                    </div>
                    <div class="config-item">
                        <label>Test User ID:</label>
                        <input type="number" id="userId" value="<?php echo TEST_USER_ID; ?>">
                    </div>
                    <div class="config-item">
                        <label>Test Vehicle ID:</label>
                        <input type="number" id="vehicleId" value="<?php echo TEST_VEHICLE_ID; ?>">
                    </div>
                </div>
            </div>

            <div class="button-group">
                <button onclick="runAllTests()">Run All Tests</button>
                <button class="success" onclick="testCreate()">Test Create</button>
                <button class="success" onclick="testList()">Test List</button>
                <button class="success" onclick="testUpdate()">Test Update</button>
                <button class="danger" onclick="testDelete()">Test Delete</button>
                <button onclick="clearOutput()">Clear Output</button>
            </div>

            <h3>Test Output:</h3>
            <pre id="output"></pre>
        </div>

        <script>
            let testOutput = '';
            let createdAppointmentId = null;

            function log(message) {
                testOutput += message + '\n';
                document.getElementById('output').textContent = testOutput;
                document.getElementById('output').scrollTop = document.getElementById('output').scrollHeight;
            }

            function clearOutput() {
                testOutput = '';
                createdAppointmentId = null;
                document.getElementById('output').textContent = '';
            }

            async function makeRequest(method, params, postData = null) {
                let url = document.getElementById('apiUrl').value;
                
                if (method === 'GET' && Object.keys(params).length > 0) {
                    const query = new URLSearchParams(params).toString();
                    url += '?' + query;
                }

                const options = {
                    method: method,
                    headers: {
                        'Content-Type': 'application/json'
                    }
                };

                if (method === 'POST' && postData) {
                    options.body = JSON.stringify(postData);
                }

                try {
                    const response = await fetch(url, options);
                    return await response.json();
                } catch (error) {
                    return { status: 'error', message: 'Request failed: ' + error.message };
                }
            }

            async function testCreate() {
                log('\n=== TEST: Create Appointment ===');
                
                const postData = {
                    action: 'create',
                    tenantID: parseInt(document.getElementById('tenantId').value),
                    user_id: parseInt(document.getElementById('userId').value),
                    vehicle_id: parseInt(document.getElementById('vehicleId').value),
                    appointment_date: new Date(Date.now() + 86400000).toISOString().split('T')[0],
                    appointment_time: '10:00',
                    notes: 'Browser test appointment',
                    service_ids: [1, 2],
                    total_amount: 100.00
                };

                log('POST Data: ' + JSON.stringify(postData, null, 2));

                const response = await makeRequest('POST', {}, postData);
                log('Response: ' + JSON.stringify(response, null, 2));

                if (response.data?.appointment_id) {
                    createdAppointmentId = response.data.appointment_id;
                    log('<span class="pass">✅ CREATE PASSED</span>');
                } else {
                    log('<span class="fail">❌ CREATE FAILED</span>');
                }
            }

            async function testList() {
                log('\n=== TEST: List Appointments ===');
                
                const params = {
                    action: 'list',
                    tenantID: document.getElementById('tenantId').value,
                    limit: 50,
                    offset: 0
                };

                log('Params: ' + JSON.stringify(params, null, 2));

                const response = await makeRequest('GET', params);
                log('Response: ' + JSON.stringify(response, null, 2));

                if (response.status === 'success') {
                    log('<span class="pass">✅ LIST PASSED</span>');
                } else {
                    log('<span class="fail">❌ LIST FAILED</span>');
                }
            }

            async function testUpdate() {
                if (!createdAppointmentId) {
                    log('\n⚠️  Cannot test update: No appointment ID. Run Create test first.');
                    return;
                }

                log('\n=== TEST: Update Appointment ===');
                
                const postData = {
                    action: 'update',
                    tenantID: parseInt(document.getElementById('tenantId').value),
                    appointment_id: createdAppointmentId,
                    status: 'Confirmed'
                };

                log('POST Data: ' + JSON.stringify(postData, null, 2));

                const response = await makeRequest('POST', {}, postData);
                log('Response: ' + JSON.stringify(response, null, 2));

                if (response.status === 'success') {
                    log('<span class="pass">✅ UPDATE PASSED</span>');
                } else {
                    log('<span class="fail">❌ UPDATE FAILED</span>');
                }
            }

            async function testDelete() {
                if (!createdAppointmentId) {
                    log('\n⚠️  Cannot test delete: No appointment ID. Run Create test first.');
                    return;
                }

                log('\n=== TEST: Delete Appointment ===');
                
                const params = {
                    action: 'delete',
                    tenantID: parseInt(document.getElementById('tenantId').value),
                    appointment_id: createdAppointmentId
                };

                log('Params: ' + JSON.stringify(params, null, 2));

                const response = await makeRequest('GET', params);
                log('Response: ' + JSON.stringify(response, null, 2));

                if (response.status === 'success') {
                    log('<span class="pass">✅ DELETE PASSED</span>');
                    createdAppointmentId = null;
                } else {
                    log('<span class="fail">❌ DELETE FAILED</span>');
                }
            }

            async function runAllTests() {
                clearOutput();
                log('🚀 Starting full test suite...\n');
                
                await testCreate();
                await new Promise(resolve => setTimeout(resolve, 500));
                
                await testList();
                await new Promise(resolve => setTimeout(resolve, 500));
                
                await testUpdate();
                await new Promise(resolve => setTimeout(resolve, 500));
                
                await testDelete();
                
                log('\n\n========================================');
                log('✅ Test Suite Complete');
                log('========================================');
            }
        </script>
    </body>
    </html>
    <?php
}
?>
