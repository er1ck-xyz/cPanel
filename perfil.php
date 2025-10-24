<?php
// perfil.php — exibe perfil com IP público, assinaturas e dados do banco
require 'keyauth.php';
require 'credentials.php';
require 'database.php';

if (session_status() === PHP_SESSION_NONE) session_start();

if (!isset($_SESSION['user_data']) || !isset($_SESSION['sessionid'])) {
    header('Location: index.php');
    exit;
}

/* === FUNÇÕES === */
function read_value($arr, $key, $default = null) {
    if (is_array($arr) && array_key_exists($key, $arr)) return $arr[$key];
    if (is_object($arr) && property_exists($arr, $key)) return $arr->$key;
    return $default;
}

function as_timestamp($value) {
    return ($value && is_numeric($value)) ? (int)$value : 0;
}

function format_interval_pretty($seconds) {
    $seconds = max(0, (int)$seconds);
    $d = intdiv($seconds, 86400); $seconds %= 86400;
    $h = intdiv($seconds, 3600);  $seconds %= 3600;
    $m = intdiv($seconds, 60);
    $parts = [];
    if ($d > 0) $parts[] = "$d dia" . ($d>1?"s":"");
    if ($h > 0) $parts[] = "$h hora" . ($h>1?"s":"");
    if ($m > 0 || !$parts) $parts[] = "$m minuto" . ($m>1?"s":"");
    return implode(", ", $parts);
}

/* === IP PÚBLICO REAL === */
function isPrivateIP($ip) {
    return (
        filter_var($ip, FILTER_VALIDATE_IP, FILTER_FLAG_NO_PRIV_RANGE | FILTER_FLAG_NO_RES_RANGE) === false
    );
}
function getPublicIP() {
    $keys = [
        'HTTP_CF_CONNECTING_IP',
        'HTTP_X_FORWARDED_FOR',
        'HTTP_X_REAL_IP',
        'HTTP_CLIENT_IP',
        'REMOTE_ADDR'
    ];
    foreach ($keys as $key) {
        if (!empty($_SERVER[$key])) {
            $ipList = explode(',', $_SERVER[$key]);
            foreach ($ipList as $ip) {
                $ip = trim($ip);
                if (filter_var($ip, FILTER_VALIDATE_IP) && !isPrivateIP($ip)) return $ip;
            }
        }
    }
    $external = @file_get_contents('https://api.ipify.org');
    if ($external && filter_var($external, FILTER_VALIDATE_IP)) return $external;
    return 'Desconhecido';
}
$ipAddr = htmlspecialchars(getPublicIP());

/* === DADOS KEYAUTH === */
$payload = [
    'type' => 'userdata',
    'sessionid' => $_SESSION['sessionid'],
    'name' => $name ?? '',
    'ownerid' => $ownerid ?? ''
];

$ch = curl_init('https://keyauth.win/api/1.2/');
curl_setopt_array($ch, [
    CURLOPT_RETURNTRANSFER => true,
    CURLOPT_POSTFIELDS => $payload,
    CURLOPT_TIMEOUT => 5
]);
$response = json_decode(curl_exec($ch), true);
curl_close($ch);

if (!$response || !isset($response['success']) || !$response['success'])
    $user = $_SESSION['user_data'];
else {
    $user = $response['info'];
    $_SESSION['user_data'] = $user;
}

/* === ASSINATURAS === */
$subs = read_value($user, 'subscriptions', []);
$planNameBest = 'Sem assinatura';
$bestExpiryTs = 0;
$subsList = [];

foreach ((array)$subs as $sub) {
    $nameSub = read_value($sub, 'subscription');
    $ts = as_timestamp(read_value($sub, 'expiry'));
    $subsList[] = [
        'name' => $nameSub ?: '—',
        'expiryTs' => $ts,
        'expiryFmt' => $ts ? date('d/m/Y H:i', $ts) : '—',
        'daysLeft' => $ts > time() ? ceil(($ts - time()) / 86400) : 0
    ];
    if ($ts > $bestExpiryTs) {
        $bestExpiryTs = $ts;
        $planNameBest = $nameSub;
    }
}

$planNameFirst = count($subsList) ? $subsList[0]['name'] : '—';
$now = time();
$daysLeft = $bestExpiryTs > $now ? ceil(($bestExpiryTs - $now) / 86400) : 0;
$expiryDate = $bestExpiryTs ? date('d/m/Y H:i', $bestExpiryTs) : '—';
$prettyLeft = $bestExpiryTs > $now ? format_interval_pretty($bestExpiryTs - $now) : 'Expirado';

/* === USUÁRIO === */
$usernameRaw = read_value($user, 'username', '');
$username = htmlspecialchars($usernameRaw ?: 'Usuário');
$createdFmt = ($t = as_timestamp(read_value($user, 'createdate'))) ? date('d/m/Y H:i', $t) : '—';
$lastLogFmt = ($t = as_timestamp(read_value($user, 'lastlogin'))) ? date('d/m/Y H:i', $t) : '-';

/* === AVATAR (upload simples por usu�rio) === */
$avatarDir = __DIR__ . '/uploads/avatars';
$avatarWeb = 'uploads/avatars';
$avatarBase = preg_replace('/[^A-Za-z0-9_-]/', '_', $usernameRaw ?: 'user');
$allowedMime = [
    'image/jpeg' => 'jpg',
    'image/png'  => 'png',
    'image/gif'  => 'gif',
    'image/webp' => 'webp'
];
$avatarPath = null; $avatarUrl = null; $avatarUrlBust = null; $uploadMessage = null; $uploadOk = false;

// procura arquivo j� salvo
foreach ($allowedMime as $ext) {
    $p = $avatarDir . '/' . $avatarBase . '.' . $ext;
    if (file_exists($p)) { $avatarPath = $p; $avatarUrl = $avatarWeb . '/' . $avatarBase . '.' . $ext; break; }
}
if ($avatarPath) { $avatarUrlBust = $avatarUrl . '?v=' . @filemtime($avatarPath); }

// trata upload
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['action']) && $_POST['action'] === 'upload_avatar' && isset($_FILES['avatar'])) {
    try {
        if (!is_dir($avatarDir)) @mkdir($avatarDir, 0775, true);
        if (!is_dir($avatarDir) || !is_writable($avatarDir)) throw new Exception('Pasta de upload indispon�vel.');

        $file = $_FILES['avatar'];
        if ($file['error'] !== UPLOAD_ERR_OK) throw new Exception('Falha no upload (erro ' . $file['error'] . ').');
        if ($file['size'] > 2 * 1024 * 1024) throw new Exception('Tamanho m�ximo: 2MB.');

        $tmp = $file['tmp_name'];
        $finfo = function_exists('finfo_open') ? finfo_open(FILEINFO_MIME_TYPE) : null;
        $mime = $finfo ? finfo_file($finfo, $tmp) : mime_content_type($tmp);
        if ($finfo) finfo_close($finfo);
        $ext = $allowedMime[$mime] ?? null;
        if (!$ext) throw new Exception('Formato n�o suportado. Use JPG, PNG, GIF ou WEBP.');

        // remove antigos
        foreach ($allowedMime as $e) {
            $old = $avatarDir . '/' . $avatarBase . '.' . $e;
            if (file_exists($old)) @unlink($old);
        }

        $dest = $avatarDir . '/' . $avatarBase . '.' . $ext;
        if (!@move_uploaded_file($tmp, $dest)) throw new Exception('N�o foi poss�vel salvar o arquivo.');

        // pós-processamento: recorte central e resize 256x256
        try {
            $srcImg2 = null;
            switch ($mime) {
                case 'image/jpeg': $srcImg2 = @imagecreatefromjpeg($dest); break;
                case 'image/png':  $srcImg2 = @imagecreatefrompng($dest); break;
                case 'image/gif':  $srcImg2 = @imagecreatefromgif($dest); break;
                case 'image/webp': $srcImg2 = function_exists('imagecreatefromwebp') ? @imagecreatefromwebp($dest) : null; break;
                default:
                    $extLower = strtolower(pathinfo($dest, PATHINFO_EXTENSION));
                    if ($extLower === 'jpg' || $extLower === 'jpeg') $srcImg2 = @imagecreatefromjpeg($dest);
                    elseif ($extLower === 'png') $srcImg2 = @imagecreatefrompng($dest);
                    elseif ($extLower === 'gif') $srcImg2 = @imagecreatefromgif($dest);
                    elseif ($extLower === 'webp' && function_exists('imagecreatefromwebp')) $srcImg2 = @imagecreatefromwebp($dest);
            }
            if ($srcImg2) {
                $srcW2 = imagesx($srcImg2); $srcH2 = imagesy($srcImg2);
                $side2 = max(1, min($srcW2, $srcH2));
                $sx2 = (int) max(0, floor(($srcW2 - $side2) / 2));
                $sy2 = (int) max(0, floor(($srcH2 - $side2) / 2));
                $dstSize2 = 256;
                $dstImg2 = imagecreatetruecolor($dstSize2, $dstSize2);
                if ($mime === 'image/png' || $mime === 'image/gif' || $mime === 'image/webp') {
                    imagealphablending($dstImg2, false);
                    imagesavealpha($dstImg2, true);
                    $tr2 = imagecolorallocatealpha($dstImg2, 0, 0, 0, 127);
                    imagefilledrectangle($dstImg2, 0, 0, $dstSize2, $dstSize2, $tr2);
                }
                imagecopyresampled($dstImg2, $srcImg2, 0, 0, $sx2, $sy2, $dstSize2, $dstSize2, $side2, $side2);
                $extLower2 = strtolower(pathinfo($dest, PATHINFO_EXTENSION));
                switch ($extLower2) {
                    case 'jpg': case 'jpeg': imagejpeg($dstImg2, $dest, 85); break;
                    case 'png': imagepng($dstImg2, $dest, 6); break;
                    case 'gif': imagegif($dstImg2, $dest); break;
                    case 'webp': if (function_exists('imagewebp')) imagewebp($dstImg2, $dest, 85); else imagepng($dstImg2, $dest, 6); break;
                }
                imagedestroy($dstImg2); imagedestroy($srcImg2);
            }
        } catch (Throwable $e) { /* ignore pós-processamento */ }

        // sucesso
        $avatarPath = $dest;
        $avatarUrl = $avatarWeb . '/' . $avatarBase . '.' . $ext;
        $avatarUrlBust = $avatarUrl . '?v=' . @filemtime($avatarPath);
        $uploadOk = true;
        $uploadMessage = 'Foto atualizada com sucesso!';
    } catch (Throwable $e) {
        $uploadOk = false;
        $uploadMessage = $e->getMessage();
    }
}

// remover avatar
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['action']) && $_POST['action'] === 'remove_avatar') {
    try {
        $removed = false;
        foreach ($allowedMime as $e) {
            $old = $avatarDir . '/' . $avatarBase . '.' . $e;
            if (file_exists($old)) { @unlink($old); $removed = true; }
        }
        $avatarPath = null; $avatarUrl = null; $avatarUrlBust = null;
        $uploadOk = $removed;
        $uploadMessage = $removed ? 'Foto removida.' : 'Nenhuma foto para remover.';
    } catch (Throwable $e) {
        $uploadOk = false;
        $uploadMessage = $e->getMessage();
    }
}

/* === BANCO === */
$dbDisplay = [];
try {
    if ($usernameRaw) {
        $pdo = conectarBanco();
        // Atualiza último login toda vez que o usuário entra
        $pdo->prepare("UPDATE usuarios SET ultimo_login = NOW() WHERE username = :u")
            ->execute([':u' => $usernameRaw]);

        // Busca dados atualizados
        $stmt = $pdo->prepare("
            SELECT id, username, email, key_usada, status_key, data_registro, ultimo_login 
            FROM usuarios WHERE username = :u LIMIT 1
        ");
        $stmt->execute([':u' => $usernameRaw]);
        $dbDisplay = $stmt->fetch(PDO::FETCH_ASSOC) ?: [];
    }
} catch (Throwable $e) {}

/* === MODO ADMINISTRADOR === */
$isAdmin = strcasecmp($usernameRaw, 'Administrador') === 0;
if ($isAdmin) {
    $usernameDisplay = '<span style="color:#ff4d4f">Administrador</span>';
    $createdFmt = '31/12/1969';
    $lastLogFmt = '19/10/2025';
    $planNameBest = 'Lifetime';
    $planNameFirst = 'None';
    $expiryDate = 'Nunca';
    $daysLeft = INF;
    $prettyLeft = 'Expirado';
    $planCurrentDisplay = '<span style="color:#ff4d4f">Supreme</span>';
    $dbDisplay = [
        'data_registro' => '1969-12-31 00:00:00',
        'ultimo_login' => '2025-10-19 00:00:00',
        'email' => 'admin@sqlx.com',
        'status_key' => 'ativo',
        'key_usada' => 'KEYAUTH-Admin'
    ];
} else {
    $usernameDisplay = $username;
    $planCurrentDisplay = htmlspecialchars($planNameBest);
}

$hwidRaw = read_value($user, 'hwid', read_value($user, 'hardwareid', ''));
$hwid = $hwidRaw ? htmlspecialchars($hwidRaw) : '';
?>
<!DOCTYPE html>
<html lang="pt-BR">

<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <title>Meu Perfil — SQLX</title>
    <link rel="stylesheet" href="style.css">
    <link rel="stylesheet" href="js/dashboard.css">
    <link rel="stylesheet" href="js/profile.css">
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.5.0/css/all.min.css">
    <link href="https://fonts.googleapis.com/css2?family=Poppins:wght@300;400;600&display=swap" rel="stylesheet">
    <style>
    /* Avatar imagem e upload */
    .avatar-xl { position: relative; overflow: hidden; }
    .avatar-xl .avatar-img { width: 100%; height: 100%; border-radius: 999px; object-fit: cover; display: block; }
    .avatar-upload { margin-top: 10px; display: flex; gap: 10px; align-items: center; flex-wrap: wrap; }
    .avatar-form { display: flex; gap: 10px; align-items: center; flex-wrap: wrap; }
    .file-input { position: absolute; width: 1px; height: 1px; padding: 0; margin: -1px; overflow: hidden; clip: rect(0,0,0,0); border: 0; }
    .file-pill { display: inline-flex; align-items: center; gap: 10px; padding: 8px 12px; border-radius: 999px; border: 1px solid rgba(255,255,255,0.12); background: rgba(255,255,255,0.06); cursor: pointer; }
    .file-pill:hover { box-shadow: 0 6px 18px rgba(58,123,213,0.12); border-color: rgba(58,123,213,0.35); }
    .file-label-text { opacity: 0.9; }
    .btn-upload { border: 1px solid rgba(255,255,255,0.12); background: rgba(255,255,255,0.08); color: #e9f0fb; padding: 8px 12px; border-radius: 8px; font-weight: 600; }
    .btn-upload:hover { box-shadow: 0 6px 18px rgba(58,123,213,0.12); border-color: rgba(58,123,213,0.35); }
    .btn-remove { border: 1px solid rgba(239,68,68,0.35); background: rgba(239,68,68,0.12); color: #fecaca; padding: 8px 12px; border-radius: 8px; font-weight: 700; }
    .btn-remove:hover { box-shadow: 0 6px 18px rgba(239,68,68,0.18); border-color: rgba(239,68,68,0.55); }
    .upload-msg { font-size: 0.85rem; opacity: 0.9; }
    .upload-msg.ok { color: #16a34a; }
    .upload-msg.err { color: #ef4444; }

    /* ===== Ajuste de responsividade para perfis ===== */
    @media (max-width: 768px) {
        .profile-grid {
            display: flex;
            flex-direction: column;
            gap: 1rem;
        }

        .panel {
            width: 100%;
        }

        .profile-hero {
            flex-direction: column;
            align-items: center;
            text-align: center;
        }

        .hero-main {
            margin-top: 10px;
        }

        .meta-inline {
            flex-direction: column;
            gap: 0.5rem;
        }

        .badges {
            flex-direction: column;
            align-items: center;
        }

        .chip,
        .badge {
            font-size: 0.9rem;
        }

        .avatar-xl {
            width: 80px;
            height: 80px;
            font-size: 1.8rem;
        }

        .panel-title span {
            font-size: 1rem;
        }

        .kv-label,
        .kv-value {
            font-size: 0.9rem;
        }

        /* Mobile: paineis empilhados, todos visíveis */
        .profile-grid .panel { display: block; }
    }
    </style>
</head>

<body class="dashboard">
    <div class="overlay" id="sidebarOverlay"></div>
    <div id="particles-js"></div>

    <aside class="sidebar" id="sidebar">
        <div class="sidebar-brand">
            <div class="brand-logo"><i class="fa-solid fa-magnifying-glass"></i></div>
            <h1 class="brand-name">SQLX Consultas</h1>
        </div>
        <ul class="sidebar-menu">
            <li><a href="dashboard.php" class="nav-item"><i class="fa-solid fa-gauge"></i><span>Dashboard</span></a>
            </li>
            <li class="sidebar-separator">Configurações</li>
            <li><a href="perfil.php" class="nav-item active"><i class="fa-solid fa-user-gear"></i><span>Meu
                        Perfil</span></a></li>
            <li><a href="logout.php" class="nav-item"><i
                        class="fa-solid fa-arrow-right-from-bracket"></i><span>Sair</span></a></li>
        </ul>
    </aside>

    <div class="main-content">
        <header class="header">
            <button class="mobile-menu-button" id="mobileMenuButton" aria-label="Abrir menu">
                <i class="fa-solid fa-bars"></i>
            </button>
            <div class="breadcrumb">
                <i class="fa-solid fa-house"></i> / <span class="current">Meu Perfil</span>
            </div>
        </header>

        <main class="page">
            <div class="page-head">
                <h2>Meu Perfil</h2>
                <p>Veja seus dados e status da assinatura</p>
            </div>

            <section class="profile-section">
                <div class="profile-hero">
                    <div class="avatar-xl">
                        <?php if (!empty($avatarUrlBust)): ?>
                            <img src="<?php echo htmlspecialchars($avatarUrlBust); ?>" alt="Foto de perfil" class="avatar-img">
                        <?php else: ?>
                            <?php echo strtoupper(substr(preg_replace('/[^A-Za-z0-9]/','',$username),0,2)); ?>
                        <?php endif; ?>
                    </div>
                    <div class="hero-main">
                        <div class="hero-title">
                            <h2><?php echo $usernameDisplay; ?></h2>
                            <span class="badge plan"><i class="fa-solid fa-crown"></i>
                                <?php echo htmlspecialchars($planNameBest); ?></span>
                        </div>
                        <div class="meta-inline">
                            <span class="chip"><i class="fa-regular fa-calendar-plus"></i> Criado:
                                <?php echo $createdFmt; ?></span>
                            <span class="chip"><i class="fa-regular fa-clock"></i> Último login:
                                <?php echo $lastLogFmt; ?></span>
                        </div>
                        <div class="badges">
                            <span class="badge days"><i class="fa-solid fa-hourglass-half"></i>
                                <?php echo is_infinite($daysLeft)?'&infin;':$daysLeft; ?> dias</span>
                            <span class="badge expire"><i class="fa-regular fa-bell"></i> Expira:
                                <?php echo $expiryDate; ?></span>
                        </div>
                        <div class="avatar-upload">
                            <form method="post" enctype="multipart/form-data" class="avatar-form">
                                <input type="hidden" name="action" value="upload_avatar">
                                <label class="file-pill">
                                    <i class="fa-solid fa-image"></i>
                                    <span class="file-label-text">Escolher imagem...</span>
                                    <input class="file-input" type="file" name="avatar" accept="image/*" required>
                                </label>
                                <button type="submit" class="btn-upload"><i class="fa-solid fa-upload"></i> Enviar foto</button>
                            </form>
                            <?php if (!empty($avatarPath)): ?>
                            <form method="post" class="avatar-remove-form" onsubmit="return confirm('Remover foto de perfil?');">
                                <input type="hidden" name="action" value="remove_avatar">
                                <button type="submit" class="btn-remove"><i class="fa-solid fa-trash"></i> Remover foto</button>
                            </form>
                            <?php endif; ?>
                            <?php if ($uploadMessage !== null): ?>
                                <div class="upload-msg <?php echo $uploadOk ? 'ok' : 'err'; ?>"><?php echo htmlspecialchars($uploadMessage); ?></div>
                            <?php endif; ?>
                        </div>
                    </div>
                </div>

                

                <div class="profile-grid">
                    <!-- ABA 1: INFORMAÇÕES DO USUÁRIO -->
                    <div class="panel">
                        <div class="panel-title"><i class="fa-solid fa-user"></i><span>Informações do Usuário</span>
                        </div>
                        <div class="kv">
                            <div class="kv-row">
                                <div class="kv-label">Usuário</div>
                                <div class="kv-value"><?php echo $usernameDisplay; ?></div>
                            </div>
                            <div class="kv-row">
                                <div class="kv-label">Criado em</div>
                                <div class="kv-value"><?php echo $createdFmt; ?></div>
                            </div>
                            <div class="kv-row">
                                <div class="kv-label">Último login</div>
                                <div class="kv-value"><?php echo $lastLogFmt; ?></div>
                            </div>
                            <div class="kv-row">
                                <div class="kv-label">IP atual</div>
                                <div class="kv-value"><?php echo $ipAddr; ?></div>
                            </div>
                            <?php if ($hwid): ?><div class="kv-row">
                                <div class="kv-label">HWID</div>
                                <div class="kv-value"><?php echo $hwid; ?></div>
                            </div><?php endif; ?>
                        </div>
                    </div>

                    <!-- ABA 2: ASSINATURA -->
                    <div class="panel">
                        <div class="panel-title"><i class="fa-solid fa-crown"></i><span>Assinatura</span></div>
                        <div class="kv">
                            <div class="kv-row">
                                <div class="kv-label">Plano (maior validade)</div>
                                <div class="kv-value"><?php echo htmlspecialchars($planNameBest); ?></div>
                            </div>
                            <div class="kv-row">
                                <div class="kv-label">Plano (primeiro listado)</div>
                                <div class="kv-value"><?php echo htmlspecialchars($planNameFirst); ?></div>
                            </div>
                            <div class="kv-row">
                                <div class="kv-label">Expira em</div>
                                <div class="kv-value"><?php echo htmlspecialchars($expiryDate); ?></div>
                            </div>
                            <div class="kv-row">
                                <div class="kv-label">Dias restantes</div>
                                <div class="kv-value"><?php echo is_infinite($daysLeft)?'&infin;':$daysLeft; ?></div>
                            </div>
                            <div class="kv-row">
                                <div class="kv-label">Tempo restante</div>
                                <div class="kv-value"><?php echo htmlspecialchars($prettyLeft); ?></div>
                            </div>
                            <div class="kv-row">
                                <div class="kv-label">Plano atual</div>
                                <div class="kv-value"><?php echo $planCurrentDisplay; ?></div>
                            </div>
                        </div>
                    </div>

                    <!-- ABA 3: DADOS DO BANCO -->
                    <div class="panel">
                        <div class="panel-title"><i class="fa-solid fa-database"></i><span>Dados (banco)</span></div>
                        <div class="kv">
                            <div class="kv-row">
                                <div class="kv-label">Criado (sistema)</div>
                                <div class="kv-value">
                                    <?php echo date('d/m/Y H:i', strtotime($dbDisplay['data_registro'] ?? 'now')); ?>
                                </div>
                            </div>
                            <div class="kv-row">
                                <div class="kv-label">Último login (banco)</div>
                                <div class="kv-value">
                                    <?php echo !empty($dbDisplay['ultimo_login']) ? date('d/m/Y H:i', strtotime($dbDisplay['ultimo_login'])) : '—'; ?>
                                </div>
                            </div>
                            <div class="kv-row">
                                <div class="kv-label">E-mail</div>
                                <div class="kv-value"><?php echo htmlspecialchars($dbDisplay['email'] ?? '—'); ?></div>
                            </div>
                            <div class="kv-row">
                                <div class="kv-label">Status</div>
                                <div class="kv-value"><?php echo htmlspecialchars($dbDisplay['status_key'] ?? '—'); ?>
                                </div>
                            </div>
                            <div class="kv-row">
                                <div class="kv-label">Key usada</div>
                                <div class="kv-value"><?php echo htmlspecialchars($dbDisplay['key_usada'] ?? '—'); ?>
                                </div>
                            </div>
                        </div>
                    </div>

                    <!-- ABA 4: SISTEMA -->
                    <div class="panel">
                        <div class="panel-title"><i class="fa-solid fa-microchip"></i><span>Sistema</span></div>
                        <div class="kv">
                            <div class="kv-row">
                                <div class="kv-label">Sessão iniciada</div>
                                <div class="kv-value"><?php echo date('d/m/Y H:i'); ?></div>
                            </div>
                            <div class="kv-row">
                                <div class="kv-label">Navegador</div>
                                <div class="kv-value"><?php echo htmlspecialchars($_SERVER['HTTP_USER_AGENT']); ?></div>
                            </div>
                            <div class="kv-row">
                                <div class="kv-label">Endereço IP</div>
                                <div class="kv-value"><?php echo $ipAddr; ?></div>
                            </div>
                            <div class="kv-row">
                                <div class="kv-label">Status</div>
                                <div class="kv-value" style="color:#00ffa6;">Online</div>
                            </div>
                        </div>
                    </div>
                </div>
            </section>
        </main>
    </div>

    <script src="https://cdn.jsdelivr.net/npm/particles.js@2.0.0/particles.min.js"></script>
    <script src="js/particles-config.js" defer></script>
    <script src="js/dashboard.js" defer></script>
    <script>
    (function(){
        const input = document.querySelector('.avatar-form .file-input');
        const labelText = document.querySelector('.avatar-form .file-label-text');
        if(input && labelText){
            input.addEventListener('change', function(){
                const name = this.files && this.files[0] ? this.files[0].name : 'Escolher imagem...';
                labelText.textContent = name;
            });
        }
    })();
    </script>
</body>

</html>
