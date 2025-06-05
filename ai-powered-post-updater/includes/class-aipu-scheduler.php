<?php
/**
 * AI Powered Post Updater Scheduler
 *
 * @package AIPU
 * @since   0.2.0 // Assuming version bump for Phase 2
 */

// If this file is called directly, abort.
if ( ! defined( 'WPINC' ) ) {
    die;
}

class AIPU_Scheduler {

    public function __construct() {
        add_filter( 'cron_schedules', [ $this, 'add_custom_cron_schedules' ] );
    }

    /**
     * Adds custom cron schedules.
     * @param array $schedules Existing cron schedules.
     * @return array Modified cron schedules.
     */
    public function add_custom_cron_schedules( $schedules ) {
        if ( ! isset( $schedules['aipu_weekly'] ) ) {
            $schedules['aipu_weekly'] = [
                'interval' => WEEK_IN_SECONDS,
                'display'  => __( 'Once Weekly (AIPU)', 'ai-powered-post-updater' ),
            ];
        }
        if ( ! isset( $schedules['aipu_fortnightly'] ) ) {
             $schedules['aipu_fortnightly'] = [
                 'interval' => 2 * WEEK_IN_SECONDS,
                 'display'  => __( 'Once Fortnightly (AIPU)', 'ai-powered-post-updater' ),
             ];
        }
        // Potentially add a 'every_5_minutes' for testing, if needed, but be careful with frequent crons.
        // $schedules['aipu_every_5_minutes'] = [
        //     'interval' => 5 * MINUTE_IN_SECONDS,
        //     'display'  => __( 'Every 5 Minutes (AIPU)', 'ai-powered-post-updater' ),
        // ];
        return $schedules;
    }

    /**
     * Schedules a recurring event if it's not already scheduled.
     * @param string $hook The hook to trigger.
     * @param string $recurrence How often the event should recur (e.g., 'hourly', 'daily', 'aipu_weekly').
     * @param array $args Arguments to pass to the hook's callback function.
     * @param int $first_run_timestamp Optional. When the event should first run. Defaults to now.
     */
    public static function schedule_event( $hook, $recurrence, $args = [], $first_run_timestamp = null ) {
        if ( ! wp_next_scheduled( $hook, $args ) ) {
            wp_schedule_event( $first_run_timestamp ?? time(), $recurrence, $hook, $args );
            AIPU_Logger::log( "Scheduled event '{$hook}' with recurrence '{$recurrence}'." );
        }
    }

    /**
     * Schedules a single event for a specific time if it's not already scheduled.
     * @param int $timestamp The Unix timestamp for when the event should run.
     * @param string $hook The hook to trigger.
     * @param array $args Arguments to pass to the hook's callback function.
     */
    public static function schedule_single_event( $timestamp, $hook, $args = [] ) {
         // wp_schedule_single_event doesn't check if already scheduled for the same timestamp with same args,
         // but it's generally used for one-off tasks. If multiple identical single events are an issue,
         // one might need to wp_clear_scheduled_hook before scheduling or use wp_next_scheduled carefully.
        wp_schedule_single_event( $timestamp, $hook, $args );
        AIPU_Logger::log( "Scheduled single event '{$hook}' for timestamp {$timestamp}." );
    }

    /**
     * Unschedules an event.
     * @param string $hook The hook of the event to unschedule.
     * @param array $args Arguments passed to the hook's callback function (must match the scheduled event).
     */
    public static function unschedule_event( $hook, $args = [] ) {
        $timestamp = wp_next_scheduled( $hook, $args );
        if ( $timestamp ) {
            wp_unschedule_event( $timestamp, $hook, $args );
            AIPU_Logger::log( "Unscheduled event '{$hook}'." );
        }
    }

    /**
     * Clears all scheduled instances of a specific hook, regardless of arguments.
     * @param string $hook The hook name.
     */
    public static function clear_scheduled_hook( $hook ) {
        $cleared_count = wp_clear_scheduled_hook( $hook ); // Returns number of events cleared
        if ($cleared_count > 0) {
             AIPU_Logger::log( "Cleared all {$cleared_count} scheduled instance(s) of hook '{$hook}'." );
        }
    }

    /**
     * Checks if a specific hook is scheduled.
     * @param string $hook The hook name.
     * @param array $args Optional. Arguments to check against.
     * @return bool True if scheduled, false otherwise.
     */
    public static function is_scheduled( $hook, $args = [] ) {
        return wp_next_scheduled( $hook, $args ) !== false;
    }
}
?>
