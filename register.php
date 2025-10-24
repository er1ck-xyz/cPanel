<?php
require 'keyauth.php';
require 'credentials.php';
require 'database.php';
require 'logger.php';
require 'ip_utils.php';
require 'recaptcha.php'; // inclui verificação reCAPTCHA

if (session_status() === PHP_SESSION_NONE) {
    session_start();
}

$KeyAuthApp = new KeyAuth\api($name, $ownerid);
$KeyAuthApp->init();

$flash = null;

if (isset($_POST['register'])) {
    $username = trim($_POST['nome'] ?? '');
    $password = trim($_POST['senha'] ?? '');
    $email    = trim($_POST['email'] ?? '');
    $license  = trim($_POST['key'] ?? '');
    $token    = $_POST['g-recaptcha-response'] ?? '';
    $terms    = isset($_POST['agreeTerms']); // ✅ novo campo

    // ===== Validação =====
    if ($username === '' || $password === '' || $email === '' || $license === '') {
        $flash = ['type' => 'error', 'msg' => '⚠️ Preencha todos os campos!'];
    } elseif (!$terms) {
        $flash = ['type' => 'warning', 'msg' => '⚠️ Você precisa aceitar os Termos de Uso e a Política de Privacidade.'];
    } else {
        $captcha = verifyRecaptcha($token);

        if (empty($captcha['success']) || $captcha['score'] < 0.5) {
            $flash = ['type' => 'error', 'msg' => '⚠️ Falha na verificação do reCAPTCHA.'];
        } else {
            // ===== Registro via KeyAuth =====
            $data = [
                "type"      => "register",
                "username"  => $username,
                "pass"      => $password,
                "key"       => $license,
                "sessionid" => $_SESSION['sessionid'] ?? '',
                "name"      => $name,
                "ownerid"   => $ownerid
            ];

            $ch = curl_init("https://keyauth.win/api/1.2/");
            curl_setopt_array($ch, [
                CURLOPT_RETURNTRANSFER => true,
                CURLOPT_SSL_VERIFYPEER => false,
                CURLOPT_SSL_VERIFYHOST => false,
                CURLOPT_POST => true,
                CURLOPT_POSTFIELDS => $data,
                CURLOPT_USERAGENT => 'KeyAuth-PHP'
            ]);
            $response = curl_exec($ch);
            curl_close($ch);

            $json = json_decode($response, true);

            if (!empty($json['success'])) {
                try {
                    $pdo = conectarBanco();

                    $check = $pdo->prepare("SELECT COUNT(*) FROM usuarios WHERE username = :u OR email = :e");
                    $check->execute([':u' => $username, ':e' => $email]);
                    if ($check->fetchColumn() > 0) {
                        throw new Exception('⚠️ Usuário ou e-mail já cadastrado.');
                    }

                    // ✅ Agora também grava criado_em e ultimo_login
                    $sql = "INSERT INTO usuarios (
                                username, email, senha_hash, key_usada, status_key,
                                data_registro, criado_em, ultimo_login
                            )
                            VALUES (
                                :u, :e, :s, :k, 'ativo',
                                NOW(), NOW(), NOW()
                            )";
                    $stmt = $pdo->prepare($sql);
                    $stmt->execute([
                        ':u' => $username,
                        ':e' => $email,
                        ':s' => password_hash($password, PASSWORD_DEFAULT),
                        ':k' => $license
                    ]);

                    // Atualiza IP e registra em log (prefere IP público enviado pelo navegador)
                    $ipClient = normalize_ip((string)($_POST['public_ip'] ?? ''));
                    if (!filter_var($ipClient, FILTER_VALIDATE_IP, FILTER_FLAG_NO_PRIV_RANGE | FILTER_FLAG_NO_RES_RANGE)) {
                        $ipClient = client_ip();
                    }
                    try { $pdo->prepare("UPDATE usuarios SET ip = :ip WHERE username = :u")->execute([':ip'=>$ipClient, ':u'=>$username]); } catch (Throwable $e) {}
                    system_log($username, 'new_user ip=' . ($ipClient ?: 'desconhecido') . ' email=' . $email);

                    $flash = ['type' => 'success', 'msg' => '✅ Conta criada com sucesso! Redirecionando...'];
                } catch (Exception $e) {
                    $flash = ['type' => 'error', 'msg' => '⚠️ ' . $e->getMessage()];
                }
            } else {
                $msg = $json['message'] ?? 'Erro desconhecido.';

                if (str_contains($msg, 'License does not exist')) {
                    $msg = 'A key informada não existe.';
                } elseif (str_contains($msg, 'License is already used')) {
                    $msg = 'Essa key já foi utilizada.';
                } elseif (str_contains($msg, 'Username already taken')) {
                    $msg = 'Esse nome de usuário já está em uso.';
                } elseif (str_contains($msg, 'Invalid')) {
                    $msg = 'Key inválida.';
                }

                $flash = ['type' => 'error', 'msg' => $msg];
            }
        }
    }
}
?>
<!DOCTYPE html>
<html lang="pt-BR">

<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>SQLX — Cadastro</title>
    <link rel="stylesheet" href="style.css">
    <link href="https://fonts.googleapis.com/css2?family=Poppins:wght@300;400;600&display=swap" rel="stylesheet">
    <link rel="stylesheet" href="notification.css">
    <style>
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
    </style>
</head>

<body>
    <?php if ($flash): ?>
    <div class="flash-message flash-<?php echo $flash['type']; ?>">
        <?php if ($flash['type'] === 'success'): ?>
        <svg viewBox="0 0 24 24" fill="none">
            <path d="M20 6L9 17l-5-5" stroke="#00ffcc" stroke-width="2" stroke-linecap="round"
                stroke-linejoin="round" />
        </svg>
        <?php elseif ($flash['type'] === 'warning'): ?>
        <svg viewBox="0 0 24 24" fill="none">
            <path d="M12 9v4m0 4h.01M10.29 3.86l-8.17 14A1 1 0 003 19h18a1 1 0 00.87-1.5l-8.17-14a1 1 0 00-1.74 0z"
                stroke="#ffcc00" stroke-width="2" stroke-linecap="round" stroke-linejoin="round" />
        </svg>
        <?php else: ?>
        <svg viewBox="0 0 24 24" fill="none">
            <circle cx="12" cy="12" r="10" stroke="#ff4b4b" stroke-width="2" />
            <path d="M12 8v4M12 16h.01" stroke="#ff4b4b" stroke-width="2" stroke-linecap="round" />
        </svg>
        <?php endif; ?>
        <?php echo htmlspecialchars($flash['msg']); ?>
    </div>

    <script>
    setTimeout(() => {
        const flash = document.querySelector('.flash-message');
        if (flash) {
            flash.style.animation = "fadeOut 0.5s ease forwards";
            setTimeout(() => flash.remove(), 500);
        }
    }, 4000);

    <?php if ($flash['type'] === 'success'): ?>
    setTimeout(() => {
        window.location.href = "index.php";
    }, 2000);
    <?php endif; ?>
    </script>
    <?php endif; ?>

    <div id="particles-js"></div>

    <div class="container">
        <div class="card" role="main">
            <div class="brand">
                <div class="logo">SQLX</div>
                <div>
                    <h1>Criar Conta</h1>
                    <p class="lead">Preencha as informações abaixo para se registrar</p>
                </div>
            </div>

            <form method="post" id="registerForm" novalidate>
                <label class="muted" for="nome">Usuário</label>
                <div class="input-group">
                    <svg viewBox="0 0 24 24" fill="none">
                        <path d="M12 12a4 4 0 100-8 4 4 0 000 8z" stroke="currentColor" stroke-width="1.4" />
                        <path d="M20 21v-1a4 4 0 00-4-4H8a4 4 0 00-4 4v1" stroke="currentColor" stroke-width="1.4" />
                    </svg>
                    <input id="nome" name="nome" type="text" placeholder="Seu nome de usuário">
                </div>

                <label class="muted" for="email">E-mail</label>
                <div class="input-group">
                    <svg viewBox="0 0 24 24" fill="none">
                        <rect x="3" y="5" width="18" height="14" rx="2" stroke="currentColor" stroke-width="1.4" />
                        <path d="M3 7l9 6 9-6" stroke="currentColor" stroke-width="1.4" stroke-linecap="round" />
                    </svg>
                    <input id="email" name="email" type="email" placeholder="Seu e-mail">
                </div>

                <label class="muted" for="senha">Senha</label>
                <div class="input-group">
                    <svg viewBox="0 0 24 24" fill="none">
                        <rect x="3" y="11" width="18" height="10" rx="2" stroke="currentColor" stroke-width="1.4" />
                        <path d="M7 11V8a5 5 0 0110 0v3" stroke="currentColor" stroke-width="1.4"
                            stroke-linecap="round" />
                    </svg>
                    <input id="senha" name="senha" type="password" placeholder="Crie uma senha">
                </div>

                <label class="muted" for="key">Key</label>
                <div class="input-group">
                    <svg viewBox="0 0 24 24" fill="none">
                        <path d="M3 11a8 8 0 1113.66 5.66L21 21l-2 2-3.34-3.34A8 8 0 013 11z" stroke="currentColor"
                            stroke-width="1.4" />
                        <circle cx="11" cy="11" r="2" stroke="currentColor" stroke-width="1.4" />
                    </svg>
                    <input id="key" name="key" type="text" placeholder="Insira sua key">
                </div>

                <input type="hidden" id="g-recaptcha-response" name="g-recaptcha-response">
                <input type="hidden" name="public_ip" id="public_ip_reg" value="" />

                <div style="display:flex;gap:10px;margin-top:6px;align-items:center;">
                    <button type="submit" name="register" class="btn btn-register">
                        <svg style="width:18px;height:18px;margin-right:6px;vertical-align:middle;" viewBox="0 0 24 24"
                            fill="none">
                            <path d="M5 12h14M12 5l7 7-7 7" stroke="#042033" stroke-width="2" stroke-linecap="round"
                                stroke-linejoin="round" />
                        </svg>
                        Registrar
                    </button>
                    <div style="flex:1;text-align:right" class="muted">
                        <p>Já tem uma conta?</p>
                        <a href="index.php">Fazer login</a>
                    </div>
                </div>

                <div class="terms-container">
                    <label>
                        <input type="checkbox" id="agreeTerms" name="agreeTerms">
                        Eu concordo com os <a href="termos.html" target="_blank">Termos de Uso</a> e
                        <a href="privacidade.html" target="_blank">Política de Privacidade</a>
                    </label>
                </div>
            </form>
        </div>
    </div>

    <script src="https://cdn.jsdelivr.net/npm/particles.js@2.0.0/particles.min.js"></script>
    <script src="js/particles-config.js"></script>
    <script src="senha-check.js"></script>

    <!-- ✅ reCAPTCHA v3 invisível -->
    <script>
    // Captura IP p�blico para envio junto ao formul�rio
    (function() {
        const el = document.getElementById('public_ip_reg');
        fetch('https://api.ipify.org?format=json')
            .then(r => r.json())
            .then(j => {
                if (j && j.ip && el) {
                    el.value = j.ip;
                    window.__publicIP = j.ip;
                }
            })
            .catch(() => {});
    })();
    </script>
    <script src="https://www.google.com/recaptcha/api.js?render=6LfhAfArAAAAAA0uVk6jnxv9N-65AoiFdKdx9J_4"></script>
    <script>
    grecaptcha.ready(function() {
        grecaptcha.execute('6LfhAfArAAAAAA0uVk6jnxv9N-65AoiFdKdx9J_4', {
            action: 'register'
        }).then(function(token) {
            document.getElementById('g-recaptcha-response').value = token;
        });
    });
    </script>
</body>

</html>