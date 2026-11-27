<?php
/**
 * venda_detalhe.php - Detalhe de uma venda: itens, pagamentos, parcelas do crediário, devoluções/trocas
 * e, se foi cancelada, os dados do cancelamento. Botão "Cancelar venda" abre a confirmação (venda_cancelar.php).
 */
require_once __DIR__ . '/../config/config.php';
require_once RAIZ_PROJETO . '/funcoes/cancelamento_funcoes.php';

$id    = (int) ($_GET['id'] ?? 0);
$venda = buscarVenda($id);
if ($venda === null) {
    definirMensagem('erro', 'Venda não encontrada.');
    redirecionar('vendas.php');
}

$itens      = itensDaVenda($id);
$pagamentos = pagamentosDaVenda($id);
$devolucoes = devolucoesDaVenda($id);
$creditoUsado = creditoUsadoNaVenda($id);   // crédito na loja usado como pagamento (está dentro do desconto)
$parcelas   = parcelasDaVenda($id);   // vazio quando a venda não é no crediário
$realizada  = trim((string) $venda['STATUS_VENDA']) === STATUS_VENDA_FINALIZADA;
$cancelada  = trim((string) $venda['STATUS_VENDA']) === STATUS_VENDA_CANCELADA;
$cancelamento = $cancelada ? cancelamentoDaVenda($id) : null;   // vendas canceladas no sistema antigo não têm registro

$tituloPagina = 'Venda #' . $id;
$cssPagina    = ['cadastros.css', 'vendas.css', 'dashboard.css', 'crediario.css'];

require __DIR__ . '/includes/cabecalho.php';
?>

<div class="cabecalho-pagina">
    <p>Venda #<?= $id ?> <?= badgeStatus($venda['STATUS_VENDA']) ?></p>
    <div class="acoes-condicao">
        <?php if (trim((string) $venda['STATUS_VENDA']) === STATUS_CONDICAO): ?>
            <a href="nova_venda.php?condicao=<?= $id ?>" class="botao botao-primario">Finalizar venda</a>
            <form method="post" action="condicoes.php" class="form-inline"
                  data-confirmar="Cancelar esta condição? As peças voltam ao estoque.">
                <input type="hidden" name="acao" value="cancelar">
                <input type="hidden" name="id" value="<?= $id ?>">
                <button type="submit" class="botao botao-perigo">Cancelar condição</button>
            </form>
            <a href="condicoes.php" class="botao botao-secundario">Voltar às condições</a>
        <?php else: ?>
            <?php if ($realizada): ?>
                <a href="devolucoes.php?venda=<?= $id ?>" class="botao botao-secundario"><?= icone('conciliacao', 16) ?> Devolver / trocar</a>
                <a href="venda_cancelar.php?id=<?= $id ?>" class="botao botao-perigo">Cancelar venda</a>
            <?php endif; ?>
            <a href="vendas.php" class="botao botao-secundario">Voltar ao histórico</a>
        <?php endif; ?>
    </div>
</div>

<?php if ($cancelada): ?>
    <div class="alerta alerta-erro aviso-cancelada" role="status">
        <strong>Venda cancelada<?= $cancelamento ? ' em ' . formatarData($cancelamento['DATA']) . ' às ' . e(substr((string) $cancelamento['DATA'], 11, 5)) : '' ?>.</strong>
        <?php if ($cancelamento): ?>
            Motivo: <?= e($cancelamento['MOTIVO']) ?><?= $cancelamento['OBSERVACAO'] ? ' (' . e($cancelamento['OBSERVACAO']) . ')' : '' ?>.
            As peças voltaram ao estoque e a venda não conta nos relatórios.
            <?php if ((float) $cancelamento['VALOR_PAGO'] > 0): ?>
                Valor que a cliente tinha pago: <?= formatarMoeda($cancelamento['VALOR_PAGO']) ?>, devolvido em: <?= e((string) $cancelamento['REEMBOLSO']) ?>.
            <?php endif; ?>
            <?php if ((float) $cancelamento['CREDITO_DEVOLVIDO'] > 0): ?>
                Crédito na loja devolvido ao saldo: <?= formatarMoeda($cancelamento['CREDITO_DEVOLVIDO']) ?>.
            <?php endif; ?>
            <?php if ((int) $cancelamento['PARCELAS_CANCELADAS'] > 0): ?>
                Parcelas do crediário canceladas: <?= (int) $cancelamento['PARCELAS_CANCELADAS'] ?> (<?= formatarMoeda($cancelamento['VALOR_PARCELAS']) ?>).
            <?php endif; ?>
        <?php else: ?>
            A venda não conta nos relatórios.
        <?php endif; ?>
    </div>
<?php endif; ?>

<div class="venda-layout">
    <section class="cartao">
        <div class="cartao-titulo"><h2>Itens</h2></div>
        <div class="tabela-container">
            <table class="tabela">
                <thead><tr><th>Produto</th><th>Tam.</th><th>Qtd.</th><th>Unitário</th><th class="texto-direita">Total</th></tr></thead>
                <tbody>
                    <?php foreach ($itens as $i): ?>
                        <tr>
                            <td class="celula-nome">
                                <strong><?= e(trim((string) $i['DESCRICAO'])) ?></strong>
                                <small><?= $i['VENDEDOR'] ? 'Vendedor: ' . e(trim($i['VENDEDOR'])) : '' ?></small>
                            </td>
                            <td><?= e(trim((string) $i['TAMANHO']) ?: '-') ?></td>
                            <td><?= e(formatarQuantidade($i['QUANT'])) ?></td>
                            <td><?= formatarMoeda($i['PRECO_UNIT']) ?></td>
                            <td class="texto-direita"><strong><?= formatarMoeda($i['PRECO_TOTAL']) ?></strong></td>
                        </tr>
                    <?php endforeach; ?>
                    <?php if (empty($itens)): ?>
                        <tr><td colspan="5" class="estado-vazio">Sem itens registrados.</td></tr>
                    <?php endif; ?>
                </tbody>
            </table>
        </div>
    </section>

    <aside class="coluna-pilha">
        <section class="cartao">
            <div class="cartao-titulo"><h2>Resumo</h2></div>
            <dl class="lista-resumo">
                <div><dt>Data</dt><dd><?= formatarData($venda['DATA']) ?> <?= e(formatarHora($venda['HORA'])) ?></dd></div>
                <div><dt>Cliente</dt><dd><?= $venda['CLIENTE_NOME'] ? e(trim($venda['CLIENTE_NOME'])) : 'Sem cadastro' ?></dd></div>
                <div><dt>Peças</dt><dd><?= e(formatarQuantidade($venda['NUM_PECAS'])) ?></dd></div>
                <div><dt>Subtotal</dt><dd><?= formatarMoeda($venda['VALOR']) ?></dd></div>
                <?php if ($creditoUsado > 0): ?>
                    <div><dt>Desconto</dt><dd><?= formatarMoeda(max(0, (float) $venda['DESCONTO'] - $creditoUsado)) ?></dd></div>
                    <div><dt>Crédito da loja</dt><dd><?= formatarMoeda($creditoUsado) ?></dd></div>
                <?php else: ?>
                    <div><dt>Desconto</dt><dd><?= formatarMoeda($venda['DESCONTO']) ?></dd></div>
                <?php endif; ?>
                <div class="total"><dt>Total</dt><dd><?= formatarMoeda($venda['TOTAL']) ?></dd></div>
            </dl>
        </section>

        <?php if (!empty($parcelas)): ?>
            <section class="cartao">
                <div class="cartao-titulo">
                    <h2>Crediário</h2>
                    <?php if (!$cancelada): ?>
                        <a href="crediario.php?busca=<?= $id ?>&amp;situacao=todos" class="botao botao-secundario botao-pequeno">Dar baixa</a>
                    <?php endif; ?>
                </div>
                <ul class="lista-recentes">
                    <?php foreach ($parcelas as $p): ?>
                        <?php $atraso = diasDeAtraso($p); ?>
                        <li class="<?= $atraso > 0 ? 'parcela-atrasada' : '' ?>">
                            <div>
                                <strong>Parcela <?= (int) $p['NUMERO'] ?>/<?= (int) $p['TOTAL_PARCELAS'] ?></strong>
                                <small>
                                    venc. <?= formatarData($p['VENCIMENTO']) ?> ·
                                    <?php if ($p['STATUS'] === PARCELA_PAGA): ?>
                                        paga em <?= formatarData($p['DATA_PAGAMENTO']) ?> (<?= e($p['FORMA_RECEBIMENTO']) ?>)
                                    <?php elseif ($p['STATUS'] === PARCELA_ABATIDA): ?>
                                        abatida por devolução
                                    <?php elseif ($p['STATUS'] === PARCELA_CANCELADA): ?>
                                        cancelada com a venda
                                    <?php elseif ($atraso > 0): ?>
                                        atrasada há <?= $atraso ?> dia(s)
                                    <?php else: ?>
                                        em aberto
                                    <?php endif; ?>
                                </small>
                            </div>
                            <strong><?= formatarMoeda($p['STATUS'] === PARCELA_ABERTA ? saldoParcela($p) : $p['VALOR']) ?></strong>
                        </li>
                    <?php endforeach; ?>
                </ul>
            </section>
        <?php endif; ?>

        <?php if (!empty($devolucoes)): ?>
            <section class="cartao">
                <div class="cartao-titulo"><h2>Devoluções e trocas</h2></div>
                <ul class="lista-recentes">
                    <?php foreach ($devolucoes as $d): ?>
                        <li>
                            <div>
                                <strong><?= e(rotuloTipoDevolucao($d['TIPO'])) ?> #<?= (int) $d['ID'] ?></strong>
                                <small>
                                    <?= formatarData($d['DATA']) ?> · <?= e(formatarQuantidade($d['PECAS'])) ?> peça(s)
                                    <?php foreach ($d['ITENS'] as $p): ?>
                                        · <?= e(formatarQuantidade($p['QUANT'])) ?>× <?= e(trim((string) $p['DESCRICAO'])) ?>
                                    <?php endforeach; ?>
                                    <?php if ($d['TIPO'] === TIPO_TROCA && $d['CODIGO_VENDA_TROCA']): ?>
                                        · crédito usado na <a href="venda_detalhe.php?id=<?= (int) $d['CODIGO_VENDA_TROCA'] ?>">venda #<?= (int) $d['CODIGO_VENDA_TROCA'] ?></a>
                                    <?php elseif ($d['TIPO'] === TIPO_TROCA || $d['TIPO'] === TIPO_CREDITO): ?>
                                        · <?= e($d['REEMBOLSO'] === REEMBOLSO_ABATIDO_CREDIARIO ? 'abatido no crediário' : 'foi para o crédito na loja') ?>
                                    <?php else: ?>
                                        · reembolso: <?= e($d['REEMBOLSO'] ?: '-') ?>
                                    <?php endif; ?>
                                </small>
                            </div>
                            <strong><?= formatarMoeda($d['VALOR']) ?></strong>
                        </li>
                    <?php endforeach; ?>
                </ul>
            </section>
        <?php endif; ?>

        <section class="cartao">
            <div class="cartao-titulo"><h2>Pagamentos</h2></div>
            <?php if (empty($pagamentos)): ?>
                <p class="estado-vazio">Sem pagamentos registrados.</p>
            <?php else: ?>
                <ul class="lista-recentes">
                    <?php foreach ($pagamentos as $p): ?>
                        <li>
                            <div>
                                <strong><?= e(rotuloFormaPagamento($p['TIPO_FORMA_PGTO'])) ?></strong>
                                <small><?= e(trim((string) $p['BANDEIRA'])) ?> <?= $p['DAT_VENC'] ? '· venc. ' . formatarData($p['DAT_VENC']) : '' ?></small>
                            </div>
                            <strong><?= formatarMoeda($p['VALOR']) ?></strong>
                        </li>
                    <?php endforeach; ?>
                </ul>
            <?php endif; ?>
        </section>
    </aside>
</div>

<?php require __DIR__ . '/includes/rodape.php'; ?>
