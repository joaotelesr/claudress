<?php
/**
 * logout.php - Botão "Sair" do topo. Encerra o login e volta para a tela de entrada.
 * Só aceita o formulário do botão (POST com o código da sessão), para outro site não conseguir deslogar a loja.
 */
require_once __DIR__ . '/../config/config.php';

$token = (string) ($_POST['token'] ?? '');

if (!ehPost() || !hash_equals(tokenLogin(), $token)) {
    redirecionar('index.php');
}

encerrarLogin();

// Abre uma sessão nova e limpa só para mostrar o aviso "Você saiu do sistema." na tela de login
session_start();
$_SESSION['login_saiu'] = true;

redirecionar('login.php');
