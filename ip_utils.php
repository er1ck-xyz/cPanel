<?php
// Utilitários de IP (cliente)
// get via proxies comuns e normaliza para IPv4 quando possível

if (!function_exists('normalize_ip')) {
    function normalize_ip($ip) {
        $ip = trim((string)$ip);
        if ($ip === '' || $ip === null) return '';
        if ($ip === '::1' || $ip === '0:0:0:0:0:0:0:1') return '127.0.0.1';
        if (stripos($ip, '::ffff:') === 0) {
            $v4 = substr($ip, 7);
            if (filter_var($v4, FILTER_VALIDATE_IP, FILTER_FLAG_IPV4)) return $v4;
        }
        return $ip;
    }
}

if (!function_exists('client_ip')) {
    function client_ip() {
        $candidates = [
            'HTTP_CF_CONNECTING_IP',    // Cloudflare
            'HTTP_X_FORWARDED_FOR',     // Proxy padrão
            'HTTP_X_REAL_IP',           // Nginx
            'REMOTE_ADDR',
        ];
        foreach ($candidates as $k) {
            if (!empty($_SERVER[$k])) {
                $raw = (string)$_SERVER[$k];
                if ($k === 'HTTP_X_FORWARDED_FOR') {
                    $raw = explode(',', $raw)[0];
                }
                $ip = normalize_ip($raw);
                if (filter_var($ip, FILTER_VALIDATE_IP)) return $ip;
            }
        }
        return '';
    }
}
?>

