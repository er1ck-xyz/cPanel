<?php
require 'keyauth.php';
require 'credentials.php';
require 'database.php';
require 'recaptcha.php';

if (session_status() === PHP_SESSION_NONE) {
    session_start();
}


$userSess = isset($_SESSION['user_data']) ? $_SESSION['user_data'] : [];
$usernameRaw = is_array($userSess) && isset($userSess['username']) ? $userSess['username'] : '';
$isAdmin = strcasecmp($usernameRaw, 'Administrador') === 0;
$avatarDir = __DIR__ . '/uploads/avatars';
$avatarWeb = 'uploads/avatars';
$avatarBase = preg_replace('/[^A-Za-z0-9_-]/', '_', $usernameRaw ?: 'user');
$avatarUrlBust = null;
if ($usernameRaw) {
    $exts = ['jpg','png','gif','webp'];
    foreach ($exts as $ext) {
        $p = $avatarDir . '/' . $avatarBase . '.' . $ext;
        if (file_exists($p)) {
            $avatarUrlBust = $avatarWeb . '/' . $avatarBase . '.' . $ext . '?v=' . @filemtime($p);
            break;
        }
    }
}
?>
<!DOCTYPE html>
<html lang="pt-BR">

<head>
    <meta charset="UTF-8" />
    <meta name="viewport" content="width=device-width, initial-scale=1.0" />
    <title>SQLX â€” Consultas Avançadas</title>
    <link rel="stylesheet" href="style.css" />
    <link rel="stylesheet" href="js/dashboard.css" />
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.5.0/css/all.min.css" />
    <link href="https://fonts.googleapis.com/css2?family=Poppins:wght@300;400;600&display=swap" rel="stylesheet" />
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
            <li>
                <a href="dashboard.php" class="nav-item active"><i
                        class="fa-solid fa-gauge"></i><span>Dashboard</span></a>
            </li>
            <li>
                <a href="#" class="nav-item"><i class="fa-solid fa-user-group"></i><span>Indicações</span></a>
            </li>
            <li>
                <a href="#" class="nav-item"><i class="fa-solid fa-crown"></i><span>Assinatura</span></a>
            </li>
            <li>
                <a href="#" class="nav-item"><i class="fa-solid fa-code"></i><span>APIs</span></a>
            </li>
            <?php if ($isAdmin): ?>
            <li>
                <a href="admin.php" class="nav-item"><i class="fa-solid fa-shield-halved"></i><span>Painel
                        Admin</span></a>
            </li>
            <li>
                <a href="licenses.php" class="nav-item"><i class="fa-solid fa-key"></i><span>Licenças</span></a>
            </li>
            <?php endif; ?>

            <li class="sidebar-separator">Configurações</li>
            <li>
                <a href="perfil.php" class="nav-item"><i class="fa-solid fa-user-gear"></i><span>Meu Perfil</span></a>
            </li>
            <li>
                <a href="logout.php" class="nav-item"><i
                        class="fa-solid fa-arrow-right-from-bracket"></i><span>Sair</span></a>
            </li>
        </ul>
    </aside>

    
    <div class="main-content">
        
        <header class="header">
            <button class="mobile-menu-button" id="mobileMenuButton" aria-label="Abrir menu">
                <i class="fa-solid fa-bars"></i>
            </button>

            <div class="breadcrumb">
                <i class="fa-solid fa-house"></i>
                <span>/</span>
                <span class="current">Consultas</span>
            </div>

            <div class="header-actions">
                <div class="user-menu">
                    <?php 
                        $displayName = isset($_SESSION['user_data']['username']) ? $_SESSION['user_data']['username'] : 'Bem-vindo';
                        $initials = strtoupper(substr(preg_replace('/[^A-Za-z0-9]/', '', $displayName), 0, 2));
                    ?>
                    <button class="user-chip" id="userMenuButton" aria-haspopup="true" aria-expanded="false">
                        <div class="avatar-circle">
                            <?php if (!empty($avatarUrlBust)): ?>
                            <img src="<?php echo htmlspecialchars($avatarUrlBust); ?>" alt="Avatar"
                                class="avatar-img" />
                            <?php else: ?>
                            <?php echo htmlspecialchars($initials); ?>
                            <?php endif; ?>
                        </div>
                        <i class="fa-solid fa-chevron-down"></i>
                    </button>
                    <div class="user-dropdown" id="userDropdown" role="menu">
                        <a href="perfil.php" role="menuitem">Meu Perfil</a>
                        <a href="perfil.php" role="menuitem">Configurações</a>
                        <a href="logout.php" role="menuitem" class="logout">Sair</a>
                    </div>
                </div>
            </div>
        </header>

        
        <main class="page">
            <div class="page-head">
                <div class="titles">
                    <h2>Consultas Avançadas</h2>
                    <p>Selecione o tipo de consulta que deseja realizar</p>
                </div>
                <div class="search-bar mobile-only">
                    <i class="fa-solid fa-magnifying-glass"></i>
                    <input type="text" placeholder="Pesquisar..." aria-label="Pesquisar" />
                </div>
            </div>

            
            <section class="consultations">
                <h3 class="category-title">Modulos de Consulta</h3>
                <div class="consultations-grid">
                    <a href="cpf.php" class="consultation-card">
                        <div class="icon-circle blue"><i class="fa-solid fa-id-card"></i></div>
                        <span class="title">CPF</span>
                        <span class="desc">Consulta Básica</span>
                    </a>
                    <a href="cpf-completo.php" class="consultation-card">
                        <div class="icon-circle blue"><i class="fa-solid fa-user-check"></i></div>
                        <span class="title">CPF Completo</span>
                        <span class="desc">Dados completos</span>
                    </a>
                    <a href="nome.php" class="consultation-card">
                        <div class="icon-circle teal"><i class="fa-solid fa-user"></i></div>
                        <span class="title">Nome</span>
                        <span class="desc">Busca por nome</span>
                    </a>
                    <a href="rg.php" class="consultation-card">
                        <div class="icon-circle amber"><i class="fa-solid fa-id-card-clip"></i></div>
                        <span class="title">RG</span>
                        <span class="desc">Registro Geral</span>
                    </a>
                    <a href="titulo-eleitor.php" class="consultation-card">
                        <div class="icon-circle teal"><i class="fa-solid fa-passport"></i></div>
                        <span class="title">Título Eleitor</span>
                        <span class="desc">Dados eleitorais</span>
                    </a>
                    <a href="cns.php" class="consultation-card">
                        <div class="icon-circle teal"><i class="fa-solid fa-notes-medical"></i></div>
                        <span class="title">CNS</span>
                        <span class="desc">Cartão Nacional de Saúde</span>
                    </a>
                    <a href="telefone.php" class="consultation-card">
                        <div class="icon-circle indigo"><i class="fa-solid fa-phone"></i></div>
                        <span class="title">Telefone</span>
                        <span class="desc">Números cadastrados</span>
                    </a>
                    <a href="email.php" class="consultation-card">
                        <div class="icon-circle red"><i class="fa-solid fa-envelope"></i></div>
                        <span class="title">Email</span>
                        <span class="desc">Endereços Eléletronicos</span>
                    </a>
                    <a href="cep.php" class="consultation-card">
                        <div class="icon-circle orange"><i class="fa-solid fa-location-dot"></i></div>
                        <span class="title">CEP</span>
                        <span class="desc">Endereços por CEP</span>
                    </a>
                    <a href="placa.php" class="consultation-card">
                        <div class="icon-circle gray"><i class="fa-solid fa-car"></i></div>
                        <span class="title">Placa</span>
                        <span class="desc">Veículos</span>
                    </a>
                    <a href="radar-veiculo.php" class="consultation-card">
                        <div class="icon-circle red"><i class="fa-solid fa-location-crosshairs"></i></div>
                        <span class="title">Radar Veículos</span>
                        <span class="desc">Monitoramento veicular</span>
                    </a>
                    <a href="vistoria-veiculo.php" class="consultation-card">
                        <div class="icon-circle green"><i class="fa-solid fa-clipboard-check"></i></div>
                        <span class="title">Vistoria Veicular</span>
                        <span class="desc">Inspeção detalhada</span>
                    </a>
                    <a href="pix.php" class="consultation-card">
                        <div class="icon-circle blue"><i class="fa-solid fa-money-bill-transfer"></i></div>
                        <span class="title">PIX</span>
                        <span class="desc">Chaves cadastradas</span>
                    </a>
                    <a href="processos.php" class="consultation-card">
                        <div class="icon-circle purple"><i class="fa-solid fa-scale-balanced"></i></div>
                        <span class="title">Processos</span>
                        <span class="desc">Judiciais e administrativos</span>
                    </a>
                    <a href="credilink.php" class="consultation-card">
                        <div class="icon-circle green"><i class="fa-solid fa-chart-line"></i></div>
                        <span class="title">Credilink</span>
                        <span class="desc">Análise de Crédito</span>
                    </a>
                    <a href="score.php" class="consultation-card">
                        <div class="icon-circle pink"><i class="fa-solid fa-chart-column"></i></div>
                        <span class="title">Score</span>
                        <span class="desc">Pontuação de crédito</span>
                    </a>
                    <a href="parentes.php" class="consultation-card">
                        <div class="icon-circle lime"><i class="fa-solid fa-users"></i></div>
                        <span class="title">Parentes</span>
                        <span class="desc">Família e relacionamentos</span>
                    </a>
                    <a href="cnpj.php" class="consultation-card">
                        <div class="icon-circle purple"><i class="fa-solid fa-building"></i></div>
                        <span class="title">CNPJ</span>
                        <span class="desc">Dados empresariais</span>
                    </a>
                    <a href="gerador-estado.php" class="consultation-card">
                        <div class="icon-circle green"><i class="fa-solid fa-map-location-dot"></i></div>
                        <span class="title">Gerador por Estado</span>
                        <span class="desc">Dados por UF</span>
                    </a>
                    <a href="gerador-nascimento.php" class="consultation-card">
                        <div class="icon-circle purple"><i class="fa-solid fa-calendar-days"></i></div>
                        <span class="title">Gerador por Nascimento</span>
                        <span class="desc">Dados por data</span>
                    </a>
                    <a href="senha.php" class="consultation-card">
                        <div class="icon-circle red"><i class="fa-solid fa-key"></i></div>
                        <span class="title">Senha</span>
                        <span class="desc">Credenciais</span>
                    </a>
                    <a href="mae.php" class="consultation-card">
                        <div class="icon-circle pink"><i class="fa-solid fa-person-dress"></i></div>
                        <span class="title">Mãe</span>
                        <span class="desc">Nome da mãe</span>
                    </a>
                    <a href="pai.php" class="consultation-card">
                        <div class="icon-circle indigo"><i class="fa-solid fa-person"></i></div>
                        <span class="title">Pai</span>
                        <span class="desc">Nome do pai</span>
                    </a>
                    <a href="pis.php" class="consultation-card">
                        <div class="icon-circle indigo"><i class="fa-solid fa-address-card"></i></div>
                        <span class="title">PIS</span>
                        <span class="desc">Programa de Integração Social</span>
                    </a>
                    <a href="foto-nacional.php" class="consultation-card">
                        <div class="icon-circle yellow"><i class="fa-solid fa-image"></i></div>
                        <span class="title">Foto Nacional</span>
                        <span class="desc">Imagens registradas</span>
                    </a>
                </div>
            </section>
        </main>
    </div>

    <script src="https://cdn.jsdelivr.net/npm/particles.js@2.0.0/particles.min.js"></script>
    <script src="js/particles-config.js" defer></script>
    <script src="js/dashboard.js" defer></script>
</body>

</html>

