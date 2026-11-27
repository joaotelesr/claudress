<?php
/**
 * vendas.php - Histórico de vendas (VENDAS) com filtros e totais
 */
require_once __DIR__ . '/../config/config.php';
require_once RAIZ_PROJETO . '/funcoes/vendas_funcoes.php';

$filtros = filtrosVendas($_GET);
$resumo  = resumoListaVendas($filtros);
$pg      = paginar((int) $resumo['QTD'], ITENS_POR_PAGINA, (int) ($_GET['pagina'] ?? 1));
$vendas  = listarVendas($filtros, $pg['por_pagina'], $pg['offset']);
$status  = listarStatusVendas();
$tipos   = listarTiposPagamento();

$tituloPagina = 'Histórico de vendas';
$cssPagina    = ['cadastros.css', 'paginacao.css', 'vendas.css'];

require __DIR__ . '/includes/cabecalho.php';
?>

<div class="cabecalho-pagina">
    <p><?= number_format((int) $resumo['QTD'], 0, ',', '.') ?> venda(s) · finalizadas somam <strong><?= formatarMoeda($resumo['TOTAL']) ?></strong></p>
    <a href="nova_venda.php" class="botao botao-primario"><?= icone('venda', 18) ?> Nova venda</a>
</div>

<div class="cartao">
    <form method="get" class="barra-filtros">
        <div class="campo">
            <label for="busca">Cliente ou nº da venda</label>
            <input type="search" id="busca" name="busca" value="<?= e($filtros['busca']) ?>">
        </div>
        <div class="campo">
            <label for="data_ini">De</label>
            <input type="date" id="data_ini" name="data_ini" value="<?= e($filtros['data_ini']) ?>">
        </div>
        <div class="campo">
            <label for="data_fim">Até</label>
            <input type="date" id="data_fim" name="data_fim" value="<?= e($filtros['data_fim']) ?>">
        </div>
        <div class="campo">
            <label for="status">Situação</label>
            <select id="status" name="status">
                <option value="">Todas</option>
                <?php foreach ($status as $s): ?>
                    <option value="<?= e($s) ?>" <?= $filtros['status'] === $s ? 'selected' : '' ?>><?= e($s) ?></option>
                <?php endforeach; ?>
            </select>
        </div>
        <div class="campo">
            <label for="pagamento">Pagamento</label>
            <select id="pagamento" name="pagamento">
                <option value="">Todos</option>
                <?php foreach ($tipos as $t): ?>
                    <option value="<?= e($t) ?>" <?= $filtros['pagamento'] === $t ? 'selected' : '' ?>><?= e(rotuloFormaPagamento($t)) ?></option>
                <?php endforeach; ?>
            </select>
        </div>
        <div class="campo">
            <label for="ordem">Ordem</label>
            <select id="ordem" name="ordem">
                <?php foreach (ORDENS_VENDAS as $valor => $rotulo): ?>
                    <option value="<?= e($valor) ?>" <?= $filtros['ordem'] === $valor ? 'selected' : '' ?>><?= e($rotulo) ?></option>
                <?php endforeach; ?>
            </select>
        </div>
        <div class="barra-filtros-botoes">
            <button type="submit" class="botao botao-primario"><?= icone('busca', 16) ?> Filtrar</button>
            <a href="vendas.php" class="botao botao-secundario">Limpar</a>
        </div>
    </form>

    <?php if (empty($vendas)): ?>
        <p class="estado-vazio">Nenhuma venda encontrada.</p>
    <?php else: ?>
        <div class="tabela-container">
            <table class="tabela">
                <thead>
                    <tr>
                        <th>Venda</th><th>Data</th><th>Cliente</th><th>Peças</th><th>Pagamento</th><th>Situação</th>
                        <th class="texto-direita">Total</th><th></th>
                    </tr>
                </thead>
                <tbody>
                    <?php foreach ($vendas as $v): ?>
                        <tr>
                            <td>#<?= (int) $v['CODIGO_VENDA'] ?></td>
                            <td><?= formatarData($v['DATA']) ?> <small class="texto-suave"><?= e(formatarHora($v['HORA'])) ?></small></td>
                            <td><?= $v['CLIENTE_NOME'] ? e(trim($v['CLIENTE_NOME'])) : '<span class="texto-suave">Sem cadastro</span>' ?></td>
                            <td><?= e(formatarQuantidade($v['NUM_PECAS'])) ?></td>
                            <td>
                                <?php foreach ($v['FORMAS'] as $forma): ?>
                                    <span class="badge badge-info"><?= e($forma) ?></span>
                                <?php endforeach; ?>
                            </td>
                            <td><?= badgeStatus($v['STATUS_VENDA']) ?></td>
                            <td class="texto-direita"><strong><?= formatarMoeda($v['TOTAL']) ?></strong></td>
                            <td class="texto-direita">
                                <a href="venda_detalhe.php?id=<?= (int) $v['CODIGO_VENDA'] ?>" class="botao botao-secundario botao-pequeno">Detalhes</a>
                            </td>
                        </tr>
                    <?php endforeach; ?>
                </tbody>
            </table>
        </div>
        <?php renderPaginacao($pg, $filtros); ?>
    <?php endif; ?>
</div>

<?php require __DIR__ . '/includes/rodape.php'; ?>
