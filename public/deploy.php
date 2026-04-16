<?php

/**
 * GitHub Webhook Deploy Script for Laravel on cPanel
 *
 * Usage: https://yourdomain.com/deploy.php?secret=YOUR_SECRET
 *
 * Setup:
 * 1. Generate a secure secret: openssl_random_pseudo_bytes(32) -> bin2hex()
 * 2. Add webhook in GitHub: Settings → Webhooks → Add webhook
 *    URL: https://yourdomain.com/deploy.php?secret=YOUR_SECRET
 *    Events: Push
 * 3. Ensure this file is in public/ directory
 */
define('DEPLOY_SECRET', '7df6128a44b99ad343817491fdfd86f0c50a9ec629675e810d0efc765241cf6b');
define('LOG_FILE', __DIR__.'/deploy.log');
define('PROJECT_DIR', dirname(__DIR__));
define('BOOTSTRAP_DIR', PROJECT_DIR.'/bootstrap');
define('STORAGE_DIR', PROJECT_DIR.'/storage');
define('COMPOSER_PATH', '/usr/local/bin/composer');
define('PHP_PATH', '/usr/bin/php');

$startTime = microtime(true);
$response = ['status' => 'error', 'message' => '', 'output' => [], 'duration' => 0];

try {
    logMessage('=== Deploy started at '.date('Y-m-d H:i:s').' ===');

    // Verify secret token
    $providedSecret = $_GET['secret'] ?? '';
    if (empty($providedSecret) || $providedSecret !== DEPLOY_SECRET) {
        throw new Exception('Invalid or missing secret token', 401);
    }

    logMessage('Secret verified successfully');

    // Get JSON payload from GitHub
    $payload = file_get_contents('php://input');
    if (empty($payload)) {
        throw new Exception('No payload received', 400);
    }

    $data = json_decode($payload, true);
    if (json_last_error() !== JSON_ERROR_NONE) {
        throw new Exception('Invalid JSON payload', 400);
    }

    $branch = $data['ref'] ?? 'unknown';
    $repoUrl = $data['repository']['html_url'] ?? 'unknown';
    $pusher = $data['pusher']['name'] ?? 'unknown';

    logMessage("Push detected: branch=$branch, repo=$repoUrl, pusher=$pusher");

    // Only deploy on main/master branch
    if ( ! in_array($branch, ['refs/heads/main', 'refs/heads/master'])) {
        logMessage("Skipping deploy - not main/master branch (got: $branch)");
        $response['status'] = 'skipped';
        $response['message'] = 'Not main/master branch, skipped';
        sendResponse($response, 200);
    }

    // Change to project directory
    chdir(PROJECT_DIR);
    logMessage('Working directory: '.getcwd());

    // Git pull
    logMessage('Running git pull...');
    $gitOutput = runCommand('git pull');
    logMessage('Git pull output: '.substr($gitOutput, 0, 500));

    // Check if there were any changes
    if (strpos($gitOutput, 'Already up to date') !== false) {
        logMessage('No changes to pull - skipping composer/migrate');
        $response['status'] = 'skipped';
        $response['message'] = 'Already up to date';
        sendResponse($response, 200);
    }

    // Composer install (production)
    if (file_exists('composer.json')) {
        logMessage('Running composer install --no-dev --optimize-autoloader...');
        $composerOutput = runCommand(COMPOSER_PATH.' install --no-dev --optimize-autoloader --no-interaction 2>&1', 300);
        logMessage('Composer output: '.substr($composerOutput, 0, 500));
    }

    // Run migrations
    logMessage('Running php artisan migrate --force...');
    $migrateOutput = runCommand(PHP_PATH.' artisan migrate --force --no-interaction 2>&1', 120);
    logMessage('Migrate output: '.substr($migrateOutput, 0, 500));

    // Clear caches
    logMessage('Clearing Laravel caches...');
    runCommand(PHP_PATH.' artisan optimize:clear 2>&1', 60);
    runCommand(PHP_PATH.' artisan config:cache 2>&1', 60);
    runCommand(PHP_PATH.' artisan route:cache 2>&1', 60);

    // Set proper permissions for storage and bootstrap/cache
    logMessage('Setting permissions...');
    @chmod(STORAGE_DIR.'/app/public', 0755);
    @chmod(STORAGE_DIR.'/framework/cache', 0755);
    @chmod(STORAGE_DIR.'/framework/sessions', 0755);
    @chmod(STORAGE_DIR.'/framework/views', 0755);
    @chmod(BOOTSTRAP_DIR.'/cache', 0755);

    $response['status'] = 'success';
    $response['message'] = 'Deployment completed successfully';
    logMessage('=== Deploy completed successfully ===');

} catch (Exception $e) {
    $response['status'] = 'error';
    $response['message'] = $e->getMessage();
    logMessage('ERROR: '.$e->getMessage());
    sendResponse($response, $e->getCode() ?: 500);
}

$response['duration'] = round(microtime(true) - $startTime, 2);
sendResponse($response, 200);

// ============= Helper Functions =============

function logMessage(string $message): void
{
    $logEntry = date('Y-m-d H:i:s').' | '.$message."\n";
    @file_put_contents(LOG_FILE, $logEntry, FILE_APPEND);
    echo $logEntry;
}

function runCommand(string $command, int $timeout = 60): string
{
    $descriptorSpec = [
        0 => ['pipe', 'r'],
        1 => ['pipe', 'w'],
        2 => ['pipe', 'w'],
    ];

    $process = proc_open($command, $descriptorSpec, $pipes, PROJECT_DIR);

    if ( ! is_resource($process)) {
        throw new Exception('Failed to execute command: '.$command);
    }

    stream_set_timeout($pipes[1], $timeout);
    stream_set_timeout($pipes[2], $timeout);

    $output = stream_get_contents($pipes[1]);
    $error = stream_get_contents($pipes[2]);

    fclose($pipes[0]);
    fclose($pipes[1]);
    fclose($pipes[2]);

    $returnCode = proc_close($process);

    if ($returnCode !== 0) {
        logMessage("Command failed with exit code $returnCode: $command");
        logMessage('Error output: '.substr($error, 0, 300));
    }

    return $output.($error ? "\n--- ERRORS ---\n".$error : '');
}

function sendResponse(array $response, int $httpCode): void
{
    http_response_code($httpCode);
    header('Content-Type: application/json');
    header('X-Content-Type-Options: nosniff');

    if ($httpCode === 401) {
        header('WWW-Authenticate: Basic realm="Deploy"');
    }

    echo json_encode($response, JSON_PRETTY_PRINT | JSON_UNESCAPED_SLASHES);
    exit;
}
