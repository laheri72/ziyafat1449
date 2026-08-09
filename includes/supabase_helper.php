<?php
/**
 * Helper functions to communicate with Supabase REST API for Ziyarat Flow
 */

function get_supabase_config() {
    return [
        'url' => 'https://nwgdoctvwqidythcceec.supabase.co/rest/v1',
        'key' => 'sb_publishable_aHcW67jiXV21ezGaz0CvuQ_2UXV7lEM'
    ];
}

function supabase_request($path, $query_params = []) {
    $config = get_supabase_config();
    $url = $config['url'] . '/' . ltrim($path, '/');
    if (!empty($query_params)) {
        $url .= '?' . http_build_query($query_params);
    }

    $ch = curl_init();
    curl_setopt($ch, CURLOPT_URL, $url);
    curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
    curl_setopt($ch, CURLOPT_HTTPHEADER, [
        'apikey: ' . $config['key'],
        'Authorization: Bearer ' . $config['key'],
        'Content-Type: application/json'
    ]);
    curl_setopt($ch, CURLOPT_TIMEOUT, 15);
    curl_setopt($ch, CURLOPT_SSL_VERIFYPEER, false);

    $response = curl_exec($ch);
    $http_code = curl_getinfo($ch, CURLINFO_HTTP_CODE);
    $error = curl_error($ch);
    curl_close($ch);

    if ($error || $http_code >= 400) {
        error_log("Supabase API Error ($http_code): " . ($error ?: $response));
        return null;
    }

    return json_decode($response, true);
}

/**
 * Fetch the current active event set in Supabase app_settings
 */
function get_supabase_current_event() {
    $settings = supabase_request('app_settings', [
        'select' => 'setting_value',
        'setting_key' => 'eq.current_event_for_availability'
    ]);
    if (!empty($settings) && isset($settings[0]['setting_value']) && !empty(trim($settings[0]['setting_value']))) {
        return trim($settings[0]['setting_value']);
    }
    return 'Safar 1448H';
}

/**
 * Fetch distinct event tags from Supabase assignments & app_settings
 */
function get_supabase_event_tags() {
    $tags = [];
    $current = get_supabase_current_event();
    if (!empty($current)) {
        $tags[] = $current;
    }

    $assignments = supabase_request('assignments', ['select' => 'event_tag', 'limit' => 1000]);
    if (is_array($assignments)) {
        foreach ($assignments as $a) {
            if (!empty($a['event_tag']) && !in_array($a['event_tag'], $tags)) {
                $tags[] = trim($a['event_tag']);
            }
        }
    }

    return array_values(array_unique(array_filter($tags)));
}

/**
 * Fetch active students from Supabase
 */
function get_supabase_students() {
    $res = supabase_request('students', [
        'select' => 'tr_number,its_id,name,branch,available_in_mumbai,is_active',
        'is_active' => 'eq.true',
        'limit' => 1000
    ]);
    return is_array($res) ? $res : [];
}

/**
 * Fetch a single student profile by TR number from Supabase
 */
function get_supabase_student_by_tr($tr_number) {
    if (empty($tr_number)) return null;
    $res = supabase_request('students', [
        'select' => 'tr_number,its_id,name,branch,available_in_mumbai,availability_updated_at,is_active',
        'tr_number' => 'eq.' . urlencode($tr_number),
        'limit' => 1
    ]);
    return (!empty($res) && isset($res[0])) ? $res[0] : null;
}

/**
 * Fetch precise assignment stats for a batch of TR numbers for a specific event tag
 */
function get_supabase_stats_for_trs($tr_numbers, $event_tag = null) {
    if (empty($tr_numbers)) return [];

    $formatted_trs = array_map(function($tr) {
        return '"' . trim($tr) . '"';
    }, (array)$tr_numbers);

    $params = [
        'select' => 'student_tr_number,status,event_tag',
        'student_tr_number' => 'in.(' . implode(',', $formatted_trs) . ')',
        'limit' => 5000
    ];

    if (!empty($event_tag)) {
        $params['event_tag'] = 'eq.' . $event_tag;
    }

    $assignments = supabase_request('assignments', $params);

    $stats = [];
    if (is_array($assignments)) {
        foreach ($assignments as $a) {
            $tr = $a['student_tr_number'];
            if (!isset($stats[$tr])) {
                $stats[$tr] = ['assigned' => 0, 'completed' => 0, 'pending' => 0];
            }
            $stats[$tr]['assigned']++;
            if ($a['status'] === 'completed') {
                $stats[$tr]['completed']++;
            } else {
                $stats[$tr]['pending']++;
            }
        }
    }
    return $stats;
}

/**
 * Update student's available_in_mumbai status in Supabase
 */
function update_supabase_student_availability($tr_number, $available_in_mumbai) {
    if (empty($tr_number)) return false;

    $config = get_supabase_config();
    $url = $config['url'] . '/students?tr_number=eq.' . urlencode($tr_number);

    $payload = json_encode([
        'available_in_mumbai' => (bool)$available_in_mumbai,
        'availability_updated_at' => date('c')
    ]);

    $ch = curl_init();
    curl_setopt($ch, CURLOPT_URL, $url);
    curl_setopt($ch, CURLOPT_CUSTOMREQUEST, 'PATCH');
    curl_setopt($ch, CURLOPT_POSTFIELDS, $payload);
    curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
    curl_setopt($ch, CURLOPT_HTTPHEADER, [
        'apikey: ' . $config['key'],
        'Authorization: Bearer ' . $config['key'],
        'Content-Type: application/json',
        'Prefer: return=minimal'
    ]);
    curl_setopt($ch, CURLOPT_TIMEOUT, 15);
    curl_setopt($ch, CURLOPT_SSL_VERIFYPEER, false);

    $response = curl_exec($ch);
    $http_code = curl_getinfo($ch, CURLINFO_HTTP_CODE);
    curl_close($ch);

    return ($http_code >= 200 && $http_code < 300);
}
