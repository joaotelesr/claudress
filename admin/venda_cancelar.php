<?php
/**
 * venda_cancelar.php - Tela de confirmação para cancelar uma venda (aberta pelo botão "Cancelar venda"
 * do histórico ou do detalhe da venda). Mostra a venda e o que vai acontecer; só cancela quando a
 * pessoa confirma no botão "Sim, cancelar a venda". Regras em funcoes/cancelamento_funcoes.php.
 */
require_once __DIR__ . '/../config/config.php';
require_once RAIZ_PROJETO . '/funcoes/cancelamento_funcoes.php';

$id     = (int) ($_GET['id'] ?? $_POST['id'] ?? 0);
$voltar = ($_GET['voltar'] ?? $_POST['voltar'] ?? '') === 'historico' ? 'historico' : 'detalhe';
$urlVoltar = $voltar === 'historico' ? 'vendas.php' : 'venda_detalhe.php?id=' . $id;

$previa = previaCancelamento($id);
if ($previa === null) {
    definirMensagem('erro', 'Venda não encontrada.');
    redirecionar('vendas.php');
}

$erro  = '';
$dados = ['motivo' => '', 'observacao' => '', 'reembolso' => ''];

// Confirmação (somente via POST, com o código da sessão para outro site não conseguir cancelar vendas)
if (ehPost()) {
    $dados = [
        'motivo'     => (string) ($_POST['motivo'] ?? ''),
        'observacao' => (string) ($_POST['observacao'] ?? ''),
        'reembolso'  => (string) ($_POST['reembolso'] ?? ''),
    ];
    if (!hash_equals(tokenLogin(), (string) ($_POST['token'] ?? ''))) {
        $erro = 'A página ficou aberta por muito tempo. Confira os dados e clique de novo em "Sim, cancelar a venda".';
    } else {
        try {
            definirMensagem('sucesso', cancelarVenda($id, $dados));
            redirecionar('venda_detalhe.php?id=' . $id);
        } catch (RuntimeException $ex) {
            $erro = $ex->getMessage();
        } catch (PDOException $ex) {
            $erro = 'Não foi possível cancelar a venda. Nada foi alterado.' . (DB_MOSTRAR_ERROS ? ' ' . $ex->getMessage() : '');
        }
        $previa = previaCancelamento($id) ?? $previa;
    }
}

$venda    = $previa['venda'];
$itens    = $previa['itens'];
$cliente  = $venda['CLIENTE_NOME'] ? trim((string) $venda['CLIENTE_NOME']) : '';
$temPago  = $previa['valor_pago'] > 0;

$tituloPagina = 'Cancelar venda #' . $id;
$cssPagina    = ['cadastros.css', 'vendas.css'];

require __DIR__ . '/includes/cabecalho.php';
?>

<div class="cabecalho-pagina">
    <p>Venda #<?= $id ?> <?= badgeStatus($venda['STATUS_VENDA']) ?></p>
    <a href="<?= e($urlVoltar) ?>" class="botao botao-secundario">Voltar</a>
</div>

<?php if ($erro !== ''): ?>
    <div class="alerta alerta-erro" role="alert"><?= e($erro) ?></div>
<?php endif; ?>

<?php if (!$previa['pode']): ?>
    <section class="cartao">
        <div class="alerta alerta-aviso"><?= e($previa['bloqueio']) ?></div>
        <div class="formulario-acoes">
            <?php if (trim((string) $venda['STATUS_VENDA']) === STATUS_VENDA_FINALIZADA): ?>
                <a href="devolucoes.php?venda=<?= $id ?>" class="botao botao-secundario">Devolver / trocar</a>
            <?php endif; ?>
            <a href="venda_detalhe.php?id=<?= $id ?>" class="botao botao-primario">Ver a venda</a>
        </div>
    </section>
<?php else: ?>
    <div class="venda-layout">
        <section class="cartao cartao-cancelamento">
            <div class="cartao-titulo"><h2>Tem certeza que quer cancelar esta venda?</h2></div>
            <p class="texto-suave">Confira os dados abaixo. A venda não é apagada: ela fica no histórico marcada como cancelada, com o motivo.</p>

            <dl class="lista-resumo">
                <div><dt>Venda</dt><dd>#<?= $id ?></dd></div>
                <div><dt>Data</dt><dd><?= formatarData($venda['DATA']) ?> <?= e(formatarHora($venda['HORA'])) ?></dd></div>
                <div><dt>Cliente</dt><dd><?= $cliente !== '' ? e($cliente) : 'Sem cadastro' ?></dd></div>
                <div><dt>Peças</dt><dd><?= e(formatarQuantidade($venda['NUM_PECAS'])) ?></dd></div>
                <div class="total"><dt>Total da venda</dt><dd><?= formatarMoeda($venda['TOTAL']) ?></dd></div>
            </dl>

            <h3 class="titulo-secao">O que vai acontecer</h3>
            <ul class="lista-consequencias">
                <li>As peças voltam ao estoque:
                    <ul class="lista-pecas">
                        <?php foreach ($itens as $i): ?>
                            <?php if (trim((string) $i['STATUS_ITENS_VENDA']) !== 'Ativo') { continue; } ?>
                            <li><?= e(formatarQuantidade($i['QUANT'])) ?>× <?= e(trim((string) $i['DESCRICAO'])) ?>
                                <small><?= e(trim((string) $i['TAMANHO'])) ?></small></li>
                        <?php endforeach; ?>
                    </ul>
                </li>
                <li>A venda sai do painel, dos relatórios e da conciliação.</li>
                <?php if ($previa['parcelas_abertas'] > 0): ?>
                    <li><?= $previa['parcelas_abertas'] ?> parcela(s) do crediário em aberto
                        (<?= formatarMoeda($previa['valor_parcelas_abertas']) ?>) serão canceladas e saem da cobrança.</li>
                <?php endif; ?>
                <?php if ($previa['credito_usado'] > 0): ?>
                    <li><?= formatarMoeda($previa['credito_usado']) ?> de crédito na loja usado nesta venda voltam para o saldo da cliente.</li>
                <?php endif; ?>
                <?php if ($temPago): ?>
                    <li><strong>A cliente já pagou <?= formatarMoeda($previa['valor_pago']) ?></strong><?= $previa['valor_parcelas_pagas'] > 0 ? ' (incluindo ' . formatarMoeda($previa['valor_parcelas_pagas']) . ' de parcelas pagas)' : '' ?>.
                        Informe ao lado como esse valor foi devolvido.</li>
                <?php endif; ?>
            </ul>
        </section>

        <aside class="cartao">
            <form method="post" action="venda_cancelar.php">
                <input type="hidden" name="id" value="<?= $id ?>">
                <input type="hidden" name="voltar" value="<?= e($voltar) ?>">
                <input type="hidden" name="token" value="<?= e(tokenLogin()) ?>">

                <div class="campo">
                    <label for="motivo">Motivo do cancelamento</label>
                    <select id="motivo" name="motivo" required>
                        <option value="">Escolha...</option>
                        <?php foreach (MOTIVOS_CANCELAMENTO as $m): ?>
                            <option value="<?= e($m) ?>" <?= $dados['motivo'] === $m ? 'selected' : '' ?>><?= e($m) ?></option>
                        <?php endforeach; ?>
                    </select>
                </div>

                <?php if ($temPago): ?>
                    <div class="campo campo-espaco">
                        <label for="reembolso">Valor pago (<?= formatarMoeda($previa['valor_pago']) ?>) devolvido em</label>
                        <select id="reembolso" name="reembolso" required>
                            <option value="">Escolha...</option>
                            <?php foreach (FORMAS_REEMBOLSO_CANCELAMENTO as $f): ?>
                                <?php if ($f === REEMBOLSO_CANCELAMENTO_CREDITO && !(int) $venda['CODIGO_CLIENTE']) { continue; } ?>
                                <option value="<?= e($f) ?>" <?= $dados['reembolso'] === $f ? 'selected' : '' ?>><?= e($f) ?></option>
                            <?php endforeach; ?>
                        </select>
                        <small class="texto-suave">"Crédito na loja" deixa o valor no saldo da cliente para as próximas compras.</small>
                    </div>
                <?php endif; ?>

                <div class="campo campo-espaco">
                    <label for="observacao">Observação <small class="texto-suave">(obrigatória se o motivo for "Outro")</small></label>
                    <input type="text" id="observacao" name="observacao" maxlength="255" value="<?= e($dados['observacao']) ?>">
                </div>

                <label class="confirmar-cancelamento">
                    <input type="checkbox" name="confirmo" value="1" required>
                    Confirmo que quero cancelar a venda #<?= $id ?><?= $cliente !== '' ? ' de ' . e($cliente) : '' ?>.
                </label>

                <button type="submit" class="botao botao-perigo-cheio botao-bloco">Sim, cancelar a venda</button>
                <a href="<?= e($urlVoltar) ?>" class="botao botao-secundario botao-bloco">Não, voltar sem cancelar</a>
            </form>
        </aside>
    </div>
<?php endif; ?>

<?php require __DIR__ . '/includes/rodape.php'; ?>
