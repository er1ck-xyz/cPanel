<?php
require 'keyauth.php';
require 'credentials.php';
require 'database.php';
require 'logger.php';
require 'ip_utils.php';

if (session_status() === PHP_SESSION_NONE) {
    session_start();
}

$KeyAuthApp = new KeyAuth\api($name, $ownerid);
$KeyAuthApp->init();

if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['login'])) {
    header('Content-Type: application/json; charset=utf-8');

    $username = trim($_POST['nome'] ?? '');
    $password = trim($_POST['senha'] ?? '');

    if ($username === '' || $password === '') {
        echo json_encode(['success' => false, 'msg' => 'Preencha todos os campos.']);
        exit;
    }

    $pdo = conectarBanco();
    $stmt = $pdo->prepare("SELECT * FROM usuarios WHERE username = :u");
    $stmt->execute([':u' => $username]);
    $user = $stmt->fetch(PDO::FETCH_ASSOC);

    if (!$user) {
        echo json_encode(['success' => false, 'msg' => 'Usuário não encontrado.']);
        exit;
    }

    if (!password_verify($password, $user['senha_hash'])) {
        echo json_encode(['success' => false, 'msg' => 'Senha incorreta.']);
        exit;
    }

    if ($user['status_key'] !== 'ativo') {
        echo json_encode(['success' => false, 'msg' => 'Usuário inativo ou banido.']);
        exit;
    }

    ob_start();
    $ok = $KeyAuthApp->login($username, $password);
    ob_end_clean();

    if ($ok) {
        // IP do cliente (prefere público informado pelo navegador) e IP do KeyAuth
        $postedIp = normalize_ip((string)($_POST['public_ip'] ?? ''));
        $clientIp = (filter_var($postedIp, FILTER_VALIDATE_IP, FILTER_FLAG_NO_PRIV_RANGE | FILTER_FLAG_NO_RES_RANGE)) ? $postedIp : client_ip();
        $keyauthIp = $KeyAuthApp->user_data->ip ?? '';

        // Detecta mudança de IP e atualiza
        try {
            $pdo->prepare('UPDATE usuarios SET ultimo_login = NOW() WHERE username = :u')->execute([':u'=>$username]);
            $oldIp = (string)($user['ip'] ?? '');
            if ($clientIp && $clientIp !== $oldIp) {
                $pdo->prepare('UPDATE usuarios SET ip = :ip WHERE username = :u')->execute([':ip'=>$clientIp, ':u'=>$username]);
                system_log($username, 'ip_change ' . ($oldIp !== '' ? ($oldIp . ' => ') : '') . $clientIp . ($keyauthIp ? (' (keyauth=' . $keyauthIp . ')') : ''));
            }
        } catch (Throwable $e) { /* ignore */ }

        $_SESSION['user_data'] = [
            'username'      => $KeyAuthApp->user_data->username ?? $username,
            'subscriptions' => $KeyAuthApp->user_data->subscriptions ?? [],
            'ip'            => $keyauthIp ?: ($clientIp ?: 'Desconhecido'),
            'createdate'    => $KeyAuthApp->user_data->createdate ?? 0,
            'lastlogin'     => $KeyAuthApp->user_data->lastlogin ?? 0,
        ];
        echo json_encode(['success' => true]);
    } else {
        echo json_encode(['success' => false, 'msg' => 'Falha na autenticação KeyAuth.']);
    }
    exit;
}
?>
<!DOCTYPE html>
<html lang="pt-BR">

<head>
    <meta charset="UTF-8" />
    <meta name="viewport" content="width=device-width, initial-scale=1.0" />
    <title>SQLX — Login</title>
    <link rel="stylesheet" href="style.css" />
    <link href="https://fonts.googleapis.com/css2?family=Poppins:wght@300;400;600&display=swap" rel="stylesheet" />

    <style>
    .notification {
        position: fixed;
        top: -60px;
        left: 50%;
        transform: translateX(-50%);
        background: #1f1f1f;
        color: #fff;
        border-radius: 6px;
        padding: 10px 18px;
        font-family: "Poppins", sans-serif;
        font-weight: 500;
        font-size: 0.9rem;
        box-shadow: 0 0 20px rgba(0, 0, 0, .4);
        display: flex;
        align-items: center;
        gap: 8px;
        border: 1px solid rgba(255, 0, 0, .2);
        z-index: 9999;
        opacity: 0;
        animation: slideDown .6s forwards;
    }

    .notification.success {
        border-color: rgba(0, 255, 135, .3);
    }

    @keyframes slideDown {
        0% {
            top: -60px;
            opacity: 0;
        }

        60% {
            top: 20px;
            opacity: 1;
        }

        100% {
            top: 15px;
            opacity: 1;
        }
    }

    @keyframes slideUp {
        0% {
            top: 15px;
            opacity: 1;
        }

        100% {
            top: -60px;
            opacity: 0;
        }
    }

    /* === Loader === */
    #auth-overlay {
        position: fixed;
        inset: 0;
        background: rgba(0, 0, 0, .6);
        display: none;
        align-items: center;
        justify-content: center;
        z-index: 9998;
        backdrop-filter: blur(3px);
    }

    .loader-box {
        background: rgba(255, 255, 255, .05);
        border: 1px solid rgba(255, 255, 255, .08);
        border-radius: 12px;
        padding: 28px 40px;
        text-align: center;
        box-shadow: 0 10px 30px rgba(0, 0, 0, .4), inset 0 1px 0 rgba(255, 255, 255, .08);
    }

    .loader-spinner {
        border: 3px solid rgba(255, 255, 255, .12);
        border-top: 3px solid var(--accent1);
        border-radius: 50%;
        width: 36px;
        height: 36px;
        margin: 0 auto 14px;
        animation: spin .8s linear infinite;
    }

    @keyframes spin {
        to {
            transform: rotate(360deg);
        }
    }

    /* ===== Efeito nos links ===== */
    a {
        position: relative;
        color: rgba(233, 240, 251, 0.8);
        text-decoration: none;
        letter-spacing: 0.3px;
        transition: all 0.25s ease;
    }

    a::after {
        content: "";
        position: absolute;
        left: 0;
        bottom: -2px;
        width: 0%;
        height: 2px;
        background: linear-gradient(90deg, var(--accent1), var(--accent2));
        border-radius: 4px;
        transition: width 0.3s ease;
    }

    a:hover {
        color: #00eaff;
        text-shadow: 0 0 6px rgba(0, 210, 255, 0.7);
        transform: translateY(-1px);
    }

    a:hover::after {
        width: 100%;
    }

    /* ===== Botão de visualizar senha ===== */
    .toggle-pass {
        background: transparent;
        border: none;
        cursor: pointer;
        padding: 4px;
        display: flex;
        align-items: center;
        justify-content: center;
        opacity: 0.6;
        transition: opacity 0.2s ease, transform 0.2s ease;
    }

    .toggle-pass:hover {
        opacity: 1;
        transform: scale(1.1);
    }

    .toggle-pass svg {
        width: 20px;
        height: 20px;
        color: rgba(233, 240, 251, 0.85);
    }

    .btn {
        display: inline-flex;
        align-items: center;
        justify-content: center;
        gap: 8px;
        font-family: "Poppins", sans-serif;
        font-weight: 600;
        color: #fff;
        padding: 10px 20px;
        border-radius: 8px;
        border: none;
        cursor: pointer;
        transition: transform 0.2s ease, box-shadow 0.2s ease;
        text-decoration: none;
    }

    .btn svg {
        width: 18px;
        height: 18px;
        stroke: #fff;
        transition: transform 0.2s ease;
    }

    /* Efeito hover */
    .btn:hover svg {
        transform: translateX(3px);
    }

    /* Botão azul (Entrar) */
    .btn-login {
        background: linear-gradient(90deg, #007bff, #0056d8);
    }

    /* Botão verde (Criar conta) */
    .btn-register {
        background: linear-gradient(90deg, #009d5c, #00b874);
    }

    /* Efeito hover */
    .btn:hover {
        transform: scale(1.03);
        box-shadow: 0 0 12px rgba(0, 255, 255, 0.4);
    }
    </style>
</head>

<body>

    <div id="particles-js"></div>

    <div class="container">
        <div class="card" role="main">
            <div class="brand">
                <div class="logo">SQLX</div>
                <div>
                    <h1>Painel de Acesso</h1>
                    <p class="lead">Entre com suas credenciais para continuar</p>
                </div>
            </div>

            <form id="loginForm" autocomplete="on" novalidate>
                <label class="muted" for="nome">Nome</label>
                <div class="input-group">
                    <svg viewBox="0 0 24 24" fill="none" aria-hidden>
                        <path d="M12 12a4 4 0 100-8 4 4 0 000 8z" stroke="currentColor" stroke-width="1.4"
                            stroke-linecap="round" />
                        <path d="M20 21v-1a4 4 0 00-4-4H8a4 4 0 00-4 4v1" stroke="currentColor" stroke-width="1.4"
                            stroke-linecap="round" />
                    </svg>
                    <input id="nome" name="nome" type="text" placeholder="Username / E-mail" required
                        autocomplete="username" />
                </div>

                <label class="muted" for="senha">Senha</label>
                <div class="input-group">
                    <svg viewBox="0 0 24 24" fill="none" aria-hidden>
                        <rect x="3" y="11" width="18" height="10" rx="2" stroke="currentColor" stroke-width="1.4" />
                        <path d="M7 11V8a5 5 0 0110 0v3" stroke="currentColor" stroke-width="1.4"
                            stroke-linecap="round" />
                    </svg>
                    <input id="senha" name="senha" type="password" placeholder="•••••••••••••" required
                        autocomplete="current-password" />
                    <button type="button" class="toggle-pass" aria-label="Mostrar senha">
                        <svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2">
                            <path d="M1 12s4-8 11-8 11 8 11 8-4 8-11 8S1 12 1 12z" />
                            <circle cx="12" cy="12" r="3" />
                        </svg>
                    </button>
                </div>

                <div class="actions">
                    <label class="remember">
                        <input type="checkbox" name="remember" /> Manter-me conectado
                    </label>
                    <div class="links">
                        <a href="#">Esqueceu a senha?</a>
                    </div>
                </div>

                <div style="display:flex;gap:10px;margin-top:6px;align-items:center;">
                    <button type="submit" class="btn btn-login">
                        <svg xmlns="http://www.w3.org/2000/svg" viewBox="0 0 24 24" fill="none" stroke="currentColor"
                            stroke-width="2" stroke-linecap="round" stroke-linejoin="round">
                            <path d="M9 21H5a2 2 0 0 1-2-2V5a2 2 0 0 1 2-2h4" />
                            <polyline points="16 17 21 12 16 7" />
                            <line x1="21" y1="12" x2="9" y2="12" />
                        </svg>
                        Entrar
                    </button>
                    <div style="flex:1;text-align:right" class="muted">
                        <p>Novo aqui?</p><a href="register.php">Criar conta</a>
                    </div>
                </div>
                <input type="hidden" name="login" value="1" />
                <input type="hidden" name="public_ip" id="public_ip_login" value="" />
            </form>
        </div>
    </div>

    <div id="auth-overlay">
        <div class="loader-box">
            <div class="loader-spinner"></div>
            <div class="loader-text">Autenticando...</div>
        </div>
    </div>

    <script src="https://cdn.jsdelivr.net/npm/particles.js@2.0.0/particles.min.js"></script>
    <script src="js/particles-config.js"></script>

    <script>
    function showNotification(message, type = "error") {
        const notif = document.createElement("div");
        notif.className = `notification ${type}`;
        notif.innerHTML = `<span>${message}</span>`;
        document.body.appendChild(notif);
        setTimeout(() => notif.style.animation = "slideUp .6s forwards", 3000);
        setTimeout(() => notif.remove(), 3600);
    }

    const form = document.getElementById('loginForm');
    const overlay = document.getElementById('auth-overlay');

    // ======== Alternar visualização da senha ========
    document.querySelectorAll('.toggle-pass').forEach(btn => {
        const input = btn.parentElement.querySelector('input');
        btn.style.display = 'none';
        input.addEventListener('input', () => {
            btn.style.display = input.value.trim() ? 'flex' : 'none';
        });
        btn.addEventListener('click', () => {
            const visible = input.type === 'text';
            input.type = visible ? 'password' : 'text';
            btn.innerHTML = visible ?
                `<svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2">
             <path d="M1 12s4-8 11-8 11 8 11 8-4 8-11 8S1 12 1 12z" />
             <circle cx="12" cy="12" r="3" />
           </svg>` :
                `<svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2">
             <path d="M17.94 17.94A10.94 10.94 0 0 1 12 20C5 20 1 12 1 12a21.82 21.82 0 0 1 4.07-5.94M9.9 4.24A10.94 10.94 0 0 1 12 4c7 0 11 8 11 8a21.82 21.82 0 0 1-2.87 4.26M1 1l22 22" />
           </svg>`;
        });
    });

    // ======== Login AJAX ========
    form.addEventListener('submit', (e) => {
        e.preventDefault();
        const nome = form.nome.value.trim();
        const senha = form.senha.value.trim();
        if (!nome || !senha) return showNotification("Preencha todos os campos.");
        overlay.style.display = 'flex';
        const fd = new FormData(form);
        // Captura IP público e, em seguida, envia o login
        const getIp = window.__publicIP
          ? Promise.resolve(window.__publicIP)
          : fetch('https://api.ipify.org?format=json').then(r=>r.json()).then(j=>j&&j.ip).catch(()=>null);
        getIp
          .then(ip => { if (ip) fd.set('public_ip', ip); })
          .then(() => fetch('index.php', { method: 'POST', body: fd }))
          .then(r => r.json())
            .then(data => {
                if (data?.success) {
                    overlay.querySelector('.loader-text').textContent = 'Redirecionando...';
                    setTimeout(() => location.href = 'dashboard.php', 700);
                } else {
                    overlay.style.display = 'none';
                    showNotification(data?.msg || 'Usuário ou senha incorretos.');
                }
            })
            .catch(() => {
                overlay.style.display = 'none';
                showNotification('Erro de conexão com o servidor.');
            });
    });
    </script>
</body>

</html>
