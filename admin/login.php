<?php
/**
 * login.php - Tela de entrada do sistema (usuário e senha da loja).
 * É a única página do admin que abre sem login (por isso define PAGINA_PUBLICA antes do config).
 */
define('PAGINA_PUBLICA', true);
require_once __DIR__ . '/../config/config.php';

// Já está logada? Vai direto para o Painel.
if (usuarioLogado()) {
    redirecionar('index.php');
}

$usuario = '';
$erro    = '';
$aviso   = '';

if (!empty($_SESSION['login_expirou'])) {
    unset($_SESSION['login_expirou']);
    $aviso = 'Você ficou muito tempo sem usar o sistema. Entre de novo para continuar.';
}
if (!empty($_SESSION['login_saiu'])) {
    unset($_SESSION['login_saiu']);
    $aviso = 'Você saiu do sistema.';
}

if (ehPost()) {
    $usuario = trim((string) ($_POST['usuario'] ?? ''));
    $senha   = (string) ($_POST['senha'] ?? '');

    if ($usuario === '' || $senha === '') {
        $erro = 'Preencha o usuário e a senha.';
    } else {
        $erro = tentarLogin($usuario, $senha);
        if ($erro === '') {
            redirecionar(paginaDepoisDoLogin());
        }
    }
}
?>
<!DOCTYPE html>
<html lang="pt-BR">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <title>Entrar · <?= e(NOME_LOJA) ?></title>
    <script>
        /* Aplica o tema salvo (claro/escuro) antes de desenhar a página, para não piscar. */
        (function () {
            try {
                if (localStorage.getItem('claudress-tema') === 'escuro') {
                    document.documentElement.setAttribute('data-tema', 'escuro');
                }
            } catch (e) { }
        })();
    </script>
    <link rel="stylesheet" href="../CSS/base.css">
    <link rel="stylesheet" href="../CSS/componentes.css">
    <link rel="stylesheet" href="../CSS/login.css">
    <link rel="stylesheet" href="../CSS/tema.css">
</head>
<body class="pagina-login">

<button type="button" class="botao-tema login-tema" id="botao-tema" title="Trocar entre tema claro e escuro">
    <svg class="icone icone-lua" width="18" height="18" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.8" stroke-linecap="round" stroke-linejoin="round" aria-hidden="true"><path d="M21 12.8A9 9 0 1 1 11.2 3a7 7 0 0 0 9.8 9.8z"/></svg>
    <svg class="icone icone-sol" width="18" height="18" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.8" stroke-linecap="round" stroke-linejoin="round" aria-hidden="true"><circle cx="12" cy="12" r="4"/><path d="M12 2v2M12 20v2M4.9 4.9l1.4 1.4M17.7 17.7l1.4 1.4M2 12h2M20 12h2M4.9 19.1l1.4-1.4M17.7 6.3l1.4-1.4"/></svg>
    <span class="botao-tema-texto">Tema escuro</span>
</button>

<main class="login-caixa">
    <div class="login-marca">
        <span class="login-nome"><?= e(NOME_LOJA) ?></span>
        <span class="login-sub">Gestão de vendas</span>
    </div>

    <form method="post" action="login.php" class="cartao login-form" novalidate>
        <h1 class="login-titulo">Entrar no sistema</h1>

        <?php if ($aviso !== '' && $erro === ''): ?>
            <div class="alerta alerta-sucesso" role="status"><?= e($aviso) ?></div>
        <?php endif; ?>
        <?php if ($erro !== ''): ?>
            <div class="alerta alerta-erro" role="alert"><?= e($erro) ?></div>
        <?php endif; ?>

        <div class="campo">
            <label for="usuario">Usuário</label>
            <input type="text" id="usuario" name="usuario" value="<?= e($usuario) ?>"
                   autocomplete="username" autocapitalize="none" spellcheck="false" required <?= $usuario === '' ? 'autofocus' : '' ?>>
        </div>

        <div class="campo">
            <label for="senha">Senha</label>
            <div class="login-senha">
                <input type="password" id="senha" name="senha" autocomplete="current-password" required <?= $usuario !== '' ? 'autofocus' : '' ?>>
                <button type="button" class="login-ver-senha" id="ver-senha" aria-label="Mostrar senha" title="Mostrar senha">
                    <svg width="18" height="18" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.8" stroke-linecap="round" stroke-linejoin="round" aria-hidden="true"><path d="M1 12s4-7 11-7 11 7 11 7-4 7-11 7S1 12 1 12z"/><circle cx="12" cy="12" r="3"/></svg>
                </button>
            </div>
        </div>

        <button type="submit" class="botao botao-primario login-entrar">Entrar</button>
    </form>
</main>

<script>
    /* Botão de tema (mesmo do topo do sistema) e botão de mostrar/esconder a senha. */
    (function () {
        var raiz = document.documentElement;
        var botao = document.getElementById('botao-tema');
        var texto = botao.querySelector('.botao-tema-texto');

        function atualizarBotao() {
            var escuro = raiz.getAttribute('data-tema') === 'escuro';
            texto.textContent = escuro ? 'Tema claro' : 'Tema escuro';
            botao.setAttribute('aria-label', escuro ? 'Mudar para o tema claro' : 'Mudar para o tema escuro');
        }
        botao.addEventListener('click', function () {
            var escuro = raiz.getAttribute('data-tema') !== 'escuro';
            if (escuro) { raiz.setAttribute('data-tema', 'escuro'); } else { raiz.removeAttribute('data-tema'); }
            try { localStorage.setItem('claudress-tema', escuro ? 'escuro' : 'claro'); } catch (e) { }
            atualizarBotao();
        });
        atualizarBotao();

        var senha = document.getElementById('senha');
        var ver = document.getElementById('ver-senha');
        ver.addEventListener('click', function () {
            var mostrar = senha.type === 'password';
            senha.type = mostrar ? 'text' : 'password';
            ver.setAttribute('aria-label', mostrar ? 'Esconder senha' : 'Mostrar senha');
            ver.title = mostrar ? 'Esconder senha' : 'Mostrar senha';
            ver.classList.toggle('ativo', mostrar);
            senha.focus();
        });
    })();
</script>
</body>
</html>
