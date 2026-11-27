<?php
/**
 * crediario.php - Clientes que estão pagando no crediário (carnê) e baixa de cada parcela paga.
 * As vendas no crediário são feitas na Nova venda (forma de pagamento "Crediário").
 * Ações (somente via POST): dar baixa numa parcela (data + forma em que a cliente pagou) e desfazer a baixa.
 * A tabela CREDIARIO_PARCELA é criada sozinha na primeira vez que a tela é aberta.
 */
require_once __DIR__ . '/../config/config.php';
require_once RAIZ_PROJETO . '/funcoes/vendas_funcoes.php';
require_once RAIZ_PROJETO . '/funcoes/crediario_funcoes.php';

garantirTabelasCrediario();

$filtros = filtrosCrediario($_GET);

// ----- Ações -----
if (ehPost()) {
    $volta = 'crediario.php?' . http_build_query(filtrosCrediario($_POST));
    $id    = (int) ($_POST['id'] ?? 0);
    $acao  = (string) ($_POST['acao'] ?? '');

    try {
        $p = buscarParcela($id);
        $rotulo = $p ? 'Parcela ' . (int) $p['NUMERO'] . '/' . (int) $p['TOTAL_PARCELAS'] . ' da venda #' . (int) $p['CODIGO_VENDA'] : 'Parcela';

        if ($acao === 'baixar') {
            baixarParcela($id, (string) ($_POST['data_pagamento'] ?? ''), (string) ($_POST['forma'] ?? ''), (string) ($_POST['observacao'] ?? ''));
            $p = buscarParcela($id);
            definirMensagem('sucesso', $rotulo . ' baixada: ' . formatarMoeda($p['VALOR_PAGO']) . ' em ' . $p['FORMA_RECEBIMENTO']
                . ', dia ' . formatarData($p['DATA_PAGAMENTO']) . '.');
        } elseif ($acao === 'desfazer') {
            desfazerBaixaParcela($id);
            definirMensagem('sucesso', 'Baixa desfeita: ' . mb_strtolower(mb_substr($rotulo, 0, 1)) . mb_substr($rotulo, 1) . ' voltou a ficar em aberto.');
        }
    } catch (RuntimeException $ex) {
        definirMensagem('erro', $ex->getMessage());
    } catch (PDOException $ex) {
        definirMensagem('erro', 'Não foi possível gravar.' . (DB_MOSTRAR_ERROS ? ' ' . $ex->getMessage() : ''));
    }
    redirecionar($volta . ($id > 0 && isset($p['CODIGO_VENDA']) ? '#carne-' . (int) $p['CODIGO_VENDA'] : ''));
}

// ----- O que mostrar -----
$resumo   = resumoCrediario();
$clientes = listarCrediarios($filtros);
$hoje     = date('Y-m-d');

$tituloPagina = 'Crediário';
$cssPagina    = ['cadastros.css', 'vendas.css', 'dashboard.css', 'crediario.css'];

require __DIR__ . '/includes/cabecalho.php';
?>

<div class="cabecalho-pagina">
    <p>Parcelas das vendas feitas no crediário. Para vender no crediário, escolha "Crediário" na forma de pagamento da Nova venda.</p>
    <a href="nova_venda.php" class="botao botao-primario"><?= icone('venda', 18) ?> Nova venda</a>
</div>

<section class="grade-indicadores indicadores-compactos">
    <a class="indicador" href="crediario.php?situacao=aberto">
        <span class="indicador-icone"><?= icone('crediario', 24) ?></span>
        <span>
            <span class="indicador-rotulo">A receber</span>
            <strong class="indicador-valor"><?= formatarMoeda($resumo['aberto']) ?></strong>
            <span class="indicador-detalhe"><?= $resumo['clientes'] ?> cliente(s) pagando</span>
        </span>
    </a>
    <a class="indicador<?= $resumo['vencido'] > 0 ? ' indicador-alerta' : '' ?>" href="crediario.php?situacao=atrasado">
        <span class="indicador-icone"><?= icone('historico', 24) ?></span>
        <span>
            <span class="indicador-rotulo">Em atraso</span>
            <strong class="indicador-valor"><?= formatarMoeda($resumo['vencido']) ?></strong>
            <span class="indicador-detalhe"><?= $resumo['clientes_atraso'] ?> cliente(s) com parcela vencida</span>
        </span>
    </a>
    <div class="indicador">
        <span class="indicador-icone"><?= icone('extrato', 24) ?></span>
        <span>
            <span class="indicador-rotulo">Vence nos próximos 7 dias</span>
            <strong class="indicador-valor"><?= formatarMoeda($resumo['semana']) ?></strong>
        </span>
    </div>
    <div class="indicador">
        <span class="indicador-icone"><?= icone('conferido', 24) ?></span>
        <span>
            <span class="indicador-rotulo">Recebido em <?= e(rotuloMes(date('Y-m'))) ?></span>
            <strong class="indicador-valor"><?= formatarMoeda($resumo['recebido_mes']) ?></strong>
        </span>
    </div>
</section>

<div class="cartao">
    <form method="get" class="barra-filtros">
        <div class="campo">
            <label for="busca">Cliente, CPF ou nº da venda</label>
            <input type="search" id="busca" name="busca" value="<?= e($filtros['busca']) ?>">
        </div>
        <div class="campo">
            <label for="situacao">Situação</label>
            <select id="situacao" name="situacao">
                <?php foreach (SITUACOES_CREDIARIO as $valor => $rotulo): ?>
                    <option value="<?= e($valor) ?>" <?= $filtros['situacao'] === $valor ? 'selected' : '' ?>><?= e($rotulo) ?></option>
                <?php endforeach; ?>
            </select>
        </div>
        <div class="barra-filtros-botoes">
            <button type="submit" class="botao botao-primario"><?= icone('busca', 16) ?> Filtrar</button>
            <a href="crediario.php" class="botao botao-secundario">Limpar</a>
        </div>
    </form>

    <?php if (empty($clientes)): ?>
        <p class="estado-vazio">
            <?php if ($filtros['busca'] !== '' || $filtros['situacao'] !== 'aberto'): ?>
                Nenhum crediário encontrado com esse filtro.
            <?php else: ?>
                Nenhuma cliente pagando no crediário no momento.
            <?php endif; ?>
        </p>
    <?php endif; ?>

    <?php foreach ($clientes as $c): ?>
        <?php $whats = $c['TELEFONE'] !== '' && $c['ABERTO'] > 0
            ? linkWhatsappCobranca($c['TELEFONE'], $c['NOME'], $c['VENCIDO'] > 0 ? $c['VENCIDO'] : $c['ABERTO'], $c['ATRASO_MAX']) : ''; ?>
        <article class="cliente-crediario<?= $c['ATRASO_MAX'] > 0 ? ' com-atraso' : '' ?>">
            <header class="cliente-crediario-topo">
                <div>
                    <strong class="cliente-crediario-nome"><?= e($c['NOME']) ?></strong>
                    <small class="texto-suave">
                        <?= $c['TELEFONE'] !== '' ? e(formatarTelefone($c['TELEFONE'])) : 'Sem telefone no cadastro' ?>
                        <?php if ($c['CODIGO_CLIENTE'] > 0): ?> · <a href="cliente_form.php?id=<?= (int) $c['CODIGO_CLIENTE'] ?>">cadastro</a><?php endif; ?>
                    </small>
                </div>
                <div class="cliente-crediario-saldo">
                    <?php if ($c['ATRASO_MAX'] > 0): ?>
                        <span class="badge badge-erro"><?= formatarMoeda($c['VENCIDO']) ?> em atraso (<?= $c['ATRASO_MAX'] ?> dia<?= $c['ATRASO_MAX'] > 1 ? 's' : '' ?>)</span>
                    <?php endif; ?>
                    <?php if ($c['ABERTO'] > 0): ?>
                        <span>Falta pagar <strong><?= formatarMoeda($c['ABERTO']) ?></strong></span>
                    <?php else: ?>
                        <span class="badge badge-sucesso">Quitado</span>
                    <?php endif; ?>
                    <?php if ($whats !== ''): ?>
                        <a href="<?= e($whats) ?>" target="_blank" rel="noopener" class="botao botao-secundario botao-pequeno">Lembrar no WhatsApp</a>
                    <?php endif; ?>
                </div>
            </header>

            <?php foreach ($c['CARNES'] as $carne): ?>
                <?php $vendaId = (int) $carne['CODIGO_VENDA']; ?>
                <details class="carne" id="carne-<?= $vendaId ?>" <?= $carne['ABERTO'] > 0 || $filtros['busca'] !== '' ? 'open' : '' ?>>
                    <summary>
                        <span>
                            <strong>Venda #<?= $vendaId ?></strong> · <?= formatarData($carne['DATA']) ?>
                            · <?= count($carne['PARCELAS']) ?>x · crediário de <?= formatarMoeda($carne['TOTAL_CREDIARIO']) ?>
                            <?php if ($carne['TOTAL_CREDIARIO'] + 0.001 < $carne['TOTAL_VENDA']): ?>
                                <small class="texto-suave">(venda de <?= formatarMoeda($carne['TOTAL_VENDA']) ?> com entrada)</small>
                            <?php endif; ?>
                        </span>
                        <span class="carne-totais">
                            Pago <?= formatarMoeda($carne['PAGO']) ?>
                            <?php if ($carne['ABATIDO'] > 0): ?> · devolvido <?= formatarMoeda($carne['ABATIDO']) ?><?php endif; ?>
                            · <strong><?= $carne['ABERTO'] > 0 ? 'falta ' . formatarMoeda($carne['ABERTO']) : 'quitado' ?></strong>
                        </span>
                    </summary>

                    <div class="tabela-container">
                        <table class="tabela tabela-parcelas">
                            <thead>
                                <tr><th>Parcela</th><th>Vencimento</th><th class="texto-direita">Valor</th><th>Situação</th><th>Baixa</th></tr>
                            </thead>
                            <tbody>
                                <?php foreach ($carne['PARCELAS'] as $p): ?>
                                    <?php
                                    $venc    = substr((string) $p['VENCIMENTO'], 0, 10);
                                    $aberta  = $p['STATUS'] === PARCELA_ABERTA;
                                    $classe  = $p['ATRASO'] > 0 ? 'parcela-atrasada' : ($aberta && $venc === $hoje ? 'parcela-hoje' : ($aberta ? '' : 'parcela-fechada'));
                                    ?>
                                    <tr class="<?= $classe ?>">
                                        <td><strong><?= (int) $p['NUMERO'] ?>/<?= (int) $p['TOTAL_PARCELAS'] ?></strong></td>
                                        <td><?= formatarData($venc) ?></td>
                                        <td class="texto-direita">
                                            <strong><?= formatarMoeda($aberta ? $p['SALDO'] : ($p['STATUS'] === PARCELA_PAGA ? $p['VALOR_PAGO'] : $p['VALOR'])) ?></strong>
                                            <?php if ((float) $p['VALOR_ABATIDO'] > 0 && $p['STATUS'] !== PARCELA_ABATIDA): ?>
                                                <small class="texto-suave">de <?= formatarMoeda($p['VALOR']) ?> (<?= formatarMoeda($p['VALOR_ABATIDO']) ?> devolvido)</small>
                                            <?php endif; ?>
                                        </td>
                                        <td>
                                            <?php if ($p['STATUS'] === PARCELA_PAGA): ?>
                                                <span class="badge badge-sucesso">Paga</span>
                                                <small class="texto-suave"><?= formatarData($p['DATA_PAGAMENTO']) ?> · <?= e($p['FORMA_RECEBIMENTO']) ?></small>
                                                <?php if ($p['OBSERVACAO']): ?><small class="texto-suave"><?= e($p['OBSERVACAO']) ?></small><?php endif; ?>
                                            <?php elseif ($p['STATUS'] === PARCELA_ABATIDA): ?>
                                                <span class="badge">Abatida (devolução<?= $p['ID_DEVOLUCAO'] ? ' #' . (int) $p['ID_DEVOLUCAO'] : '' ?>)</span>
                                            <?php elseif ($p['ATRASO'] > 0): ?>
                                                <span class="badge badge-erro">Atrasada há <?= $p['ATRASO'] ?> dia<?= $p['ATRASO'] > 1 ? 's' : '' ?></span>
                                            <?php elseif ($venc === $hoje): ?>
                                                <span class="badge badge-aviso">Vence hoje</span>
                                            <?php else: ?>
                                                <span class="badge badge-info">A vencer</span>
                                            <?php endif; ?>
                                        </td>
                                        <td>
                                            <?php if ($aberta): ?>
                                                <form method="post" class="form-baixa">
                                                    <input type="hidden" name="acao" value="baixar">
                                                    <input type="hidden" name="id" value="<?= (int) $p['ID'] ?>">
                                                    <input type="hidden" name="busca" value="<?= e($filtros['busca']) ?>">
                                                    <input type="hidden" name="situacao" value="<?= e($filtros['situacao']) ?>">
                                                    <input type="date" name="data_pagamento" value="<?= e($hoje) ?>" max="<?= e($hoje) ?>" aria-label="Data do pagamento" required>
                                                    <select name="forma" aria-label="Como pagou">
                                                        <?php foreach (FORMAS_RECEBIMENTO_CREDIARIO as $forma): ?><option><?= e($forma) ?></option><?php endforeach; ?>
                                                    </select>
                                                    <button type="submit" class="botao botao-primario botao-pequeno">Dar baixa</button>
                                                </form>
                                            <?php elseif ($p['STATUS'] === PARCELA_PAGA): ?>
                                                <form method="post" class="form-inline"
                                                      data-confirmar="Desfazer a baixa da parcela <?= (int) $p['NUMERO'] ?>/<?= (int) $p['TOTAL_PARCELAS'] ?> da venda #<?= $vendaId ?>? Ela volta a ficar em aberto.">
                                                    <input type="hidden" name="acao" value="desfazer">
                                                    <input type="hidden" name="id" value="<?= (int) $p['ID'] ?>">
                                                    <input type="hidden" name="busca" value="<?= e($filtros['busca']) ?>">
                                                    <input type="hidden" name="situacao" value="<?= e($filtros['situacao']) ?>">
                                                    <button type="submit" class="botao botao-secundario botao-pequeno">Desfazer baixa</button>
                                                </form>
                                            <?php else: ?>
                                                <span class="texto-suave">-</span>
                                            <?php endif; ?>
                                        </td>
                                    </tr>
                                <?php endforeach; ?>
                            </tbody>
                        </table>
                    </div>
                    <p class="carne-rodape"><a href="venda_detalhe.php?id=<?= $vendaId ?>">Ver a venda #<?= $vendaId ?></a></p>
                </details>
            <?php endforeach; ?>
        </article>
    <?php endforeach; ?>
</div>

<?php require __DIR__ . '/includes/rodape.php'; ?>
