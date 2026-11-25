<?php
/**
 * topo.php
 * Barra superior: botão do menu (celular), título da página e data.
 */
?>
<header class="topo">
    <button type="button" class="topo-botao-menu" data-abrir-menu aria-label="Abrir menu">
        <?= icone('menu', 22) ?>
    </button>

    <h1 class="topo-titulo"><?= e($tituloPagina) ?></h1>

    <div class="topo-info">
        <span class="topo-data"><?= date('d/m/Y') ?></span>
        <span class="topo-avatar" title="Administração"><?= e(mb_substr(NOME_LOJA, 0, 1)) ?></span>
    </div>
</header>