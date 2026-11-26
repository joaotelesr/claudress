<?php
/** topo.php - Barra superior com o título da página e o botão do menu (celular). */
?>
<header class="topo">
    <button type="button" class="topo-menu" id="botao-menu" aria-label="Abrir menu"><?= icone('menu', 22) ?></button>
    <h1 class="topo-titulo"><?= e($tituloPagina) ?></h1>
    <span class="topo-data"><?= date('d/m/Y') ?></span>
</header>
