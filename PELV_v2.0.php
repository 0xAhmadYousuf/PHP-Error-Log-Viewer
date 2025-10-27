<?php
// // use it in live mode
$error_log_file = ini_get('error_log');
define('ERROR_LOG_FILE', $error_log_file);
$error_view_file = basename($_SERVER['PHP_SELF']);
define('ERROR_VIEW_FILE', $error_view_file);
// // For now, use a fixed error log file name
// define('ERROR_LOG_FILE', 'PEL/error_log');
// echo "i am api, my name is $error_view_file"; exit;

define('ERROR_CACHE_JSON_FILE', str_replace('.php', '_log.json', ERROR_VIEW_FILE));
define('JSON_ERROR_LOG', str_replace('.php', '_errors.json', ERROR_VIEW_FILE));
define('JSON_ERROR_METADATA', str_replace('.php', '_metadata.json', ERROR_VIEW_FILE));
define('EXCEPTIONAL_KEYS', ['phone', 'mobile', 'password', 'access_token']);
define('NEW_LINE_SEPARATOR', '||PEV-NEW-LINE||');
define('BASE_DIR', $_SERVER['DOCUMENT_ROOT']);

if (!isset($_GET['action']) && !isset($_GET['multi_action']) && !isset($_GET['frontend_info'])) {
    // print the page
    $page = html_full_page();
    echo $page;
    exit;
}
$metaData = [
    'base_dir' => $_SERVER['DOCUMENT_ROOT'],
    'new_line_separator' => '||PEV-NEW-LINE||',
];
$base_dir = $_SERVER['DOCUMENT_ROOT'];

$page = 1;
$limit = 50;
if (isset($_GET['page']) && is_numeric($_GET['page']) && $_GET['page'] > 0) {
    $page = intval($_GET['page']);
}
if (isset($_GET['limit']) && is_numeric($_GET['limit']) && $_GET['limit'] > 0) {
    $limit = intval($_GET['limit']);
}
// fro raw log
$total_line = 300;
if(isset($_GET['total_line']) && is_numeric($_GET['total_line']) && $_GET['total_line'] > 0) {
    $total_line = intval($_GET['total_line']);
}

header('Content-Type: application/json');
// Handle GET requests
if ($_SERVER['REQUEST_METHOD'] === 'GET') {

    // load errors in a variable to make it little faster
    $loaded_errors = extractErrorsFromFile(ERROR_LOG_FILE);
    define('LOADED_ERRORS', $loaded_errors);

    // if needed multi action so to make it lite action do all actions in one go
    if(isset($_GET['multi_action'])){
        $multi_action = $_GET['multi_action'];
        $actions = json_decode($multi_action, true);
        if (empty($actions) || !is_array($actions)) {
            echo json_encode(['error' => 'Invalid multi_action parameter'], JSON_PRETTY_PRINT);
            exit;
        }
        $allowed_actions = ['recent_error', 'all_error', 'raw_log', 'reoccurred', 'get_statics', 'get_solvers', 'all_solvers', 'cache_status'];
        
        // echo json_encode(['success' => true, 'actions' => $actions], JSON_PRETTY_PRINT);
        // exit;
        $response = [];
        foreach ($actions as $action) {
            if (in_array($action, $allowed_actions)) {
                if($action == 'recent_error'){
                    $response['recent_error'] = getFullErrorLog('recency');
                }
                else if($action == 'all_error'){
                    $response['all_error'] = getFullErrorLog();
                }
                else if($action == 'raw_log'){
                    ob_start();
                    sendRawErrorLog($total_line);
                    $raw_log_output = ob_get_clean();
                    $response['raw_log'] = json_decode($raw_log_output, true);
                }
                else if($action == 'reoccurred'){
                    $response['reoccurred'] = getReoccurredErrors();
                }
                else if($action == 'get_statics'){
                    $response['get_statics'] = getStatics();
                }
                else if($action == 'get_solvers'){
                    if (!isset($_GET['hash'])) {
                        echo json_encode(['error' => 'Hash parameter is required for get_solvers action'], JSON_PRETTY_PRINT);
                        exit;
                    }
                    $response['get_solvers'] = get_solvers($_GET['hash']);
                }
                else if($action == 'all_solvers'){
                    $response['all_solvers'] = all_solver_records();
                }
                else if($action == 'cache_status'){
                    $response['cache_status'] = getCacheStatus();
                }
            }
        }
        echo json_encode(['metaData' => $metaData, 'data' => $response], JSON_PRETTY_PRINT);
    }


    switch ($_GET['action']) {
        case 'recent_error':
            $sort_by = isset($_GET['sort']) ? $_GET['sort'] : 'recency';
            $errors = getFullErrorLog($sort_by);
            $errors_pagination = pagination_data($errors, $limit, $page);
            $errors_data = paginate($errors, $limit, $page);
            echo json_encode(['metaData' => $metaData, 'errors' => $errors_data, 'pagination' => $errors_pagination], JSON_PRETTY_PRINT);
            // echo json_encode(['metaData' => $metaData, 'errors' => $errors], JSON_PRETTY_PRINT);
            break;
        case 'all_error':
            $errors = getFullErrorLog();
            // $errors_pagination = pagination_data($errors, $limit, $page);
            // $errors_data = paginate($errors, $limit, $page);
            // echo json_encode(['metaData' => $metaData, 'errors' => $errors_data, 'pagination' => $errors_pagination], JSON_PRETTY_PRINT);
            echo json_encode(['metaData' => $metaData, 'errors' => $errors], JSON_PRETTY_PRINT);
            break;
        case 'raw_log':
            sendRawErrorLog($total_line);
            break;
        case 'reoccurred':
            $reoccurredErrors = getReoccurredErrors();
            $reoccurredErrors_pagination = pagination_data($reoccurredErrors, $limit, $page);
            $reoccurredErrors_data = paginate($reoccurredErrors, $limit, $page);
            echo json_encode(['metaData' => $metaData, 'errors' => $reoccurredErrors_data, 'pagination' => $reoccurredErrors_pagination], JSON_PRETTY_PRINT);
            break;
        case 'get_statics':
            $statistics = getStatics();
            echo json_encode(['metaData' => $metaData, 'statistics' => $statistics], JSON_PRETTY_PRINT);
            break;
        case 'get_solvers':
            if (!isset($_GET['hash'])) {
                echo json_encode(['error' => 'Hash parameter is required'], JSON_PRETTY_PRINT);
                exit;
            }
            $solvers = get_solvers($_GET['hash']);
            echo json_encode($solvers, JSON_PRETTY_PRINT);
            break;
        case 'all_solvers':
            $allSolvers = all_solver_records();
            echo json_encode($allSolvers, JSON_PRETTY_PRINT);
            break;
        case 'cache_status':
            $cacheStatus = getCacheStatus();
            echo json_encode(['metaData' => $metaData, 'cache_status' => $cacheStatus], JSON_PRETTY_PRINT);
            break;
        case 'clear_cache':
            $result = clearErrorCache();
            echo json_encode(['success' => $result, 'message' => 'Error cache cleared successfully'], JSON_PRETTY_PRINT);
            break;
        default:
            // echo json_encode(['error' => 'Invalid action specified'], JSON_PRETTY_PRINT);
            break;
    }
}
// Handle POST requests
else if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    switch ($_GET['action']) {
        case 'add_solver':
            $data = json_decode(file_get_contents('php://input'), true);
            if (!isset($data['hash']) || (!isset($data['solver_name']) && !isset($data['solved_by']))) {
                echo json_encode(['error' => 'Hash and solved_by parameters are required'], JSON_PRETTY_PRINT);
                exit;
            }
            // Support both solver_name and solved_by parameters
            $solverName = isset($data['solved_by']) ? $data['solved_by'] : $data['solver_name'];
            $additionalData = isset($data['data']) ? $data['data'] : [];
            $result = add_solver($data['hash'], $solverName, $additionalData);
            echo json_encode(['success' => $result, 'data' => $additionalData], JSON_PRETTY_PRINT);
            break;
        case 'clear_log':
            // Clear the error log file
            $result = clear_error_log();
            $result = $result ? true : false;
            echo json_encode(['success' => $result, 'message' => 'Error log cleared successfully'], JSON_PRETTY_PRINT);
            
            // echo json_encode(['success' => true, 'message' => 'Error log cleared successfully'], JSON_PRETTY_PRINT);
            break;
        default:
            echo json_encode(['error' => 'Invalid action specified'], JSON_PRETTY_PRINT);
            break;
    }
}
else {
    echo json_encode(['error' => 'Unsupported request method'], JSON_PRETTY_PRINT);
    exit;
}
/**
 * @r___________________________________________________________________________________________________________________________________________
 * @r___________________________________________________________________________________________________________________________________________
 * @r___________________________________________________________________________________________________________________________________________
 * @r___________________________________________________________________________________________________________________________________________
 * @r___________________________________________________________________________________________________________________________________________
 * @r___________________________________________________________________________________________________________________________________________
 * @r___________________________________________________________________________________________________________________________________________
 * @r___________________________________________________________________________________________________________________________________________
 */

function pagination_data($data, $per_page = 50, $page = 1){
    $total_items = count($data);
    $total_pages = ceil($total_items / $per_page);
    $page = max(1, min($page, $total_pages));
    return [
        'current_page' => $page,
        'per_page' => $per_page,
        'total_items' => $total_items,
        'total_pages' => $total_pages
    ];
}

function paginate($data, $per_page = 50, $page = 1){
    $total_items = count($data);
    $total_pages = ceil($total_items / $per_page);
    $page = max(1, min($page, $total_pages));
    $start_index = ($page - 1) * $per_page;
    $paged_data = array_slice($data, $start_index, $per_page);
    return $paged_data;
}

function getStatics() {
    $response = [
        'total_errors' => 0,
        'total_unique_errors' => 0,
        'total_solved_errors' => 0,
        'total_reoccurred_errors' => 0,
        'total_errors_by_type' => [],
        'total_errors_by_file' => [],
        'total_errors_by_date' => [],
        'error_timestamps' => [],

    ];
    $data = getErrorAndCount(false);
    // echo json_encode($data, JSON_PRETTY_PRINT); exit;
    $errors_data = $data['errors'];
    $response['total_errors'] = $data['count'];
    foreach ($errors_data as $error) {
        // count by type
        $type = $error['type'];
        if (!isset($response['total_errors_by_type'][$type])) {
            $response['total_errors_by_type'][$type] = 0;
        }
        $response['total_errors_by_type'][$type]++;
        // count by file
        $file = $error['file'] ?? 'unknown';
        if (!isset($response['total_errors_by_file'][$file])) {
            $response['total_errors_by_file'][$file] = 0;
        }
        $response['total_errors_by_file'][$file]++;
        // count by date
        $date = date('Y-m-d', $error['timestamp']);
        if (!isset($response['total_errors_by_date'][$date])) {
            $response['total_errors_by_date'][$date] = 0;
        }
        $response['total_errors_by_date'][$date]++;

        // collect error timestamps
        // echo json_encode($error); exit;
        // $previus_timestamps = $response['error_timestamps'];
        // $newfound_timestamps = $error['occurred']['timestamps'] ?? [];
        // $response['error_timestamps'][] = array_merge($previus_timestamps, $newfound_timestamps);
        $response['error_timestamps'] = array_merge($response['error_timestamps'], $error['occurred']['timestamps'] ?? []);
    }
    // now get reoccurred errors count
    $reoccurred_errors = getReoccurredErrors();
    $response['total_reoccurred_errors'] = count($reoccurred_errors);
    // count total solved errors from solved.json then minus reoccurred errors
    $solved = all_solver_records();
    $response['total_solved_errors'] = count($solved) - count($reoccurred_errors);

    // calculate total unique errors by counting total of total_by_type and total_by_file and total_by_date are same or not
    // if they are same then take any of them as total unique errors
    $total_count = [
        'by_type' => 0,
        'by_file' => 0,
        'by_date' => 0,
    ];
    foreach ($response['total_errors_by_type'] as $type => $count) {
        $total_count['by_type'] += $count;
    }
    foreach ($response['total_errors_by_file'] as $file => $count) {
        $total_count['by_file'] += $count;
    }
    foreach ($response['total_errors_by_date'] as $date => $count) {
        $total_count['by_date'] += $count;
    }
    // now check if all three are same
    if ($total_count['by_type'] === $total_count['by_file'] && $total_count['by_file'] === $total_count['by_date']) {
        $response['total_unique_errors'] = $total_count['by_type'];
    }

    // now group error timestamps to unique and count total similar timestamps so it be short
    $unique_timestamps = [];
    foreach ($response['error_timestamps'] as $timestamp) {
        $unique_timestamps[$timestamp] = ($unique_timestamps[$timestamp] ?? 0) + 1;
    }
    $response['error_timestamps'] = $unique_timestamps;

    return $response;
}
function getReoccurredErrors() {
    $errors = getFullErrorLog();
    $solved = all_solver_records();
    // if error's hash exists in solved
    // then take error's []'occurred']['timestamps'] 's biggest one
    // also get solver's ['solvers'][*]['timestamp'] biggest one
    // if both exists and solver's timestamp < error's biggest occurred timestamp
    // it means the error reoccurred
    // butild all hashes list with biggest solved timestamp from solved data
    $solved_timestamps = [];
    if (isset($solved['errors'])) {
        foreach ($solved['errors'] as $hash => $errorData) {
            $maxSolvedTimestamp = 0;
            if (isset($errorData['solvers'])) {
                foreach ($errorData['solvers'] as $solver) {
                    if (isset($solver['timestamp']) && $solver['timestamp'] > $maxSolvedTimestamp) {
                        $maxSolvedTimestamp = $solver['timestamp'];
                    }
                }
            }
            $solved_timestamps[$hash] = $maxSolvedTimestamp;
        }
    }
    $errors_timestamps = [];
    // foreach ($errors as $error) {
    //     echo json_encode($error, JSON_PRETTY_PRINT); echo '---'; die;
    //     $hash = $error['hash'];
    //     $maxErrorTimestamp = max($error['occurred']['timestamps']);
    //     $errors_timestamps[$hash] = $maxErrorTimestamp;
    // }
    // get each of error's max occurred timestamp
    foreach ($errors as $error) {
        // echo json_encode($errors, JSON_PRETTY_PRINT); echo '---'; die;
        $hash = $error['hash'];
        $maxErrorTimestamp = 0;
        if (isset($error['occurred']['timestamps'])) {
            foreach ($error['occurred']['timestamps'] as $ts) {
                if ($ts > $maxErrorTimestamp) {
                    $maxErrorTimestamp = $ts;
                }
            }
        }
        $errors_timestamps[$hash] = $maxErrorTimestamp;
    }
    // $r_data = [
    //     'solved_timestamps' => $solved_timestamps,
    //     'errors_timestamps' => $errors_timestamps,
    // ];
    // now compare both
    $reoccurred_errors = array();
    foreach ($errors_timestamps as $hash => $errorTimestamp) {
        if (isset($solved_timestamps[$hash])) {
            $solvedTimestamp = $solved_timestamps[$hash];
            if ($solvedTimestamp < $errorTimestamp) {
                // error reoccurred
                $reoccurred = $errors[array_search($hash, array_column($errors, 'hash'))];
                $reoccurred['last_solved_at'] = $solvedTimestamp;
                $reoccurred['last_occurred_at'] = $errorTimestamp;
                $reoccurred_errors[] = $reoccurred;
            }
        }
    }
    return $reoccurred_errors;
}
function getFullErrorLog($sort_by=false) {
    $errors = LOADED_ERRORS;
    $unique_errors = [];
    $hashes = [];
    foreach ($errors as $error) {
        if (!in_array($error['hash'], $hashes)) {
            $error['occurred']['times'] = countOccurrences($errors, $error['hash']);
            $error['occurred']['timestamps'] = getOccurrenceTimestamps($errors, $error['hash']);
            $unique_errors[] = $error;
            $hashes[] = $error['hash'];
        }
    }
    if ($sort_by) {
        if ($sort_by === 'recency') {
            usort($unique_errors, function($a, $b) {
                return $b['timestamp'] - $a['timestamp'];
            });
        }
    }
    return $unique_errors;
}
function getErrorAndCount($sort_by=false) {
    $count = 0;
    $errors = LOADED_ERRORS;
    $count = count($errors);
    $unique_errors = [];
    $hashes = [];
    foreach ($errors as $error) {
        if (!in_array($error['hash'], $hashes)) {
            $error['occurred']['times'] = countOccurrences($errors, $error['hash']);
            $error['occurred']['timestamps'] = getOccurrenceTimestamps($errors, $error['hash']);
            $unique_errors[] = $error;
            $hashes[] = $error['hash'];
        }
    }
    if ($sort_by) {
        if ($sort_by === 'recency') {
            usort($unique_errors, function($a, $b) {
                return $b['timestamp'] - $a['timestamp'];
            });
        }
    }
    return ['errors' => $unique_errors, 'count' => $count];
}
function countOccurrences($errors, $hash) {
    $count = 0;
    foreach ($errors as $error) {
        if ($error['hash'] === $hash) {
            $count++;
        }
    }
    return $count;
}
function getOccurrenceTimestamps($errors, $hash) {
    $timestamps = [];
    foreach ($errors as $error) {
        if ($error['hash'] === $hash) {
            $timestamps[] = $error['timestamp'];
        }
    }
    return $timestamps;
}
function sendRawErrorLog($total_line) {
    // read last N lines from error log
    // if total_line is not set, default to 300
    // use class SplFileObject for efficient reading
    $file = new SplFileObject(ERROR_LOG_FILE, 'r');
    $file->seek(PHP_INT_MAX);
    $last_line = $file->key();
    $start_line = max(0, $last_line - $total_line + 1); // +1 to include last line
    $lines = [];
    for ($i = $start_line; $i <= $last_line; $i++) {
        $file->seek($i);
        $lines[] = rtrim($file->current(), "\r\n");
    }
    echo json_encode(['raw_log' => $lines], JSON_PRETTY_PRINT);
}
/**
 * Clear the error log file
 * @return boolean True if successful, false otherwise
 */
function clear_error_log() {
    $success = false;
    if (file_exists(ERROR_LOG_FILE)) {
        // Empty the file by writing an empty string to it
        if (file_put_contents(ERROR_LOG_FILE, '') !== false) {
            $success = true;
        }
    } else {
        // If file doesn't exist, create an empty file
        $handle = fopen(ERROR_LOG_FILE, 'w');
        if ($handle) {
            fclose($handle);
            $success = true;
        }
    }
    // Clear the cache as well since log has been cleared
    if ($success) {
        clearErrorCache();
    }
    return $success;
}
/**
 * @r___________________________________________________________________________________________________________________________________________
 * @r___________________________________________________________________________________________________________________________________________
 * @r___________________________________________________________________________________________________________________________________________
 */
function checkFileExists($filePath) {
    if (file_exists($filePath)) {
        return true;
    } else {
        try {
            // try to create the file
            $handle = fopen($filePath, 'w');
            fclose($handle);
            return true;
        } catch (Exception $e) {
        }
    }
    return false;
}
function add_solver($hash, $solver_name, $additional_data = []) {
    $jsonFile = 'solved.json';
    if (!checkFileExists($jsonFile)) {
        return false;
    }
    $data = [];
    if (file_exists($jsonFile)) {
        $data = json_decode(file_get_contents($jsonFile), true) ?: [];
    }
    if (!isset($data['errors'][$hash])) {
        $data['errors'][$hash] = [];
        $data['errors'][$hash]['data'] = $additional_data;
    }
    $solver = [
        'solved' => true,
        'solved_by' => $solver_name,
        'timestamp' => time(),
    ];
    $data['errors'][$hash]['solvers'][] = $solver;
    $data['errors'][$hash]['last_updated'] = time();
    if (file_put_contents($jsonFile, json_encode($data, JSON_PRETTY_PRINT))) {
        // Clear the error cache since solving an error changes the error state
        clearErrorCache();
        return true;
    } else {
        return false;
    }
}
function get_solvers($hash) {
    $jsonFile = 'solved.json';
    if (!checkFileExists($jsonFile)) {
        return [];
    }
    $data = [];
    if (file_exists($jsonFile)) {
        $data = json_decode(file_get_contents($jsonFile), true) ?: [];
    }
    if (isset($data['errors'][$hash])) {
        return $data['errors'][$hash];
    } else {
        return [];
    }
}
function all_solver_records() {
    $jsonFile = 'solved.json';
    if (!checkFileExists($jsonFile)) {
        return [];
    }
    $data = [];
    if (file_exists($jsonFile)) {
        $data = json_decode(file_get_contents($jsonFile), true) ?: [];
    }
    return $data;
}
/**
 * @r___________________________________________________________________________________________________________________________________________
 * @r___________________________________________________________________________________________________________________________________________
 * @__|||        oooooooooooo ooooooooo.   ooooooooo.     .oooooo.   ooooooooo.          ooooo          .oooooo.     .oooooo.            |||___@
 * @__|||        `888'     `8 `888   `Y88. `888   `Y88.  d8P'  `Y8b  `888   `Y88.        `888'         d8P'  `Y8b   d8P'  `Y8b           |||___@
 * @__|||         888          888   .d88'  888   .d88' 888      888  888   .d88'         888         888      888 888                   |||___@
 * @__|||         888oooo8     888ooo88P'   888ooo88P'  888      888  888ooo88P'          888         888      888 888                   |||___@
 * @__|||         888    "     888`88b.     888`88b.    888      888  888`88b.            888         888      888 888     ooooo         |||___@
 * @__|||         888       o  888  `88b.   888  `88b.  `88b    d88'  888  `88b.          888       o `88b    d88' `88.    .88'          |||___@
 * @__|||        o888ooooood8 o888o  o888o o888o  o888o  `Y8bood8P'  o888o  o888o        o888ooooood8  `Y8bood8P'   `Y8bood8P'           |||___@
 * @r___________________________________________________________________________________________________________________________________________
 * @r___________________________________________________________________________________________________________________________________________
 */
/**
 * PHP Error Log Extractor
 * Functions to parse PHP error logs for various error types
 * Handles both timestamped and custom errors with hash generation for duplicate detection
 */
/**
 * Main function to extract errors from a log file
 * @param string $logFile Path to the error log file
 * @return array Array of parsed errors
 */


function readLinesFromFile($filename, $start, $end = null) {
    $file = new SplFileObject($filename);
    $lines = [];
    $lineNum = 0;
    while (!$file->eof()) {
        $line = $file->fgets();
        $lineNum++;
        if ($lineNum < $start) continue;
        if ($end !== null && $lineNum > $end) break;
        $lines[] = rtrim($line, "\r\n");
    }
    return $lines;
}

function smartPHPErrorReader($logFile){
    if (!file_exists($logFile)) {
        file_put_contents($logFile, json_encode(['errors' => []], JSON_PRETTY_PRINT));
    }
}

/**
 * Get cached errors from JSON file
 * @param string $logFile Path to the error log file
 * @return array|null Returns cached data or null if cache doesn't exist/is invalid
 */
function getCachedErrors($logFile) {
    $metadataFile = JSON_ERROR_METADATA;
    $errorFile = JSON_ERROR_LOG;
    
    // Check if both files exist
    if (!file_exists($metadataFile) || !file_exists($errorFile)) {
        return null;
    }
    
    // Read metadata first (small file)
    $metadata = json_decode(file_get_contents($metadataFile), true);
    if (!$metadata || !isset($metadata['log_file']) || $metadata['log_file'] !== $logFile) {
        return null;
    }
    
    // Return metadata with errors loaded separately
    $metadata['errors'] = json_decode(file_get_contents($errorFile), true) ?: [];
    
    return $metadata;
}

/**
 * Update error cache with new data using separated files
 * @param string $logFile Path to the error log file
 * @param array $errors Array of parsed errors
 * @param array $metadata Cache metadata (line count, hash, etc.)
 * @return bool Success status
 */
function updateErrorCache($logFile, $errors, $metadata) {
    $metadataFile = JSON_ERROR_METADATA;
    $errorFile = JSON_ERROR_LOG;
    
    // Prepare metadata
    $cacheMetadata = [
        'log_file' => $logFile,
        'last_updated' => time(),
        'last_line_count' => $metadata['line_count'],
        'last_hash' => $metadata['file_hash'],
        'last_size' => $metadata['file_size'],
        'cache_version' => '2.0',
        'error_count' => count($errors)
    ];
    
    // Save metadata (small file)
    $metadataSuccess = file_put_contents($metadataFile, json_encode($cacheMetadata, JSON_PRETTY_PRINT)) !== false;
    
    // Save errors (large file)
    $errorSuccess = file_put_contents($errorFile, json_encode($errors, JSON_PRETTY_PRINT)) !== false;
    
    return $metadataSuccess && $errorSuccess;
}

/**
 * Check if log file has been updated since last cache (optimized with metadata file)
 * @param string $logFile Path to the error log file
 * @param array $cacheMeta Cache metadata
 * @return bool True if log file has been updated
 */
function isLogUpdated($logFile, $cacheMeta) {
    if (!file_exists($logFile)) {
        // Log file no longer exists, clear cache
        clearErrorCache();
        return false;
    }
    
    $currentSize = filesize($logFile);
    $currentLineCount = count(file($logFile, FILE_IGNORE_NEW_LINES | FILE_SKIP_EMPTY_LINES));
    $currentHash = md5_file($logFile);
    
    return (
        $currentSize !== $cacheMeta['last_size'] ||
        $currentLineCount !== $cacheMeta['last_line_count'] ||
        $currentHash !== $cacheMeta['last_hash']
    );
}

/**
 * Quick cache validity check using only metadata file (very fast)
 * @param string $logFile Path to the error log file
 * @return bool True if cache is valid and current
 */
function isCacheValid($logFile) {
    $metadataFile = JSON_ERROR_METADATA;
    $errorFile = JSON_ERROR_LOG;
    
    // Check if both cache files exist
    if (!file_exists($metadataFile) || !file_exists($errorFile)) {
        return false;
    }
    
    // Check if log file exists
    if (!file_exists($logFile)) {
        clearErrorCache();
        return false;
    }
    
    // Read only the small metadata file
    $metadata = json_decode(file_get_contents($metadataFile), true);
    if (!$metadata || !isset($metadata['log_file']) || $metadata['log_file'] !== $logFile) {
        return false;
    }
    
    // Quick file checks without reading content
    $currentSize = filesize($logFile);
    $currentHash = md5_file($logFile);
    
    return (
        $currentSize === $metadata['last_size'] &&
        $currentHash === $metadata['last_hash']
    );
}

/**
 * Get cache status and information for separated cache files
 * @return array Cache status information
 */
function getCacheStatus() {
    $metadataFile = JSON_ERROR_METADATA;
    $errorFile = JSON_ERROR_LOG;
    
    // Check metadata file
    if (!file_exists($metadataFile)) {
        return [
            'exists' => false,
            'message' => 'Metadata cache file does not exist',
            'metadata_file' => $metadataFile,
            'error_file' => $errorFile
        ];
    }
    
    // Check error file
    if (!file_exists($errorFile)) {
        return [
            'exists' => false,
            'message' => 'Error cache file does not exist',
            'metadata_file' => $metadataFile,
            'error_file' => $errorFile
        ];
    }
    
    // Read metadata
    $metadata = json_decode(file_get_contents($metadataFile), true);
    if (!$metadata) {
        return [
            'exists' => true,
            'valid' => false,
            'message' => 'Metadata file exists but contains invalid JSON',
            'metadata_file' => $metadataFile,
            'error_file' => $errorFile
        ];
    }
    
    return [
        'exists' => true,
        'valid' => true,
        'log_file' => $metadata['log_file'] ?? 'unknown',
        'last_updated' => $metadata['last_updated'] ?? 0,
        'last_updated_human' => isset($metadata['last_updated']) ? date('Y-m-d H:i:s', $metadata['last_updated']) : 'unknown',
        'error_count' => $metadata['error_count'] ?? 0,
        'cache_version' => $metadata['cache_version'] ?? 'unknown',
        'metadata_file_size' => filesize($metadataFile),
        'error_file_size' => filesize($errorFile),
        'metadata_file' => $metadataFile,
        'error_file' => $errorFile
    ];
}

/**
 * Clear all error cache files (metadata, errors, and legacy cache)
 * @return bool Success status
 */
function clearErrorCache() {
    $success = true;
    
    // Clear new separated cache files
    $metadataFile = JSON_ERROR_METADATA;
    $errorFile = JSON_ERROR_LOG;
    
    if (file_exists($metadataFile)) {
        $success = $success && unlink($metadataFile);
    }
    
    if (file_exists($errorFile)) {
        $success = $success && unlink($errorFile);
    }
    
    // Also clear legacy cache file for backward compatibility
    $legacyCacheFile = ERROR_CACHE_JSON_FILE;
    if (file_exists($legacyCacheFile)) {
        $success = $success && unlink($legacyCacheFile);
    }
    
    return $success;
}

function extractErrorsFromFile($logFile, $lineLimit=false,$llimit=[0,10])
{
    if (!file_exists($logFile)) {
        // Clear cache since log file doesn't exist
        clearErrorCache();
        return ["error" => "Log file not found: $logFile"];
    }

    // Quick cache validity check (very fast - only reads small metadata file)
    if (!$lineLimit && isCacheValid($logFile)) {
        // Load errors from cache file
        $errorFile = JSON_ERROR_LOG;
        $cachedErrors = json_decode(file_get_contents($errorFile), true);
        return $cachedErrors ?: [];
    }

    // If cache is invalid or line limiting is used, parse the log file
    if ($lineLimit) {
        $lines = readLinesFromFile($logFile, $llimit[0], $llimit[1]);
    } else {
        $lines = readLinesFromFile($logFile, 0);
    }
    $errors = [];
    // Two-pass parsing approach
    // Pass 1: Parse all timestamped errors and identify their positions
    $timestampedErrors = [];
    $errorPositions = [];
    for ($i = 0; $i < count($lines); $i++) {
        $line = $lines[$i];
        // Detect start of any PHP error line with timestamp (Fatal, Warning, Notice, Parse, etc.)
        if (preg_match('/^\[(.*?)\].*?PHP\s+(Fatal error|Parse error|Warning|Notice|Deprecated|Strict Standards|Catchable fatal error|Recoverable fatal error):\s+(.*)$/i', $line, $matches)) {
            $timestamp = parseOccurred_at($matches[1]);
            $errorType = $matches[2];
            $msg = $matches[3];
            // Extract file path and line number if present
            $filePath = null;
            $lineNum = null;
            // Pattern 1: "in /path/file.php:line_number" anywhere in message
            if (preg_match('/\sin\s+([\/\\\\][^\s:]+\.php):(\d+)/i', $msg, $fileInfo)) {
                $filePath = trim($fileInfo[1]);
                $lineNum = intval($fileInfo[2]);
            }
            // Pattern 2: "in /path/file.php on line number" anywhere in message
            elseif (preg_match('/\sin\s+([\/\\\\][^\s]+\.php)\s+on\s+line\s+(\d+)/i', $msg, $fileInfo)) {
                $filePath = trim($fileInfo[1]);
                $lineNum = intval($fileInfo[2]);
            }
            // Pattern 3: "thrown in /path/file.php on line number"
            elseif (preg_match('/thrown\s+in\s+([\/\\\\][^\s]+\.php)\s+on\s+line\s+(\d+)/i', $msg, $fileInfo)) {
                $filePath = trim($fileInfo[1]);
                $lineNum = intval($fileInfo[2]);
            }
            // Pattern 4: Generic "in /path/file.php" without line number
            elseif (preg_match('/\sin\s+([\/\\\\][^\s:]+\.php)/i', $msg, $fileInfo)) {
                $filePath = trim($fileInfo[1]);
                $lineNum = null;
            }
            $error = [
                "timestamp" => $timestamp, // Changed to match what frontend expects
                "type" => $errorType,       // Changed to match what frontend expects
                "file" => $filePath,
                "line" => $lineNum,         // Changed to match what frontend expects
                "message" => $msg,
                "start_line" => $i
            ];
            // Check for multiline continuation (only include actual PHP stack traces, not custom logs)
            $j = $i + 1;
            while (
                $j < count($lines) &&
                !preg_match('/^\[(.*?)\].*?PHP\s+(Fatal error|Parse error|Warning|Notice|Deprecated|Strict Standards|Catchable fatal error|Recoverable fatal error):/i', $lines[$j]) &&
                !preg_match('/^\[(.*?)\].*?(Uncaught\s+\w+(?:Error|Exception)):/i', $lines[$j])
            ) {
                $nextLine = trim($lines[$j]);
                // Skip comment lines and comment separators
                if (preg_match('/^\s*#/', $lines[$j])) {
                    $j++;
                    continue;
                }
                // Only include as continuation if it looks like a PHP stack trace or continuation
                // Stack trace patterns: "Stack trace:", "#0 /path/file.php(123)", "thrown in /path/file.php on line 123"
                if (preg_match('/^Stack trace:|^#\d+\s+|thrown in\s+.+?\s+on\s+line\s+\d+/i', $nextLine)) {
                    $error['message'] .= "\n" . $lines[$j];
                    // Try to extract file/line from continuation lines if not already set
                    if (!$error['file']) {
                        // Pattern 1: "thrown in /path/file.php on line number"
                        if (preg_match('/thrown\s+in\s+([\/\\\\][^\s]+\.php)\s+on\s+line\s+(\d+)/i', $lines[$j], $fileInfo)) {
                            $error['file'] = trim($fileInfo[1]);
                            $error['line'] = intval($fileInfo[2]);
                        }
                        // Pattern 2: Stack trace line "#0 /path/file.php(line)"
                        elseif (preg_match('/#\d+\s+([\/\\\\][^\s\(]+\.php)\((\d+)\)/i', $lines[$j], $fileInfo)) {
                            $error['file'] = trim($fileInfo[1]);
                            $error['line'] = intval($fileInfo[2]);
                        }
                        // Pattern 3: "in /path/file.php:line_number" anywhere in line
                        elseif (preg_match('/\sin\s+([\/\\\\][^\s:]+\.php):(\d+)/i', $lines[$j], $fileInfo)) {
                            $error['file'] = trim($fileInfo[1]);
                            $error['line'] = intval($fileInfo[2]);
                        }
                        // Pattern 4: "in /path/file.php on line number" anywhere in line
                        elseif (preg_match('/\sin\s+([\/\\\\][^\s]+\.php)\s+on\s+line\s+(\d+)/i', $lines[$j], $fileInfo)) {
                            $error['file'] = trim($fileInfo[1]);
                            $error['line'] = intval($fileInfo[2]);
                        }
                    }
                    $j++;
                } else {
                    // This doesn't look like a PHP continuation, break here
                    break;
                }
            }
            $error['end_line'] = $j - 1;
            $timestampedErrors[] = $error;
            $errorPositions[] = $i;
            $i = $j - 1; // Skip processed lines
        }
        // Detect "Uncaught" errors that might not have the standard format
        elseif (preg_match('/^\[(.*?)\].*?(Uncaught\s+\w+(?:Error|Exception)):\s+(.*)$/i', $line, $matches)) {
            $timestamp = parseOccurred_at($matches[1]);
            $errorType = $matches[2];
            $msg = $matches[3];
            $filePath = null;
            $lineNum = null;
            // Pattern 1: "in /path/file.php:line_number" anywhere in message
            if (preg_match('/\sin\s+([\/\\\\][^\s:]+\.php):(\d+)/i', $msg, $fileInfo)) {
                $filePath = trim($fileInfo[1]);
                $lineNum = intval($fileInfo[2]);
            }
            // Pattern 2: "in /path/file.php on line number" anywhere in message
            elseif (preg_match('/\sin\s+([\/\\\\][^\s]+\.php)\s+on\s+line\s+(\d+)/i', $msg, $fileInfo)) {
                $filePath = trim($fileInfo[1]);
                $lineNum = intval($fileInfo[2]);
            }
            // Pattern 3: Generic "in /path/file.php" without line number
            elseif (preg_match('/\sin\s+([\/\\\\][^\s:]+\.php)/i', $msg, $fileInfo)) {
                $filePath = trim($fileInfo[1]);
                $lineNum = null;
            }
            $error = [
                "timestamp" => $timestamp,
                "type" => $errorType,
                "file" => $filePath,
                "line" => $lineNum,
                "message" => $msg,
                "start_line" => $i
            ];
            // Check for multiline continuation (only include actual PHP stack traces, not custom logs)
            $j = $i + 1;
            while (
                $j < count($lines) &&
                !preg_match('/^\[(.*?)\].*?PHP\s+(Fatal error|Parse error|Warning|Notice|Deprecated|Strict Standards|Catchable fatal error|Recoverable fatal error):/i', $lines[$j]) &&
                !preg_match('/^\[(.*?)\].*?(Uncaught\s+\w+(?:Error|Exception)):/i', $lines[$j])
            ) {
                $nextLine = trim($lines[$j]);
                // Skip comment lines and comment separators
                if (preg_match('/^\s*#/', $lines[$j])) {
                    $j++;
                    continue;
                }
                // Only include as continuation if it looks like a PHP stack trace or continuation
                // Stack trace patterns: "Stack trace:", "#0 /path/file.php(123)", "thrown in /path/file.php on line 123"
                if (preg_match('/^Stack trace:|^#\d+\s+|thrown in\s+.+?\s+on\s+line\s+\d+/i', $nextLine)) {
                    $error['message'] .= "\n" . $lines[$j];
                    if (!$error['file']) {
                        // Pattern 1: "thrown in /path/file.php on line number"
                        if (preg_match('/thrown\s+in\s+([\/\\\\][^\s]+\.php)\s+on\s+line\s+(\d+)/i', $lines[$j], $fileInfo)) {
                            $error['file'] = trim($fileInfo[1]);
                            $error['line'] = intval($fileInfo[2]);
                        }
                        // Pattern 2: Stack trace line "#0 /path/file.php(line)"
                        elseif (preg_match('/#\d+\s+([\/\\\\][^\s\(]+\.php)\((\d+)\)/i', $lines[$j], $fileInfo)) {
                            $error['file'] = trim($fileInfo[1]);
                            $error['line'] = intval($fileInfo[2]);
                        }
                        // Pattern 3: "in /path/file.php:line_number" anywhere in line
                        elseif (preg_match('/\sin\s+([\/\\\\][^\s:]+\.php):(\d+)/i', $lines[$j], $fileInfo)) {
                            $error['file'] = trim($fileInfo[1]);
                            $error['line'] = intval($fileInfo[2]);
                        }
                        // Pattern 4: "in /path/file.php on line number" anywhere in line
                        elseif (preg_match('/\sin\s+([\/\\\\][^\s]+\.php)\s+on\s+line\s+(\d+)/i', $lines[$j], $fileInfo)) {
                            $error['file'] = trim($fileInfo[1]);
                            $error['line'] = intval($fileInfo[2]);
                        }
                    }
                    $j++;
                } else {
                    // This doesn't look like a PHP continuation, break here
                    break;
                }
            }
            $error['end_line'] = $j - 1;
            $timestampedErrors[] = $error;
            $errorPositions[] = $i;
            $i = $j - 1; // Skip processed lines
        } elseif (preg_match('/^\[(.*?)\].*?Stack trace:$/i', $line, $matches)) {
            $j = $i + 1;
            $stackLines = [];
            while (
                $j < count($lines) &&
                preg_match('/^\[(.*?)\].*?#\d+\s+(.+)$/i', $lines[$j], $stackMatch)
            ) {
                $stackLines[] = trim($stackMatch[2]);
                $j++;
            }
            // Check for "thrown in" line
            if (
                $j < count($lines) &&
                preg_match('/^\[(.*?)\].*?thrown\s+in\s+([\/\\\\][^\s]+\.php)\s+on\s+line\s+(\d+)/i', $lines[$j], $thrownMatch)
            ) {
                $timestamp = parseOccurred_at($thrownMatch[1]);
                $filePath = trim($thrownMatch[2]);
                $lineNum = intval($thrownMatch[3]);
                $msg = implode("\n", $stackLines) . "\n" . trim($lines[$j]);
                $error = [
                    "timestamp" => $timestamp,  // Changed to match what frontend expects
                    "type" => "Stack trace",      // Changed to match what frontend expects
                    "file" => $filePath,
                    "line" => $lineNum,
                    "message" => $msg,
                    "start_line" => $i,
                    "end_line" => $j
                ];
                $timestampedErrors[] = $error;
                $errorPositions[] = $i;
                $i = $j; // Skip processed lines
            }
        }
    }
    // Pass 2: Find custom errors and logs between timestamped errors
    $allErrors = [];
    $prevOccurred_at = null;
    // Add timestamped errors to final array
    foreach ($timestampedErrors as $error) {
        unset($error['start_line'], $error['end_line']); // Remove processing fields
        finalizeError($error, $allErrors, $prevOccurred_at);
        $prevOccurred_at = $error['timestamp'];
    }
    // Now find custom errors between known timestamped errors
    $processedLines = [];
    foreach ($timestampedErrors as $error) {
        for ($i = $error['start_line']; $i <= $error['end_line']; $i++) {
            $processedLines[$i] = true;
        }
    }
    // Look for unprocessed segments that might contain custom errors
    $customErrorSegments = [];
    $currentSegment = [];
    $segmentStart = -1;
    $lastKnownOccurred_at = null;
    for ($i = 0; $i < count($lines); $i++) {
        if (!isset($processedLines[$i])) {
            $line = trim($lines[$i]);
            // Skip empty lines and pure comment separators
            if (empty($line) || preg_match('/^#+\s*$/', $line) || preg_match('/^#+\s*---/', $line)) {
                continue;
            }
            if (empty($currentSegment)) {
                $segmentStart = $i;
                // Find the timestamp from the previous known error
                foreach ($timestampedErrors as $error) {
                    if ($error['start_line'] < $i) {
                        $lastKnownOccurred_at = $error['timestamp'];
                    }
                }
            }
            $currentSegment[] = $line;
        } else {
            // We hit a processed line, save current segment if it exists
            if (!empty($currentSegment)) {
                $customErrorSegments[] = [
                    'lines' => $currentSegment,
                    'prev_timestamp' => $lastKnownOccurred_at,
                    'start_index' => $segmentStart
                ];
                $currentSegment = [];
            }
        }
    }
    // Don't forget the last segment
    if (!empty($currentSegment)) {
        $customErrorSegments[] = [
            'lines' => $currentSegment,
            'prev_timestamp' => $lastKnownOccurred_at,
            'start_index' => $segmentStart
        ];
    }
    // Parse custom error segments
    foreach ($customErrorSegments as $segment) {
        parseCustomErrorSegment($segment['lines'], $segment['prev_timestamp'], $allErrors);
    }

    // Update cache with newly parsed errors
    if (!$lineLimit) { // Only cache full log parsing, not limited parsing
        $metadata = [
            'line_count' => count(file($logFile, FILE_IGNORE_NEW_LINES | FILE_SKIP_EMPTY_LINES)),
            'file_hash' => md5_file($logFile),
            'file_size' => filesize($logFile)
        ];
        updateErrorCache($logFile, $allErrors, $metadata);
    }

    return $allErrors;
}
/**
 * Parse custom error segments that don't have timestamps
 */
function parseCustomErrorSegment($lines, $prevOccurred_at, &$allErrors)
{
    if (empty($lines)) {
        return;
    }
    $customErrors = [];
    $currentCustomError = null;
    foreach ($lines as $line) {
        $line = trim($line);
        // Skip comment-style separators and empty lines
        if (empty($line) || preg_match('/^#+/', $line)) {
            continue;
        }
        // Try to detect if this looks like a PHP error without timestamp
        // Look for patterns like "PHP Warning:", "PHP Fatal error:", "PHP Fatal Error:", etc.
        if (preg_match('/PHP\s+(Fatal\s+error|Fatal\s+Error|Parse\s+error|Warning|Notice|Deprecated|Strict\s+Standards|Catchable\s+fatal\s+error|Recoverable\s+fatal\s+error):\s+(.*)$/i', $line, $matches)) {
            // Save previous custom error if exists
            if ($currentCustomError) {
                finalizeCustomError($currentCustomError, $prevOccurred_at, $allErrors);
            }
            $errorType = $matches[1];
            $msg = $matches[2];
            // Extract file path and line number if present (but many custom errors won't have these)
            $filePath = null;
            $lineNum = null;
            // Pattern 1: "in /path/file.php:line_number" anywhere in message
            if (preg_match('/\sin\s+([\/\\\\][^\s:]+\.php):(\d+)/i', $msg, $fileInfo)) {
                $filePath = trim($fileInfo[1]);
                $lineNum = intval($fileInfo[2]);
            }
            // Pattern 2: "in /path/file.php on line number" anywhere in message
            elseif (preg_match('/\sin\s+([\/\\\\][^\s]+\.php)\s+on\s+line\s+(\d+)/i', $msg, $fileInfo)) {
                $filePath = trim($fileInfo[1]);
                $lineNum = intval($fileInfo[2]);
            }
            // Pattern 3: "thrown in /path/file.php on line number"
            elseif (preg_match('/thrown\s+in\s+([\/\\\\][^\s]+\.php)\s+on\s+line\s+(\d+)/i', $msg, $fileInfo)) {
                $filePath = trim($fileInfo[1]);
                $lineNum = intval($fileInfo[2]);
            }
            // Pattern 4: Generic "in /path/file.php" without line number
            elseif (preg_match('/\sin\s+([\/\\\\][^\s:]+\.php)/i', $msg, $fileInfo)) {
                $filePath = trim($fileInfo[1]);
                $lineNum = null;
            }
                $currentCustomError = [
                "type" => trim(preg_replace('/\s+/', ' ', $errorType)), // Normalize spacing
                "file" => $filePath,
                "line" => $lineNum,
                "message" => $msg,
                "is_custom" => true
            ];
        }
        // Look for other error patterns (Uncaught errors, Exceptions, etc.)
        elseif (preg_match('/(Uncaught\s+\w*(?:Error|Exception|RuntimeException|TypeError|ArgumentCountError|ParseError)):\s+(.*)$/i', $line, $matches)) {
            if ($currentCustomError) {
                finalizeCustomError($currentCustomError, $prevOccurred_at, $allErrors);
            }
            $errorType = $matches[1];
            $msg = $matches[2];
            $filePath = null;
            $lineNum = null;
            // Pattern 1: "in /path/file.php:line_number" anywhere in message
            if (preg_match('/\sin\s+([\/\\\\][^\s:]+\.php):(\d+)/i', $msg, $fileInfo)) {
                $filePath = trim($fileInfo[1]);
                $lineNum = intval($fileInfo[2]);
            }
            // Pattern 2: "in /path/file.php on line number" anywhere in message
            elseif (preg_match('/\sin\s+([\/\\\\][^\s]+\.php)\s+on\s+line\s+(\d+)/i', $msg, $fileInfo)) {
                $filePath = trim($fileInfo[1]);
                $lineNum = intval($fileInfo[2]);
            }
            // Pattern 3: "thrown in /path/file.php on line number"
            elseif (preg_match('/thrown\s+in\s+([\/\\\\][^\s]+\.php)\s+on\s+line\s+(\d+)/i', $msg, $fileInfo)) {
                $filePath = trim($fileInfo[1]);
                $lineNum = intval($fileInfo[2]);
            }
            // Pattern 4: Generic "in /path/file.php" without line number
            elseif (preg_match('/\sin\s+([\/\\\\][^\s:]+\.php)/i', $msg, $fileInfo)) {
                $filePath = trim($fileInfo[1]);
                $lineNum = null;
            }
            $currentCustomError = [
                "type" => $errorType,
                "file" => $filePath,
                "line" => $lineNum,
                "message" => $msg,
                "is_custom" => true
            ];
        }
        // Look for custom error descriptions or documentation comments (like the selected line)
        elseif (
            preg_match('/^(.*?)\s+(error|Error|failed|Failed|exception|Exception|warning|Warning|notice|Notice|deprecated|Deprecated|fatal|Fatal)/i', $line, $matches) &&
            !preg_match('/^#+\s*---/', $line)
        ) { // Not a comment separator
            if ($currentCustomError) {
                finalizeCustomError($currentCustomError, $prevOccurred_at, $allErrors);
            }
            // Try to extract error type from the message
            $errorType = "Custom Log";
            if (preg_match('/(PHP\s+)?(\w+\s+)?(?:Fatal\s+)?(?:Error|Exception|Warning|Notice|Deprecated)/i', $line, $typeMatch)) {
                $errorType = trim($typeMatch[0]);
            }
            $currentCustomError = [
                "type" => $errorType,
                "file" => null,
                "line" => null,
                "message" => $line,
                "is_custom" => true
            ];
        }
        // Continuation of existing custom error
        elseif ($currentCustomError) {
            $currentCustomError['message'] .= "\n" . $line;
            // Try to extract file/line from continuation if not already set
            if (!$currentCustomError['file']) {
                // Pattern 1: "thrown in /path/file.php on line number"
                if (preg_match('/thrown\s+in\s+([\/\\\\][^\s]+\.php)\s+on\s+line\s+(\d+)/i', $line, $fileInfo)) {
                    $currentCustomError['file'] = trim($fileInfo[1]);
                    $currentCustomError['line'] = intval($fileInfo[2]);
                }
                // Pattern 2: Stack trace line "#0 /path/file.php(line)"
                elseif (preg_match('/#\d+\s+([\/\\\\][^\s\(]+\.php)\((\d+)\)/i', $line, $fileInfo)) {
                    $currentCustomError['file'] = trim($fileInfo[1]);
                    $currentCustomError['line'] = intval($fileInfo[2]);
                }
                // Pattern 3: "in /path/file.php:line_number" anywhere in line
                elseif (preg_match('/\sin\s+([\/\\\\][^\s:]+\.php):(\d+)/i', $line, $fileInfo)) {
                    $currentCustomError['file'] = trim($fileInfo[1]);
                    $currentCustomError['line'] = intval($fileInfo[2]);
                }
                // Pattern 4: "in /path/file.php on line number" anywhere in line
                elseif (preg_match('/\sin\s+([\/\\\\][^\s]+\.php)\s+on\s+line\s+(\d+)/i', $line, $fileInfo)) {
                    $currentCustomError['file'] = trim($fileInfo[1]);
                    $currentCustomError['line'] = intval($fileInfo[2]);
                }
            }
        }
        // Standalone log line that doesn't fit other patterns
        else {
            if ($currentCustomError) {
                finalizeCustomError($currentCustomError, $prevOccurred_at, $allErrors);
            }
            $currentCustomError = [
                "type" => "Custom Log",
                "file" => null,
                "line" => null,
                "message" => $line,
                "is_custom" => true
            ];
        }
    }
    // Don't forget the last custom error
    if ($currentCustomError) {
        finalizeCustomError($currentCustomError, $prevOccurred_at, $allErrors);
    }
}
/**
 * Generate a hash for error duplicate detection based on content excluding timestamps
 */
function generateErrorHash($error)
{
    // Create hash content from error details excluding timestamp (previously timestamp)
    $hashContent = ($error['type'] ?? $error['type']) . '|' .
        ($error['file'] ?? 'null') . '|' .
        ($error['line'] ?? 'null') . '|' .
        $error['message'];
    // Use full MD5 hash for better duplicate detection
    return md5($hashContent);
}
/**
 * Finalize custom error and add to errors array with previous timestamp
 */
function finalizeCustomError(&$error, $prevOccurred_at, &$allErrors)
{
    $error['timestamp'] = $prevOccurred_at ?: "unknown";
    $error['prev_time'] = $prevOccurred_at;
    $error['message'] = str_replace(["\r", "\n"], NEW_LINE_SEPARATOR, trim($error['message']));
    $error['hash'] = generateErrorHash($error);
    unset($error['is_custom']); // Remove processing flag
    $allErrors[] = $error;
}
/**
 * Finalize error by replacing newlines and adding to errors array
 */
function finalizeError(&$error, &$errors, $prevOccurred_at = null)
{
    $error['message'] = str_replace(["\r", "\n"], NEW_LINE_SEPARATOR, trim($error['message']));
    $error['prev_time'] = $prevOccurred_at;
    $error['hash'] = generateErrorHash($error);
    $errors[] = $error;
}
/**
 * Parse timestamp and convert to Unix timestamp
 */
function parseOccurred_at($dateString)
{
    // Try to parse various date formats
    $timestamp = strtotime($dateString);
    if ($timestamp === false) {
        // If parsing fails, return the original string
        return $dateString;
    }
    return $timestamp;
}
/**
 * Get duplicate errors based on hash
 * @param array $errors Array of parsed errors
 * @return array Array of duplicate groups
 */
function findDuplicateErrors($errors)
{
    $duplicates = [];
    $hashCounts = [];
    // Count occurrences of each hash
    foreach ($errors as $index => $error) {
        $hash = $error['hash'];
        if (!isset($hashCounts[$hash])) {
            $hashCounts[$hash] = [];
        }
        $hashCounts[$hash][] = $index;
    }
    // Find hashes that appear more than once
    foreach ($hashCounts as $hash => $indices) {
        if (count($indices) > 1) {
            $duplicateGroup = [];
            foreach ($indices as $index) {
                $duplicateGroup[] = $errors[$index];
            }
            $duplicates[$hash] = [
                'count' => count($indices),
                'errors' => $duplicateGroup
            ];
        }
    }
    return $duplicates;
}
/**
 * Get error statistics
 * @param array $errors Array of parsed errors
 * @return array Statistics array
 */
function getErrorStatistics($errors)
{
    $stats = [
        'total_errors' => count($errors),
        'by_type' => [],
        'by_file' => [],
        'duplicates' => 0,
        'unique_errors' => 0
    ];
    $uniqueHashes = [];
    foreach ($errors as $error) {
        // Count by type
        $type = $error['type'];
        if (!isset($stats['by_type'][$type])) {
            $stats['by_type'][$type] = 0;
        }
        $stats['by_type'][$type]++;
        // Count by file
        $file = $error['file'] ?? 'unknown';
        if (!isset($stats['by_file'][$file])) {
            $stats['by_file'][$file] = 0;
        }
        $stats['by_file'][$file]++;
        // Count unique hashes
        $hash = $error['hash'];
        if (!isset($uniqueHashes[$hash])) {
            $uniqueHashes[$hash] = 0;
        }
        $uniqueHashes[$hash]++;
    }
    $stats['unique_errors'] = count($uniqueHashes);
    $stats['duplicates'] = $stats['total_errors'] - $stats['unique_errors'];
    return $stats;
}
function html_full_page(){
    // First, we'll define the HTML content with a placeholder for the apiFile variable
    $page = <<<'HTML'
        <!DOCTYPE html>
        <html lang="en">
        <head>
            <meta charset="UTF-8">
            <meta name="viewport" content="width=device-width, initial-scale=1.0">
            <title>PHP Error Logger</title>
            <link href="https://fonts.googleapis.com/icon?family=Material+Icons" rel="stylesheet">
            <style>
                :root {
                    --bg-color: #f8fafc;
                    --card-bg: #ffffff;
                    --text-color: #1e293b;
                    --text-secondary: #64748b;
                    --border-color: #e2e8f0;
                    --primary-color: #3b82f6;
                    --error-color: #ef4444;
                    --warning-color: #f59e0b;
                    --notice-color: #10b981;
                    --solved-color: #22c55e;
                    --button-text: #ffffff;
                    --button-secondary-bg: #64748b;
                    --box-shadow: 0 4px 6px -1px rgba(0, 0, 0, 0.1), 0 2px 4px -1px rgba(0, 0, 0, 0.06);
                }
                .state-card.reoccurred {
                    border-left: 6px solid #e67e22;
                    background: #fff7e6;
                }
                .state-card.solved {
                    border-left: 6px solid #27ae60;
                    background: #e6fff2;
                }
                .state-card {
                    margin-bottom: 1.5rem;
                    box-shadow: 0 2px 8px rgba(0,0,0,0.07);
                }
                .error-badge.reoccurred {
                    background: #e67e22;
                    color: #fff;
                }
                .error-badge.solved {
                    background: #27ae60;
                    color: #fff;
                }
                .dark {
                    --bg-color: #0f172a;
                    --card-bg: #1e293b;
                    --text-color: #f8fafc;
                    --text-secondary: #cbd5e1;
                    --border-color: #334155;
                    --button-text: #ffffff;
                    --button-secondary-bg: #475569;
                    --box-shadow: 0 4px 6px -1px rgba(0, 0, 0, 0.3), 0 2px 4px -1px rgba(0, 0, 0, 0.15);
                }
                body {
                    font-family: system-ui, -apple-system, sans-serif;
                    background-color: var(--bg-color);
                    color: var(--text-color);
                    margin: 0;
                    padding: 0;
                    line-height: 1.5;
                    transition: background-color 0.3s, color 0.3s;
                }
                .container {
                    max-width: 1200px;
                    margin: 0 auto;
                    padding: 1rem;
                }
                header {
                    background-color: var(--card-bg);
                    padding: 1rem;
                    border-bottom: 1px solid var(--border-color);
                    box-shadow: var(--box-shadow);
                    margin-bottom: 2rem;
                }
                .header-content {
                    display: flex;
                    justify-content: space-between;
                    align-items: center;
                    flex-wrap: wrap;
                }
                /* State card styles for reoccurred and solved errors */
                .state-card.reoccurred {
                    border-left: 6px solid #e67e22;
                    background: #fff7e6;
                }
                .state-card.solved {
                    border-left: 6px solid #27ae60;
                    background: #e6fff2;
                }
                .state-card {
                    margin-bottom: 1.5rem;
                    box-shadow: 0 2px 8px rgba(0,0,0,0.07);
                }
                .error-badge.reoccurred {
                    background: #e67e22;
                    color: #fff;
                }
                .error-badge.solved {
                    background: #27ae60;
                    color: #fff;
                }
                .reoccurrence-info {
                    background-color: #fff3e0;
                    padding: 8px;
                    margin: 8px 16px;
                    border-radius: 4px;
                }
                body.dark .reoccurrence-info {
                    background-color: #5d4037;
                    color: #fff;
                }
                body.dark .state-card.reoccurred {
                    background: #543914;
                    color: #fff;
                }
                body.dark .state-card.solved {
                    background: #1a4731;
                    color: #fff;
                }
                .title {
                    font-size: 1.5rem;
                    font-weight: bold;
                    margin: 0;
                    display: flex;
                    align-items: center;
                    gap: 0.5rem;
                }
                .button-group {
                    display: flex;
                    gap: 0.5rem;
                }
                .button {
                    padding: 0.5rem 1rem;
                    background-color: var(--primary-color);
                    color: var(--button-text);
                    border: none;
                    border-radius: 0.25rem;
                    cursor: pointer;
                    display: flex;
                    align-items: center;
                    gap: 0.5rem;
                    font-size: 0.875rem;
                }
                .button:hover {
                    opacity: 0.9;
                }
                .button.secondary {
                    background-color: var(--button-secondary-bg);
                    color: var(--button-text);
                }
                .card {
                    background-color: var(--card-bg);
                    border-radius: 0.5rem;
                    box-shadow: var(--box-shadow);
                    margin-bottom: 1rem;
                    overflow: hidden;
                }
                .stats {
                    display: flex;
                    flex-wrap: wrap;
                    gap: 1rem;
                    margin-bottom: 2rem;
                    width: 100%;
                }
                .stat-card {
                    flex: 1 1 0;
                    padding: 1rem;
                    background-color: var(--card-bg);
                    border-radius: 0.5rem;
                    box-shadow: var(--box-shadow);
                    text-align: center;
                    min-width: 150px; /* Minimum width to ensure readability */
                }
                /* Responsive design for stat cards on smaller screens */
                @media (max-width: 768px) {
                    .stats {
                        flex-direction: column;
                    }
                    .stat-card {
                        width: 100%;
                        min-width: auto;
                    }
                }
                .stat-value {
                    font-size: 1.5rem;
                    font-weight: bold;
                    margin: 0.5rem 0;
                }
                .stat-label {
                    color: var(--text-secondary);
                    font-size: 0.875rem;
                }
                .error-card {
                    border-left: 4px solid var(--error-color);
                    margin-bottom: 1rem;
                    transition: transform 0.2s;
                }
                .error-card:hover {
                    transform: translateY(-2px);
                }
                .error-card.Warning {
                    border-left-color: var(--warning-color);
                }
                .error-card.Notice {
                    border-left-color: var(--notice-color);
                }
                .error-card.Solved {
                    border-left-color: var(--solved-color);
                }
                /* Styles for error items within groups */
                .error-item {
                    border-left: 4px solid var(--error-color);
                    padding: 0.5rem;
                    margin-bottom: 0.75rem;
                    background-color: rgba(0,0,0,0.03);
                    border-radius: 4px;
                }
                .error-item:last-child {
                    margin-bottom: 0;
                }
                .error-item.Warning {
                    border-left-color: var(--warning-color);
                }
                .error-item.Notice {
                    border-left-color: var(--notice-color);
                }
                .error-item.Solved {
                    border-left-color: var(--solved-color);
                }
                .error-header {
                    padding: 1rem;
                    display: flex;
                    justify-content: space-between;
                    align-items: flex-start;
                }
                .error-title {
                    margin: 0;
                    font-weight: 500;
                    font-size: 1rem;
                }
                .error-badge {
                    padding: 0.25rem 0.5rem;
                    border-radius: 9999px;
                    font-size: 0.75rem;
                    font-weight: 500;
                    background-color: var(--error-color);
                    color: white;
                }
                .error-badge.Warning {
                    background-color: var(--warning-color);
                }
                .error-badge.Notice {
                    background-color: var(--notice-color);
                }
                .error-badge.Solved {
                    background-color: var(--solved-color);
                }
                .error-details {
                    display: flex;
                    flex-wrap: wrap;
                    gap: 1rem;
                    padding: 0 1rem 1rem;
                    color: var(--text-secondary);
                    font-size: 0.875rem;
                }
                .error-detail {
                    display: flex;
                    align-items: center;
                    gap: 0.25rem;
                }
                .error-actions {
                    padding: 0.5rem 1rem;
                    display: flex;
                    justify-content: flex-end;
                    gap: 0.5rem;
                    border-top: 1px solid var(--border-color);
                }
                .error-message {
                    padding: 1rem;
                    background-color: rgba(0, 0, 0, 0.05);
                    margin: 0 1rem 1rem;
                    border-radius: 0.25rem;
                    font-family: monospace;
                    white-space: pre-wrap;
                    overflow-x: auto;
                    font-size: 0.875rem;
                }
                .welcome {
                    padding: 1rem;
                    margin-bottom: 1.5rem;
                    background-color: var(--card-bg);
                    border-radius: 0.5rem;
                    box-shadow: var(--box-shadow);
                    display: flex;
                    justify-content: space-between;
                    align-items: center;
                }
                .welcome-text {
                    font-size: 1.25rem;
                    font-weight: 500;
                }
                .stats-number {
                    font-size: 0.875rem;
                    color: var(--text-secondary);
                }
                .modal {
                    position: fixed;
                    top: 0;
                    left: 0;
                    width: 100%;
                    height: 100%;
                    background-color: rgba(0, 0, 0, 0.5);
                    display: flex;
                    justify-content: center;
                    align-items: center;
                    z-index: 100;
                }
                .modal-content {
                    background-color: var(--card-bg);
                    padding: 1.5rem;
                    border-radius: 0.5rem;
                    box-shadow: var(--box-shadow);
                    max-width: 500px;
                    width: 100%;
                }
                .modal-header {
                    display: flex;
                    justify-content: space-between;
                    align-items: center;
                    margin-bottom: 1rem;
                }
                .modal-title {
                    font-size: 1.25rem;
                    font-weight: 500;
                    margin: 0;
                }
                .modal-close {
                    background: none;
                    border: none;
                    font-size: 1.5rem;
                    cursor: pointer;
                    color: var(--text-secondary);
                }
                body.dark .modal-close {
                    color: #cbd5e1;
                }
                .form-group {
                    margin-bottom: 1rem;
                }
                .form-label {
                    display: block;
                    margin-bottom: 0.5rem;
                    font-weight: 500;
                }
                .form-input, .form-textarea {
                    width: 100%;
                    padding: 0.5rem;
                    border: 1px solid var(--border-color);
                    border-radius: 0.25rem;
                    font-family: inherit;
                    font-size: 1rem;
                    background-color: var(--bg-color);
                    color: var(--text-color);
                }
                .form-textarea {
                    min-height: 100px;
                    resize: vertical;
                }
                .form-actions {
                    display: flex;
                    justify-content: flex-end;
                    gap: 0.5rem;
                }
                body.dark .form-actions .button {
                    color: #ffffff;
                }
                /* Error Info in Solve Modal */
                .error-info {
                    background-color: var(--bg-light);
                    padding: 1rem;
                    border-radius: 0.5rem;
                    margin-bottom: 1rem;
                }
                .error-info-item {
                    margin-bottom: 0.5rem;
                    word-break: break-word;
                }
                .error-info-item strong {
                    color: var(--primary);
                }
                .info-text {
                    font-size: 0.85rem;
                    color: var(--text-secondary);
                    font-style: italic;
                    margin-top: 0.75rem;
                    margin-bottom: 0;
                }
                .toggle-theme {
                    position: fixed;
                    bottom: 1rem;
                    right: 1rem;
                    background-color: var(--card-bg);
                    border: 1px solid var(--border-color);
                    color: var(--text-color);
                    padding: 0.5rem;
                    border-radius: 9999px;
                    cursor: pointer;
                    box-shadow: var(--box-shadow);
                    transition: background-color 0.3s, color 0.3s, border-color 0.3s;
                }
                body.dark .toggle-theme {
                    background-color: #2d3748;
                    color: #f8fafc;
                    border-color: #4a5568;
                }
                .tabs {
                    display: flex;
                    background-color: var(--card-bg);
                    border-radius: 0.5rem;
                    overflow: hidden;
                    margin-bottom: 1.5rem;
                    box-shadow: var(--box-shadow);
                }
                .tab {
                    padding: 0.75rem 1.5rem;
                    cursor: pointer;
                    font-weight: 500;
                    border-bottom: 2px solid transparent;
                    color: var(--text-color);
                    transition: color 0.3s, border-bottom-color 0.3s;
                }
                .tab.active {
                    border-bottom-color: var(--primary-color);
                    color: var(--primary-color);
                }
                body.dark .tab {
                    color: var(--text-color);
                }
                body.dark .tab.active {
                    color: var(--primary-color);
                }
                /* Group By Section */
                .group-by-container {
                    display: flex;
                    align-items: center;
                    margin-bottom: 1.5rem;
                    background-color: var(--card-bg);
                    padding: 0.75rem 1rem;
                    border-radius: 0.5rem;
                    box-shadow: var(--box-shadow);
                }
                .group-by-container label {
                    margin-right: 1rem;
                    font-weight: 500;
                    color: var(--text-secondary);
                    font-size: 0.875rem;
                }
                .group-options {
                    display: flex;
                    gap: 0.5rem;
                }
                .group-option {
                    padding: 0.4rem 0.75rem;
                    border-radius: 0.25rem;
                    cursor: pointer;
                    font-size: 0.875rem;
                    background-color: var(--bg-light);
                    transition: all 0.2s ease;
                }
                .group-option.active {
                    background-color: var(--primary-color);
                    color: white;
                }
                /* Error Groups */
                .error-group {
                    margin-bottom: 1rem;
                    border-radius: 0.5rem;
                    overflow: hidden;
                    box-shadow: var(--box-shadow);
                }
                .group-header {
                    display: flex;
                    justify-content: space-between;
                    align-items: center;
                    padding: 0.75rem 1rem;
                    background-color: var(--card-bg);
                    cursor: pointer;
                }
                .group-header h3 {
                    margin: 0;
                    font-size: 1rem;
                    display: flex;
                    align-items: center;
                }
                .group-count {
                    display: inline-block;
                    background-color: var(--primary-color);
                    color: white;
                    font-size: 0.75rem;
                    padding: 0.2rem 0.5rem;
                    border-radius: 9999px;
                    margin-left: 0.5rem;
                }
                .group-toggle {
                    background: none;
                    border: none;
                    color: var(--text-color);
                    cursor: pointer;
                    padding: 0.25rem;
                }
                .group-content {
                    background-color: var(--bg);
                    overflow: hidden;
                    transition: max-height 0.3s ease;
                    padding: 1rem;
                }
                .group-content.collapsed {
                    max-height: 0;
                    padding: 0;
                }
                .loading-overlay {
                    position: fixed;
                    top: 0;
                    left: 0;
                    width: 100%;
                    height: 100%;
                    background-color: rgba(0, 0, 0, 0.7);
                    display: flex;
                    justify-content: center;
                    align-items: center;
                    z-index: 1000;
                }
                .spinner {
                    width: 50px;
                    height: 50px;
                    border: 5px solid rgba(255, 255, 255, 0.3);
                    border-radius: 50%;
                    border-top-color: var(--primary-color);
                    animation: spin 1s ease-in-out infinite;
                }
                @keyframes spin {
                    to { transform: rotate(360deg); }
                }
                .hidden {
                    display: none;
                }
                /* Responsive styles */
                @media (max-width: 768px) {
                    .header-content {
                        flex-direction: column;
                        align-items: flex-start;
                    }
                    .stats {
                        grid-template-columns: 1fr;
                    }
                    .button-group {
                        width: 100%;
                        justify-content: space-between;
                    }
                }
                /* Tab refresh button styles */
                .refresh-tab-btn {
                    margin: 0.5rem 0 1rem 0;
                    display: inline-flex;
                    align-items: center;
                    gap: 0.5rem;
                    font-size: 0.875rem;
                    padding: 0.5rem 1rem;
                    border-radius: 0.25rem;
                }
            </style>
        </head>
        <body>
            <!-- Loading overlay -->
            <div id="loadingOverlay" class="loading-overlay hidden">
                <div class="spinner"></div>
            </div>
            <header>
                <div class="container">
                    <div class="header-content">
                        <h1 class="title">
                            <span class="material-icons">bug_report</span>
                            PHP Error Logger
                        </h1>
                        <div class="button-group">
                            <button id="refreshBtn" class="button">
                                <span class="material-icons">refresh</span>
                                Refresh
                            </button>
                            <button id="autoRefreshBtn" class="button secondary">
                                <span class="material-icons">autorenew</span>
                                Auto Refresh
                            </button>
                            <button id="backBtn" class="button secondary">
                                <span class="material-icons">arrow_back</span>
                                Back
                            </button>
                            <button id="clearLogBtn" class="button secondary">
                                <span class="material-icons">delete</span>
                                Clear Log
                            </button>
                        </div>
                    </div>
                </div>
            </header>
            <div class="container">
                <!-- Welcome and developer info -->
                <div id="welcomePanel" class="welcome">
                    <div class="welcome-text">Welcome to PHP Error Logger</div>
                </div>
                <!-- Tabs -->
                <div class="tabs">
                    <div id="parsedTab" class="tab active">Parsed Errors</div>
                    <div id="rawTab" class="tab">Raw Log</div>
                    <div id="recentTab" class="tab">Recent Errors</div>
                    <div id="reoccurredTab" class="tab">Error Reoccurred</div>
                </div>
                <!-- Group By Section -->
                <div class="group-by-container">
                    <label for="groupBy">Group By:</label>
                    <div class="group-options">
                        <div id="groupByNone" class="group-option active">None</div>
                        <div id="groupByFile" class="group-option">Files</div>
                        <div id="groupByType" class="group-option">Error Type</div>
                        <div id="groupByDate" class="group-option">Date</div>
                    </div>
                </div>
                <!-- Stats -->
                <div id="statsSection" class="stats">
                    <div class="stat-card">
                        <div class="stat-value" id="totalErrors">0</div>
                        <div class="stat-label">Total Errors</div>
                    </div>
                    <div class="stat-card">
                        <div class="stat-value" id="solvedErrors">0</div>
                        <div class="stat-label">Solved Errors</div>
                    </div>
                    <div class="stat-card">
                        <div class="stat-value" id="reoccurredErrors">0</div>
                        <div class="stat-label">Reoccurred Errors</div>
                    </div>
                    <div class="stat-card">
                        <div class="stat-value" id="errorTypes">0</div>
                        <div class="stat-label">Error Types</div>
                    </div>
                </div>
                <!-- Detailed Stats -->
                <div id="detailedStatsSection" class="detailed-stats" style="margin-bottom: 20px; display: none;">
                    <div class="card" style="padding: 16px;">
                        <h3 style="margin-top: 0;">Errors by Type</h3>
                        <div id="errorsByTypeContainer" style="display: grid; grid-template-columns: repeat(auto-fill, minmax(180px, 1fr)); gap: 10px;"></div>
                    </div>
                    <div class="card" style="margin-top: 16px; padding: 16px;">
                        <h3 style="margin-top: 0;">Errors by Date</h3>
                        <div id="errorsByDateContainer" style="display: grid; grid-template-columns: repeat(auto-fill, minmax(180px, 1fr)); gap: 10px;"></div>
                    </div>
                    <div style="text-align: center; margin-top: 10px;">
                        <button id="toggleDetailedStatsBtn" class="button secondary">
                            <span class="material-icons">analytics</span>
                            Show Detailed Stats
                        </button>
                    </div>
                </div>
                <!-- Tab content -->
                <div id="parsedContent" class="tab-content">
                    <div id="errorContainer"></div>
                </div>
                <div id="rawContent" class="tab-content hidden">
                    <div class="card">
                        <pre id="rawLogContent" style="padding: 1rem; overflow: auto; max-height: 600px;"></pre>
                    </div>
                </div>
                <div id="recentContent" class="tab-content hidden">
                    <div id="recentErrorContainer"></div>
                </div>
                <div id="reoccurredContent" class="tab-content hidden">
                    <div id="reoccurredErrorContainer"></div>
                </div>
            </div>
            <!-- Solve Error Modal -->
            <div id="solveModal" class="modal hidden">
                <div class="modal-content">
                    <div class="modal-header">
                        <h3 class="modal-title">Mark Error as Solved</h3>
                        <button class="modal-close">&times;</button>
                    </div>
                    <div id="errorInfoContainer" class="form-group">
                        <!-- Error details will be inserted here -->
                    </div>
                    <div class="form-actions">
                        <button id="cancelSolveBtn" class="button secondary">Cancel</button>
                        <button id="confirmSolveBtn" class="button">Mark as Solved</button>
                    </div>
                </div>
            </div>
            <!-- Clear Log Confirmation Modal -->
            <div id="clearLogModal" class="modal hidden">
                <div class="modal-content">
                    <div class="modal-header">
                        <h3 class="modal-title">Confirm Log Deletion</h3>
                        <button class="modal-close">&times;</button>
                    </div>
                    <div class="form-group">
                        <p>Are you sure you want to clear the error log? This action cannot be undone.</p>
                    </div>
                    <div class="form-actions">
                        <button id="cancelClearBtn" class="button secondary">Cancel</button>
                        <button id="confirmClearBtn" class="button">Clear Log</button>
                    </div>
                </div>
            </div>
            <!-- Theme Toggle -->
            <button id="themeToggle" class="toggle-theme">
                <span class="material-icons">dark_mode</span>
            </button>
            <script>
                // Global variables
                let allErrors = [];
                let solvedRecords = {};
                let autoRefreshInterval = null;
                let currentErrorHash = null;
                let apiFile = '##APIFILE##';
                // DOM elements
                const loadingOverlay = document.getElementById('loadingOverlay');
                const errorContainer = document.getElementById('errorContainer');
                const recentErrorContainer = document.getElementById('recentErrorContainer');
                const rawLogContent = document.getElementById('rawLogContent');
                const totalErrorsEl = document.getElementById('totalErrors');
                const solvedErrorsEl = document.getElementById('solvedErrors');
                const errorTypesEl = document.getElementById('errorTypes');
                const errorFilesEl = document.getElementById('errorFiles');
                // Event listeners for tabs
                document.getElementById('parsedTab').addEventListener('click', () => setActiveTab('parsed'));
                document.getElementById('rawTab').addEventListener('click', () => setActiveTab('raw'));
                document.getElementById('recentTab').addEventListener('click', () => setActiveTab('recent'));
                document.getElementById('reoccurredTab').addEventListener('click', () => setActiveTab('reoccurred'));
                // Button event listeners
                document.getElementById('refreshBtn').addEventListener('click', () => loadData());
                document.getElementById('autoRefreshBtn').addEventListener('click', toggleAutoRefresh);
                document.getElementById('backBtn').addEventListener('click', () => window.history.back());
                document.getElementById('clearLogBtn').addEventListener('click', showClearLogModal);
                document.getElementById('themeToggle').addEventListener('click', toggleTheme);
                // Toggle detailed stats
                document.getElementById('toggleDetailedStatsBtn').addEventListener('click', toggleDetailedStats);
                // Modal event listeners
                document.querySelectorAll('.modal-close').forEach(btn => {
                    btn.addEventListener('click', closeAllModals);
                });
                document.getElementById('confirmSolveBtn').addEventListener('click', solveError);
                document.getElementById('cancelSolveBtn').addEventListener('click', hideSolveModal);
                // Clear log modal event listeners
                document.getElementById('confirmClearBtn').addEventListener('click', clearLog);
                document.getElementById('cancelClearBtn').addEventListener('click', hideClearLogModal);
                // Group by event listeners
                document.getElementById('groupByNone').addEventListener('click', () => setGroupBy('none'));
                document.getElementById('groupByFile').addEventListener('click', () => setGroupBy('file'));
                document.getElementById('groupByType').addEventListener('click', () => setGroupBy('type'));
                document.getElementById('groupByDate').addEventListener('click', () => setGroupBy('date'));
                // Initialize
                document.addEventListener('DOMContentLoaded', () => {
                    // Check for saved theme
                    if (localStorage.getItem('theme') === 'dark') {
                        document.body.classList.add('dark');
                        document.getElementById('themeToggle').innerHTML = '<span class="material-icons">light_mode</span>';
                    }
                    // Initial data load
                    loadData();
                    // Initial tab activation
                    setActiveTab('parsed');
                });
                // Load errors and solver data
                function loadData() {
                    showLoading();
                    // Return a Promise so we can chain operations
                    return new Promise((resolve, reject) => {
                        // Check which tab is active to only fetch the necessary data
                        const activeTab = document.querySelector('.tab.active').id;
                        // Update statistics regardless of tab
                        updateStats();
                        if (activeTab === 'reoccurredTab') {
                            // Reoccurred tab uses its own API endpoint now
                            renderReoccurredErrors();
                            hideLoading();
                            resolve();
                            return;
                        }
                        if (activeTab === 'rawTab') {
                            // Raw tab has its own loading mechanism
                            loadRawLog();
                            hideLoading();
                            resolve();
                            return;
                        }
                        // For parsed and recent tabs, load the regular data
                        Promise.all([
                            fetch(`${apiFile}?action=all_solvers`).then(response => response.json()),
                            fetch(`${apiFile}?action=all_error`).then(response => response.json())
                        ])
                        .then(([solversData, errorsData]) => {
                            solvedRecords = solversData;
                            allErrors = errorsData.errors || [];
                            // Render the appropriate tab content
                            if (activeTab === 'parsedTab') {
                                renderErrors();
                            } else if (activeTab === 'recentTab') {
                                renderRecentErrors();
                            }
                            hideLoading();
                            resolve(); // Resolve the Promise when data is loaded
                        })
                        .catch(error => {
                            console.error('Error loading data:', error);
                            hideLoading();
                            alert('Failed to load error data. See console for details.');
                            reject(error); // Reject the Promise on error
                        });
                    });
                }
                // Render errors to the container
                function renderErrors() {
                    errorContainer.innerHTML = '';
                    if (allErrors.length === 0) {
                        errorContainer.innerHTML = `
                            <div style="text-align: center; padding: 2rem;">
                                <span class="material-icons" style="font-size: 3rem; color: var(--primary-color);">check_circle</span>
                                <p>No errors found. Everything looks good!</p>
                            </div>
                        `;
                        return;
                    }
                    // Group errors if necessary
                    if (currentGroupBy === 'none') {
                        // No grouping, render all errors
                        allErrors.forEach(error => renderErrorCard(error, errorContainer));
                    } else {
                        // Group errors
                        const groups = groupErrors(allErrors, currentGroupBy);
                        // Render groups
                        Object.keys(groups).sort().forEach(groupName => {
                            const groupContainer = document.createElement('div');
                            groupContainer.className = 'error-group';
                            // Create a single card for the group
                            const groupCard = document.createElement('div');
                            groupCard.className = 'card';
                            // Create the group header
                            const groupHeader = document.createElement('div');
                            groupHeader.className = 'group-header';
                            groupHeader.innerHTML =
                                '<h3>' + groupName + ' <span class="group-count">' + groups[groupName].length + '</span></h3>' +
                                '<button class="group-toggle"><span class="material-icons">expand_more</span></button>'
                            ;
                            // Create container for all errors in the group
                            const groupContent = document.createElement('div');
                            groupContent.className = 'group-content';
                            // Add each error to the group content
                            groups[groupName].forEach(error => {
                                const errorElement = createErrorElement(error);
                                groupContent.appendChild(errorElement);
                            });
                            // Add toggle functionality for the group
                            groupHeader.querySelector('.group-toggle').addEventListener('click', (e) => {
                                const icon = groupHeader.querySelector('.material-icons');
                                if (groupContent.classList.contains('collapsed')) {
                                    groupContent.classList.remove('collapsed');
                                    icon.textContent = 'expand_more';
                                } else {
                                    groupContent.classList.add('collapsed');
                                    icon.textContent = 'chevron_right';
                                }
                            });
                            // Assemble the group card
                            groupCard.appendChild(groupHeader);
                            groupCard.appendChild(groupContent);
                            // Add the card to the group container
                            groupContainer.appendChild(groupCard);
                            errorContainer.appendChild(groupContainer);
                        });
                    }
                }
                // Group errors by specified property
                function groupErrors(errors, groupBy) {
                    const groups = {};
                    errors.forEach(error => {
                        let groupKey;
                        switch(groupBy) {
                            case 'file':
                                // Group by file path, or 'Unknown' if no file
                                groupKey = error.file ? error.file : 'Unknown';
                                break;
                            case 'type':
                                // Group by error type
                                groupKey = error.type || 'Unknown';
                                break;
                            case 'date':
                                // Group by date (without time)
                                const date = new Date(error.timestamp * 1000);
                                groupKey = date.toLocaleDateString();
                                break;
                            default:
                                // Fallback
                                groupKey = 'Other';
                        }
                        if (!groups[groupKey]) {
                            groups[groupKey] = [];
                        }
                        groups[groupKey].push(error);
                    });
                    return groups;
                }
                // Helper function to create an error element inside a group
                function createErrorElement(error) {
                    const isSolved = isErrorSolved(error.hash);
                    const errorElement = document.createElement('div');
                    errorElement.className = `error-item ${isSolved ? 'Solved' : error.type}`;
                    // Format date
                    const date = new Date(error.timestamp * 1000);
                    const dateString = date.toLocaleString();
                    // Get file name from path
                    const fileName = error.file ? error.file.split('/').pop() : 'Unknown file';
                    errorElement.innerHTML = `
                        <div class="error-header">
                            <h3 class="error-title">${truncate(error.message.split('\n')[0], 80)}</h3>
                            <span class="error-badge ${isSolved ? 'Solved' : error.type}">${isSolved ? 'Solved' : error.type}</span>
                        </div>
                        <div class="error-details">
                            <div class="error-detail">
                                <span class="material-icons">insert_drive_file</span>
                                ${fileName}
                            </div>
                            <div class="error-detail">
                                <span class="material-icons">code</span>
                                Line ${error.line || 'unknown'}
                            </div>
                            <div class="error-detail">
                                <span class="material-icons">access_time</span>
                                ${dateString}
                            </div>
                            ${error.occurrences > 1 ? `
                            <div class="error-detail">
                                <span class="material-icons">repeat</span>
                                ${error.occurrences} occurrences
                            </div>
                            ` : ''}
                        </div>
                        <div class="error-message hidden">${error.message}</div>
                        <div class="error-actions">
                            <button class="button secondary toggle-details">View Details</button>
                            ${!isSolved ? `<button class="button solve-error" data-hash="${error.hash}">Mark Solved</button>` : ''}
                        </div>
                    `;
                    // Add event listeners
                    errorElement.querySelector('.toggle-details').addEventListener('click', () => {
                        const message = errorElement.querySelector('.error-message');
                        message.classList.toggle('hidden');
                        errorElement.querySelector('.toggle-details').textContent =
                            message.classList.contains('hidden') ? 'View Details' : 'Hide Details';
                    });
                    if (!isSolved) {
                        errorElement.querySelector('.solve-error').addEventListener('click', (e) => {
                            currentErrorHash = e.target.dataset.hash;
                            showSolveModal();
                        });
                    }
                    return errorElement;
                }
                // Helper function to render an error card (for ungrouped mode)
                function renderErrorCard(error, container) {
                    const isSolved = isErrorSolved(error.hash);
                    const errorCard = document.createElement('div');
                    errorCard.className = `card error-card ${isSolved ? 'Solved' : error.type}`;
                    // Format date
                    const date = new Date(error.timestamp * 1000);
                    const dateString = date.toLocaleString();
                    // Get file name from path
                    const fileName = error.file ? error.file.split('/').pop() : 'Unknown file';
                    errorCard.innerHTML = `
                        <div class="error-header">
                            <h3 class="error-title">${truncate(error.message.split('\n')[0], 80)}</h3>
                            <span class="error-badge ${isSolved ? 'Solved' : error.type}">${isSolved ? 'Solved' : error.type}</span>
                        </div>
                        <div class="error-details">
                            <div class="error-detail">
                                <span class="material-icons">insert_drive_file</span>
                                ${fileName}
                            </div>
                            <div class="error-detail">
                                <span class="material-icons">code</span>
                                Line ${error.line || 'unknown'}
                            </div>
                            <div class="error-detail">
                                <span class="material-icons">access_time</span>
                                ${dateString}
                            </div>
                            ${error.occurrences > 1 ? `
                            <div class="error-detail">
                                <span class="material-icons">repeat</span>
                                ${error.occurrences} occurrences
                            </div>
                            ` : ''}
                        </div>
                        <div class="error-message hidden">${error.message}</div>
                        <div class="error-actions">
                            <button class="button secondary toggle-details">View Details</button>
                            ${!isSolved ? `<button class="button solve-error" data-hash="${error.hash}">Mark Solved</button>` : ''}
                        </div>
                    `;
                    // Add event listeners
                    errorCard.querySelector('.toggle-details').addEventListener('click', () => {
                        const message = errorCard.querySelector('.error-message');
                        message.classList.toggle('hidden');
                        errorCard.querySelector('.toggle-details').textContent =
                            message.classList.contains('hidden') ? 'View Details' : 'Hide Details';
                    });
                    if (!isSolved) {
                        errorCard.querySelector('.solve-error').addEventListener('click', (e) => {
                            currentErrorHash = e.target.dataset.hash;
                            showSolveModal();
                        });
                    }
                    container.appendChild(errorCard);
                }
                // Render errors sorted by recency
                function renderRecentErrors() {
                    if (document.getElementById('recentTab').classList.contains('active')) {
                        recentErrorContainer.innerHTML = '';
                        // Sort errors by timestamp (newest first)
                        const recentErrors = [...allErrors].sort((a, b) => b.timestamp - a.timestamp);
                        if (recentErrors.length === 0) {
                            recentErrorContainer.innerHTML = `
                                <div style="text-align: center; padding: 2rem;">
                                    <span class="material-icons" style="font-size: 3rem; color: var(--primary-color);">check_circle</span>
                                    <p>No errors found. Everything looks good!</p>
                                </div>
                            `;
                            return;
                        }
                        // Group by or render as list based on current grouping
                        if (currentGroupBy === 'none') {
                            // No grouping, render all recent errors (limit to 10)
                            recentErrors.slice(0, 10).forEach(error => renderErrorCard(error, recentErrorContainer));
                        } else {
                            // Group recent errors by the chosen grouping
                            const groups = groupErrors(recentErrors.slice(0, 20), currentGroupBy);
                            // Render groups
                            Object.keys(groups).sort().forEach(groupName => {
                                const groupContainer = document.createElement('div');
                                groupContainer.className = 'error-group';
                                // Create a single card for the group
                                const groupCard = document.createElement('div');
                                groupCard.className = 'card';
                                // Create the group header
                                const groupHeader = document.createElement('div');
                                groupHeader.className = 'group-header';
                                groupHeader.innerHTML =
                                    '<h3>' + groupName + ' <span class="group-count">' + groups[groupName].length + '</span></h3>' +
                                    '<button class="group-toggle"><span class="material-icons">expand_more</span></button>'
                                ;
                                // Create container for all errors in the group
                                const groupContent = document.createElement('div');
                                groupContent.className = 'group-content';
                                // Add each error to the group content
                                groups[groupName].forEach(error => {
                                    const errorElement = createErrorElement(error);
                                    groupContent.appendChild(errorElement);
                                });
                                // Add toggle functionality
                                groupHeader.querySelector('.group-toggle').addEventListener('click', (e) => {
                                    const icon = groupHeader.querySelector('.material-icons');
                                    if (groupContent.classList.contains('collapsed')) {
                                        groupContent.classList.remove('collapsed');
                                        icon.textContent = 'expand_more';
                                    } else {
                                        groupContent.classList.add('collapsed');
                                        icon.textContent = 'chevron_right';
                                    }
                                });
                                // Assemble the group card
                                groupCard.appendChild(groupHeader);
                                groupCard.appendChild(groupContent);
                                // Add the card to the group container
                                groupContainer.appendChild(groupCard);
                                recentErrorContainer.appendChild(groupContainer);
                            });
                        }
                    }
                }
                // Check if an error is solved
                function isErrorSolved(hash) {
                    if (!hash || !solvedRecords || !solvedRecords.errors) return false;
                    if (solvedRecords.errors[hash]) {
                        // Check direct solved property
                        if (solvedRecords.errors[hash].solved === true) {
                            return true;
                        }
                        // Check solvers array
                        if (solvedRecords.errors[hash].solvers &&
                            solvedRecords.errors[hash].solvers.length > 0) {
                            return solvedRecords.errors[hash].solvers.some(solver => solver.solved === true);
                        }
                    }
                    return false;
                }
                // Update statistics using the new get_statics API
                function updateStats() {
                    fetch(`${apiFile}?action=get_statics`)
                        .then(response => {
                            if (!response.ok) throw new Error('Failed to fetch statistics');
                            return response.json();
                        })
                        .then(data => {
                            console.log("Statistics from API:", data);
                            // get the stats from the data
                            stats = data.statistics || {};
                            // Update summary statistics
                            totalErrorsEl.textContent = stats.total_errors || 0;
                            solvedErrorsEl.textContent = stats.total_solved_errors || 0;
                            // Add reoccurred errors count
                            const reoccurredErrorsEl = document.getElementById('reoccurredErrors');
                            if (reoccurredErrorsEl) {
                                reoccurredErrorsEl.textContent = stats.total_reoccurred_errors || 0;
                            }
                            // Error types count
                            const errorTypesCount = Object.keys(stats.total_errors_by_type || {}).length;
                            errorTypesEl.textContent = errorTypesCount;
                            // Update errors by type detailed stats
                            const errorsByTypeContainer = document.getElementById('errorsByTypeContainer');
                            if (errorsByTypeContainer) {
                                errorsByTypeContainer.innerHTML = '';
                                if (stats.total_errors_by_type) {
                                    Object.entries(stats.total_errors_by_type).sort((a, b) => b[1] - a[1]).forEach(([type, count]) => {
                                        const typeEl = document.createElement('div');
                                        typeEl.className = 'stat-item';
                                        typeEl.innerHTML = `
                                            <div style="display: flex; justify-content: space-between; align-items: center;">
                                                <span>${type}</span>
                                                <span style="font-weight: bold; color: var(--primary-color);">${count}</span>
                                            </div>
                                            <div class="stat-bar">
                                                <div style="width: ${(count / stats.total_errors * 100)}%; background-color: var(--primary-color); height: 6px; border-radius: 3px;"></div>
                                            </div>
                                        `;
                                        errorsByTypeContainer.appendChild(typeEl);
                                    });
                                }
                            }
                            // Update errors by date detailed stats
                            const errorsByDateContainer = document.getElementById('errorsByDateContainer');
                            if (errorsByDateContainer) {
                                errorsByDateContainer.innerHTML = '';
                                if (stats.total_errors_by_date) {
                                    Object.entries(stats.total_errors_by_date).sort((a, b) => {
                                        // Sort by date, most recent first
                                        const dateA = new Date(a[0]);
                                        const dateB = new Date(b[0]);
                                        return dateB - dateA;
                                    }).forEach(([date, count]) => {
                                        const dateEl = document.createElement('div');
                                        dateEl.className = 'stat-item';
                                        const formattedDate = new Date(date).toLocaleDateString();
                                        dateEl.innerHTML = `
                                            <div style="display: flex; justify-content: space-between; align-items: center;">
                                                <span>${formattedDate}</span>
                                                <span style="font-weight: bold; color: var(--primary-color);">${count}</span>
                                            </div>
                                            <div class="stat-bar">
                                                <div style="width: ${(count / stats.total_errors * 100)}%; background-color: var(--primary-color); height: 6px; border-radius: 3px;"></div>
                                            </div>
                                        `;
                                        errorsByDateContainer.appendChild(dateEl);
                                    });
                                }
                            }
                        })
                        .catch(error => {
                            console.error('Error updating statistics:', error);
                            // Fallback to client-side calculation if API fails
                            totalErrorsEl.textContent = allErrors.length;
                            const solved = allErrors.filter(error => isErrorSolved(error.hash)).length;
                            solvedErrorsEl.textContent = solved;
                            const types = new Set(allErrors.map(error => error.type));
                            errorTypesEl.textContent = types.size;
                            document.getElementById('reoccurredErrors').textContent = '0';
                        });
                }
                // Set active tab
                function setActiveTab(tab) {
                    console.log("Setting active tab:", tab);
                    // Update tab styling
                    document.querySelectorAll('.tab').forEach(el => el.classList.remove('active'));
                    // Hide all content
                    document.querySelectorAll('.tab-content').forEach(el => el.classList.add('hidden'));
                    // Set the active tab in the UI
                    document.getElementById(`${tab}Tab`).classList.add('active');
                    document.getElementById(`${tab}Content`).classList.remove('hidden');
                    // Handle tab-specific loading behavior
                    switch (tab) {
                        case 'raw':
                            // Raw tab has its own loading mechanism
                            loadRawLog();
                            break;
                        case 'reoccurred':
                            // Reoccurred errors tab uses dedicated API endpoint
                            renderReoccurredErrors();
                            break;
                        case 'parsed':
                            showLoading();
                            // Fetch data for parsed tab
                            Promise.all([
                                fetch(`${apiFile}?action=all_solvers`).then(response => response.json()),
                                fetch(`${apiFile}?action=recent_error`).then(response => response.json())
                            ])
                            .then(([solversData, errorsData]) => {
                                solvedRecords = solversData;
                                allErrors = errorsData.errors || [];
                                renderErrors();
                                updateDeveloperStats();
                                hideLoading();
                            })
                            .catch(error => {
                                console.error('Error loading data for parsed tab:', error);
                                hideLoading();
                            });
                            break;
                        case 'recent':
                            showLoading();
                            // Fetch data for recent tab
                            Promise.all([
                                fetch(`${apiFile}?action=all_solvers`).then(response => response.json()),
                                fetch(`${apiFile}?action=recent_error`).then(response => response.json())
                            ])
                            .then(([solversData, errorsData]) => {
                                solvedRecords = solversData;
                                allErrors = errorsData.errors || [];
                                renderRecentErrors();
                                hideLoading();
                            })
                            .catch(error => {
                                console.error('Error loading data for recent tab:', error);
                                hideLoading();
                            });
                            break;
                    }
                    // Always update statistics
                    updateStats();
                }
                // This function has been merged into setActiveTab
                // Keeping this comment as a placeholder to avoid changing line numbers too drastically
                // This functionality has been moved to the server-side API
                // The reoccurred API endpoint now handles this logic directly
                // Render reoccurred errors using the dedicated API endpoint
                function renderReoccurredErrors() {
                    console.log("Rendering reoccurred errors from API");
                    const reoccurredErrorContainer = document.getElementById('reoccurredErrorContainer');
                    reoccurredErrorContainer.innerHTML = '';
                    showLoading();
                    // Use the new dedicated API endpoint for reoccurred errors
                    fetch(`${apiFile}?action=reoccurred`)
                        .then(response => {
                            if (!response.ok) throw new Error('Failed to fetch reoccurred errors');
                            return response.json();
                        })
                        .then(response => {
                            // The API now returns {errors: [...]} format for consistency with other endpoints
                            const reoccurredErrors = response.errors || [];
                            console.log("Reoccurred errors from API:", reoccurredErrors);
                            if (!reoccurredErrors || reoccurredErrors.length === 0) {
                                reoccurredErrorContainer.innerHTML = `
                                    <div style="text-align: center; padding: 2rem;">
                                        <span class="material-icons" style="font-size: 3rem; color: var(--primary-color);">check_circle</span>
                                        <p>No reoccurred errors found.</p>
                                    </div>
                                `;
                                hideLoading();
                                return;
                            }
                            // Display each reoccurred error with additional context
                            reoccurredErrors.forEach(error => {
                                // The API already provides the necessary information
                                // Add formatted dates for display
                                error.solvedInfo = {
                                    solvedDate: new Date(error.last_solved_at * 1000).toLocaleString(),
                                    reoccurredDate: new Date(error.last_occurred_at * 1000).toLocaleString()
                                };
                                renderStateCard(error, reoccurredErrorContainer, 'reoccurred');
                            });
                            hideLoading();
                        })
                        .catch(error => {
                            console.error('Error loading reoccurred errors:', error);
                            reoccurredErrorContainer.innerHTML = `
                                <div style="text-align: center; padding: 2rem;">
                                    <span class="material-icons" style="font-size: 3rem; color: var(--error-color);">error</span>
                                    <p>Failed to load reoccurred errors. Please try again.</p>
                                </div>
                            `;
                            hideLoading();
                        });
                }
                // Render a state card for reoccurred errors
                function renderStateCard(error, container, state) {
                    const isSolved = isErrorSolved(error.hash);
                    const errorCard = document.createElement('div');
                    errorCard.className = `card error-card ${error.type}`; // Match the regular error card class
                    // Format date
                    const date = new Date(error.timestamp * 1000);
                    const dateString = date.toLocaleString();
                    // Get file name from path
                    const fileName = error.file ? error.file.split('/').pop() : 'Unknown file';
                    // Get the first line of the message (split by newline markers or just take as is)
                    let shortMessage = error.message;
                    if (typeof shortMessage === 'string') {
                        if (shortMessage.includes('##NEW_LINE_SEPARATOR##')) {
                            shortMessage = shortMessage.split('##NEW_LINE_SEPARATOR##')[0];
                        } else if (shortMessage.includes('\n')) {
                            shortMessage = shortMessage.split('\n')[0];
                        }
                    }
                    // Format the message for display
                    let formattedMessage = error.message;
                    if (typeof formattedMessage === 'string') {
                        formattedMessage = formattedMessage.replace(/\|\|PEV-NEW-LINE\|\|/g, '<br>');
                    }
                    // Get occurrences count
                    let occurrencesHtml = '';
                    if (error.occurred && error.occurred.times && error.occurred.times > 1) {
                        occurrencesHtml = `
                        <div class="error-detail">
                            <span class="material-icons">repeat</span>
                            ${error.occurred.times} occurrences
                        </div>`;
                    } else if (error.occurrences && error.occurrences > 1) {
                        occurrencesHtml = `
                        <div class="error-detail">
                            <span class="material-icons">repeat</span>
                            ${error.occurrences} occurrences
                        </div>`;
                    }
                    // For reoccurred errors, add information about when it was solved and when it reoccurred
                    let reoccurrenceInfo = '';
                    if (state === 'reoccurred') {
                        // Get solved by information from data if available
                        let solverName = 'Unknown';
                        if (error.solvers && error.solvers.length > 0) {
                            solverName = error.solvers[error.solvers.length - 1].solved_by || 'Unknown';
                        }
                        // Use the solvedInfo which is already calculated (for new API) or previously cached data
                        let solvedDate = error.solvedInfo ? error.solvedInfo.solvedDate : 'Unknown';
                        let reoccurredDate = error.solvedInfo ? error.solvedInfo.reoccurredDate : 'Unknown';
                        // For the new API format
                        if (error.last_solved_at && !solvedDate) {
                            solvedDate = new Date(error.last_solved_at * 1000).toLocaleString();
                        }
                        if (error.last_occurred_at && !reoccurredDate) {
                            reoccurredDate = new Date(error.last_occurred_at * 1000).toLocaleString();
                        }
                        reoccurrenceInfo = `
                        <div class="reoccurrence-info">
                            <div><strong>Solved by:</strong> ${solverName}</div>
                            <div><strong>Solved on:</strong> ${solvedDate}</div>
                            <div><strong>Reoccurred on:</strong> ${reoccurredDate}</div>
                        </div>`;
                        // Add a "Reoccurred" badge alongside the original type
                        errorCard.classList.add('reoccurred-error');
                    }
                    errorCard.innerHTML = `
                        <div class="error-header">
                            <h3 class="error-title">${truncate(shortMessage, 80)}</h3>
                            <div style="display: flex; gap: 5px;">
                                <span class="error-badge ${error.type}">${error.type}</span>
                                ${state === 'reoccurred' ? `<span class="error-badge reoccurred">Reoccurred</span>` : ''}
                            </div>
                        </div>
                        <div class="error-details">
                            <div class="error-detail">
                                <span class="material-icons">insert_drive_file</span>
                                ${fileName}
                            </div>
                            <div class="error-detail">
                                <span class="material-icons">code</span>
                                Line ${error.line || 'unknown'}
                            </div>
                            <div class="error-detail">
                                <span class="material-icons">access_time</span>
                                ${dateString}
                            </div>
                            ${occurrencesHtml}
                        </div>
                        ${reoccurrenceInfo}
                        <div class="error-message hidden">${formattedMessage}</div>
                        <div class="error-actions">
                            <button class="button secondary toggle-details">View Details</button>
                            <button class="button solve-error" data-hash="${error.hash}">Mark Solved Again</button>
                        </div>
                    `;
                    // Add event listeners
                    errorCard.querySelector('.toggle-details').addEventListener('click', () => {
                        const message = errorCard.querySelector('.error-message');
                        message.classList.toggle('hidden');
                        errorCard.querySelector('.toggle-details').textContent =
                            message.classList.contains('hidden') ? 'View Details' : 'Hide Details';
                    });
                    // For reoccurred errors, always attach the solve button event listener
                    // For other errors, only attach if not solved
                    if (state === 'reoccurred' || !isSolved) {
                        const solveButton = errorCard.querySelector('.solve-error');
                        if (solveButton) {
                            solveButton.addEventListener('click', (e) => {
                                currentErrorHash = e.target.dataset.hash;
                                showSolveModal();
                            });
                        }
                    }
                    container.appendChild(errorCard);
                }
                // Global variable for current grouping
                let currentGroupBy = 'none';
                // Set grouping option
                function setGroupBy(option) {
                    currentGroupBy = option;
                    // Update button styling
                    document.querySelectorAll('.group-option').forEach(el => el.classList.remove('active'));
                    document.getElementById('groupBy' + option.charAt(0).toUpperCase() + option.slice(1)).classList.add('active');
                    // Re-render errors with the new grouping
                    renderErrors();
                }
                // Load raw log content
                function loadRawLog() {
                    showLoading();
                    fetch(`${apiFile}?action=raw_log`)
                        .then(response => {
                            if (!response.ok) throw new Error('Failed to fetch raw log');
                            return response.json();
                        })
                        .then(data => {
                            rawLogContent.textContent = data.raw_log || 'No log data available';
                            hideLoading();
                        })
                        .catch(error => {
                            console.error('Error loading raw log:', error);
                            hideLoading();
                            rawLogContent.textContent = 'Error loading raw log';
                        });
                }
                // Toggle auto-refresh
                function toggleAutoRefresh() {
                    const btn = document.getElementById('autoRefreshBtn');
                    if (autoRefreshInterval) {
                        clearInterval(autoRefreshInterval);
                        autoRefreshInterval = null;
                        btn.classList.remove('active');
                        btn.classList.add('secondary');
                    } else {
                        btn.classList.add('active');
                        btn.classList.remove('secondary');
                        autoRefreshInterval = setInterval(loadData, 5000);
                    }
                }
                // Solve an error
                function solveError() {
                    if (!currentErrorHash) return;
                    // Find the error data to include with the solution
                    const errorData = allErrors.find(err => err.hash === currentErrorHash);
                    const data = {
                        file: errorData?.file || null,
                        line: errorData?.line || null,
                        message: errorData?.message || null,
                        category: errorData?.type || null
                    };
                    showLoading();
                    fetch(`${apiFile}?action=add_solver`, {
                        method: 'POST',
                        headers: {
                            'Content-Type': 'application/json'
                        },
                        body: JSON.stringify({
                            hash: currentErrorHash,
                            solver_name: 'Anonymous',
                            data: data
                        })
                    })
                    .then(response => {
                        if (!response.ok) throw new Error('Failed to mark error as solved');
                        return response.json();
                    })
                    .then(data => {
                        if (data.success) {
                            // Reload data to update UI
                            loadData();
                            hideSolveModal();
                        } else {
                            throw new Error(data.error || 'Failed to mark error as solved');
                        }
                    })
                    .catch(error => {
                        console.error('Error solving error:', error);
                        hideLoading();
                        alert('Failed to mark error as solved');
                    });
                }
                function showSolveModal() {
                    // Find the error data to display
                    const errorData = allErrors.find(err => err.hash === currentErrorHash);
                    const errorInfoContainer = document.getElementById('errorInfoContainer');
                    if (errorData) {
                        errorInfoContainer.innerHTML = `
                            <div class="error-info">
                                <div class="error-info-item">
                                    <strong>File:</strong> ${errorData.file || 'N/A'}
                                </div>
                                <div class="error-info-item">
                                    <strong>Line:</strong> ${errorData.line || 'N/A'}
                                </div>
                                <div class="error-info-item">
                                    <strong>Type:</strong> ${errorData.type || 'N/A'}
                                </div>
                                <div class="error-info-item">
                                    <strong>Message:</strong> ${errorData.message || 'N/A'}
                                </div>
                                <p class="info-text">This error will be marked as solved with the above details included automatically.</p>
                            </div>
                        `;
                    }
                    document.getElementById('solveModal').classList.remove('hidden');
                }
                function hideSolveModal() {
                    document.getElementById('solveModal').classList.add('hidden');
                    currentErrorHash = null;
                }
                function closeAllModals() {
                    document.querySelectorAll('.modal').forEach(modal => {
                        modal.classList.add('hidden');
                    });
                    currentErrorHash = null;
                }
                // Show the clear log confirmation modal
                function showClearLogModal() {
                    document.getElementById('clearLogModal').classList.remove('hidden');
                }
                // Hide the clear log confirmation modal
                function hideClearLogModal() {
                    document.getElementById('clearLogModal').classList.add('hidden');
                }
                // Clear the error log
                function clearLog() {
                    showLoading();
                    fetch(`${apiFile}?action=clear_log`, {
                        method: 'POST',
                        headers: {
                            'Content-Type': 'application/json'
                        }
                    })
                    .then(response => response.json())
                    .then(data => {
                        if (data.success) {
                            // Reload data after successful clearing
                            loadData().then(() => {
                                // Show success message
                                alert('Error log cleared successfully!');
                            });
                        } else {
                            alert('Failed to clear error log');
                        }
                        hideClearLogModal();
                    })
                    .catch(error => {
                        console.error('Error clearing log:', error);
                        alert('An error occurred while clearing the log');
                        hideClearLogModal();
                    })
                    .finally(() => {
                        hideLoading();
                    });
                }
                // Toggle theme
                function toggleTheme() {
                    const btn = document.getElementById('themeToggle');
                    if (document.body.classList.contains('dark')) {
                        document.body.classList.remove('dark');
                        btn.innerHTML = '<span class="material-icons">dark_mode</span>';
                        localStorage.setItem('theme', 'light');
                    } else {
                        document.body.classList.add('dark');
                        btn.innerHTML = '<span class="material-icons">light_mode</span>';
                        localStorage.setItem('theme', 'dark');
                    }
                }
                // Show/hide loading overlay
                function showLoading() {
                    loadingOverlay.classList.remove('hidden');
                }
                function hideLoading() {
                    loadingOverlay.classList.add('hidden');
                }
                // Utility functions
                function truncate(str, length) {
                    if (!str) return '';
                    return str.length > length ? str.substring(0, length) + '...' : str;
                }
                // Toggle detailed statistics view
                function toggleDetailedStats() {
                    const detailedStatsSection = document.getElementById('detailedStatsSection');
                    const btn = document.getElementById('toggleDetailedStatsBtn');
                    if (detailedStatsSection.style.display === 'none') {
                        detailedStatsSection.style.display = 'block';
                        btn.innerHTML = '<span class="material-icons">analytics</span> Hide Detailed Stats';
                        // Refresh the stats to ensure detailed stats are up to date
                        updateStats();
                    } else {
                        detailedStatsSection.style.display = 'none';
                        btn.innerHTML = '<span class="material-icons">analytics</span> Show Detailed Stats';
                    }
                }
            </script>
        </body>
        </html>
        HTML;
    // Now replace the placeholder with the actual PHP script name
    $page = str_replace('##APIFILE##', ERROR_VIEW_FILE, $page);
    $page = str_replace('##NEW_LINE_SEPARATOR##', NEW_LINE_SEPARATOR, $page);
    return $page;
}