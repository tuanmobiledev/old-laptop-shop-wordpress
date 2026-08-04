<?php
/*
Plugin Name: Cron Helper
Description: Force oscar_nhanh_product_sync to use oscar_15_minutes schedule
Version: 1.3
*/

if (!defined('ABSPATH')) exit;

// Force oscar_nhanh_product_sync to have exactly ONE entry using oscar_15_minutes
add_action('init', function() {
    $cron = get_option('cron');
    if (!is_array($cron)) return;
    
    $osc_entries = [];
    $modified = false;
    
    // Find all oscar_nhanh_product_sync entries
    foreach ($cron as $ts => &$events) {
        if (!is_array($events)) continue;
        if (isset($events['oscar_nhanh_product_sync'])) {
            $osc_entries[$ts] = $events['oscar_nhanh_product_sync'];
            if ($ts !== end(array_keys($osc_entries)) || count($osc_entries) > 1) {
                unset($events['oscar_nhanh_product_sync']);
                $modified = true;
            }
        }
    }
    unset($events);
    
    // If multiple entries existed, keep only the earliest one (or create new if none)
    if (count($osc_entries) > 1 || empty($osc_entries)) {
        // Remove all oscar_nhanh_product_sync entries
        foreach ($cron as $ts => $events) {
            if (isset($events['oscar_nhanh_product_sync'])) {
                unset($cron[$ts]['oscar_nhanh_product_sync']);
                if (empty($cron[$ts])) unset($cron[$ts]);
                $modified = true;
            }
        }
        // Add a single clean entry
        $cron[time() + 60]['oscar_nhanh_product_sync'] = [
            '40cd750bba9870f18aada2478b24840a' => [
                'schedule' => 'oscar_15_minutes',
                'args' => [],
                'interval' => 900,
            ],
        ];
        $modified = true;
    } else {
        // Single entry — force its schedule to oscar_15_minutes
        $only_ts = array_key_first($osc_entries);
        $entry = $osc_entries[$only_ts];
        // Find the actual data (could be at root or nested under md5 hash)
        $actual = is_array($entry) && isset($entry['40cd750bba9870f18aada2478b24840a']) 
            ? $entry['40cd750bba9870f18aada2478b24840a'] 
            : $entry;
        
        if (($actual['schedule'] ?? '') !== 'oscar_15_minutes' || ($actual['interval'] ?? 0) !== 900) {
            $cron[$only_ts]['oscar_nhanh_product_sync'] = [
                '40cd750bba9870f18aada2478b24840a' => [
                    'schedule' => 'oscar_15_minutes',
                    'args' => [],
                    'interval' => 900,
                ],
            ];
            $modified = true;
        }
    }
    
    if ($modified) {
        update_option('cron', $cron);
    }
}, 999);

add_action('rest_api_init', function() {
    register_rest_route('helper/v1', '/cron', [
        'methods' => 'GET, POST',
        'callback' => 'helper_cron_handler',
        'permission_callback' => function() {
            return current_user_can('manage_options');
        }
    ]);
});

function helper_cron_handler($req) {
    $action = $req->get_param('action') ?? 'info';
    
    if ($action === 'list') {
        return ['cron' => get_option('cron'), 'now' => time()];
    }
    
    if ($action === 'set_schedule') {
        $hook = $req->get_param('hook');
        $schedule = $req->get_param('schedule');
        $cron = get_option('cron');
        $modified = 0;
        foreach ($cron as $ts => &$events) {
            if (isset($events[$hook])) {
                $events[$hook]['schedule'] = $schedule;
                $events[$hook]['interval'] = wp_get_schedules()[$schedule]['interval'] ?? 0;
                $modified++;
            }
        }
        update_option('cron', $cron);
        return ['modified' => $modified, 'cron' => get_option('cron')];
    }
    
    if ($action === 'unschedule_all') {
        $hook = $req->get_param('hook');
        $cron = get_option('cron');
        $removed = 0;
        foreach ($cron as $ts => &$events) {
            if (isset($events[$hook])) {
                unset($events[$ts][$hook]);
                $removed++;
                if (empty($events[$ts])) unset($cron[$ts]);
            }
        }
        update_option('cron', $cron);
        return ['removed' => $removed];
    }
    
    if ($action === 'schedule') {
        $hook = $req->get_param('hook');
        $schedule = $req->get_param('schedule') ?? 'oscar_15_minutes';
        $delay = (int)($req->get_param('delay') ?? 60);
        $result = wp_schedule_event(time() + $delay, $schedule, $hook);
        return ['scheduled' => $result, 'next' => wp_next_scheduled($hook)];
    }
    
    return ['error' => 'unknown action', 'available' => ['list', 'set_schedule', 'unschedule_all', 'schedule']];
}
