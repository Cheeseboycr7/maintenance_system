
<?php
// test_api.php
echo "<h1>API Endpoint Tests</h1>";

function testEndpoint($url, $method = 'GET', $data = null) {
    $ch = curl_init();
    
    curl_setopt($ch, CURLOPT_URL, $url);
    curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
    curl_setopt($ch, CURLOPT_CUSTOMREQUEST, $method);
    
    if ($data) {
        curl_setopt($ch, CURLOPT_POSTFIELDS, json_encode($data));
        curl_setopt($ch, CURLOPT_HTTPHEADER, ['Content-Type: application/json']);
    }
    
    // Handle cookies for session
    curl_setopt($ch, CURLOPT_COOKIEJAR, 'cookies.txt');
    curl_setopt($ch, CURLOPT_COOKIEFILE, 'cookies.txt');
    
    $response = curl_exec($ch);
    $http_code = curl_getinfo($ch, CURLINFO_HTTP_CODE);
    curl_close($ch);
    
    return [
        'status' => $http_code,
        'data' => json_decode($response, true)
    ];
}

// Test 1: Login
echo "<h2>1. Testing Login...</h2>";
$result = testEndpoint('http://localhost/maintenance_system/api/', 'POST', [
    'username' => 'admin',
    'password' => 'admin123'
]);
echo "<pre>" . print_r($result, true) . "</pre>";

// Test 2: Dashboard
echo "<h2>2. Testing Dashboard...</h2>";
$result = testEndpoint('http://localhost/maintenance_system/api/dashboard');
echo "<pre>" . print_r($result, true) . "</pre>";

// Test 3: Employees
echo "<h2>3. Testing Employees...</h2>";
$result = testEndpoint('http://localhost/maintenance_system/api/employees');
echo "<pre>" . print_r($result, true) . "</pre>";

// Test 4: Create a task
echo "<h2>4. Testing Task Creation...</h2>";
$result = testEndpoint('http://localhost/maintenance_system/api/tasks', 'POST', [
    'title' => 'Test Task from API',
    'description' => 'This is a test task',
    'priority' => 'Medium'
]);
echo "<pre>" . print_r($result, true) . "</pre>";

echo "<h2 style='color: green;'>✅ API Testing Complete!</h2>";
?>