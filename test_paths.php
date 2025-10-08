<?php
// test_paths.php
echo "<h1>Path Diagnosis</h1>";

echo "<h2>Server Information:</h2>";
echo "<pre>";
echo "Server Name: " . ($_SERVER['SERVER_NAME'] ?? 'N/A') . "\n";
echo "Server Port: " . ($_SERVER['SERVER_PORT'] ?? 'N/A') . "\n";
echo "Document Root: " . ($_SERVER['DOCUMENT_ROOT'] ?? 'N/A') . "\n";
echo "Request URI: " . ($_SERVER['REQUEST_URI'] ?? 'N/A') . "\n";
echo "Script Name: " . ($_SERVER['SCRIPT_NAME'] ?? 'N/A') . "\n";
echo "</pre>";

echo "<h2>File Existence Check:</h2>";
$files_to_check = [
    'install.php' => 'api/install.php',
    'index.php' => 'api/index.php',
    'auth.php' => 'auth.php'
];

foreach ($files_to_check as $name => $path) {
    $exists = file_exists($path) ? "✅ EXISTS" : "❌ MISSING";
    echo "<p>$name ($path): $exists</p>";
}

echo "<h2>Test Links:</h2>";
$base_url = 'http://' . ($_SERVER['HTTP_HOST'] ?? 'localhost');
echo "<ul>";
echo "<li><a href='{$base_url}/api/install.php?install=1'>Install via GET</a></li>";
echo "<li><a href='{$base_url}/api/install.php'>Install page (should show instructions)</a></li>";
echo "<li><a href='{$base_url}/api/'>Login endpoint</a></li>";
echo "</ul>";

echo "<h2>Quick Install Form:</h2>";
echo '
<form action="api/install.php" method="POST">
    <input type="hidden" name="install" value="1">
    <button type="submit">Install Database Now</button>
</form>
';
?>