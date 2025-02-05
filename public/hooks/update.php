<?php
require dirname(__DIR__) . '/../vendor/autoload.php';

$dotenv = Dotenv\Dotenv::createImmutable(dirname(__DIR__));
$dotenv->load();

// Ensure the request method is POST
if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    http_response_code(405); // Method Not Allowed
    header('Allow: POST');
    exit;
}

// Check if the token is provided and valid
if (!isset($_GET['token']) || $_GET['token'] !== $_ENV['DEPLOYTOKEN']) {
    http_response_code(403);
    exit;
}

$projectRoot = dirname(__DIR__, 2);
chdir($projectRoot);

exec('git pull 2>&1', $gitOutput, $gitReturnVar);
echo "Git Pull Output:\n" . implode("\n", $gitOutput) . "\n";

if ($gitReturnVar !== 0) {
    echo "Git pull failed with status $gitReturnVar\n";
    exit($gitReturnVar);
}

echo "Update completed successfully.\n";
?>
