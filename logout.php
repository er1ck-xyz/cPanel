<?php
session_start();

// Destroi a sessão
session_destroy();

// Redireciona de volta para o login
header("Location: index.php");
exit();
?>