<?php
/**
 * OSCAR Shop — DB readiness probe for wp-init.sh
 *
 * WordPress base image does NOT include the `mysql` client, so we cannot
 * use the standard `mysql -e "SELECT 1"` pattern. This script uses the
 * mysqli extension (always present in the PHP image) to test DB connection.
 *
 * Env vars consumed (set by caller):
 *   WORDPRESS_DB_HOST       (default: db)
 *   WORDPRESS_DB_USER       (default: wordpress)
 *   WORDPRESS_DB_PASSWORD   (default: wordpress)
 *   WORDPRESS_DB_NAME       (default: wordpress)
 *
 * Exit code:
 *   0 — connection OK
 *   1 — connection failed (host down, bad creds, network issue)
 *
 * Used by: scripts/wp-init.sh
 */

$host = getenv('WORDPRESS_DB_HOST') ?: 'db';
$user = getenv('WORDPRESS_DB_USER') ?: 'wordpress';
$pass = getenv('WORDPRESS_DB_PASSWORD') ?: 'wordpress';
$name = getenv('WORDPRESS_DB_NAME') ?: 'wordpress';

// Host may be "host:port" or just "host". Default to 3306 if no port.
$parts = explode(':', $host, 2);
$hostname = $parts[0];
$port = isset($parts[1]) ? (int) $parts[1] : 3306;

mysqli_report(MYSQLI_REPORT_OFF);

$conn = @mysqli_connect($hostname, $user, $pass, $name, $port);

if (!$conn) {
    // Connection failed — caller will retry.
    exit(1);
}

// Run a trivial query to confirm we can actually USE the DB.
$result = @mysqli_query($conn, 'SELECT 1');
if (!$result) {
    @mysqli_close($conn);
    exit(1);
}

$row = @mysqli_fetch_row($result);
@mysqli_free_result($result);
@mysqli_close($conn);

if (!$row || (int) $row[0] !== 1) {
    exit(1);
}

exit(0);
