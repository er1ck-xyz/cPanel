<?php
// LOGGER COMPARTILHADO
// Uso: system_log($actor, $message)

if (!function_exists('system_log')) {
    function system_log($actor, $message) {
        try {
            $actor = (string)$actor;
            $message = str_replace(["\r", "\n"], ' ', (string)$message);
            $dir = __DIR__ . '/storage';
            if (!is_dir($dir)) @mkdir($dir, 0775, true);
            $file = $dir . '/admin.log';
            $line = date('Y-m-d H:i:s') . ' [' . ($actor !== '' ? $actor : 'system') . '] ' . $message . PHP_EOL;
            @file_put_contents($file, $line, FILE_APPEND);
        } catch (Throwable $e) {
            // Silencia para não quebrar o fluxo principal
        }
    }
}
?>

