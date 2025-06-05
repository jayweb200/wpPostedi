<?php
/**
 * AI Powered Post Updater Logger
 *
 * @package AIPU
 * @since   0.2.0
 */

// If this file is called directly, abort.
if ( ! defined( 'WPINC' ) ) {
    die;
}

class AIPU_Logger {

    const LOG_OPTION_KEY = 'aipu_logs';
    const MAX_LOG_ENTRIES = 100; // Max number of log entries to keep

    /**
     * Adds a log entry.
     * @param string $message The message to log.
     * @param string $level Log level (e.g., INFO, WARNING, ERROR, DEBUG).
     */
    public static function log( $message, $level = 'INFO' ) {
        $logs = get_option( self::LOG_OPTION_KEY, [] );
        if (!is_array($logs)) { // Ensure logs is an array
            $logs = [];
        }

        $entry = [
            'timestamp' => current_time( 'timestamp' ), // WordPress way to get current time
            'level'     => strtoupper( $level ),
            'message'   => $message,
        ];

        // Add new entry to the beginning of the array
        array_unshift( $logs, $entry );

        // Trim logs to MAX_LOG_ENTRIES
        if ( count( $logs ) > self::MAX_LOG_ENTRIES ) {
            $logs = array_slice( $logs, 0, self::MAX_LOG_ENTRIES );
        }

        update_option( self::LOG_OPTION_KEY, $logs, false ); // 'false' for not autoloading if logs get big
    }

    /**
     * Retrieves log entries.
     * @param int $count Number of recent entries to retrieve.
     * @return array Array of log entries.
     */
    public static function get_logs( $count = 50 ) {
        $logs = get_option( self::LOG_OPTION_KEY, [] );
         if (!is_array($logs)) {
            $logs = [];
        }
        return array_slice( $logs, 0, $count );
    }

    /**
     * Clears all log entries.
     */
    public static function clear_logs() {
        delete_option( self::LOG_OPTION_KEY );
        self::log( 'Logs cleared.' ); // Log the clearance action itself
    }
}
?>
