<?php
/**
 * index.php - Dashboard (painel inicial)
 */
require_once __DIR__ . '/../config/config.php';
require_once RAIZ_PROJETO . '/funcoes/produtos_funcoes.php';
require_once RAIZ_PROJETO . '/funcoes/vendas_funcoes.php';

$totalProdutos = count(repoListar('produtos'));
$totalClientes = count(repoListar('clientes'));
$totalVendas   = count(repoListar('vendas'));
$valorVendido  = totalVendido();

$vendasRecentes   = ultimasVendas(5);
$produtosRecentes = produtosRecentes(5);

$tituloPagina = 'Painel';
$cssPagina    = ['dashboard.css'];

require __DIR__ . '/includes/cabecalho.php';
?>

<div class="cabecalho-pagina">
    <div>
        <h2>Olá! Veja como está a <?= e(NOME_LOJA) ?> hoje</h2>
        <p>Os números abaixo usam os dados de demonstração.</p>
    </div>
    <div class="acoes-rapidas">
        <a href="nova_venda.php" class="botao botao-primario"><?= icone('venda', 18) ?> Nova venda</a>
        <a href="produto_form.php" class="botao botao-secundario"><?= icone('mais', 18) ?> Produto</a>
        <a href="cliente_form.php" class="botao botao-secundario"><?= icone('mais', 18) ?> Cliente</a>
    </div>
</div>

<!-- Indicadores -->
<section class="grade-indicadores">
    <a class="indicador" href="produtos.php">
        <span class="indicador-icone"><?= icone('produtos', 24) ?></span>
        <span>
            <span class="indicador-rotulo">Produtos cadastrados</span>
            <strong class="indicador-valor"><?= $totalProdutos ?></strong>
        </span>
    </a>
    <a class="indicador" href="clientes.php">
        <span class="indicador-icone"><?= icone('clientes', 24) ?></span>
        <span>
            <span class="indicador-rotulo">Clientes cadastrados</span>
            <strong class="indicador-valor"><?= $totalClientes ?></strong>
        </span>
    </a>
    <a class="indicador" href="vendas.php">
        <span class="indicador-icone"><?= icone('venda', 24) ?></span>
        <span>
            <span class="indicador-rotulo">Vendas realizadas</span>
            <strong class="indicador-valor"><?= $totalVendas ?></strong>
        </span>
    </a>
    <a class="indicador" href="vendas.php">
        <span class="indicador-icone"><?= icone('pagamento', 24) ?></span>
        <span>
            <span class="indicador-rotulo">Valor total vendido</span>
            <strong class="indicador-valor"><?= formatarMoeda($valorVendido) ?></strong>
        </span>
    </a>
</section>

<div class="dashboard-colunas">

    <!-- Últimas vendas -->
    <section class="cartao">
        <div class="cartao-titulo">
            <h2>Últimas vendas</h2>
            <a href="vendas.php" class="botao botao-secundario botao-pequeno">Ver todas</a>
        </div>

        <?php if (empty($vendasRecentes)): ?>
            <p class="estado-vazio">Nenhuma venda registrada ainda.</p>
        <?php else: ?>
            <div class="tabela-container">
                <table class="tabela">
                    <thead>
                        <tr>
                            <th>Venda</th>
                            <th>Cliente</th>
                            <th>Data</th>
                            <th>Pagamento</th>
                            <th class="texto-direita">Total</th>
                        </tr>
                    </thead>
                    <tbody>
                        <?php foreach ($vendasRecentes as $venda): ?>
                            <tr>
                                <td><a href="venda_detalhe.php?id=<?= (int) $venda['id'] ?>">#<?= (int) $venda['id'] ?></a></td>
                                <td><?= e(nomeCliente((int) $venda['cliente_id'])) ?></td>
                                <td><?= formatarData($venda['data']) ?></td>
                                <td><span class="badge badge-info"><?= e(nomeFormaPagamento((int) $venda['forma_pagamento_id'])) ?></span></td>
                                <td class="texto-direita"><strong><?= formatarMoeda($venda['total']) ?></strong></td>
                            </tr>
                        <?php endforeach; ?>
                    </tbody>
                </table>
            </div>
        <?php endif; ?>
    </section>

    <!-- Produtos recentes -->
    <section class="cartao">
        <div class="cartao-titulo">
            <h2>Produtos recentes</h2>
            <a href="produtos.php" class="botao botao-secundario botao-pequeno">Ver todos</a>
        </div>

        <?php if (empty($produtosRecentes)): ?>
            <p class="estado-vazio">Nenhum produto cadastrado ainda.</p>
        <?php else: ?>
            <ul class="lista-recentes">
                <?php foreach ($produtosRecentes as $produto): ?>
                    <li>
                        <div>
                            <strong><?= e($produto['nome']) ?></strong>
                            <small><?= e($produto['categoria']) ?> · <?= formatarData($produto['criado_em']) ?></small>
                        </div>
                        <strong><?= formatarMoeda($produto['preco_venda']) ?></strong>
                    </li>
                <?php endforeach; ?>
            </ul>
        <?php endif; ?>
    </section>

</div>

<?php require __DIR__ . '/includes/rodape.php'; ?>