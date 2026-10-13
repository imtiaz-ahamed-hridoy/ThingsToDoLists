<?php

declare(strict_types=1);

/**
 * =============================================================================
 * Laravel Cron Runner — script.php
 * Production-Ready | Royalloge Backend
 * =============================================================================
 *
 * This file is called by the server's cron job every minute.
 * It runs `php artisan schedule:run`, which lets Laravel decide
 * which scheduled tasks are due to execute.
 *
 * CRITICAL FEATURES:
 *   1. CLI-only guard         → Returns 404 if accessed via browser.
 *   2. Exclusive file lock    → Prevents two cron processes from running
 *                               schedule:run simultaneously (race condition).
 *   3. Log rotation           → Keeps the log file from growing unbounded.
 *   4. Structured logging     → Timestamps + exit code on every run.
 *   5. Stale lock detection   → Auto-removes locks older than MAX_LOCK_AGE.
 *
 * CloudPanel cron entry (every minute):
 *   * * * * * php /path/to/project/public/script.php
 *
 * =============================================================================
 */

// ── 1. CLI-only guard ─────────────────────────────────────────────────────
// If someone hits this file in a browser, return a clean 404.
if (PHP_SAPI !== 'cli') {
    http_response_code(404);
    exit;
}

// ── 2. Runtime settings ───────────────────────────────────────────────────
// Give the scheduler plenty of time; individual commands control their own limits.
ini_set('max_execution_time', '0');
ini_set('memory_limit', '256M');

// ── 3. Path resolution ────────────────────────────────────────────────────
$projectRoot = dirname(__DIR__);
$artisan     = $projectRoot . DIRECTORY_SEPARATOR . 'artisan';
$logDir      = $projectRoot . DIRECTORY_SEPARATOR . 'storage' . DIRECTORY_SEPARATOR . 'logs';
$logFile     = $logDir . DIRECTORY_SEPARATOR . 'cron-runner.log';
$lockFile    = $logDir . DIRECTORY_SEPARATOR . 'cron-runner.lock';

// ── 4. Ensure log directory exists ───────────────────────────────────────
if (!is_dir($logDir)) {
    if (!@mkdir($logDir, 0755, true) && !is_dir($logDir)) {
        fwrite(STDERR, '[' . date('Y-m-d H:i:s') . '] FATAL: Cannot create log directory: ' . $logDir . PHP_EOL);
        exit(1);
    }
}

// ── 5. Helper: append a line to the log with automatic rotation ───────────
const LOG_MAX_BYTES    = 5 * 1024 * 1024; // 5 MB — rotate when exceeded
const LOG_ROTATED_KEEP = 3;               // Keep up to 3 rotated files

function writeLog(string $logFile, string $message): void
{
    // Rotate if the log file exceeds LOG_MAX_BYTES
    if (is_file($logFile) && filesize($logFile) >= LOG_MAX_BYTES) {
        for ($i = LOG_ROTATED_KEEP - 1; $i >= 1; $i--) {
            $src = $logFile . '.' . $i;
            $dst = $logFile . '.' . ($i + 1);
            if (is_file($src)) {
                @rename($src, $dst);
            }
        }
        @rename($logFile, $logFile . '.1');
    }

    file_put_contents($logFile, $message . PHP_EOL, FILE_APPEND | LOCK_EX);
}

// ── 6. Guard: artisan file must exist ────────────────────────────────────
if (!is_file($artisan)) {
    $msg = '[' . date('Y-m-d H:i:s') . '] [ERROR] artisan file not found: ' . $artisan;
    fwrite(STDERR, $msg . PHP_EOL);
    writeLog($logFile, $msg);
    exit(1);
}

// ── 7. Guard: project directory must be reachable ────────────────────────
if (!chdir($projectRoot)) {
    $msg = '[' . date('Y-m-d H:i:s') . '] [ERROR] Cannot chdir to: ' . $projectRoot;
    fwrite(STDERR, $msg . PHP_EOL);
    writeLog($logFile, $msg);
    exit(1);
}

// ── 8. Exclusive file lock — prevent overlapping scheduler runs ───────────
//
// Without this, if a previous `schedule:run` call is still running (e.g. a
// long sync command), the next cron tick would start a second one, causing
// duplicate jobs and DB lock contention.
//
const MAX_LOCK_AGE = 600; // Treat locks older than 10 min as stale (dead process)

$lockHandle = @fopen($lockFile, 'c');

if ($lockHandle === false) {
    $msg = '[' . date('Y-m-d H:i:s') . '] [SKIP] Cannot open lock file: ' . $lockFile;
    writeLog($logFile, $msg);
    exit(0);
}

// Detect and remove stale locks left by crashed processes
if (is_file($lockFile) && (time() - filemtime($lockFile)) > MAX_LOCK_AGE) {
    writeLog($logFile, '[' . date('Y-m-d H:i:s') . '] [WARN] Stale lock detected — removing and continuing.');
    @fclose($lockHandle);
    @unlink($lockFile);
    $lockHandle = @fopen($lockFile, 'c');
}

// Non-blocking acquire — if another process holds the lock, exit cleanly
if (!flock($lockHandle, LOCK_EX | LOCK_NB)) {
    $msg = '[' . date('Y-m-d H:i:s') . '] [SKIP] Another scheduler is already running — skipping this tick.';
    writeLog($logFile, $msg);
    fclose($lockHandle);
    exit(0);
}

// ── 9. Build the artisan command ──────────────────────────────────────────
$phpBinary = PHP_BINARY ?: 'php';

$command = sprintf(
    '%s %s schedule:run 2>&1',
    escapeshellarg($phpBinary),
    escapeshellarg($artisan)
);

// ── 10. Execute the scheduler ─────────────────────────────────────────────
$startTime = microtime(true);
$output    = [];
$exitCode  = 0;

exec($command, $output, $exitCode);

$elapsed = round(microtime(true) - $startTime, 2);

// ── 11. Write structured log entry ───────────────────────────────────────
$timestamp  = '[' . date('Y-m-d H:i:s') . ']';
$statusTag  = $exitCode === 0 ? '[OK]' : '[FAIL]';
$outputText = !empty($output) ? implode(PHP_EOL . '    ', $output) : '(no output)';

$logEntry = implode(PHP_EOL, [
    "─────────────────────────────────────────────",
    "{$timestamp} {$statusTag} schedule:run completed in {$elapsed}s (exit: {$exitCode})",
    "    {$outputText}",
]);

writeLog($logFile, $logEntry);

// ── 12. Release lock and exit ─────────────────────────────────────────────
flock($lockHandle, LOCK_UN);
fclose($lockHandle);

exit((int) $exitCode);
