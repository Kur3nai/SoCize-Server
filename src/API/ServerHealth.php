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
    if (function_exists('log_error')) {
        log_error("Initialization failed: " . $e->getMessage());
    }
    exit(json_encode(ServerHealthResponse::createErrorResponse("Initialization failed")));
}

function get_cpu_usage(): string {
    if (strtoupper(substr(PHP_OS, 0, 3)) === 'WIN') {
        exec('wmic cpu get loadpercentage', $output, $result);
        if ($result !== 0 || !isset($output[1])) {
            return 'N/A';
        }
        return trim($output[1]).'%';
    } else {
        $load = sys_getloadavg();
        return $load === false ? 'N/A' : round($load[0], 2).'% (1min avg)';
    }
}

function get_memory_usage(): string {
    if (strtoupper(substr(PHP_OS, 0, 3)) === 'WIN') {
        exec('wmic OS get FreePhysicalMemory,TotalVisibleMemorySize /Value', $output, $result);
        if ($result !== 0) {
            return 'N/A';
        }
        
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
        $percent = $total > 0 ? round(($used / $total) * 100) : 0;
        return "$used GB / $total GB ($percent% used)";
    } else {
        $free = shell_exec('free -m');
        if ($free === null) {
            return 'N/A';
        }
        
        $free = trim($free);
        $free_arr = explode("\n", $free);
        if (count($free_arr) < 2) {
            return 'N/A';
        }
        
        $mem = explode(" ", $free_arr[1]);
        $mem = array_filter($mem);
        $mem = array_merge($mem);
        
        if (count($mem) < 3) {
            return 'N/A';
        }
        
        $used = round($mem[2] / 1024, 2);
        $total = round($mem[1] / 1024, 2);
        $percent = $total > 0 ? round(($used / $total) * 100) : 0;
        return "$used GB / $total GB ($percent% used)";
    }
}

function get_disk_space(): string {
    $free = strtoupper(substr(PHP_OS, 0, 3)) === 'WIN' 
        ? disk_free_space("C:") 
        : disk_free_space("/");
    
    return $free === false ? 'N/A' : round($free / 1024 / 1024 / 1024).' GB';
}

function get_server_status(mysqli $conn): array {
    $status = [];
    
    try {
        $conn->ping();
        $status['databaseStatus'] = "ONLINE";
    } catch (Exception $e) {
        $status['databaseStatus'] = "OFFLINE";
    }

    $status['cpuUsage'] = get_cpu_usage();
    $status['memoryUsage'] = get_memory_usage();
    $status['diskSpaceAvailable'] = get_disk_space();

    return $status;
}

function verify_admin_session(string $sessionId): ?array {
    session_id($sessionId);
    session_start();

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
    $conn = null;
    try {
        if (!verifyPostMethod()) {
            send_api_response(new ServerHealthResponse(false, "Only POST requests allowed"));
            return;
        }

        $requiredFields = ['sessionId'];
        $input = fetch_json_data($requiredFields);

        $session = verify_admin_session($input['sessionId']);
        if (!$session) {
            send_api_response(new ServerHealthResponse(false, "Access denied: Admin privileges required"));
            return;
        }

        $conn = mysqli_connect(...$db_credentials);
        if (!$conn) {
            $status = [
                'databaseStatus' => 'OFFLINE',
                'cpuUsage' => get_cpu_usage(),
                'memoryUsage' => get_memory_usage(),
                'diskSpaceAvailable' => get_disk_space()
            ];
            send_api_response(new ServerHealthResponse(true, "Database is offline", $status));
            return;
        }

        $status = get_server_status($conn);
        send_api_response(new ServerHealthResponse(true, null, $status));

    } catch (Exception $e) {
        log_error("Application error: " . $e->getMessage());
        send_api_response(new ServerHealthResponse(false, "An error occurred"));
    } finally {
        if ($conn) {
            mysqli_close($conn);
        }
    }
}

Main($db_credentials);