<?php

namespace App\Traits;

use Illuminate\Support\Facades\File;
use Illuminate\Support\Facades\Response;
use Illuminate\Support\Facades\DB;

trait DatabaseExportable
{
    /**
     * Export the full database and return downloadable SQL file.
     *
     * @return \Symfony\Component\HttpFoundation\BinaryFileResponse|\Illuminate\Http\JsonResponse
     */
    public function exportDatabase()
    {
        $connection = config('database.default');
        $config = config("database.connections.$connection");

        $database = $config['database'];
        $username = $config['username'];
        $password = $config['password'] ?? '';
        $host = $config['host'] ?? '127.0.0.1';
        $port = $config['port'] ?? 3306;

        $backupDir = storage_path('app/backups');
        if (!File::exists($backupDir)) File::makeDirectory($backupDir, 0755, true);
        $filename = 'backup_' . date('Y-m-d_H-i-s') . '.sql';
        $backupPath = $backupDir . DIRECTORY_SEPARATOR . $filename;

        // Detect mysqldump
        $possiblePaths = [
            '/usr/bin/mysqldump',
            '/usr/local/bin/mysqldump',
            '/usr/sbin/mysqldump',
            '/usr/local/mysql/bin/mysqldump',
            '/opt/lampp/bin/mysqldump',
            '/opt/cpanel/ea-mysql57/bin/mysqldump',
            '/opt/cpanel/ea-mysql80/bin/mysqldump',
            '/usr/libexec/mysqldump',
            '/bin/mysqldump',
            // Windows fallback paths
            'C:\\Program Files\\MySQL\\MySQL Server 8.0\\bin\\mysqldump.exe',
            'C:\\xampp\\mysql\\bin\\mysqldump.exe'
        ];
        $mysqldump = null;
        foreach ($possiblePaths as $path) {
            $found = glob($path);
            if ($found && file_exists($found[0])) {
                $mysqldump = $found[0];
                break;
            }
        }
        if (!$mysqldump) $mysqldump = trim(@shell_exec('which mysqldump'));

        // Use mysqldump if available
        if ($mysqldump && file_exists($mysqldump) && function_exists('exec')) {
            $passwordPart = $password ? "--password=\"{$password}\"" : '';
            $command = "\"{$mysqldump}\" --user=\"{$username}\" {$passwordPart} --host=\"{$host}\" --port=\"{$port}\" {$database} " .
                "--routines --events --triggers --single-transaction --add-drop-table " .
                "--default-character-set=utf8mb4 --set-charset > \"{$backupPath}\"";
            $output = null; $resultCode = null;
            @exec($command, $output, $resultCode);

            if ($resultCode === 0 && File::exists($backupPath)) {
                return Response::download($backupPath)->deleteFileAfterSend(true);
            }
        }

        // Fallback: pure PHP export
        $tables = DB::select('SHOW TABLES');
        $dbKey = 'Tables_in_' . $database;
        $sql = "-- Backup created at " . now() . "\n\n";

        foreach ($tables as $table) {
            $tableName = $table->$dbKey;
            $createTable = DB::select("SHOW CREATE TABLE `$tableName`")[0]->{'Create Table'};
            $sql .= "DROP TABLE IF EXISTS `$tableName`;\n$createTable;\n\n";
            $rows = DB::table($tableName)->get();
            foreach ($rows as $row) {
                $values = array_map(fn($v) => $v === null ? 'NULL' : "'" . str_replace("'", "\\'", $v) . "'", (array) $row);
                $sql .= "INSERT INTO `$tableName` VALUES(" . implode(',', $values) . ");\n";
            }
            $sql .= "\n\n";
        }

        file_put_contents($backupPath, $sql);
        return Response::download($backupPath)->deleteFileAfterSend(true);
    }

}
