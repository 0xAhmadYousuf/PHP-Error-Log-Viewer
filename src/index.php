<?php

if (isset($_GET['clear'])) {
    file_put_contents('error_log', '');
    header('Location: ./');
    die;
}

// Load solved errors data
$solvedData = [];
$solvedFile = 'solved_errors.json';
if (file_exists($solvedFile)) {
    $solvedData = json_decode(file_get_contents($solvedFile), true) ?: [];
}

$log = file_get_contents('error_log');

// Improved regex to capture ALL error blocks including multiline ones
preg_match_all('/^\[([^\]]+)\]\s*(.*?)(?=\n\[|\z)/sm', $log, $all_matches, PREG_SET_ORDER);

$parsed_errors = [];
$recent_errors = [];
$non_php_errors = [];
$solved_but_reoccurred = [];

foreach (array_reverse($all_matches) as $match) {
    $timestamp = trim($match[1]);
    $full_content = trim($match[2]);

    if (empty($full_content))
        continue;

    $error_type = 'Unknown';
    $is_php_error = false;

    if (preg_match('/^PHP\s+(\w+):\s*/i', $full_content, $phpMatch)) {
        $is_php_error = true;
        $error_type = $phpMatch[1];
        $error_content = preg_replace('/^PHP\s+\w+:\s*/i', '', $full_content);
    } else {
        $error_content = $full_content;
    }

    $main_message = '';
    $file_info = '';
    $line_number = '';
    $stack_trace = '';

    if (preg_match('/^(.*?)\s+in\s+([^\s]+)\s+on\s+line\s+(\d+)/s', $error_content, $msgMatch)) {
        $main_message = trim($msgMatch[1]);
        $file_info = trim($msgMatch[2]);
        $line_number = trim($msgMatch[3]);

        if (preg_match('/Stack trace:(.*?)(?=thrown in|$)/s', $error_content, $stackMatch)) {
            $stack_trace = trim($stackMatch[1]);
        }
    } elseif (preg_match('/^(.*?)\s+thrown in\s+([^\s]+)\s+on\s+line\s+(\d+)/s', $error_content, $thrownMatch)) {
        $main_message = trim($thrownMatch[1]);
        $file_info = trim($thrownMatch[2]);
        $line_number = trim($thrownMatch[3]);
    } else {
        $main_message = $error_content;

        if (preg_match('/([\/\w\-\.]+\.php)/', $error_content, $fileMatch)) {
            $file_info = $fileMatch[1];
        }

        if (preg_match('/line\s+(\d+)/', $error_content, $lineMatch)) {
            $line_number = $lineMatch[1];
        }
    }

    $main_message = preg_replace('/\s+/', ' ', $main_message);
    $display_file = $file_info ? basename(dirname($file_info)) . '/' . basename($file_info) : 'Unknown';
    $short_message = strlen($main_message) > 150 ? substr($main_message, 0, 150) . '...' : $main_message;

    // Check if file or specific error is solved
    $file_solved = isset($solvedData['files'][$file_info]) && $solvedData['files'][$file_info]['solved'];
    $error_hash = md5($file_info . $main_message . $line_number);
    $error_solved = isset($solvedData['errors'][$error_hash]) && $solvedData['errors'][$error_hash]['solved'];

    // Check if error was solved but occurred again
    $is_reoccurred = false;
    if ($file_solved || $error_solved) {
        $solve_timestamp = '';
        if ($file_solved && isset($solvedData['files'][$file_info]['timestamp'])) {
            $solve_timestamp = $solvedData['files'][$file_info]['timestamp'];
        } elseif ($error_solved && isset($solvedData['errors'][$error_hash]['timestamp'])) {
            $solve_timestamp = $solvedData['errors'][$error_hash]['timestamp'];
        }

        if ($solve_timestamp && strtotime($timestamp) > strtotime($solve_timestamp)) {
            $is_reoccurred = true;

            // Add to solved but reoccurred list
            $group_key = $file_info ? basename(dirname($file_info)) . '/' . basename($file_info) : 'Unknown Location';
            if (!isset($solved_but_reoccurred[$group_key])) {
                $solved_but_reoccurred[$group_key] = [];
            }
            $error_key = $error_type . ': ' . $short_message;
            if (!isset($solved_but_reoccurred[$group_key][$error_key])) {
                $solved_but_reoccurred[$group_key][$error_key] = [
                    'occurrences' => [],
                    'details' => [
                        'type' => $error_type,
                        'tag_class' => getErrorTagClass($error_type),
                        'message' => $main_message,
                        'file' => $file_info,
                        'line' => $line_number,
                        'stack_trace' => $stack_trace,
                        'full_content' => $full_content,
                        'file_solved' => $file_solved,
                        'error_solved' => $error_solved,
                        'error_hash' => $error_hash,
                        'solved_by' => $file_solved ? ($solvedData['files'][$file_info]['solved_by'] ?? 'Unknown') : ($solvedData['errors'][$error_hash]['solved_by'] ?? 'Unknown'),
                        'solve_timestamp' => $solve_timestamp,
                        'is_reoccurred' => true
                    ]
                ];
            }
            $solved_but_reoccurred[$group_key][$error_key]['occurrences'][] = $timestamp;
        }
    }

    $is_non_php_error = false;
    if (
        $is_php_error && (
            strpos($file_info, 'vendor/') !== false ||
            strpos($main_message, 'Uncaught') !== false ||
            strpos($file_info, '/lib/') !== false ||
            strpos($file_info, '/src/') !== false ||
            preg_match('/Exception|Error.*thrown/', $main_message)
        )
    ) {
        $is_non_php_error = true;
    }

    if ($is_non_php_error) {
        $library_name = 'Unknown Library';

        if (preg_match('/vendor\/([^\/]+)\/([^\/]+)/', $file_info, $vendorMatch)) {
            $vendor = ucfirst($vendorMatch[1]);
            $package = ucfirst($vendorMatch[2]);
            $library_name = "{$vendor}/{$package}";
        } elseif (preg_match('/vendor\/([^\/]+)/', $file_info, $vendorMatch)) {
            $library_name = ucfirst($vendorMatch[1]);
        }

        // Common libraries detection
        $commonLibs = [
            'guzzle' => 'GuzzleHttp',
            'stripe' => 'Stripe',
            'monolog' => 'Monolog',
            'symfony' => 'Symfony',
            'laravel' => 'Laravel',
            'composer' => 'Composer'
        ];

        foreach ($commonLibs as $key => $name) {
            if (stripos($full_content, $key) !== false) {
                $library_name = $name;
                break;
            }
        }

        $group_key = "🔥 " . $library_name . " Error";
        $error_key = $error_type . ': ' . $short_message;

        if (!isset($non_php_errors[$group_key])) {
            $non_php_errors[$group_key] = [];
        }

        if (!isset($non_php_errors[$group_key][$error_key])) {
            $non_php_errors[$group_key][$error_key] = [
                'occurrences' => [],
                'details' => [
                    'type' => $error_type,
                    'tag_class' => getErrorTagClass($error_type),
                    'message' => $main_message,
                    'file' => $file_info,
                    'line' => $line_number,
                    'stack_trace' => $stack_trace,
                    'full_content' => $full_content,
                    'file_solved' => $file_solved,
                    'error_solved' => $error_solved,
                    'error_hash' => $error_hash,
                    'is_reoccurred' => $is_reoccurred,
                    'solve_timestamp' => $is_reoccurred ? $solve_timestamp : null,
                    'solved_by' => ($file_solved || $error_solved) ? ($file_solved ? ($solvedData['files'][$file_info]['solved_by'] ?? 'Unknown') : ($solvedData['errors'][$error_hash]['solved_by'] ?? 'Unknown')) : null
                ]
            ];
        }
        $non_php_errors[$group_key][$error_key]['occurrences'][] = $timestamp;
    } else {
        $group_key = $file_info ? $display_file : 'Unknown Location';
        $error_key = $error_type . ': ' . $short_message . ($file_info ? " ({$display_file})" : '');

        if (!isset($parsed_errors[$group_key])) {
            $parsed_errors[$group_key] = [];
        }
        if (!isset($parsed_errors[$group_key][$error_key])) {
            $parsed_errors[$group_key][$error_key] = [
                'occurrences' => [],
                'details' => [
                    'type' => $error_type,
                    'tag_class' => getErrorTagClass($error_type),
                    'message' => $main_message,
                    'file' => $file_info,
                    'line' => $line_number,
                    'stack_trace' => $stack_trace,
                    'full_content' => $full_content,
                    'file_solved' => $file_solved,
                    'error_solved' => $error_solved,
                    'error_hash' => $error_hash,
                    'is_reoccurred' => $is_reoccurred,
                    'solve_timestamp' => $is_reoccurred ? $solve_timestamp : null,
                    'solved_by' => ($file_solved || $error_solved) ? ($file_solved ? ($solvedData['files'][$file_info]['solved_by'] ?? 'Unknown') : ($solvedData['errors'][$error_hash]['solved_by'] ?? 'Unknown')) : null
                ]
            ];
        }
        $parsed_errors[$group_key][$error_key]['occurrences'][] = $timestamp;
    }

    $recent_errors[] = [
        'time' => $timestamp,
        'type' => $error_type,
        'tag_class' => getErrorTagClass($error_type),
        'message' => $main_message,
        'file' => $display_file,
        'line' => $line_number,
        'full_content' => $full_content,
        'is_non_php' => $is_non_php_error,
        'file_solved' => $file_solved,
        'error_solved' => $error_solved,
        'error_hash' => $error_hash,
        'full_file_path' => $file_info,
        'is_reoccurred' => $is_reoccurred,
        'solve_timestamp' => $is_reoccurred ? $solve_timestamp : null,
        'solved_by' => ($file_solved || $error_solved) ? ($file_solved ? ($solvedData['files'][$file_info]['solved_by'] ?? 'Unknown') : ($solvedData['errors'][$error_hash]['solved_by'] ?? 'Unknown')) : null
    ];
}

// Filter recent errors for 24h period
$twenty_four_hours_ago = time() - (24 * 60 * 60);
$recent_24h_errors = [];

foreach ($recent_errors as $error) {
    $error_time = strtotime($error['time']);
    if ($error_time !== false && $error_time >= $twenty_four_hours_ago) {
        $recent_24h_errors[] = $error;
    }
}

$recent_errors = array_slice($recent_24h_errors, 0, 20);

function typeColor($type)
{
    return match (strtolower($type)) {
        'warning' => 'bg-yellow-50 border-yellow-200 text-yellow-800',
        'notice' => 'bg-blue-50 border-blue-200 text-blue-800',
        'error', 'fatal' => 'bg-red-50 border-red-200 text-red-800',
        'deprecated' => 'bg-orange-50 border-orange-200 text-orange-800',
        'unknown' => 'bg-black border-gray-800 text-white error-unknown',
        default => 'bg-gray-50 border-gray-200 text-gray-800',
    };
}

function getProgressBarColor($type)
{
    return match (strtolower($type)) {
        'warning' => '#fbbf24', // yellow
        'notice' => '#3b82f6',  // blue
        'error', 'fatal' => '#ef4444', // red
        'deprecated' => '#f97316', // orange
        'unknown' => '#000000', // black
        default => '#6b7280', // gray
    };
}

function getErrorTagClass($type)
{
    return match (strtolower($type)) {
        'warning' => 'error-tag tag-warning',
        'notice' => 'error-tag tag-notice',
        'error', 'fatal' => 'error-tag tag-error',
        'deprecated' => 'error-tag tag-deprecated',
        'parse' => 'error-tag tag-parse',
        'unknown' => 'error-tag tag-unknown',
        default => 'error-tag tag-unknown',
    };
}

function generateProgressBar($errors, $reoccurred_errors = [])
{
    if (empty($errors) && empty($reoccurred_errors)) {
        return '<div class="w-full bg-gray-200 rounded-full h-4"><div class="bg-gray-400 h-4 rounded-full" style="width: 0%"></div></div>';
    }

    $total_errors = count($errors) + count($reoccurred_errors);
    $solved_count = 0;
    $error_types = [];

    // Count solved errors and categorize by type
    foreach ($errors as $error_data) {
        $type = strtolower($error_data['details']['type']);
        if (!isset($error_types[$type])) {
            $error_types[$type] = ['solved' => 0, 'total' => 0];
        }
        $error_types[$type]['total']++;

        if ($error_data['details']['file_solved'] || $error_data['details']['error_solved']) {
            $error_types[$type]['solved']++;
            $solved_count++;
        }
    }

    // Add reoccurred errors (these are considered unsolved again)
    foreach ($reoccurred_errors as $error_data) {
        $type = strtolower($error_data['details']['type']);
        if (!isset($error_types[$type])) {
            $error_types[$type] = ['solved' => 0, 'total' => 0];
        }
        $error_types[$type]['total']++;
        // Don't increment solved count for reoccurred errors
    }

    // Create individual segments for each error
    $segments = [];

    foreach ($error_types as $type => $counts) {
        $color = getProgressBarColor($type);

        // Add solved segments (with striped pattern to show they're solved)
        for ($i = 0; $i < $counts['solved']; $i++) {
            $segments[] = [
                'type' => $type,
                'solved' => true,
                'color' => $color
            ];
        }

        // Add unsolved segments
        for ($i = 0; $i < ($counts['total'] - $counts['solved']); $i++) {
            $segments[] = [
                'type' => $type,
                'solved' => false,
                'color' => $color
            ];
        }
    }

    $segment_width = $total_errors > 0 ? (100 / $total_errors) : 0;

    $progress_html = '<div class="w-full bg-gray-200 rounded-full h-4 flex overflow-hidden border border-gray-300">';

    foreach ($segments as $index => $segment) {
        $is_first = $index === 0;
        $is_last = $index === count($segments) - 1;

        $rounded_class = '';
        if ($is_first && $is_last) {
            $rounded_class = 'rounded-full';
        } elseif ($is_first) {
            $rounded_class = 'rounded-l-full';
        } elseif ($is_last) {
            $rounded_class = 'rounded-r-full';
        }

        if ($segment['solved']) {
            // Solved segments have diagonal stripes pattern
            $progress_html .= '<div class="h-4 border-r border-white border-opacity-50 relative ' . $rounded_class . '" 
                                    style="width: ' . $segment_width . '%; background-color: ' . $segment['color'] . ';" 
                                    title="Solved ' . ucfirst($segment['type']) . ' error">
                                    <div class="absolute inset-0 opacity-40 ' . $rounded_class . '" 
                                         style="background-image: repeating-linear-gradient(45deg, transparent, transparent 2px, rgba(255,255,255,0.8) 2px, rgba(255,255,255,0.8) 4px);"></div>
                               </div>';
        } else {
            // Unsolved segments are solid color
            $progress_html .= '<div class="h-4 border-r border-white border-opacity-50 ' . $rounded_class . '" 
                                    style="width: ' . $segment_width . '%; background-color: ' . $segment['color'] . ';" 
                                    title="Unsolved ' . ucfirst($segment['type']) . ' error"></div>';
        }
    }

    $progress_html .= '</div>';
    return $progress_html;
}

function renderErrorGroup($group, $errors, $solvedData, $solved_but_reoccurred = [])
{
    $error_count = array_sum(array_map(fn($e) => count($e['occurrences']), $errors));
    $solved_count = 0;
    $reoccurred_count = 0;

    // Check if this group has reoccurred errors
    if (isset($solved_but_reoccurred[$group])) {
        $reoccurred_count = count($solved_but_reoccurred[$group]);
    }

    foreach ($errors as $error_data) {
        // Don't count reoccurred errors as solved
        if (
            ($error_data['details']['file_solved'] || $error_data['details']['error_solved']) &&
            !($error_data['details']['is_reoccurred'] ?? false)
        ) {
            $solved_count++;
        }
    }

    $group_id = 'group_' . md5($group);
    $progress_percent = count($errors) > 0 ? round(($solved_count / count($errors)) * 100) : 0;

    // Check if entire file is solved
    $file_path = '';
    foreach ($errors as $error_data) {
        if (!empty($error_data['details']['file'])) {
            $file_path = $error_data['details']['file'];
            break;
        }
    }
    $file_solved = isset($solvedData['files'][$file_path]) && $solvedData['files'][$file_path]['solved'];

    $output = "
    <div class='mb-6 border border-custom rounded-lg overflow-hidden bg-custom-secondary relative'>";

    // Generate progress bar with different segments
    $progress_bar = generateProgressBar($errors, $solved_but_reoccurred[$group] ?? []);

    $output .= "
        <div class='bg-custom-accent p-4 border-b border-custom cursor-pointer group-header' onclick='toggleGroup(\"{$group_id}\")'>
            <div class='flex justify-between items-center'>
                <h2 class='text-lg font-semibold text-custom-primary'>
                    📁 <code class='bg-custom-secondary px-2 py-1 rounded'>{$group}</code>
                </h2>
                <div class='flex items-center gap-3'>
                    <div class='text-sm text-custom-secondary'>
                        Progress: {$solved_count}/{$error_count} ({$progress_percent}%)";

    if ($reoccurred_count > 0) {
        $reoccurred_list = [];
        foreach ($solved_but_reoccurred[$group] ?? [] as $error_key => $error_data) {
            $reoccurred_list[] = $error_data['details']['message'];
        }
        $reoccurred_json = htmlentities(json_encode($reoccurred_list), ENT_QUOTES);
        $output .= " | <span class='text-red-600 font-bold cursor-pointer hover:underline' onclick='showReoccurredList(\"" . addslashes($group) . "\", {$reoccurred_json})' title='Click to see which errors reoccurred'>⚠️ {$reoccurred_count} Reoccurred</span>";
    }

    $output .= "
                    </div>
                    <div class='w-40'>
                        {$progress_bar}
                    </div>";

    if ($file_solved) {
        $solved_by = $solvedData['files'][$file_path]['solved_by'] ?? 'Unknown';
        $output .= "
                    <div class='bg-green-500 text-white px-3 py-1 rounded text-sm'>
                        ✓ Solved by {$solved_by}
                    </div>";
    } else {
        $output .= "
                    <button onclick='event.stopPropagation(); promptMarkSolved(\"file\", \"{$file_path}\")' 
                            class='bg-teal-700 hover:bg-teal-500 text-white px-3 py-1 rounded text-sm'>
                        ✓ Mark File Solved
                    </button>";
    }

    $output .= "
                </div>
            </div>
        </div>
        <div id='{$group_id}' class='space-y-3 p-4 hidden'>
    ";

    foreach ($errors as $message => $error_data) {
        $details = $error_data['details'];
        $occurrences = $error_data['occurrences'];
        $color = typeColor($details['type']);
        $times_id = 'times_' . md5($group . $message);
        $stack_id = 'stack_' . md5($group . $message);

        $output .= "
        <div class='border border-custom rounded-lg p-4 {$color} relative'>
            " . "
            
            " . ($details['is_reoccurred'] ?? false ? "
            <div class='absolute top-2 right-2 bg-red-500 text-white px-2 py-1 rounded text-xs cursor-pointer' 
                 onclick=\"showReoccurrenceDetails(" .
                htmlspecialchars(json_encode($details['error_hash']), ENT_QUOTES) . ", " .
                htmlspecialchars(json_encode($details['solved_by'] ?? 'Unknown'), ENT_QUOTES) . ", " .
                htmlspecialchars(json_encode($details['solve_timestamp'] ? date('M j, Y H:i', strtotime($details['solve_timestamp'])) : 'Unknown'), ENT_QUOTES) . ", " .
                htmlspecialchars(json_encode($details['message']), ENT_QUOTES) . ", " .
                htmlspecialchars(json_encode($details['file']), ENT_QUOTES) . ", " .
                htmlspecialchars(json_encode($details['line']), ENT_QUOTES) . ")\"
                 title='Click for reoccurrence details'>
                ⚠️ REOCCURRED
            </div>" : "") . "
            
            <div class='flex justify-between items-start mb-2'>
                <div class='flex-1'>
                    <div class='font-semibold text-sm mb-1'>
                        <span class='{$details['tag_class']}'>{$details['type']}</span>
                    </div>
                    <div class='text-sm font-medium mb-2 break-words'>" . htmlentities($details['message']) . "</div>
                    <div class='text-xs text-custom-secondary flex items-center justify-between'>
                        <div>
                            File: <code class='break-all'>{$details['file']}</code> | Line: <code>{$details['line']}</code>
                        </div>
                        <button onclick='copyPath(\"{$details['file']}\")' class='copy-btn' title='Copy file path'>
                            📋 Copy Path
                        </button>
                    </div>
                </div>
                <div class='flex flex-col gap-2 ml-4'>
                    <button class='text-xs text-blue-600 hover:underline whitespace-nowrap' onclick='toggleElement(\"{$times_id}\")'>
                        Times (" . count($occurrences) . ")
                    </button>
                    <button class='text-xs text-purple-600 hover:underline whitespace-nowrap' data-error-content='" . htmlentities($details['full_content'], ENT_QUOTES) . "' onclick='showFullErrorById(this)'>
                        Full Error
                    </button>";

        // Show solver name for solved errors that haven't reoccurred, otherwise show solve button
        if ($details['error_solved'] && !($details['is_reoccurred'] ?? false)) {
            $solved_by = $details['solved_by'] ?? 'Unknown';
            $output .= "
                    <div class='bg-green-500 text-white px-2 py-1 rounded text-xs'>
                        ✓ Solved by {$solved_by}
                    </div>";
        } else {
            $output .= "
                    <button onclick='promptMarkSolved(\"error\", \"{$details['error_hash']}\")' 
                            class='bg-teal-700 hover:bg-teal-500 text-white px-2 py-1 rounded text-xs'>
                        ✓ Solve
                    </button>";
        }

        $output .= "";

        if (!empty($details['stack_trace'])) {
            $output .= "
                    <button class='text-xs text-purple-600 hover:underline whitespace-nowrap' onclick='toggleElement(\"{$stack_id}\")'>
                        Stack
                    </button>";
        }

        $output .= "
                </div>
            </div>
            
            <div id='{$times_id}' class='hidden mt-3'>
                <div class='text-xs font-semibold text-custom-secondary mb-2'>Occurrences:</div>
                <div class='flex flex-wrap gap-2'>";

        foreach (array_slice($occurrences, 0, 10) as $time) {
            $output .= "<span class='bg-custom-accent border border-custom text-xs px-2 py-1 rounded shadow'>{$time}</span>";
        }

        if (count($occurrences) > 10) {
            $output .= "<span class='text-xs text-custom-secondary'>... and " . (count($occurrences) - 10) . " more</span>";
        }

        $output .= "</div></div>";

        if (!empty($details['stack_trace'])) {
            $output .= "<div id='{$stack_id}' class='hidden mt-3 p-3 bg-custom-accent rounded text-xs font-mono'>
                <div class='font-semibold text-custom-primary mb-2'>Stack Trace:</div>
                <pre class='text-custom-secondary whitespace-pre-wrap'>" . htmlentities($details['stack_trace']) . "</pre>
            </div>";
        }

        $output .= "</div>";
    }

    $output .= "</div></div>";
    return $output;
}

ob_start();
?>

<!-- Statistics Cards -->
<div class="grid grid-cols-1 md:grid-cols-5 gap-4 mb-6">
    <div class="bg-red-100 border border-red-300 rounded-lg p-4 text-center">
        <div class="text-2xl font-bold text-red-800"><?= count($non_php_errors) ?></div>
        <div class="text-sm text-red-600">Critical Errors</div>
    </div>
    <div class="bg-yellow-100 border border-yellow-300 rounded-lg p-4 text-center">
        <div class="text-2xl font-bold text-yellow-800"><?= count($parsed_errors) ?></div>
        <div class="text-sm text-yellow-600">PHP Error Files</div>
    </div>
    <div class="bg-blue-100 border border-blue-300 rounded-lg p-4 text-center">
        <div class="text-2xl font-bold text-blue-800"><?= count($recent_errors) ?></div>
        <div class="text-sm text-blue-600">Recent (24h)</div>
    </div>
    <div class="bg-green-100 border border-green-300 rounded-lg p-4 text-center">
        <div class="text-2xl font-bold text-green-800">
            <?= count($solvedData['files'] ?? []) + count($solvedData['errors'] ?? []) ?></div>
        <div class="text-sm text-green-600">Solved Items</div>
    </div>
    <div class="bg-orange-100 border border-orange-300 rounded-lg p-4 text-center">
        <div class="text-2xl font-bold text-orange-800"><?= array_sum(array_map('count', $solved_but_reoccurred)) ?>
        </div>
        <div class="text-sm text-orange-600">Solved but Reoccurred</div>
    </div>
</div>

<!-- Master Progress Bar -->
<?php
$all_errors = [];

// Combine all error types for master progress bar - count the same way as individual groups
foreach ($parsed_errors as $group => $errors) {
    foreach ($errors as $error_key => $error_data) {
        $type = strtolower($error_data['details']['type']);
        if (!isset($all_errors[$type])) {
            $all_errors[$type] = ['count' => 0, 'solved' => 0];
        }
        $all_errors[$type]['count']++;
        // Don't count reoccurred errors as solved
        if (
            ($error_data['details']['file_solved'] || $error_data['details']['error_solved']) &&
            !($error_data['details']['is_reoccurred'] ?? false)
        ) {
            $all_errors[$type]['solved']++;
        }
    }
}

foreach ($non_php_errors as $group => $errors) {
    foreach ($errors as $error_key => $error_data) {
        $type = strtolower($error_data['details']['type']);
        if (!isset($all_errors[$type])) {
            $all_errors[$type] = ['count' => 0, 'solved' => 0];
        }
        $all_errors[$type]['count']++;
        // Don't count reoccurred errors as solved
        if (
            ($error_data['details']['file_solved'] || $error_data['details']['error_solved']) &&
            !($error_data['details']['is_reoccurred'] ?? false)
        ) {
            $all_errors[$type]['solved']++;
        }
    }
}

$total_errors_count = array_sum(array_column($all_errors, 'count'));
$total_solved_count = array_sum(array_column($all_errors, 'solved'));
$total_progress = $total_errors_count > 0 ? round(($total_solved_count / $total_errors_count) * 100) : 0;
?>

<div class="bg-custom-secondary border border-custom rounded-lg p-4 mb-6">
    <h3 class="text-lg font-semibold text-custom-primary mb-3">📊 Master Progress Overview</h3>
    <div class="flex items-center justify-between mb-2">
        <span class="text-sm text-custom-secondary">Overall Progress:
            <?= $total_solved_count ?>/<?= $total_errors_count ?> (<?= $total_progress ?>%)</span>
        <span class="text-xs text-custom-secondary">Legend: Solved (faded) | Unsolved (full opacity)</span>
    </div>

    <!-- Master Progress Bar -->
    <div class="w-full bg-gray-200 rounded-full h-6 flex overflow-hidden mb-4 border border-gray-300">
        <?php foreach ($all_errors as $type => $data): ?>
            <?php
            $color = getProgressBarColor($type);
            $solved_width = $total_errors_count > 0 ? ($data['solved'] / $total_errors_count) * 100 : 0;
            $unsolved_width = $total_errors_count > 0 ? (($data['count'] - $data['solved']) / $total_errors_count) * 100 : 0;
            ?>
            <?php if ($solved_width > 0): ?>
                <div class="h-6 border-r border-white border-opacity-50 relative"
                    style="width: <?= $solved_width ?>%; background-color: <?= $color ?>;"
                    title="<?= ucfirst($type) ?>: <?= $data['solved'] ?> solved">
                    <div class="absolute inset-0 opacity-40"
                        style="background-image: repeating-linear-gradient(45deg, transparent, transparent 2px, rgba(255,255,255,0.8) 2px, rgba(255,255,255,0.8) 4px);">
                    </div>
                </div>
            <?php endif; ?>
            <?php if ($unsolved_width > 0): ?>
                <div class="h-6 border-r border-white border-opacity-50"
                    style="width: <?= $unsolved_width ?>%; background-color: <?= $color ?>;"
                    title="<?= ucfirst($type) ?>: <?= $data['count'] - $data['solved'] ?> unsolved"></div>
            <?php endif; ?>
        <?php endforeach; ?>
    </div>

    <!-- Error Type Legend -->
    <div class="flex flex-wrap gap-3 text-xs">
        <?php foreach ($all_errors as $type => $data): ?>
            <?php $color = getProgressBarColor($type); ?>
            <div class="flex items-center gap-1">
                <div class="w-3 h-3 rounded-sm border border-gray-300" style="background-color: <?= $color ?>;"></div>
                <span class="text-custom-secondary"><?= ucfirst($type) ?>
                    (<?= $data['solved'] ?>/<?= $data['count'] ?>)</span>
            </div>
        <?php endforeach; ?>
    </div>
</div>

<!-- Solved but Occurred Again Section -->
<?php if (!empty($solved_but_reoccurred)): ?>
    <div class="bg-orange-50 border-2 border-orange-300 rounded-lg overflow-hidden mb-6">
        <div class="bg-orange-200 p-4 border-b border-orange-300 cursor-pointer hover:bg-orange-300 transition-colors duration-200"
            onclick="toggleSection('solvedReoccurred')">
            <div class="flex items-center justify-between">
                <h2 class="text-xl font-bold text-orange-800 flex items-center gap-2">
                    <span id="solvedReoccurredIcon" class="transition-transform duration-200">▼</span>
                    ⚠️ Solved but Occurred Again
                    <span
                        class="text-sm bg-orange-300 text-orange-800 px-2 py-1 rounded"><?= array_sum(array_map('count', $solved_but_reoccurred)) ?>
                        errors</span>
                </h2>
            </div>
        </div>
        <div id="solvedReoccurred" class="p-4">
            <div class="text-sm text-orange-700 mb-4">These errors were previously marked as solved but have occurred again
                after the solve date.</div>
            <?php foreach ($solved_but_reoccurred as $group => $errors): ?>
                <div class="mb-4 border border-orange-200 rounded-lg overflow-hidden bg-orange-100">
                    <div class="bg-orange-200 p-3 border-b border-orange-300">
                        <h3 class="font-semibold text-orange-900">📁 <?= htmlentities($group) ?> (<?= count($errors) ?>
                            reoccurred errors)</h3>
                    </div>
                    <div class="p-3 space-y-2">
                        <?php foreach ($errors as $error_key => $error_data): ?>
                            <div class="bg-white border border-orange-200 rounded p-3">
                                <div class="flex justify-between items-start">
                                    <div class="flex-1">
                                        <div class="text-sm font-medium text-orange-900 mb-1">
                                            <span
                                                class="bg-orange-200 px-2 py-1 rounded text-xs"><?= htmlentities($error_data['details']['type']) ?></span>
                                            <?= htmlentities($error_data['details']['message']) ?>
                                        </div>
                                        <div class="text-xs text-orange-700">
                                            File: <code><?= htmlentities($error_data['details']['file']) ?></code> |
                                            Line: <code><?= htmlentities($error_data['details']['line']) ?></code>
                                        </div>
                                        <div class="text-xs text-orange-600 mt-1">
                                            Originally solved by:
                                            <strong><?= htmlentities($error_data['details']['solved_by']) ?></strong>
                                            on <?= date('M j, Y', strtotime($error_data['details']['solve_timestamp'])) ?>
                                        </div>
                                        <div class="text-xs text-orange-600">
                                            Reoccurred <?= count($error_data['occurrences']) ?> time(s) since then
                                        </div>
                                    </div>
                                    <div class="flex flex-col gap-2 ml-4">
                                        <button
                                            data-error-content="<?= htmlentities($error_data['details']['full_content'], ENT_QUOTES) ?>"
                                            onclick="showFullErrorById(this)" class="text-xs text-purple-600 hover:underline">
                                            Full Error
                                        </button>
                                        <button onclick="promptMarkSolved('error', '<?= $error_data['details']['error_hash'] ?>')"
                                            class="bg-teal-700 hover:bg-teal-500 text-white px-2 py-1 rounded text-xs">
                                            ✓ Re-Solve
                                        </button>
                                    </div>
                                </div>
                            </div>
                        <?php endforeach; ?>
                    </div>
                </div>
            <?php endforeach; ?>
        </div>
    </div>
<?php endif; ?>

<!-- Critical Errors -->
<?php if (!empty($non_php_errors)): ?>
    <div class="bg-red-50 border-2 border-red-200 rounded-lg overflow-hidden mb-6">
        <div class="bg-red-200 p-4 border-b border-red-300 cursor-pointer hover:bg-red-300 transition-colors duration-200"
            onclick="toggleSection('criticalErrors')">
            <div class="flex items-center justify-between">
                <h2 class="text-xl font-bold text-red-800 flex items-center gap-2">
                    <span id="criticalErrorsIcon" class="transition-transform duration-200">▶</span>
                    🚨 Critical Library/Framework Errors (only exist here)
                    <span class="text-sm bg-red-300 text-red-800 px-2 py-1 rounded"><?= count($non_php_errors) ?>
                        groups</span>
                </h2>
            </div>
        </div>
        <div id="criticalErrors" class="p-4 hidden">
            <?php foreach ($non_php_errors as $group => $errors): ?>
                <?= renderErrorGroup($group, $errors, $solvedData, $solved_but_reoccurred) ?>
            <?php endforeach; ?>
        </div>
    </div>
<?php endif; ?>

<!-- Recent Errors (24h) -->
<div class="bg-blue-50 border-2 border-blue-200 rounded-lg overflow-hidden mb-6">
    <div class="bg-blue-200 p-4 border-b border-blue-300 cursor-pointer hover:bg-blue-300 transition-colors duration-200"
        onclick="toggleSection('recentErrors')">
        <div class="flex items-center justify-between">
            <h2 class="text-xl font-bold text-blue-800 flex items-center gap-2">
                <span id="recentErrorsIcon" class="transition-transform duration-200">▶</span>
                🕒 Recent Errors (Last 24 Hours)
                <span class="text-sm bg-blue-300 text-blue-800 px-2 py-1 rounded"><?= count($recent_errors) ?>
                    errors</span>
            </h2>
        </div>
    </div>
    <div id="recentErrors" class="p-4 hidden">
        <div class="space-y-3">
            <?php foreach ($recent_errors as $err): ?>
                <div
                    class="border-l-4 <?= $err['is_non_php'] ? 'border-red-600 bg-red-50' : 'border-red-400' ?> pl-4 py-2 pr-4 <?= typeColor($err['type']) ?> rounded-r relative">
                    <?php if ($err['is_reoccurred'] ?? false): ?>
                        <?php
                        $error_hash = htmlspecialchars(json_encode($err['error_hash']), ENT_QUOTES);
                        $solved_by = htmlspecialchars(json_encode($err['solved_by'] ?? 'Unknown'), ENT_QUOTES);
                        $solve_timestamp = htmlspecialchars(json_encode($err['solve_timestamp'] ? date('M j, Y H:i', strtotime($err['solve_timestamp'])) : 'Unknown'), ENT_QUOTES);
                        $message = htmlspecialchars(json_encode($err['message']), ENT_QUOTES);
                        $file_path = htmlspecialchars(json_encode($err['full_file_path']), ENT_QUOTES);
                        $line_num = htmlspecialchars(json_encode($err['line']), ENT_QUOTES);
                        ?>
                        <div class="absolute top-2 right-2 bg-red-500 text-white px-2 py-1 rounded text-xs cursor-pointer"
                            onclick="showReoccurrenceDetails(<?= $error_hash ?>, <?= $solved_by ?>, <?= $solve_timestamp ?>, <?= $message ?>, <?= $file_path ?>, <?= $line_num ?>)"
                            title="Click for reoccurrence details">
                            ⚠️ REOCCURRED
                        </div>
                    <?php endif; ?>

                    <div class="flex justify-between items-start">
                        <div class="flex-1">
                            <div class="font-semibold text-sm mb-2">
                                <?php if ($err['is_non_php']): ?>
                                    <span class="bg-red-500 text-white px-2 py-1 rounded text-xs mr-1">CRITICAL</span>
                                <?php endif; ?>
                                <span class="<?= $err['tag_class'] ?>"><?= htmlentities($err['type']) ?></span>
                                <?= htmlentities(substr($err['message'], 0, 120)) ?>    <?= strlen($err['message']) > 120 ? '...' : '' ?>
                            </div>
                            <div class="text-xs text-gray-600 flex items-center justify-between">
                                <div>
                                    <?= htmlentities($err['file']) ?>    <?= $err['line'] ? ':' . $err['line'] : '' ?>
                                    <span class="ml-3 text-gray-500"><?= htmlentities($err['time']) ?></span>
                                </div>
                                <div class="flex gap-2">
                                    <button data-error-content="<?= htmlentities($err['full_content'], ENT_QUOTES) ?>"
                                        onclick="showFullErrorById(this)" class="copy-btn" title="View full error">
                                        👁️ View Full
                                    </button>
                                    <button onclick="copyPath('<?= htmlentities($err['full_file_path']) ?>')"
                                        class="copy-btn" title="Copy file path">
                                        📋 Copy Path
                                    </button>
                                    <?php if ($err['error_solved'] && !$err['is_reoccurred']): ?>
                                        <div class="bg-green-500 text-white px-2 py-1 rounded text-xs">
                                            ✓ Solved by <?= htmlentities($err['solved_by']) ?>
                                        </div>
                                    <?php else: ?>
                                        <button onclick="promptMarkSolved('error', '<?= $err['error_hash'] ?>')"
                                            class="bg-teal-700 hover:bg-teal-500 text-white px-2 py-1 rounded text-xs">
                                            ✓
                                        </button>
                                    <?php endif; ?>
                                </div>
                            </div>
                        </div>
                    </div>
                </div>
            <?php endforeach; ?>
        </div>
    </div>
</div>

<!-- Main Error Groups Section -->
<div class="bg-gray-50 border-2 border-gray-200 rounded-lg overflow-hidden mb-6">
    <div class="bg-gray-200 p-4 border-b border-gray-300 cursor-pointer hover:bg-gray-300 transition-colors duration-200"
        onclick="toggleSection('errorGroups')">
        <div class="flex items-center justify-between">
            <h2 class="text-xl font-bold text-gray-800 flex items-center gap-2">
                <span id="errorGroupsIcon" class="transition-transform duration-200">▶</span>
                📂 Error Groups by File
                <span class="text-sm bg-gray-300 text-gray-800 px-2 py-1 rounded"><?= count($parsed_errors) ?>
                    files</span>
            </h2>
        </div>
    </div>
    <div id="errorGroups" class="p-4 hidden">
        <div class="text-sm text-gray-700 mb-4">Errors organized by source files for better management and tracking.
        </div>
        <?php foreach ($parsed_errors as $group => $errors): ?>
            <?= renderErrorGroup($group, $errors, $solvedData, $solved_but_reoccurred) ?>
        <?php endforeach; ?>
    </div>
</div>

<?php
$html_output = ob_get_clean();
$template_body = file_get_contents('template.html');
echo str_replace('{errors}', $html_output, $template_body);
?>