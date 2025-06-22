<?php declare(strict_types=1);

class ServerHealthResponse {
    public bool $success;
    public ?string $errorMessage;
    public ?array $status;

    public function __construct(bool $success, ?string $errorMessage, ?array $status = null) {
        $this->success = $success;
        $this->errorMessage = $errorMessage;
        $this->status = $status;
    }

    public static function createErrorResponse(string $errorMessage) {
        return new ServerHealthResponse(false, $errorMessage);
    }
}

error_reporting(0);

try {
    require_once "../Utility/ErrorLogging.php";
    require_once "../Utility/ResponseHelper.php";
    require_once "../Utility/SessionHelper.php";
    require_once "../Config/DatabaseConfig.php";
} catch (Throwable $e) {
    exit(json_encode(ServerHealthResponse::createErrorResponse("Something went wrong...")));
}

function get_server_status(mysqli $conn): array {
    $status = [];
    
    try {
        $conn->ping();
        $status['databaseStatus'] = "ONLINE";
    } catch (Exception $e) {
        $status['databaseStatus'] = "OFFLINE";
    }

    if (strtoupper(substr(PHP_OS, 0, 3)) === 'WIN') {
        exec('wmic cpu get loadpercentage', $output);
        $cpuUsage = isset($output[1]) ? trim($output[1]).'%' : 'N/A';
    } else {
        $load = sys_getloadavg();
        $cpuUsage = round($load[0], 2).'% (1min avg)';
    }
    $status['cpuUsage'] = $cpuUsage;

    if (strtoupper(substr(PHP_OS, 0, 3)) === 'WIN') {
        exec('wmic OS get FreePhysicalMemory,TotalVisibleMemorySize /Value', $output);
        $memory = [];
        foreach ($output as $line) {
            if (strpos($line, '=') !== false) {
                [$key, $value] = explode('=', $line);
                $memory[$key] = $value;
            }
        }
        $total = round(($memory['TotalVisibleMemorySize'] ?? 0) / 1024 / 1024, 2);
        $free = round(($memory['FreePhysicalMemory'] ?? 0) / 1024 / 1024, 2);
        $used = $total - $free;
        $percent = round(($used / $total) * 100);
        $status['memoryUsage'] = "$used GB / $total GB ($percent% used)";
    } else {
        $free = shell_exec('free -m');
        $free = (string)trim($free);
        $free_arr = explode("\n", $free);
        $mem = explode(" ", $free_arr[1]);
        $mem = array_filter($mem);
        $mem = array_merge($mem);
        $used = round($mem[2] / 1024, 2);
        $total = round($mem[1] / 1024, 2);
        $percent = round(($used / $total) * 100);
        $status['memoryUsage'] = "$used GB / $total GB ($percent% used)";
    }

    if (strtoupper(substr(PHP_OS, 0, 3)) === 'WIN') {
        $free = round(disk_free_space("C:") / 1024 / 1024 / 1024);
        $status['diskSpaceAvailable'] = "$free GB";
    } else {
        $free = round(disk_free_space("/") / 1024 / 1024 / 1024);
        $status['diskSpaceAvailable'] = "$free GB";
    }

    return $status;
}

function verify_admin_session(string $sessionId): ?array {
    if (!check_login_status()) {
        return null;
    }

    if ($_SESSION['role'] !== 'admin') {
        return null;
    }

    return [
        'username' => $_SESSION['username'],
        'role' => $_SESSION['role']
    ];
}

function Main($db_credentials) {
    try {
        if (!verifyPostMethod()) {
            send_api_response(new ServerHealthResponse(false, "Something went wrong..."));
            return;
        }

        $input = json_decode(file_get_contents('php://input'), true);
        
        if (!isset($input['sessionId'])) {
            send_api_response(new ServerHealthResponse(false, "Something went wrong..."));
            return;
        }

        $session = verify_admin_session($input['sessionId']);
        if (!$session) {
            send_api_response(new ServerHealthResponse(false, "Access denied: Admin privileges required"));
            return;
        }

        $conn = mysqli_connect(...$db_credentials);
        if (!$conn) {
            send_api_response(new ServerHealthResponse(false, "Something went wrong with the server...."));
            return;
        }

        $status = get_server_status($conn);
        mysqli_close($conn);

        send_api_response(new ServerHealthResponse(true, null, $status));

    } catch (Exception $e) {
        if (isset($conn)) {
            mysqli_close($conn);
        }
        log_error("Application error: " . $e->getMessage());
        send_api_response(new ServerHealthResponse(false, "Something went wrong..."));
    }
}

Main($db_credentials);