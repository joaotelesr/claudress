<?php
/**
 * relatorios.php - Relatórios de vendas: evolução no período escolhido (sem limite de 6 meses),
 * vendas por estado (UF do cadastro da cliente), vendas por cidade do estado escolhido (com filtro de cidade)
 * vendas por vendedor (com a divisão por estado) e produtos mais vendidos.
 * Filtros: período, estado, cidade e vendedor (o vendedor é o gravado em cada peça da venda).
 * Só vendas 'Finalizado' entram. Devoluções em dinheiro aparecem à parte (coluna "Devolvido").
 */
require_once __DIR__ . '/../config/config.php';
require_once RAIZ_PROJETO . '/funcoes/relatorios_funcoes.php';

$f = filtrosRelatorio($_GET);

$vend = $f['vendedor'];

$resumo   = resumoRelatorio($f['data_ini'], $f['data_fim'], $f['uf'], $f['cidade'], $vend);
$anterior = periodoAnterior($f['data_ini'], $f['data_fim']);
$resumoAnt = resumoRelatorio($anterior['ini'], $anterior['fim'], $f['uf'], $f['cidade'], $vend);
$variacao = variacaoPercentual($resumo['total'], $resumoAnt['total']);

$evolucao = evolucaoVendas($f['data_ini'], $f['data_fim'], $f['agrupar'], $f['uf'], $f['cidade'], $vend);
$estados  = vendasPorEstado($f['data_ini'], $f['data_fim'], $vend);

// Todas as cidades do estado escolhido (da que mais vendeu para a que menos vendeu)
$cidades = $f['uf'] !== '' ? vendasPorCidade($f['data_ini'], $f['data_fim'], $f['uf'], 0, $vend) : [];

// Ranking de vendedores (respeita estado e cidade; mostra todos os vendedores para comparar)
$vendedores = vendasPorVendedor($f['data_ini'], $f['data_fim'], $f['uf'], $f['cidade']);

if (($_GET['exportar'] ?? '') === 'csv') {
    exportarRelatorioCsv($f, $evolucao, $estados, $cidades, $vendedores);
}

$produtos = produtosMaisVendidosPeriodo($f['data_ini'], $f['data_fim'], $f['uf'], 10, $f['cidade'], $vend);

// Vendedores para o filtro: os que venderam no período + os ativos do cadastro + o escolhido
$opcoesVendedor = [];
foreach ($vendedores as $vd) {
    if ($vd['codigo'] > 0) {
        $opcoesVendedor[$vd['codigo']] = $vd['nome'];
    }
}
foreach (listarVendedores() as $vd) {
    $opcoesVendedor[(int) $vd['CODIGO']] = $opcoesVendedor[(int) $vd['CODIGO']] ?? (string) $vd['NOME'];
}
if ($vend > 0 && !isset($opcoesVendedor[$vend])) {
    $opcoesVendedor[$vend] = nomeVendedor($vend);
}
asort($opcoesVendedor);
$nomeVendedorFiltro = $vend > 0 ? $opcoesVendedor[$vend] : '';

// Cidades para o filtro: as que venderam no período + a escolhida
$opcoesCidade = array_column($cidades, 'total', 'cidade');
if ($f['cidade'] !== '' && !isset($opcoesCidade[$f['cidade']])) {
    $opcoesCidade[$f['cidade']] = 0;
}
$localFiltrado = rotuloLocalRelatorio($f['uf'], $f['cidade']);

$maiorPonto  = max(1, max(array_column($evolucao, 'total') ?: [0]));
$melhorPonto = null;
foreach ($evolucao as $p) {
    if ($p['total'] > 0 && ($melhorPonto === null || $p['total'] > $melhorPonto['total'])) {
        $melhorPonto = $p;
    }
}
$pontosComVenda = count(array_filter($evolucao, fn($p) => $p['qtd'] > 0));
$mediaPorPonto  = count($evolucao) > 0 ? $resumo['total'] / count($evolucao) : 0;
$nomeAgrupamento = ['dia' => 'dia', 'mes' => 'mês', 'ano' => 'ano'][$f['agrupar']];

// Estados para o filtro: os que venderam no período + o escolhido
$opcoesUf = [];
foreach ($estados as $e) {
    $opcoesUf[$e['uf']] = $e['nome'];
}
if ($f['uf'] !== '' && !isset($opcoesUf[$f['uf']])) {
    $opcoesUf[$f['uf']] = nomeEstado($f['uf']);
}

/** Endereço desta tela trocando só alguns filtros. */
$urlRelatorio = function (array $troca) use ($f): string {
    $base = ['data_ini' => $f['data_ini'], 'data_fim' => $f['data_fim'], 'agrupar' => $f['agrupar'], 'uf' => $f['uf'], 'cidade' => $f['cidade'],
             'vendedor' => $f['vendedor'] ?: ''];
    $q    = array_filter(array_merge($base, $troca), fn($v) => $v !== '' && $v !== null);
    return 'relatorios.php?' . http_build_query($q);
};
/** Link para o histórico com as vendas finalizadas do período. */
$urlHistorico = fn(string $ini, string $fim): string => 'vendas.php?' . http_build_query(
    ['data_ini' => $ini, 'data_fim' => $fim, 'status' => STATUS_VENDA_FINALIZADA, 'ordem' => 'antigas']
);

$tituloPagina = 'Relatórios de vendas';
$cssPagina    = ['dashboard.css', 'vendas.css', 'relatorios.css'];

require __DIR__ . '/includes/cabecalho.php';
?>

<div class="cabecalho-pagina">
    <div>
        <h2>
            <?= formatarData($f['data_ini']) ?> a <?= formatarData($f['data_fim']) ?>
            <?php if ($f['uf'] !== ''): ?>
                · <?= e($localFiltrado) ?>
            <?php endif; ?>
            <?php if ($vend > 0): ?>
                · vendedor <?= e($nomeVendedorFiltro) ?>
            <?php endif; ?>
        </h2>
        <p>Somente vendas finalizadas. Devoluções em dinheiro aparecem à parte e são descontadas no líquido.</p>
    </div>
    <a href="<?= e($urlRelatorio(['exportar' => 'csv'])) ?>" class="botao botao-secundario"><?= icone('extrato', 18) ?> Baixar planilha (CSV)</a>
</div>

<!-- Filtros -->
<div class="cartao">
    <div class="atalhos-periodo">
        <?php foreach (ATALHOS_RELATORIO as $chave => $rotulo): ?>
            <a href="<?= e('relatorios.php?' . http_build_query(array_filter(['atalho' => $chave, 'uf' => $f['uf'], 'cidade' => $f['cidade'], 'vendedor' => $f['vendedor']]))) ?>"
               class="botao botao-pequeno <?= $f['atalho'] === $chave ? 'botao-primario' : 'botao-secundario' ?>"><?= e($rotulo) ?></a>
        <?php endforeach; ?>
    </div>

    <form method="get" class="barra-filtros barra-filtros-relatorio">
        <div class="campo">
            <label for="data_ini">De</label>
            <input type="date" id="data_ini" name="data_ini" value="<?= e($f['data_ini']) ?>">
        </div>
        <div class="campo">
            <label for="data_fim">Até</label>
            <input type="date" id="data_fim" name="data_fim" value="<?= e($f['data_fim']) ?>">
        </div>
        <div class="campo">
            <label for="agrupar">Gráfico</label>
            <select id="agrupar" name="agrupar">
                <?php foreach (AGRUPAMENTOS_RELATORIO as $valor => $rotulo): ?>
                    <option value="<?= e($valor) ?>" <?= $f['agrupar'] === $valor ? 'selected' : '' ?>><?= e($rotulo) ?></option>
                <?php endforeach; ?>
            </select>
        </div>
        <div class="campo">
            <label for="uf">Estado</label>
            <select id="uf" name="uf" onchange="var c = document.getElementById('cidade'); if (c) { c.value = ''; }">
                <option value="">Todos os estados</option>
                <?php foreach ($opcoesUf as $sigla => $nome): ?>
                    <option value="<?= e($sigla) ?>" <?= $f['uf'] === $sigla ? 'selected' : '' ?>>
                        <?= e($sigla === UF_SEM_ESTADO ? $nome : $sigla . ' - ' . $nome) ?>
                    </option>
                <?php endforeach; ?>
            </select>
        </div>
        <?php if ($f['uf'] !== ''): ?>
            <div class="campo">
                <label for="cidade">Cidade</label>
                <select id="cidade" name="cidade">
                    <option value="">Todas as cidades</option>
                    <?php foreach ($opcoesCidade as $nomeCidade => $totalCidade): ?>
                        <option value="<?= e($nomeCidade) ?>" <?= $f['cidade'] === (string) $nomeCidade ? 'selected' : '' ?>>
                            <?= e($nomeCidade) ?><?= $totalCidade > 0 ? ' · ' . e(formatarMoedaCurta($totalCidade)) : '' ?>
                        </option>
                    <?php endforeach; ?>
                </select>
            </div>
        <?php endif; ?>
        <div class="campo">
            <label for="vendedor">Vendedor</label>
            <select id="vendedor" name="vendedor">
                <option value="">Todos os vendedores</option>
                <?php foreach ($opcoesVendedor as $codVend => $nomeVend): ?>
                    <option value="<?= (int) $codVend ?>" <?= $vend === (int) $codVend ? 'selected' : '' ?>><?= e($nomeVend) ?></option>
                <?php endforeach; ?>
            </select>
        </div>
        <div class="barra-filtros-botoes">
            <button type="submit" class="botao botao-primario"><?= icone('busca', 16) ?> Filtrar</button>
            <a href="relatorios.php" class="botao botao-secundario">Limpar</a>
        </div>
    </form>

    <?php if ($f['agrupar_ajustado']): ?>
        <p class="texto-suave aviso-relatorio">O período é longo demais para ver dia a dia, então o gráfico foi agrupado por <?= e($nomeAgrupamento) ?>.</p>
    <?php endif; ?>
</div>

<!-- Indicadores -->
<section class="grade-indicadores">
    <div class="indicador">
        <span class="indicador-icone"><?= icone('pagamento', 24) ?></span>
        <span>
            <span class="indicador-rotulo">Vendido no período</span>
            <strong class="indicador-valor"><?= formatarMoeda($resumo['total']) ?></strong>
            <span class="indicador-detalhe">
                <?php if ($variacao === null): ?>
                    sem vendas no período anterior para comparar
                <?php else: ?>
                    <span class="<?= $variacao >= 0 ? 'variacao-alta' : 'variacao-baixa' ?>">
                        <?= $variacao >= 0 ? '▲' : '▼' ?> <?= number_format(abs($variacao), 1, ',', '.') ?>%
                    </span>
                    vs <?= formatarData($anterior['ini']) ?> a <?= formatarData($anterior['fim']) ?> (<?= formatarMoeda($resumoAnt['total']) ?>)
                <?php endif; ?>
            </span>
        </span>
    </div>
    <div class="indicador">
        <span class="indicador-icone"><?= icone('venda', 24) ?></span>
        <span>
            <span class="indicador-rotulo">Vendas</span>
            <strong class="indicador-valor"><?= number_format($resumo['qtd'], 0, ',', '.') ?></strong>
            <span class="indicador-detalhe">ticket médio <?= formatarMoeda($resumo['ticket']) ?> · <?= e(formatarQuantidade($resumo['pecas'])) ?> peças</span>
        </span>
    </div>
    <div class="indicador">
        <span class="indicador-icone"><?= icone('clientes', 24) ?></span>
        <span>
            <span class="indicador-rotulo">Clientes que compraram</span>
            <strong class="indicador-valor"><?= number_format($resumo['clientes'], 0, ',', '.') ?></strong>
            <span class="indicador-detalhe">descontos dados: <?= formatarMoeda($resumo['desconto']) ?></span>
        </span>
    </div>
    <div class="indicador">
        <span class="indicador-icone"><?= icone('conciliacao', 24) ?></span>
        <span>
            <span class="indicador-rotulo">Líquido (vendido - devolvido)</span>
            <strong class="indicador-valor"><?= formatarMoeda($resumo['liquido']) ?></strong>
            <span class="indicador-detalhe">devoluções em dinheiro: <?= formatarMoeda($resumo['devolvido']) ?></span>
        </span>
    </div>
</section>

<!-- Evolução -->
<section class="cartao">
    <div class="cartao-titulo">
        <h2>Vendas <?= e(mb_strtolower(AGRUPAMENTOS_RELATORIO[$f['agrupar']])) ?></h2>
        <span class="texto-suave">
            <?php if ($melhorPonto !== null): ?>
                melhor <?= e($nomeAgrupamento) ?>: <strong><?= e($melhorPonto['rotulo']) ?></strong> (<?= formatarMoeda($melhorPonto['total']) ?>)
                · média por <?= e($nomeAgrupamento) ?>: <?= formatarMoeda($mediaPorPonto) ?>
            <?php else: ?>
                nenhuma venda no período
            <?php endif; ?>
        </span>
    </div>

    <div class="grafico-rolagem">
        <div class="grafico-barras grafico-relatorio<?= count($evolucao) > 24 ? ' grafico-denso' : '' ?>"
             style="min-width: <?= max(100, count($evolucao) * (count($evolucao) > 24 ? 22 : 56)) ?>px">
            <?php foreach ($evolucao as $p): ?>
                <?php $altura = $p['total'] > 0 ? max(4, (int) round($p['total'] * 180 / $maiorPonto)) : 2; ?>
                <a class="barra-coluna" href="<?= e($urlHistorico($p['ini'], $p['fim'])) ?>"
                   title="<?= e($p['rotulo'] . ': ' . formatarMoeda($p['total']) . ' em ' . $p['qtd'] . ' vendas · ticket ' . formatarMoeda($p['ticket'])
                        . ($p['devolvido'] > 0 ? ' · devolvido ' . formatarMoeda($p['devolvido']) : '')) ?>">
                    <span class="barra-valor"><?= $p['total'] > 0 ? e(formatarMoedaCurta($p['total'])) : '' ?></span>
                    <div class="barra" style="height: <?= $altura ?>px"></div>
                    <span class="barra-rotulo"><?= e($p['rotulo']) ?></span>
                </a>
            <?php endforeach; ?>
        </div>
    </div>
    <p class="texto-suave dica-grafico">Passe o mouse numa barra para ver os detalhes; clique para abrir as vendas daquele <?= e($nomeAgrupamento) ?>.</p>

    <details class="detalhe-tabela" <?= count($evolucao) <= 24 ? 'open' : '' ?>>
        <summary>Tabela detalhada (<?= count($evolucao) ?> <?= $f['agrupar'] === 'mes' ? 'meses' : ($f['agrupar'] === 'ano' ? 'anos' : 'dias') ?>, <?= $pontosComVenda ?> com venda)</summary>
        <div class="tabela-container">
            <table class="tabela">
                <thead>
                    <tr>
                        <th>Período</th>
                        <th class="texto-direita">Vendas</th>
                        <th class="texto-direita">Peças</th>
                        <th class="texto-direita">Vendido</th>
                        <th class="texto-direita">Ticket médio</th>
                        <th class="texto-direita">Devolvido</th>
                        <th class="texto-direita">Líquido</th>
                        <th class="texto-direita">Vs anterior</th>
                    </tr>
                </thead>
                <tbody>
                    <?php $antes = null; ?>
                    <?php foreach ($evolucao as $p): ?>
                        <?php $var = $antes === null ? null : variacaoPercentual($p['total'], $antes); $antes = $p['total']; ?>
                        <tr>
                            <td><a href="<?= e($urlHistorico($p['ini'], $p['fim'])) ?>"><?= e($p['rotulo']) ?></a></td>
                            <td class="texto-direita"><?= number_format($p['qtd'], 0, ',', '.') ?></td>
                            <td class="texto-direita"><?= e(formatarQuantidade($p['pecas'])) ?></td>
                            <td class="texto-direita"><strong><?= formatarMoeda($p['total']) ?></strong></td>
                            <td class="texto-direita"><?= formatarMoeda($p['ticket']) ?></td>
                            <td class="texto-direita"><?= $p['devolvido'] > 0 ? formatarMoeda($p['devolvido']) : '<span class="texto-suave">-</span>' ?></td>
                            <td class="texto-direita"><?= formatarMoeda($p['liquido']) ?></td>
                            <td class="texto-direita">
                                <?php if ($var === null): ?>
                                    <span class="texto-suave">-</span>
                                <?php else: ?>
                                    <span class="<?= $var >= 0 ? 'variacao-alta' : 'variacao-baixa' ?>"><?= $var >= 0 ? '+' : '' ?><?= number_format($var, 1, ',', '.') ?>%</span>
                                <?php endif; ?>
                            </td>
                        </tr>
                    <?php endforeach; ?>
                </tbody>
                <tfoot>
                    <tr>
                        <th>Total</th>
                        <th class="texto-direita"><?= number_format($resumo['qtd'], 0, ',', '.') ?></th>
                        <th class="texto-direita"><?= e(formatarQuantidade($resumo['pecas'])) ?></th>
                        <th class="texto-direita"><?= formatarMoeda($resumo['total']) ?></th>
                        <th class="texto-direita"><?= formatarMoeda($resumo['ticket']) ?></th>
                        <th class="texto-direita"><?= formatarMoeda($resumo['devolvido']) ?></th>
                        <th class="texto-direita"><?= formatarMoeda($resumo['liquido']) ?></th>
                        <th></th>
                    </tr>
                </tfoot>
            </table>
        </div>
    </details>
</section>

<!-- Por estado -->
<section class="cartao">
        <div class="cartao-titulo">
            <h2>Vendas por estado<?= $vend > 0 ? ' · ' . e($nomeVendedorFiltro) : '' ?></h2>
            <span class="texto-suave">pelo estado (UF) do cadastro da cliente<?= $vend > 0 ? ' · só as vendas deste vendedor' : '' ?></span>
        </div>

        <?php if (empty($estados)): ?>
            <p class="estado-vazio">Nenhuma venda no período.</p>
        <?php else: ?>
            <div class="tabela-container">
                <table class="tabela tabela-estados">
                    <thead>
                        <tr>
                            <th>#</th>
                            <th>Estado</th>
                            <th class="texto-direita">Vendas</th>
                            <th class="texto-direita">Clientes</th>
                            <th class="texto-direita">Vendido</th>
                            <th>Participação</th>
                            <th class="texto-direita">Ticket médio</th>
                            <th class="texto-direita">Líquido</th>
                        </tr>
                    </thead>
                    <tbody>
                        <?php foreach ($estados as $i => $e): ?>
                            <tr class="<?= $f['uf'] === $e['uf'] ? 'linha-selecionada' : '' ?>">
                                <td class="texto-suave"><?= $i + 1 ?></td>
                                <td class="celula-nome">
                                    <a href="<?= e($urlRelatorio(['uf' => $e['uf'], 'cidade' => ''])) ?>">
                                        <strong><?= e($e['uf'] === UF_SEM_ESTADO ? $e['nome'] : $e['uf'] . ' - ' . $e['nome']) ?></strong>
                                    </a>
                                </td>
                                <td class="texto-direita"><?= number_format($e['qtd'], 0, ',', '.') ?></td>
                                <td class="texto-direita"><?= number_format($e['clientes'], 0, ',', '.') ?></td>
                                <td class="texto-direita"><strong><?= formatarMoeda($e['total']) ?></strong></td>
                                <td>
                                    <div class="barra-progresso" title="<?= number_format($e['percentual'], 1, ',', '.') ?>%">
                                        <span style="width: <?= max(0, min(100, round($e['percentual']))) ?>%"></span>
                                    </div>
                                    <small><?= number_format($e['percentual'], 1, ',', '.') ?>%</small>
                                </td>
                                <td class="texto-direita"><?= formatarMoeda($e['ticket']) ?></td>
                                <td class="texto-direita"><?= formatarMoeda($e['liquido']) ?></td>
                            </tr>
                        <?php endforeach; ?>
                    </tbody>
                </table>
            </div>
            <p class="texto-suave dica-grafico">Clique num estado para ver o gráfico, as cidades e os produtos só dele.
                <?php if ($f['uf'] !== ''): ?><a href="<?= e($urlRelatorio(['uf' => '', 'cidade' => ''])) ?>">Ver todos os estados</a><?php endif; ?>
            </p>
        <?php endif; ?>
</section>

<?php if ($f['uf'] !== ''): ?>
<!-- Cidades do estado escolhido -->
<section class="cartao">
    <div class="cartao-titulo">
        <h2>Vendas por cidade · <?= e(nomeEstado($f['uf'])) ?></h2>
        <span class="texto-suave">pela cidade do cadastro da cliente</span>
    </div>

    <?php if (empty($cidades)): ?>
        <p class="estado-vazio">Nenhuma venda neste estado no período.</p>
    <?php else: ?>
        <?php $maisVendeu = $cidades[0]; ?>
        <p class="destaque-cidade">
            Cidade que mais vendeu: <strong><?= e($maisVendeu['cidade']) ?></strong>
            com <?= formatarMoeda($maisVendeu['total']) ?> em <?= number_format($maisVendeu['qtd'], 0, ',', '.') ?> vendas
            (<?= number_format($maisVendeu['percentual'], 1, ',', '.') ?>% do estado).
        </p>
        <div class="tabela-container">
            <table class="tabela tabela-estados">
                <thead>
                    <tr>
                        <th>#</th>
                        <th>Cidade</th>
                        <th class="texto-direita">Vendas</th>
                        <th class="texto-direita">Clientes</th>
                        <th class="texto-direita">Peças</th>
                        <th class="texto-direita">Vendido</th>
                        <th>Participação no estado</th>
                        <th class="texto-direita">Ticket médio</th>
                        <th class="texto-direita">Líquido</th>
                    </tr>
                </thead>
                <tbody>
                    <?php foreach ($cidades as $i => $c): ?>
                        <tr class="<?= $f['cidade'] === $c['cidade'] ? 'linha-selecionada' : '' ?>">
                            <td class="texto-suave"><?= $i + 1 ?></td>
                            <td class="celula-nome">
                                <a href="<?= e($urlRelatorio(['cidade' => $c['cidade']])) ?>"><strong><?= e($c['cidade']) ?></strong></a>
                            </td>
                            <td class="texto-direita"><?= number_format($c['qtd'], 0, ',', '.') ?></td>
                            <td class="texto-direita"><?= number_format($c['clientes'], 0, ',', '.') ?></td>
                            <td class="texto-direita"><?= e(formatarQuantidade($c['pecas'])) ?></td>
                            <td class="texto-direita"><strong><?= formatarMoeda($c['total']) ?></strong></td>
                            <td>
                                <div class="barra-progresso" title="<?= number_format($c['percentual'], 1, ',', '.') ?>%">
                                    <span style="width: <?= max(0, min(100, round($c['percentual']))) ?>%"></span>
                                </div>
                                <small><?= number_format($c['percentual'], 1, ',', '.') ?>%</small>
                            </td>
                            <td class="texto-direita"><?= formatarMoeda($c['ticket']) ?></td>
                            <td class="texto-direita"><?= formatarMoeda($c['liquido']) ?></td>
                        </tr>
                    <?php endforeach; ?>
                </tbody>
            </table>
        </div>
        <p class="texto-suave dica-grafico">Clique numa cidade para ver o gráfico, os números e os produtos só dela.
            <?php if ($f['cidade'] !== ''): ?><a href="<?= e($urlRelatorio(['cidade' => ''])) ?>">Ver todas as cidades de <?= e(nomeEstado($f['uf'])) ?></a><?php endif; ?>
        </p>
    <?php endif; ?>
</section>
<?php endif; ?>

<!-- Por vendedor -->
<section class="cartao">
    <div class="cartao-titulo">
        <h2>Vendas por vendedor<?= $f['uf'] !== '' ? ' · ' . e($localFiltrado) : '' ?></h2>
        <span class="texto-suave">pelo vendedor de cada peça · venda com mais de um vendedor é dividida pelas peças</span>
    </div>

    <?php if (empty($vendedores)): ?>
        <p class="estado-vazio">Nenhuma venda no período.</p>
    <?php else: ?>
        <div class="tabela-container">
            <table class="tabela tabela-estados">
                <thead>
                    <tr>
                        <th>#</th>
                        <th>Vendedor</th>
                        <th class="texto-direita">Vendas</th>
                        <th class="texto-direita">Peças</th>
                        <th class="texto-direita">Vendido</th>
                        <th>Participação</th>
                        <th class="texto-direita">Ticket médio</th>
                        <th class="texto-direita">Líquido</th>
                        <th>Vendas por estado</th>
                    </tr>
                </thead>
                <tbody>
                    <?php foreach ($vendedores as $i => $vd): ?>
                        <tr class="<?= $vend > 0 && $vend === $vd['codigo'] ? 'linha-selecionada' : '' ?>">
                            <td class="texto-suave"><?= $i + 1 ?></td>
                            <td class="celula-nome">
                                <?php if ($vd['codigo'] > 0): ?>
                                    <a href="<?= e($urlRelatorio(['vendedor' => $vd['codigo']])) ?>"><strong><?= e($vd['nome']) ?></strong></a>
                                <?php else: ?>
                                    <strong class="texto-suave"><?= e($vd['nome']) ?></strong>
                                <?php endif; ?>
                            </td>
                            <td class="texto-direita"><?= number_format($vd['qtd'], 0, ',', '.') ?></td>
                            <td class="texto-direita"><?= e(formatarQuantidade($vd['pecas'])) ?></td>
                            <td class="texto-direita"><strong><?= formatarMoeda($vd['total']) ?></strong></td>
                            <td>
                                <div class="barra-progresso" title="<?= number_format($vd['percentual'], 1, ',', '.') ?>%">
                                    <span style="width: <?= max(0, min(100, round($vd['percentual']))) ?>%"></span>
                                </div>
                                <small><?= number_format($vd['percentual'], 1, ',', '.') ?>%</small>
                            </td>
                            <td class="texto-direita"><?= formatarMoeda($vd['ticket']) ?></td>
                            <td class="texto-direita"><?= formatarMoeda($vd['liquido']) ?></td>
                            <td class="celula-estados-vendedor">
                                <?php foreach (array_slice($vd['estados'], 0, 4) as $j => $ev): ?>
                                    <span class="badge <?= $j === 0 ? 'badge-sucesso' : 'badge-info' ?>"
                                          title="<?= e($ev['nome'] . ': ' . formatarMoeda($ev['total'])) ?>">
                                        <?= e($ev['uf'] === UF_SEM_ESTADO ? 'Sem estado' : $ev['uf']) ?>
                                        <?= number_format($ev['percentual'], 0, ',', '.') ?>%
                                    </span>
                                <?php endforeach; ?>
                                <?php if (count($vd['estados']) > 4): ?>
                                    <small class="texto-suave">+<?= count($vd['estados']) - 4 ?></small>
                                <?php endif; ?>
                            </td>
                        </tr>
                    <?php endforeach; ?>
                </tbody>
            </table>
        </div>
        <p class="texto-suave dica-grafico">Clique num vendedor para ver o gráfico, os estados, as cidades e os produtos só dele.
            Em "Vendas por estado", o verde é o estado onde ele mais vendeu (passe o mouse para ver o valor).
            <?php if ($vend > 0): ?><a href="<?= e($urlRelatorio(['vendedor' => ''])) ?>">Ver todos os vendedores</a><?php endif; ?>
        </p>
    <?php endif; ?>
</section>

<div class="colunas-relatorio">
        <!-- Produtos mais vendidos no período -->
        <section class="cartao">
            <div class="cartao-titulo">
                <h2>Mais vendidos</h2>
                <span class="texto-suave">no período<?= $f['uf'] !== '' ? ' · ' . e($localFiltrado) : '' ?><?= $vend > 0 ? ' · ' . e($nomeVendedorFiltro) : '' ?></span>
            </div>
            <?php if (empty($produtos)): ?>
                <p class="estado-vazio">Sem vendas no período.</p>
            <?php else: ?>
                <ul class="lista-recentes">
                    <?php foreach ($produtos as $item): ?>
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
</div>

<?php require __DIR__ . '/includes/rodape.php'; ?>
