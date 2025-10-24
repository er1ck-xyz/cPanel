<?php



require_once __DIR__ . '/credentials.php';

if (!function_exists('keyauth_seller_base_url')) {
    function keyauth_seller_base_url(): string {
        return 'https://keyauth.win/api/seller/';
    }
}

if (!function_exists('keyauth_seller_call')) {
    
    function keyauth_seller_call(string $type, array $params = [], string $format = 'json') {
        global $sellerkey;
        $sk = getenv('KEYAUTH_SELLER_KEY');
        if (!$sk) { $sk = isset($sellerkey) ? (string)$sellerkey : ''; }
        if ($sk === '') { throw new Exception('Seller key não configurada.'); }

        $params['type'] = $type;
        $params['sellerkey'] = $sk;
        if ($format) { $params['format'] = $format; }

        $url = keyauth_seller_base_url() . '?' . http_build_query($params);

        $ch = curl_init($url);
        curl_setopt_array($ch, [
            CURLOPT_RETURNTRANSFER => true,
            CURLOPT_TIMEOUT => 30,
            CURLOPT_SSL_VERIFYPEER => true,
        ]);
        $resp = curl_exec($ch);
        if ($resp === false) {
            $err = curl_error($ch);
            curl_close($ch);
            throw new Exception('Erro ao chamar Seller API: ' . $err);
        }
        curl_close($ch);

        if ($format === 'json') {
            $data = json_decode($resp, true);
            if ($data === null) {
                return [ 'success' => false, 'message' => 'Resposta inválida (não é JSON).', 'raw' => $resp ];
            }
            return $data;
        }

        return $resp; 
    }
}

if (!function_exists('seller_create_licenses')) {
    
    function seller_create_licenses(int $amount, int $expiry, int $level = 1, string $mask = '******-******-******-******-******-******', string $format = 'text') : array {
        $amount = max(1, min($amount, 100));
        $level = max(1, $level);
        $expiry = max(1, $expiry);

        $params = [
            'expiry' => $expiry,
            'mask'   => $mask,
            'level'  => $level,
            'amount' => $amount,
        ];
        $out = keyauth_seller_call('add', $params, $format);
        if (is_array($out)) return $out;
        return ['success' => true, 'raw' => (string)$out];
    }
}


if (!function_exists('seller_verify_license')) {
    function seller_verify_license(string $key): array { return (array) keyauth_seller_call('verify', ['key'=>$key], 'json'); }
}
if (!function_exists('seller_license_info')) {
    function seller_license_info(string $key): array { return (array) keyauth_seller_call('info', ['key'=>$key], 'json'); }
}
if (!function_exists('seller_ban_license')) {
    function seller_ban_license(string $key, string $reason = '', bool $userToo = false): array { return (array) keyauth_seller_call('ban', ['key'=>$key, 'reason'=>$reason, 'userToo'=>$userToo ? 'true':'false'], 'json'); }
}
if (!function_exists('seller_unban_license')) {
    function seller_unban_license(string $key): array { return (array) keyauth_seller_call('unban', ['key'=>$key], 'json'); }
}
if (!function_exists('seller_set_note')) {
    function seller_set_note(string $key, string $note): array { return (array) keyauth_seller_call('setnote', ['key'=>$key, 'note'=>$note], 'json'); }
}
if (!function_exists('seller_delete_license')) {
    function seller_delete_license(string $key, bool $userToo=false): array { return (array) keyauth_seller_call('del', ['key'=>$key, 'userToo'=>$userToo?'true':'false'], 'json'); }
}
if (!function_exists('seller_delete_multiple')) {
    function seller_delete_multiple(string $keysJoined, bool $userToo=false): array { return (array) keyauth_seller_call('delmultiple', ['key'=>$keysJoined, 'userToo'=>$userToo?'true':'false'], 'json'); }
}
if (!function_exists('seller_delete_all')) {
    function seller_delete_all(): array { return (array) keyauth_seller_call('delalllicenses', [], 'json'); }
}
if (!function_exists('seller_delete_used')) {
    function seller_delete_used(): array { return (array) keyauth_seller_call('delused', [], 'json'); }
}
if (!function_exists('seller_delete_unused')) {
    function seller_delete_unused(): array { return (array) keyauth_seller_call('delunused', [], 'json'); }
}
if (!function_exists('seller_add_time_unused')) {
    function seller_add_time_unused(int $days): array { return (array) keyauth_seller_call('addtime', ['time'=>$days], 'json'); }
}
if (!function_exists('seller_fetch_all')) {
    function seller_fetch_all(string $format='text') { return keyauth_seller_call('fetchallkeys', [], $format); }
}

?>

