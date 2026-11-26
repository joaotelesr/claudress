<?php
/**
 * index.php - Painel (dashboard) com os números reais da loja
 */
require_once __DIR__ . '/../config/config.php';
require_once RAIZ_PROJETO . '/funcoes/produtos_funcoes.php';
require_once RAIZ_PROJETO . '/funcoes/clientes_funcoes.php';
require_once RAIZ_PROJETO . '/funcoes/vendas_funcoes.php';

$hoje      = date('Y-m-d');
$inicioMes = date('Y-m-01');

$vendasHoje = resumoPeriodoVendas($hoje, $hoje);
$vendasMes  = resumoPeriodoVendas($inicioMes, $hoje);
$ticketMedio = $vendasMes['QTD'] > 0 ? $vendasMes['TOTAL'] / $vendasMes['QTD'] : 0;

$estoque        = resumoEstoque();
$clientesAtivos = totalClientesAtivos();

$grafico  = vendasPorMes(6);
$maiorMes = max(1, max(array_column($grafico, 'total')));

$filtroUltimas = ['busca' => '', 'data_ini' => '', 'data_fim' => '', 'status' => STATUS_VENDA_FINALIZADA, 'pagamento' => ''];
$ultimasVendas = listarVendas($filtroUltimas, 6, 0);
$maisVendidos  = produtosMaisVendidos(date('Y-m-d', strtotime('-30 days')), 5);
$estoqueBaixo  = produtosEstoqueBaixo(5);

$tituloPagina = 'Painel';
$cssPagina    = ['dashboard.css', 'vendas.css'];

require __DIR__ . '/includes/cabecalho.php';
?>

<div class="cabecalho-pagina">
    <div>
        <h2>Olá! Veja como está a <?= e(NOME_LOJA) ?></h2>
        <p>Números calculados com as vendas finalizadas do banco da loja.</p>
    </div>
    <div class="acoes-rapidas">
        <a href="nova_venda.php" class="botao botao-primario"><?= icone('venda', 18) ?> Nova venda</a>
        <a href="produto_form.php" class="botao botao-secundario"><?= icone('mais', 18) ?> Produto</a>
        <a href="cliente_form.php" class="botao botao-secundario"><?= icone('mais', 18) ?> Cliente</a>
    </div>
</div>

<!-- Indicadores -->
<section class="grade-indicadores">
    <a class="indicador" href="vendas.php?data_ini=<?= e($inicioMes) ?>">
        <span class="indicador-icone"><?= icone('pagamento', 24) ?></span>
        <span>
            <span class="indicador-rotulo">Vendido em <?= e(rotuloMes(date('Y-m'))) ?></span>
            <strong class="indicador-valor"><?= formatarMoeda($vendasMes['TOTAL']) ?></strong>
            <span class="indicador-detalhe"><?= (int) $vendasMes['QTD'] ?> vendas · ticket médio <?= formatarMoeda($ticketMedio) ?></span>
        </span>
    </a>
    <a class="indicador" href="vendas.php?data_ini=<?= e($hoje) ?>&amp;data_fim=<?= e($hoje) ?>">
        <span class="indicador-icone"><?= icone('venda', 24) ?></span>
        <span>
            <span class="indicador-rotulo">Vendido hoje</span>
            <strong class="indicador-valor"><?= formatarMoeda($vendasHoje['TOTAL']) ?></strong>
            <span class="indicador-detalhe"><?= (int) $vendasHoje['QTD'] ?> vendas · <?= e(formatarQuantidade($vendasHoje['PECAS'])) ?> peças</span>
        </span>
    </a>
    <a class="indicador" href="clientes.php">
        <span class="indicador-icone"><?= icone('clientes', 24) ?></span>
        <span>
            <span class="indicador-rotulo">Clientes ativos</span>
            <strong class="indicador-valor"><?= number_format($clientesAtivos, 0, ',', '.') ?></strong>
            <span class="indicador-detalhe">cadastrados no sistema</span>
        </span>
    </a>
    <a class="indicador" href="produtos.php?estoque=com">
        <span class="indicador-icone"><?= icone('produtos', 24) ?></span>
        <span>
            <span class="indicador-rotulo">Produtos em estoque</span>
            <strong class="indicador-valor"><?= number_format((int) $estoque['COM_ESTOQUE'], 0, ',', '.') ?></strong>
            <span class="indicador-detalhe"><?= e(formatarQuantidade($estoque['PECAS'])) ?> peças · <?= formatarMoeda($estoque['VALOR_VENDA']) ?> a preço de venda</span>
        </span>
    </a>
</section>

<div class="dashboard-colunas">

    <div class="coluna-pilha">
        <!-- Vendas por mês -->
        <section class="cartao">
            <div class="cartao-titulo">
                <h2>Vendas por mês</h2>
                <span class="texto-suave">últimos 6 meses</span>
            </div>
            <div class="grafico-barras">
                <?php foreach ($grafico as $mes): ?>
                    <?php $altura = $mes['total'] > 0 ? max(4, (int) round($mes['total'] * 150 / $maiorMes)) : 2; ?>
                    <div class="barra-coluna" title="<?= e(rotuloMes($mes['mes']) . ': ' . formatarMoeda($mes['total']) . ' em ' . $mes['qtd'] . ' vendas') ?>">
                        <span class="barra-valor"><?= e(formatarMoedaCurta($mes['total'])) ?></span>
                        <div class="barra" style="height: <?= $altura ?>px"></div>
                        <span class="barra-rotulo"><?= e(rotuloMes($mes['mes'])) ?></span>
                    </div>
                <?php endforeach; ?>
            </div>
        </section>

        <!-- Últimas vendas -->
        <section class="cartao">
            <div class="cartao-titulo">
                <h2>Últimas vendas</h2>
                <a href="vendas.php" class="botao botao-secundario botao-pequeno">Ver todas</a>
            </div>

            <?php if (empty($ultimasVendas)): ?>
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
                            <?php foreach ($ultimasVendas as $venda): ?>
                                <tr>
                                    <td><a href="venda_detalhe.php?id=<?= (int) $venda['CODIGO_VENDA'] ?>">#<?= (int) $venda['CODIGO_VENDA'] ?></a></td>
                                    <td><?= $venda['CLIENTE_NOME'] ? e($venda['CLIENTE_NOME']) : '<span class="texto-suave">Sem cadastro</span>' ?></td>
                                    <td><?= formatarData($venda['DATA']) ?></td>
                                    <td>
                                        <?php foreach ($venda['FORMAS'] as $forma): ?>
                                            <span class="badge badge-info"><?= e($forma) ?></span>
                                        <?php endforeach; ?>
                                    </td>
                                    <td class="texto-direita"><strong><?= formatarMoeda($venda['TOTAL']) ?></strong></td>
                                </tr>
                            <?php endforeach; ?>
                        </tbody>
                    </table>
                </div>
            <?php endif; ?>
        </section>
    </div>

    <div class="coluna-pilha">
        <!-- Mais vendidos -->
        <section class="cartao">
            <div class="cartao-titulo">
                <h2>Mais vendidos</h2>
                <span class="texto-suave">últimos 30 dias</span>
            </div>
            <?php if (empty($maisVendidos)): ?>
                <p class="estado-vazio">Sem vendas no período.</p>
            <?php else: ?>
                <ul class="lista-recentes">
                    <?php foreach ($maisVendidos as $item): ?>
                        <li>
                            <div>
                                <strong><?= e(trim((string) $item['DESCRICAO'])) ?></strong>
                                <small><?= e(formatarQuantidade($item['QTD'])) ?> un. vendidas</small>
                            </div>
                            <strong><?= formatarMoeda($item['TOTAL']) ?></strong>
                        </li>
                    <?php endforeach; ?>
                </ul>
            <?php endif; ?>
        </section>

        <!-- Estoque baixo -->
        <section class="cartao">
            <div class="cartao-titulo">
                <h2>Estoque baixo</h2>
                <a href="produtos.php?estoque=com" class="botao botao-secundario botao-pequeno">Ver estoque</a>
            </div>
            <?php if (empty($estoqueBaixo)): ?>
                <p class="estado-vazio">Nenhum produto com estoque baixo.</p>
            <?php else: ?>
                <ul class="lista-recentes">
                    <?php foreach ($estoqueBaixo as $p): ?>
                        <li>
                            <div>
                                <strong><?= e(trim((string) $p['DESCRICAO'])) ?></strong>
                                <small>Tamanho <?= e($p['TAMANHO'] ?: '-') ?></small>
                            </div>
                            <?= badgeEstoque($p['QUANT']) ?>
                        </li>
                    <?php endforeach; ?>
                </ul>
            <?php endif; ?>
        </section>
    </div>

</div>

<?php require __DIR__ . '/includes/rodape.php'; ?>
