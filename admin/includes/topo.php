<?php
/** topo.php - Barra superior com o título da página, o botão de tema, o botão Sair e o botão do menu (celular). */
?>
<header class="topo">
    <button type="button" class="topo-menu" id="botao-menu" aria-label="Abrir menu"><?= icone('menu', 22) ?></button>
    <h1 class="topo-titulo"><?= e($tituloPagina) ?></h1>
    <span class="topo-data"><?= date('d/m/Y') ?></span>
    <button type="button" class="botao-tema" id="botao-tema" title="Trocar entre tema claro e escuro">
        <svg class="icone icone-lua" width="18" height="18" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.8" stroke-linecap="round" stroke-linejoin="round" aria-hidden="true"><path d="M21 12.8A9 9 0 1 1 11.2 3a7 7 0 0 0 9.8 9.8z"/></svg>
        <svg class="icone icone-sol" width="18" height="18" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.8" stroke-linecap="round" stroke-linejoin="round" aria-hidden="true"><circle cx="12" cy="12" r="4"/><path d="M12 2v2M12 20v2M4.9 4.9l1.4 1.4M17.7 17.7l1.4 1.4M2 12h2M20 12h2M4.9 19.1l1.4-1.4M17.7 6.3l1.4-1.4"/></svg>
        <span class="botao-tema-texto">Tema escuro</span>
    </button>
    <form method="post" action="logout.php" class="form-sair">
        <input type="hidden" name="token" value="<?= e(tokenLogin()) ?>">
        <button type="submit" class="botao-sair" title="Sair do sistema (usuário <?= e($_SESSION['login_usuario'] ?? '') ?>)">
            <svg class="icone" width="18" height="18" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.8" stroke-linecap="round" stroke-linejoin="round" aria-hidden="true"><path d="M9 21H5a2 2 0 0 1-2-2V5a2 2 0 0 1 2-2h4"/><path d="M16 17l5-5-5-5"/><path d="M21 12H9"/></svg>
            <span class="botao-sair-texto">Sair</span>
        </button>
    </form>
</header>
<script>
    /* Botão de tema: alterna claro/escuro e lembra a escolha neste navegador. */
    (function () {
        var raiz = document.documentElement;
        var botao = document.getElementById('botao-tema');
        if (!botao) { return; }
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
    })();
</script>
