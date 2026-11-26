<?php
/**
 * sidebar.php
 * Menu lateral. Para adicionar uma página ao menu, basta incluir uma linha em $menu.
 * Formato: [arquivo, rótulo, ícone, [páginas que deixam o item marcado como ativo]]
 */
$menu = [
    ['index.php',      'Painel',               'painel',    ['index.php']],
    ['produtos.php',   'Produtos',             'produtos',  ['produtos.php', 'produto_form.php']],
    ['clientes.php',   'Clientes',             'clientes',  ['clientes.php', 'cliente_form.php']],
    ['nova_venda.php', 'Nova venda',           'venda',     ['nova_venda.php']],
    ['vendas.php',     'Histórico de vendas',  'historico', ['vendas.php', 'venda_detalhe.php']],
    ['pagamentos.php', 'Formas de pagamento',  'pagamento', ['pagamentos.php']],
];
?>
<aside class="sidebar" id="sidebar">
    <div class="sidebar-logo">
        <span class="sidebar-logo-nome"><?= e(NOME_LOJA) ?></span>
        <span class="sidebar-logo-sub">Gestão de vendas</span>
    </div>

    <nav class="sidebar-menu" aria-label="Menu principal">
        <?php foreach ($menu as [$arquivo, $rotulo, $nomeIcone, $paginas]): ?>
            <a class="sidebar-link<?= classeAtiva($paginas) ?>" href="<?= e($arquivo) ?>">
                <?= icone($nomeIcone) ?>
                <span><?= e($rotulo) ?></span>
            </a>
        <?php endforeach; ?>
    </nav>

    <div class="sidebar-rodape">
        <span class="selo-demo">Banco: <?= e(DB_NOME) ?></span>
    </div>
</aside>
