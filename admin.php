<?php
require 'database.php';
require_once 'ip_utils.php';
require_once 'logger.php';
if (session_status() === PHP_SESSION_NONE) session_start();


$userSess   = isset($_SESSION['user_data']) && is_array($_SESSION['user_data']) ? $_SESSION['user_data'] : [];
$usernameRaw = isset($userSess['username']) ? (string)$userSess['username'] : '';
if (strcasecmp($usernameRaw, 'Administrador') !== 0) { header('Location: dashboard.php'); exit; }


if (empty($_SESSION['csrf_admin'])) $_SESSION['csrf_admin'] = bin2hex(random_bytes(16));
$csrf = $_SESSION['csrf_admin'];


$avatarDir = __DIR__ . '/uploads/avatars';
$avatarWeb = 'uploads/avatars';
$avatarBase = preg_replace('/[^A-Za-z0-9_-]/', '_', $usernameRaw ?: 'user');
$avatarUrlBust = null; foreach (['jpg','jpeg','png','gif','webp'] as $ext){ $p=$avatarDir.'/'.$avatarBase.'.'.$ext; if(file_exists($p)){ $avatarUrlBust=$avatarWeb.'/'.$avatarBase.'.'.$ext.'?v='.@filemtime($p); break; }}


$q     = trim((string)($_GET['q'] ?? ''));
$status= strtolower(trim((string)($_GET['status'] ?? '')));
$p     = max(1,(int)($_GET['p'] ?? 1));
$per   = (int)($_GET['per'] ?? 20); if($per<=0)$per=20; if($per>200)$per=200;
$sort  = trim((string)($_GET['sort'] ?? 'id'));
$dir   = strtolower(trim((string)($_GET['dir'] ?? 'desc'))) === 'asc' ? 'asc' : 'desc';


$logDir = __DIR__ . '/storage';
$logFile = $logDir . '/admin.log'; if (!is_dir($logDir)) { @mkdir($logDir, 0775, true); }
function admin_log($message){ global $usernameRaw; system_log($usernameRaw ?: 'admin', $message); }


function build_where(array &$params,string $search='',string $status=''): string {
  $where=[];
  if($search!==''){ $where[]='(username ILIKE :q OR email ILIKE :q)'; $params[':q']='%'.$search.'%'; }
  if($status!==''){ $where[]='LOWER(status_key)=:status'; $params[':status']=$status; }
  return $where?(' WHERE '.implode(' AND ',$where)) : '';
}
function fetch_users(PDO $pdo,string $search='',string $status='',string $sort='id',string $dir='desc',int $limit=20,int $offset=0): array {
  $params=[]; $whereSql=build_where($params,$search,$status);
  $map=['id'=>'id','username'=>'username','email'=>'email','ip'=>'ip','key'=>'key_usada','status'=>'status_key','created'=>'data_registro','lastlogin'=>'ultimo_login'];
  $col=$map[$sort]??'id'; $dirSql=strtolower($dir)==='asc'?'ASC':'DESC';
  $sql='SELECT id,username,email,ip,key_usada,status_key,data_registro,ultimo_login FROM usuarios'.$whereSql." ORDER BY $col $dirSql LIMIT :lim OFFSET :off";
  $stmt=$pdo->prepare($sql); foreach($params as $k=>$v){$stmt->bindValue($k,$v,PDO::PARAM_STR);} $stmt->bindValue(':lim',(int)$limit,PDO::PARAM_INT); $stmt->bindValue(':off',(int)$offset,PDO::PARAM_INT); $stmt->execute(); return $stmt->fetchAll(PDO::FETCH_ASSOC)?:[];
}
function count_users(PDO $pdo,string $search='',string $status=''): int {
  $params=[]; $whereSql=build_where($params,$search,$status); $stmt=$pdo->prepare('SELECT COUNT(*) FROM usuarios'.$whereSql); foreach($params as $k=>$v){$stmt->bindValue($k,$v,PDO::PARAM_STR);} $stmt->execute(); return (int)$stmt->fetchColumn();
}


$noticeMsg=null; $noticeOk=null;
if($_SERVER['REQUEST_METHOD']==='POST' && isset($_POST['action'])){
  try{
    if(!hash_equals($csrf,(string)($_POST['csrf']??''))) throw new Exception('Falha de segurança (CSRF).');
    $action=(string)$_POST['action'];
    $pdo=conectarBanco();
    if($action==='update_status'){
      $u=trim((string)($_POST['username']??'')); $statusNew=strtolower(trim((string)($_POST['status']??'')));
      if($u==='' || !in_array($statusNew,['ativo','bloqueado'],true)) throw new Exception('Dados inválidos.');
      if(strcasecmp($u,'Administrador')===0) throw new Exception('Não permitido alterar Administrador.');
      $stmt=$pdo->prepare('UPDATE usuarios SET status_key=:s WHERE LOWER(username)=LOWER(:u)'); $stmt->execute([':s'=>$statusNew,':u'=>$u]);
      if($stmt->rowCount()<1){ $check=$pdo->prepare('SELECT 1 FROM usuarios WHERE LOWER(username)=LOWER(:u)'); $check->execute([':u'=>$u]); if(!$check->fetchColumn()) throw new Exception('Usuário não encontrado.'); }
      $noticeOk=true; $noticeMsg='Status atualizado.'; admin_log("update_status {$u} => {$statusNew}");
    }elseif($action==='reset_key'){
      $u=trim((string)($_POST['username']??'')); if($u==='') throw new Exception('Usuário ausente.'); if(strcasecmp($u,'Administrador')===0) throw new Exception('Não permitido alterar Administrador.');
      $pdo->prepare('UPDATE usuarios SET key_usada=NULL WHERE LOWER(username)=LOWER(:u)')->execute([':u'=>$u]); $noticeOk=true; $noticeMsg='Key resetada.'; admin_log("reset_key {$u}");
    }elseif($action==='update_key'){
      $u=trim((string)($_POST['username']??'')); $key=trim((string)($_POST['key']??'')); if($u==='') throw new Exception('Usuário ausente.'); if(strcasecmp($u,'Administrador')===0) throw new Exception('Não permitido alterar Administrador.');
      if($key===''){ $noticeOk=true; $noticeMsg='Nada a atualizar.'; } else { $pdo->prepare('UPDATE usuarios SET key_usada=:k WHERE LOWER(username)=LOWER(:u)')->execute([':k'=>$key,':u'=>$u]); $noticeOk=true; $noticeMsg='Key atualizada.'; admin_log("update_key {$u}"); }
    }elseif($action==='delete_user'){
      $u=trim((string)($_POST['username']??'')); if($u==='') throw new Exception('Usuário ausente.'); if(strcasecmp($u,'Administrador')===0) throw new Exception('Não permitido deletar Administrador.');
      $pdo->prepare('DELETE FROM usuarios WHERE LOWER(username)=LOWER(:u)')->execute([':u'=>$u]); $noticeOk=true; $noticeMsg='Usuário excluído.'; admin_log("delete_user {$u}");
    }elseif($action==='clear_logs'){
      if(file_exists($logFile)) @file_put_contents($logFile,''); $noticeOk=true; $noticeMsg='Logs limpos.';
    }
  }catch(Throwable $e){ $noticeOk=false; $noticeMsg=$e->getMessage(); admin_log('erro: '.$noticeMsg); }
}


$users=[]; $totalUsers=0; $totalPages=1; try{
  $pdoList=conectarBanco(); $off=($p-1)*$per;
  if(isset($_GET['export']) && $_GET['export']==='csv'){
    $rows=fetch_users($pdoList,$q,$status,$sort,$dir,1000000,0);
    header('Content-Type: text/csv; charset=UTF-8'); header('Content-Disposition: attachment; filename="usuarios-'.date('Ymd-His').'.csv"'); echo "\xEF\xBB\xBF"; $out=fopen('php://output','w'); fputcsv($out,['id','username','email','ip','key_usada','status_key','data_registro','ultimo_login']); foreach($rows as $r) fputcsv($out,[$r['id']??'',$r['username']??'',$r['email']??'',$r['ip']??'',$r['key_usada']??'',$r['status_key']??'',$r['data_registro']??'',$r['ultimo_login']??'']); fclose($out); exit;
  }
  $users=fetch_users($pdoList,$q,$status,$sort,$dir,$per,$off);
  $totalUsers=count_users($pdoList,$q,$status); $totalPages=max(1,(int)ceil($totalUsers/$per));
} catch (Throwable $e) { $noticeOk=false; $noticeMsg='Erro ao listar usuários. Verifique a conexão com o banco. Detalhe: '.htmlspecialchars($e->getMessage()); $users=[]; }
?>
<!DOCTYPE html>
<html lang="pt-BR">

<head>
    <meta charset="UTF-8" />
    <meta name="viewport" content="width=device-width, initial-scale=1.0" />
    <title>Painel Admin - SQLX</title>
    <link rel="stylesheet" href="style.css" />
    <link rel="stylesheet" href="js/dashboard.css" />
    <link rel="stylesheet" href="admin.css" />
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
            <li><a href="dashboard.php" class="nav-item"><i class="fa-solid fa-gauge"></i><span>Dashboard</span></a>
            </li>
            <li><a href="#" class="nav-item"><i class="fa-solid fa-user-group"></i><span>Indicações</span></a></li>
            <li><a href="#" class="nav-item"><i class="fa-solid fa-crown"></i><span>Assinatura</span></a></li>
            <li><a href="#" class="nav-item"><i class="fa-solid fa-code"></i><span>APIs</span></a></li>
            <li>
                <a href="licenses.php" class="nav-item"><i class="fa-solid fa-key"></i><span>Licenças</span></a>
            </li>
            <li><a href="admin.php" class="nav-item active"><i class="fa-solid fa-shield-halved"></i><span>Painel
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
            <div class="breadcrumb"><i class="fa-solid fa-house"></i> / <span class="current">Painel Admin</span></div>
            <div class="header-actions">
                <div class="user-menu">
                    <?php $initials=strtoupper(substr(preg_replace('/[^A-Za-z0-9]/','',$usernameRaw),0,2)); ?>
                    <button class="user-chip" id="userMenuButton" aria-haspopup="true" aria-expanded="false">
                        <div class="avatar-circle">
                            <?php if(!empty($avatarUrlBust)): ?>
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
                        <a href="logout.php" role="menuitem" class="logout">Sair</a>
                    </div>
                </div>
            </div>
        </header>

        <main class="page">
            <div class="page-head">
                <div class="titles">
                    <h2>Painel do Administrador</h2>
                    <p>Gestão de usuários e logs.</p>
                </div>
            </div>
            <?php if ($noticeMsg !== null): ?>
            <div class="notice <?php echo $noticeOk ? 'ok' : 'err'; ?>"><?php echo htmlspecialchars($noticeMsg); ?>
            </div>
            <?php endif; ?>

            <div class="admin-tabs" role="tablist">
                <button type="button" class="tab active" data-target="tab-users" role="tab"
                    aria-selected="true">Usuários</button>
                <button type="button" class="tab" data-target="tab-logs" role="tab" aria-selected="false">Logs</button>
            </div>

            <section id="tab-users" class="admin-tab active" aria-label="Gestão de Usuários">
                <div class="admin-card">
                    <div class="search-box">
                        <form method="get" class="filters-row">
                            <label class="pill search" title="Buscar"><i class="fa-solid fa-magnifying-glass"></i><input
                                    class="pill-control" type="text" name="q"
                                    value="<?php echo htmlspecialchars($q); ?>"
                                    placeholder="Buscar por username ou email" /></label>
                            <label class="pill select" title="Status"><i class="fa-solid fa-filter"></i><select
                                    class="pill-control" name="status">
                                    <option value="" <?php echo ($status==='')?'selected':''; ?>>Todos status</option>
                                    <option value="ativo" <?php echo ($status==='ativo')?'selected':''; ?>>Ativo
                                    </option>
                                    <option value="bloqueado" <?php echo ($status==='bloqueado')?'selected':''; ?>>
                                        Bloqueado</option>
                                </select><i class="fa-solid fa-caret-down caret"></i></label>
                            <label class="pill per" title="Itens por página"><i class="fa-solid fa-list-ol"></i><select
                                    class="pill-control" name="per"><?php foreach([10,20,50,100] as $opt): ?><option
                                        value="<?php echo $opt; ?>" <?php echo ($per==$opt)?'selected':''; ?>>
                                        <?php echo $opt; ?>/pág</option><?php endforeach; ?></select><i
                                    class="fa-solid fa-caret-down caret"></i></label>
                            <input type="hidden" name="sort" value="<?php echo htmlspecialchars($sort); ?>" />
                            <input type="hidden" name="dir" value="<?php echo htmlspecialchars($dir); ?>" />
                            <button class="btn-sm btn-icon" type="submit"><i class="fa-solid fa-search"></i>
                                Buscar</button>
                            <?php $qsExport=http_build_query(['q'=>$q,'status'=>$status,'sort'=>$sort,'dir'=>$dir,'export'=>'csv']); ?>
                            <a class="btn-sm btn-icon" href="?<?php echo $qsExport; ?>" title="Exportar CSV"><i
                                    class="fa-solid fa-file-csv"></i> Exportar</a>
                        </form>
                    </div>

                    <div class="table-wrap">
                        <table class="admin-table">
                            <thead>
                                <tr>
                                    <?php $qsBase=['q'=>$q,'status'=>$status,'per'=>$per]; $nextDir=($dir==='asc')?'desc':'asc'; $th=function($label,$skey) use($qsBase,$sort,$dir,$nextDir){ $curr=$sort===$skey; $d=$curr?$nextDir:'asc'; $qs=http_build_query($qsBase+['sort'=>$skey,'dir'=>$d]); echo '<th><a href="?'.$qs.'" style="color:inherit;text-decoration:none;">'.$label.($curr?($dir==='asc'?' ▲':' ▼'):'').'</a></th>'; }; ?>
                                    <?php $th('ID','id'); ?><th>Foto</th>
                                    <?php $th('Username','username'); ?><?php $th('Email','email'); ?><?php $th('IP','ip'); ?><?php $th('Key','key'); ?><?php $th('Status','status'); ?><?php $th('Registro','created'); ?><?php $th('Últ. login','lastlogin'); ?>
                                    
                                </tr>
                            </thead>
                            <tbody>
                                <?php foreach($users as $u): $uname=(string)($u['username']??''); $isAdminRow=strcasecmp($uname,'Administrador')===0; ?>
                                <tr class="<?php echo $isAdminRow?'row-admin':''; ?>">
                                    <td class="mono" data-label="ID"><?php echo (int)($u['id']??0); ?></td>
                                    <td data-label="Foto">
                                        <?php $base=preg_replace('/[^A-Za-z0-9_-]/','_',$uname?:'user'); $avatarDirRow=__DIR__.'/uploads/avatars'; $avatarWebRow='uploads/avatars'; $avatarUrlRow=null; $initials=''; foreach(['jpg','jpeg','png','gif','webp'] as $ext){ $pp=$avatarDirRow.'/'.$base.'.'.$ext; if(file_exists($pp)){ $avatarUrlRow=$avatarWebRow.'/'.$base.'.'.$ext.'?v='.@filemtime($pp); break; }} if(!$avatarUrlRow){ $parts=preg_split('/\s+/',trim($uname)); if(!empty($parts[0])) $initials.=strtoupper(substr($parts[0],0,1)); if(!empty($parts[1])) $initials.=strtoupper(substr($parts[1],0,1)); if($initials==='') $initials=strtoupper(substr($base,0,2)); } ?>
                                        <div class="avatar avatar-sm"><?php if($avatarUrlRow): ?><img
                                                src="<?php echo htmlspecialchars($avatarUrlRow); ?>"
                                                alt="avatar" /><?php else: ?><span
                                                class="avatar-fallback"><?php echo htmlspecialchars($initials); ?></span><?php endif; ?>
                                        </div>
                                    </td>
                                    <td data-label="Username">
                                        <?php echo htmlspecialchars($uname?:'-'); ?><?php if($isAdminRow): ?><span
                                            class="badge-admin"><i class="fa-solid fa-crown"></i>
                                            Admin</span><?php endif; ?></td>
                                    <td data-label="Email"><?php echo htmlspecialchars($u['email']??'-'); ?></td>
                                    <td class="mono" data-label="IP">
                                        <?php $ipOut=normalize_ip((string)($u['ip']??'')); echo htmlspecialchars($ipOut?:'-'); ?>
                                    </td>
                                    <td class="mono" data-label="Key">
                                        <?php echo htmlspecialchars($u['key_usada']??''); ?></td>
                                    <td data-label="Status">
                                        <?php $st=strtolower((string)($u['status_key']??'')); $cls=$st==='ativo'?'status-ativo':($st==='bloqueado'?'status-bloqueado':''); ?><span
                                            class="status-badge <?php echo $cls; ?>"><?php echo htmlspecialchars($st?:'-'); ?></span>
                                    </td>
                                    <td class="mono" data-label="Registro">
                                        <?php echo !empty($u['data_registro'])?date('d/m/Y H:i',strtotime($u['data_registro'])):'-'; ?>
                                    </td>
                                    <td class="mono" data-label="Ult. login">
                                        <?php echo !empty($u['ultimo_login'])?date('d/m/Y H:i',strtotime($u['ultimo_login'])):'-'; ?>
                                    </td>
                                </tr>
                                <?php endforeach; ?>
                                <?php if(!$users): ?><tr>
                                    <td colspan="10">Nenhum registro encontrado.</td>
                                </tr><?php endif; ?>
                            </tbody>
                        </table>
                    </div>
                    <?php $baseQS=function($page) use($q,$status,$per,$sort,$dir){ return http_build_query(['q'=>$q,'status'=>$status,'per'=>$per,'sort'=>$sort,'dir'=>$dir,'p'=>$page]); }; $curr=$p; $tp=$totalPages??1; $hasPrev=$curr>1; $hasNext=$curr<$tp; ?>
                    <div class="pagination"
                        style="display:flex;gap:8px;align-items:center;justify-content:flex-end;margin-top:10px;">
                        <?php if($hasPrev): ?><a class="btn-sm" href="?<?php echo $baseQS($curr-1); ?>">&laquo;
                            Anterior</a><?php endif; ?>
                        <span>Página <?php echo (int)$curr; ?> de <?php echo (int)$tp; ?></span>
                        <?php if($hasNext): ?><a class="btn-sm" href="?<?php echo $baseQS($curr+1); ?>">Próxima
                            &raquo;</a><?php endif; ?>
                    </div>
                </div>
            </section>

            <section id="tab-logs" class="admin-tab" aria-label="Logs do Sistema" style="display:none;">
                <div class="admin-card">
                    <h3>Logs de Admin</h3>
                    <p class="muted">Eventos do sistema (mais recentes primeiro).</p>
                    <div class="log-toolbar">
                        <form method="post" onsubmit="return confirm('Limpar todos os logs?');"><input type="hidden"
                                name="csrf" value="<?php echo htmlspecialchars($csrf); ?>" /><input type="hidden"
                                name="action" value="clear_logs" /><button class="btn-sm btn-danger" type="submit"><i
                                    class="fa-solid fa-trash"></i> Limpar Logs</button></form>
                    </div>
                    <?php function h($s){ return htmlspecialchars((string)$s,ENT_QUOTES,'UTF-8'); }
                    function render_log_msg($actor,$raw){ $rawStr=(string)$raw; $rawEsc=h($rawStr);
                        if(preg_match('/^update_status\s+(.+?)\s*=>\s*(ativo|bloqueado)$/i',$rawStr,$mm)){ $alvo=h($mm[1]); $estado=strtolower($mm[2]); $cls=$estado==='ativo'?'badge-ok':'badge-warn'; return "<span class='ev ev-status'><i class='fa-solid fa-toggle-on'></i>Status</span> <span class='mono'>".$alvo."</span> <span class='sep'>&rarr;</span> <span class='badge {$cls}'>".h($estado)."</span>"; }
                        if(preg_match('/^update_key\s+(.+)$/i',$rawStr,$mm)){ return "<span class='ev ev-key'><i class='fa-solid fa-key'></i>Key</span> Atualizada para <span class='mono'>".h($mm[1])."</span>"; }
                        if(preg_match('/^reset_key\s+(.+)$/i',$rawStr,$mm)){ return "<span class='ev ev-key'><i class='fa-regular fa-circle'></i>Key</span> Resetada para <span class='mono'>".h($mm[1])."</span>"; }
                        if(preg_match('/^delete_user\s+(.+)$/i',$rawStr,$mm)){ return "<span class='ev ev-del'><i class='fa-solid fa-trash'></i>Usuário</span> <span class='mono'>".h($mm[1])."</span>"; }
                        if(stripos($rawStr,'erro')!==false || stripos($rawStr,'error')!==false){ return "<span class='ev ev-del'><i class='fa-solid fa-triangle-exclamation'></i>Erro</span> <span class='mono'>".$rawEsc."</span>"; }
                        return "<span class='mono'>".$rawEsc."</span>"; }
                    $hasLog=file_exists($logFile) && filesize($logFile)>0; if($hasLog){ $lines=@file($logFile, FILE_IGNORE_NEW_LINES|FILE_SKIP_EMPTY_LINES)?:[]; $last=array_slice($lines,-300); $last=array_reverse($last); echo '<ul class="log-list">'; foreach($last as $line){ $line=rtrim((string)$line,"\r\n"); if(preg_match('/^(\d{4}-\d{2}-\d{2} \d{2}:\d{2}:\d{2}) \[(.*?)\] (.*)$/',$line,$m)){ $ts=h($m[1]); $user=(string)$m[2]; $msgRaw=(string)$m[3]; $userEsc=h($user); $userClass=strcasecmp($user,'Administrador')===0?' is-admin':''; $msgHtml=render_log_msg($user,$msgRaw); echo "<li class='log-row'><span class='ts'>{$ts}</span><span class='user{$userClass}'>{$userEsc}</span><span class='msg'>{$msgHtml}</span></li>"; } else { $raw=h($line); echo "<li class='log-row'><span class='ts'>-</span><span class='user'>-</span><span class='msg'><span class='mono'>{$raw}</span></span></li>"; } } echo '</ul>'; } else { echo '<div class="log-empty">Sem logs ainda.</div>'; } ?>
                </div>
            </section>
        </main>
    </div>

    <script src="https://cdn.jsdelivr.net/npm/particles.js@2.0.0/particles.min.js"></script>
    <script src="js/particles-config.js" defer></script>
    <script src="js/dashboard.js" defer></script>
    <script>
    (function() {
        const tabs = document.querySelectorAll('.admin-tabs .tab');
        const panes = document.querySelectorAll('.admin-tab');

        function show(id) {
            panes.forEach(p => {
                const s = p.id === id;
                p.classList.toggle('active', s);
                p.style.display = s ? 'block' : 'none';
            });
            tabs.forEach(t => t.classList.toggle('active', (t.dataset && t.dataset.target) === id));
        }
        const start = (location.hash && location.hash.substring(1)) || document.querySelector('.admin-tab.active')
            ?.id || 'tab-users';
        show(start);
        tabs.forEach(t => t.addEventListener('click', e => {
            e.preventDefault();
            const id = t.dataset ? t.dataset.target : '';
            if (id) show(id);
        }));
    })();
    </script>
</body>

</html>

