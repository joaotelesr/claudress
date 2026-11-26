<?php
/**
 * venda_detalhe.php - Detalhe de uma venda: itens e pagamentos
 */
require_once __DIR__ . '/../config/config.php';
require_once RAIZ_PROJETO . '/funcoes/vendas_funcoes.php';

$id    = (int) ($_GET['id'] ?? 0);
$venda = buscarVenda($id);
if ($venda === null) {
    definirMensagem('erro', 'Venda não encontrada.');
    redirecionar('vendas.php');
}

$itens      = itensDaVenda($id);
$pagamentos = pagamentosDaVenda($id);

$tituloPagina = 'Venda #' . $id;
$cssPagina    = ['cadastros.css', 'vendas.css'];

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
            <a href="vendas.php" class="botao botao-secundario">Voltar ao histórico</a>
        <?php endif; ?>
    </div>
</div>

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
                <div><dt>Desconto</dt><dd><?= formatarMoeda($venda['DESCONTO']) ?></dd></div>
                <div class="total"><dt>Total</dt><dd><?= formatarMoeda($venda['TOTAL']) ?></dd></div>
            </dl>
        </section>

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
