<?php
require_once 'seller_api.php';
require_once 'logger.php';
if (session_status() === PHP_SESSION_NONE) session_start();

$userSess = isset($_SESSION['user_data']) && is_array($_SESSION['user_data']) ? $_SESSION['user_data'] : [];
$usernameRaw = isset($userSess['username']) ? (string)$userSess['username'] : '';
if (strcasecmp($usernameRaw, 'Administrador') !== 0) { header('Location: dashboard.php'); exit; }

if (empty($_SESSION['csrf_admin'])) $_SESSION['csrf_admin'] = bin2hex(random_bytes(16));
$csrf = $_SESSION['csrf_admin'];


$avatarDir = __DIR__ . '/uploads/avatars';
$avatarWeb = 'uploads/avatars';
$avatarBase = preg_replace('/[^A-Za-z0-9_-]/', '_', $usernameRaw ?: 'user');
$avatarUrlBust = null; foreach (['jpg','jpeg','png','gif','webp'] as $ext){ $p=$avatarDir.'/'.$avatarBase.'.'.$ext; if(file_exists($p)){ $avatarUrlBust=$avatarWeb.'/'.$avatarBase.'.'.$ext.'?v='.@filemtime($p); break; }}

$noticeMsg=null; $noticeOk=null; $generatedKeysText=null;
if($_SERVER['REQUEST_METHOD']==='POST' && isset($_POST['action']) && $_POST['action']==='create_license'){
  try{
    if(!hash_equals($csrf,(string)($_POST['csrf']??''))) throw new Exception('Falha de segurança (CSRF).');
    $amount=(int)($_POST['amount']??1); $expiry=(int)($_POST['expiry']??1); $level=(int)($_POST['level']??1);
    $mask=trim((string)($_POST['mask']??'******-******-******-******-******-******'));
    $format='text';
    if($amount<1) $amount=1; if($amount>100) $amount=100; if($expiry<1) $expiry=1; if($level<1) $level=1;
    $resp=seller_create_licenses($amount,$expiry,$level,$mask,$format);
    if(isset($resp['success']) && $resp['success']===false){ throw new Exception($resp['message']??'Falha ao gerar licenças.'); }
    $noticeOk=true; $noticeMsg='Licenças geradas com sucesso.';
    if(isset($resp['raw'])){ $generatedKeysText=(string)$resp['raw']; }
    elseif(isset($resp['keys'])){ $generatedKeysText=implode("\n",(array)$resp['keys']); }
    elseif(isset($resp['license'])){ $generatedKeysText=(string)$resp['license']; }
    elseif(isset($resp['licenses'])){ $generatedKeysText=implode("\n",(array)$resp['licenses']); }
    else { $generatedKeysText=json_encode($resp,JSON_PRETTY_PRINT|JSON_UNESCAPED_SLASHES|JSON_UNESCAPED_UNICODE); }
    system_log($usernameRaw ?: 'admin', "create_license amt={$amount} level={$level} expiry={$expiry}");
  }catch(Throwable $e){ $noticeOk=false; $noticeMsg=$e->getMessage(); }
}
?>
<!DOCTYPE html>
<html lang="pt-BR">

<head>
    <meta charset="UTF-8" />
    <meta name="viewport" content="width=device-width, initial-scale=1.0" />
    <title>Gerar Licenças - SQLX</title>
    <link rel="stylesheet" href="style.css" />
    <link rel="stylesheet" href="js/dashboard.css" />
    <link rel="stylesheet" href="admin.css" />
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.5.0/css/all.min.css" />
    <link href="https://fonts.googleapis.com/css2?family=Poppins:wght@300;400;600&display=swap" rel="stylesheet" />
    <style>
    
    :root {
        --control-h: 42px;
    }

    

    
    .form-grid {
        align-items: start;
    }

    .field {
        display: flex;
        flex-direction: column;
        gap: 8px;
    }

    .field .control.with-bg {
        display: flex;
        align-items: center;
        height: var(--control-h);
    }

    .field .control.with-bg i {
        display: inline-flex;
        align-items: center;
        height: 100%;
    }

    .field .control.with-bg input[type="number"],
    .field .control.with-bg input[type="text"] {
        height: 100%;
        line-height: 1;
        
    }

    
    .quick-select {
        margin-top: 8px;
    }

    .quick-select .label {
        opacity: .85;
        margin-right: 6px;
    }

    .quick-select .seg {
        display: flex;
        flex-wrap: wrap;
        gap: 8px;
        align-items: center;
    }

    .quick-select .seg-btn {
        display: inline-flex;
        align-items: center;
        justify-content: center;
        height: var(--control-h);
        padding: 0 12px;
        line-height: 1;
        vertical-align: middle;
        
        border-radius: 8px;
        cursor: pointer;
    }

    .quick-select .seg-btn.active {
        outline: 2px solid rgba(255, 255, 255, .15);
    }

    
    @media (max-width: 900px) {
        .form-grid {
            grid-template-columns: 1fr !important;
        }
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
            <li><a href="#" class="nav-item"><i class="fa-solid fa-user-group"></i><span>Indicações</span></a></li>
            <li><a href="#" class="nav-item"><i class="fa-solid fa-crown"></i><span>Assinatura</span></a></li>
            <li><a href="#" class="nav-item"><i class="fa-solid fa-code"></i><span>APIs</span></a></li>
            <li><a href="licenses.php" class="nav-item active"><i class="fa-solid fa-key"></i><span>Licenças</span></a>
            </li>
            <li><a href="admin.php" class="nav-item"><i class="fa-solid fa-shield-halved"></i><span>Painel
                        Admin</span></a></li>
            <li class="sidebar-separator">Configurações</li>
            <li><a href="perfil.php" class="nav-item"><i class="fa-solid fa-user-gear"></i><span>Meu Perfil</span></a>
            </li>
            <li><a href="logout.php" class="nav-item"><i
                        class="fa-solid fa-arrow-right-from-bracket"></i><span>Sair</span></a></li>
        </ul>
    </aside>

    <div class="main-content">
        <header class="header">
            <button class="mobile-menu-button" id="mobileMenuButton" aria-label="Abrir menu"><i
                    class="fa-solid fa-bars"></i></button>
            <div class="breadcrumb"><i class="fa-solid fa-key"></i><span>/</span><span class="current">Licenças</span>
            </div>
            <div class="header-actions">
                <div class="user-menu">
                    <?php $initials = strtoupper(substr(preg_replace('/[^A-Za-z0-9]/','',$usernameRaw?:'ADM'),0,2)); ?>
                    <button class="user-chip" id="userMenuButton" aria-haspopup="true" aria-expanded="false">
                        <div class="avatar-circle">
                            <?php if($avatarUrlBust): ?><img src="<?php echo htmlspecialchars($avatarUrlBust); ?>"
                                alt="Avatar"
                                class="avatar-img" /><?php else: ?><?php echo htmlspecialchars($initials); ?><?php endif; ?>
                        </div>
                        <i class="fa-solid fa-chevron-down"></i>
                    </button>
                    <div class="user-dropdown" id="userDropdown" role="menu">
                        <a href="perfil.php" role="menuitem">Meu Perfil</a>
                        <a href="logout.php" role="menuitem" class="logout">Sair</a>
                    </div>
                </div>
            </div>
        </header>

        <main class="page">
            <div class="page-head">
                <div class="titles">
                    <h2>Gerar Licenças (KeyAuth)</h2>
                    <p>Crie novas chaves de licença usando sua conta premium.</p>
                </div>
            </div>

            <?php if ($noticeMsg !== null): ?>
            <div class="notice <?php echo $noticeOk ? 'ok' : 'err'; ?>"><?php echo htmlspecialchars($noticeMsg); ?>
            </div>
            <?php endif; ?>

            <div class="admin-card license-card">
                <div class="card-head">
                    <div class="icon-bubble"><i class="fa-solid fa-key"></i></div>
                    <div>
                        <div class="head-title">Gerador de Licenças</div>
                        <div class="head-sub">Crie chaves com máscara e nível desejados</div>
                    </div>
                </div>

                <form method="post" class="form-grid">
                    <input type="hidden" name="csrf" value="<?php echo htmlspecialchars($csrf); ?>" />
                    <input type="hidden" name="action" value="create_license" />

                    <div class="field">
                        <label>Quantidade</label>
                        <div class="control with-bg"><i class="fa-solid fa-sort-numeric-up"></i>
                            <input type="number" name="amount" min="1" max="100" value="1" />
                        </div>
                        <div class="hint">Até 100 por vez.</div>
                    </div>

                    <div class="field">
                        <label>Expiração (dias)</label>
                        <div class="control with-bg"><i class="fa-regular fa-calendar"></i>
                            <input type="number" name="expiry" min="1" value="1" />
                        </div>
                        <div class="quick-select">
                            <span class="label">Rápido:</span>
                            <div class="seg" role="group" aria-label="Expiração rápida">
                                <button type="button" class="seg-btn" data-days="1">1d</button>
                                <button type="button" class="seg-btn" data-days="7">7d</button>
                                <button type="button" class="seg-btn" data-days="15">15d</button>
                                <button type="button" class="seg-btn" data-days="30">30d</button>
                                <button type="button" class="seg-btn" data-days="90">90d</button>
                                <button type="button" class="seg-btn" data-days="365">365d</button>
                            </div>
                        </div>
                    </div>

                    <div class="field">
                        <label>Nível</label>
                        <div class="control with-bg"><i class="fa-solid fa-layer-group"></i>
                            <input type="number" name="level" min="1" value="1" />
                        </div>
                    </div>

                    <div class="field wide">
                        <label>Máscara</label>
                        <div class="control with-bg"><i class="fa-solid fa-key"></i>
                            <input type="text" name="mask" value="******-******-******-******-******-******" />
                        </div>
                        <div class="hint">Use * para caracteres aleatórios. Você pode ajustar o padrão.</div>
                        <div class="preset-row">
                            <span class="preset" data-mask="*****-*****-*****-*****">5x4</span>
                            <span class="preset" data-mask="******-******-******-******-******-******">6x6
                                (padrão)</span>
                            <span class="preset" data-mask="****-****-****-****-****">4x5</span>
                            <span class="preset" data-mask="*****-*****-*****">5x3</span>
                        </div>
                    </div>

                    <div class="form-actions" style="grid-column:1/-1;display:flex;gap:10px;justify-content:flex-end;">
                        <button class="btn-sm btn-primary" type="submit"><i class="fa-solid fa-wand-magic-sparkles"></i>
                            Gerar Licenças</button>
                    </div>
                </form>

                <?php if($generatedKeysText): ?>
                <div class="result-box">
                    <label>Licenças geradas</label>
                    <textarea readonly rows="5" onfocus="this.select()" style="width:100%;"></textarea>
                    <div class="result-actions">
                        <button class="btn-sm btn-ghost" id="copyKeys" type="button"><i class="fa-solid fa-copy"></i>
                            Copiar</button>
                    </div>
                    <script>
                    (function() {
                        const ta = document.currentScript.previousElementSibling.previousElementSibling;
                        ta.value = <?php echo json_encode($generatedKeysText); ?>;
                    })();
                    </script>
                </div>
                <?php endif; ?>
            </div>
        </main>
    </div>

    <script src="https://cdn.jsdelivr.net/npm/particles.js@2.0.0/particles.min.js"></script>
    <script src="js/particles-config.js" defer></script>
    <script src="js/dashboard.js" defer></script>
    <script>
    (function() {
        const presets = document.querySelectorAll('.preset[data-mask]');
        const maskInput = document.querySelector('input[name="mask"]');
        presets.forEach(p => p.addEventListener('click', () => {
            if (maskInput) {
                maskInput.value = p.getAttribute('data-mask') || maskInput.value;
                maskInput.focus();
            }
        }));

        const copyBtn = document.getElementById('copyKeys');
        if (copyBtn) {
            copyBtn.addEventListener('click', async () => {
                const ta = document.querySelector('.result-box textarea');
                if (!ta) return;
                ta.select();
                try {
                    await navigator.clipboard.writeText(ta.value);
                    showToast('Licenças copiadas');
                } catch (e) {
                    document.execCommand('copy');
                    showToast('Copiado');
                }
            });
        }

        
        const expiryInput = document.querySelector('input[name="expiry"]');
        const segBtns = document.querySelectorAll('.seg-btn[data-days]');

        function refreshActive() {
            const v = parseInt(expiryInput && expiryInput.value ? expiryInput.value : '0');
            segBtns.forEach(b => b.classList.toggle('active', parseInt(b.dataset.days || '') === v));
        }
        segBtns.forEach(b => b.addEventListener('click', () => {
            if (!expiryInput) return;
            expiryInput.value = b.dataset.days || expiryInput.value;
            refreshActive();
            expiryInput.focus();
        }));
        if (expiryInput) {
            expiryInput.addEventListener('input', refreshActive);
            refreshActive();
        }

        function showToast(text) {
            const t = document.createElement('div');
            t.className = 'toast';
            t.textContent = text;
            document.body.appendChild(t);
            requestAnimationFrame(() => t.classList.add('show'));
            setTimeout(() => {
                t.classList.remove('show');
                setTimeout(() => t.remove(), 250);
            }, 1800);
        }
    })();
    </script>
</body>

</html>
