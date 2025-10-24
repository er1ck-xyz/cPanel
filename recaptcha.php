<?php
// recaptcha.php — verificação do reCAPTCHA v3 (baseado em score)
function verifyRecaptcha($token)
{
    $secretKey = "6LfhAfArAAAAAP_rp80q2Y2h_0EsBVpN7DQul-r3"; // 🔐 sua chave secreta v3
    $url = "https://www.google.com/recaptcha/api/siteverify";

    $data = [
        'secret'   => $secretKey,
        'response' => $token,
        'remoteip' => $_SERVER['REMOTE_ADDR']
    ];

    $options = [
        'http' => [
            'method'  => 'POST',
            'header'  => "Content-type: application/x-www-form-urlencoded\r\n",
            'content' => http_build_query($data)
        ]
    ];

    $context  = stream_context_create($options);
    $response = file_get_contents($url, false, $context);
    $result   = json_decode($response, true);

    // ✅ ReCAPTCHA v3 usa "score" para avaliar comportamento (>= 0.5 = humano)
    if (!empty($result['success']) && isset($result['score']) && $result['score'] >= 0.5) {
        return ['success' => true, 'score' => $result['score']];
    } else {
        return [
            'success' => false,
            'score'   => $result['score'] ?? 0,
            'msg'     => $result['error-codes'] ?? []
        ];
    }
}

// Opcional: permite testar enviando POST manualmente
if (basename($_SERVER['PHP_SELF']) === 'recaptcha.php') {
    header('Content-Type: application/json; charset=utf-8');
    if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
        echo json_encode(['success' => false, 'msg' => 'Método inválido']);
        exit;
    }

    $token = $_POST['g-recaptcha-response'] ?? '';
    if (!$token) {
        echo json_encode(['success' => false, 'msg' => 'Token ausente.']);
        exit;
    }

    echo json_encode(verifyRecaptcha($token));
}
?>