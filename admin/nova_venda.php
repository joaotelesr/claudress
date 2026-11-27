<?php
/**
 * nova_venda.php - Registro de venda (VENDAS + ITENS_VENDA + FORMA_PAGAMENTO_VENDA + baixa de estoque)
 * O navegador só envia ids e quantidades; preços e estoque são sempre relidos do banco.
 * Botão "Condição": grava as peças separadas para a cliente, sem pagamento (ver condicoes.php).
 * nova_venda.php?condicao=ID abre a tela preenchida com uma condição, para receber o pagamento.
 * nova_venda.php?troca=ID abre a tela com a cliente da troca e o crédito pronto para usar (ver devolucoes.php).
 * nova_venda.php?cliente=ID abre a tela já com a cliente escolhida.
 * Crédito na loja: quando a cliente tem saldo, aparece "Pagar com crédito da loja" no quadro de pagamento.
 * Forma "Crediário": escolhe nº de parcelas e 1º vencimento; as parcelas aparecem em crediario.php para dar baixa.
 */
require_once __DIR__ . '/../config/config.php';
require_once RAIZ_PROJETO . '/funcoes/vendas_funcoes.php';

garantirTabelasCrediario();   // cria a tabela de parcelas na primeira vez (fora da transação da venda)
garantirTabelasDevolucao();   // e a do crédito na loja
garantirVendedoresPadrao();   // vendedores "Cliente Novo" e "Cliente"

$erros  = [];
$estado = [
    'cliente' => null, 'vendedor_id' => 0, 'itens' => [],   // vendedor começa sem nenhum escolhido
    'pagamentos' => [], 'desconto' => 0, 'frete' => 0, 'condicao_id' => null,
    'credito_usar' => 0, 'troca_id' => null,
];

// Abrir já com a cliente escolhida (ex.: botão "Usar crédito" da tela de devoluções)
if (!ehPost() && (int) ($_GET['cliente'] ?? 0) > 0) {
    $estado = estadoComCliente((int) $_GET['cliente']) ?? $estado;
}

// Finalizar uma condição: abre a tela já com a cliente e as peças separadas
if (!ehPost() && (int) ($_GET['condicao'] ?? 0) > 0) {
    $estadoCondicao = estadoDaCondicao((int) $_GET['condicao']);
    if ($estadoCondicao === null) {
        definirMensagem('erro', 'Condição não encontrada ou já encerrada.');
        redirecionar('condicoes.php');
    }
    $estado = $estadoCondicao;
}

// Troca: abre a tela com a cliente e o crédito da troca pronto para usar
if (!ehPost() && (int) ($_GET['troca'] ?? 0) > 0) {
    $estadoTroca = estadoDaTroca((int) $_GET['troca']);
    if ($estadoTroca === null) {
        definirMensagem('erro', 'A cliente não tem mais crédito na loja para esta troca (já foi usado) ou a troca não existe.');
        redirecionar('devolucoes.php');
    }
    $estado = $estadoTroca;
}

if (ehPost()) {
    $r      = prepararNovaVenda($_POST);
    $erros  = $r['erros'];
    $estado = $r['estado'];

    if (empty($erros)) {
        try {
            $codigo = registrarVenda($r['venda']);
            if ($r['venda']['status'] === STATUS_CONDICAO) {
                definirMensagem('sucesso', 'Condição #' . $codigo . ' gravada para ' . $r['estado']['cliente']['nome'] . '. As peças ficaram reservadas.');
                redirecionar('condicoes.php');
            }
            $msg = 'Venda #' . $codigo . ' registrada com sucesso.';
            if ($r['venda']['condicao_id']) {
                $msg .= ' A condição #' . $r['venda']['condicao_id'] . ' foi encerrada.';
            }
            if ($r['venda']['credito_cent'] > 0) {
                $msg .= ' Crédito da loja usado: ' . formatarMoeda($r['venda']['credito_cent'] / 100)
                      . ' (saldo restante: ' . formatarMoeda(saldoCredito((int) $r['venda']['cliente_id'])) . ').';
            }
            foreach ($r['venda']['pagamentos'] as $pg) {
                if (!empty($pg['parcelas'])) {
                    $msg .= ' Crediário: ' . count($pg['parcelas']) . ' parcela(s), 1º vencimento em '
                          . formatarData($pg['parcelas'][0]['vencimento']) . '.';
                }
            }
            definirMensagem('sucesso', $msg);
            redirecionar('venda_detalhe.php?id=' . $codigo);
        } catch (RuntimeException $ex) {
            $erros[] = $ex->getMessage();
        } catch (PDOException $ex) {
            $erros[] = 'Não foi possível gravar a venda.' . (DB_MOSTRAR_ERROS ? ' ' . $ex->getMessage() : '');
        }
    }
}

$formas    = formasNovaVenda();
$vendedores = listarVendedores();

$config = [
    'formas' => array_values($formas),
    'estado' => $estado,
    'crediario' => [
        'max'     => CREDIARIO_MAX_PARCELAS,
        'primeiro' => primeiroVencimentoPadrao(),
        'min'     => date('Y-m-d'),
        'limite'  => date('Y-m-d', strtotime('+' . CREDIARIO_MAX_DIAS_1VENC . ' days')),
    ],
];

$emCondicao   = !empty($estado['condicao_id']);
$emTroca      = !empty($estado['troca_id']);
$tituloPagina = $emCondicao ? 'Finalizar condição #' . (int) $estado['condicao_id'] : ($emTroca ? 'Troca #' . (int) $estado['troca_id'] . ': peças novas' : 'Nova venda');
$valorCredito = (float) ($estado['credito_usar'] ?? 0);
$cssPagina    = ['cadastros.css', 'vendas.css', 'crediario.css'];

require __DIR__ . '/includes/cabecalho.php';
?>

<?php if (!empty($erros)): ?>
    <div class="alerta alerta-erro" role="alert">
        <ul class="lista-erros">
            <?php foreach ($erros as $m): ?><li><?= e($m) ?></li><?php endforeach; ?>
        </ul>
    </div>
<?php endif; ?>

<?php if ($emCondicao): ?>
    <div class="alerta alerta-aviso">
        Recebendo o pagamento da condição #<?= (int) $estado['condicao_id'] ?>. Tire da lista as peças que a cliente devolveu:
        elas voltam ao estoque quando a venda for finalizada.
        <a href="condicoes.php">Voltar às condições</a>
    </div>
<?php endif; ?>

<?php if ($emTroca): ?>
    <div class="alerta alerta-aviso">
        Troca #<?= (int) $estado['troca_id'] ?>: <strong><?= formatarMoeda($valorCredito) ?></strong> de crédito já está em
        "Pagar com crédito da loja". Adicione as peças novas: se custarem mais, a cliente paga só a diferença;
        se custarem o mesmo, a venda fecha sem pagamento; se custarem menos, a sobra continua como crédito para a próxima compra.
        <a href="devolucoes.php">Voltar às devoluções</a>
    </div>
<?php endif; ?>

<form method="post" id="form-venda" autocomplete="off">
    <?php if ($emCondicao): ?><input type="hidden" name="condicao_id" value="<?= (int) $estado['condicao_id'] ?>"><?php endif; ?>
    <div class="venda-layout">

        <div class="coluna-pilha">
            <section class="cartao">
                <div class="cartao-titulo"><h2>Cliente e vendedor</h2></div>
                <div class="formulario-grade">
                    <div class="campo campo-largo campo-busca">
                        <label for="cliente-busca">Cliente *</label>
                        <input type="hidden" name="cliente_id" id="cliente-id" value="">
                        <input type="search" id="cliente-busca" placeholder="Digite o nome ou CPF...">
                        <ul class="sugestoes" id="cliente-sugestoes" hidden></ul>
                        <small class="texto-suave" id="cliente-escolhido"></small>
                    </div>
                    <div class="campo">
                        <label for="vendedor_id">Vendedor *</label>
                        <select id="vendedor_id" name="vendedor_id" required>
                            <option value="" <?= (int) $estado['vendedor_id'] > 0 ? '' : 'selected' ?>>Selecione...</option>
                            <?php foreach ($vendedores as $v): ?>
                                <option value="<?= (int) $v['CODIGO'] ?>" <?= (int) $v['CODIGO'] === (int) $estado['vendedor_id'] ? 'selected' : '' ?>><?= e($v['NOME']) ?></option>
                            <?php endforeach; ?>
                        </select>
                    </div>
                </div>
            </section>

            <section class="cartao">
                <div class="cartao-titulo"><h2>Produtos</h2></div>
                <div class="campo campo-busca">
                    <label for="produto-busca">Buscar produto</label>
                    <input type="search" id="produto-busca" placeholder="Nome, referência, código ou fornecedor (mín. 2 letras)">
                    <ul class="sugestoes" id="produto-sugestoes" hidden></ul>
                </div>
                <div class="tabela-container">
                    <table class="tabela" id="tabela-itens">
                        <thead><tr><th>Produto</th><th>Preço</th><th>Qtd.</th><th class="texto-direita">Subtotal</th><th></th></tr></thead>
                        <tbody id="itens"></tbody>
                    </table>
                </div>
                <p class="estado-vazio" id="itens-vazio">Nenhum produto adicionado.</p>
            </section>
        </div>

        <aside class="coluna-pilha">
            <section class="cartao">
                <div class="cartao-titulo"><h2>Pagamento</h2></div>
                <div class="formulario-grade">
                    <div class="campo">
                        <label for="desconto">Desconto (R$)</label>
                        <input type="text" id="desconto" name="desconto" inputmode="decimal" value="<?= $estado['desconto'] > 0 ? e(number_format($estado['desconto'], 2, ',', '')) : '' ?>" placeholder="0,00">
                    </div>
                    <div class="campo">
                        <label for="frete">Frete (R$)</label>
                        <input type="text" id="frete" name="frete" inputmode="decimal" value="<?= $estado['frete'] > 0 ? e(number_format($estado['frete'], 2, ',', '')) : '' ?>" placeholder="0,00">
                    </div>
                </div>

                <!-- Crédito na loja da cliente (aparece quando a cliente é escolhida) -->
                <div class="bloco-credito" id="bloco-credito" hidden>
                    <p class="credito-saldo" id="credito-info"></p>
                    <div class="campo" id="campo-credito" hidden>
                        <label for="credito_usar">Pagar com crédito da loja (R$)</label>
                        <div class="linha-credito">
                            <input type="text" id="credito_usar" name="credito_usar" inputmode="decimal" placeholder="0,00"
                                   value="<?= $valorCredito > 0 ? e(number_format($valorCredito, 2, ',', '')) : '' ?>">
                            <button type="button" class="botao botao-secundario botao-pequeno" id="credito-maximo">Usar o máximo</button>
                        </div>
                        <small class="campo-erro" id="credito-aviso" hidden></small>
                    </div>
                </div>

                <div id="pagamentos"></div>
                <button type="button" class="botao botao-secundario botao-pequeno" id="add-pagamento"><?= icone('mais', 16) ?> Dividir pagamento</button>

                <dl class="lista-resumo">
                    <div><dt>Subtotal</dt><dd id="r-subtotal">R$ 0,00</dd></div>
                    <div><dt>Desconto</dt><dd id="r-desconto">R$ 0,00</dd></div>
                    <div id="linha-credito" hidden><dt>Crédito da loja</dt><dd id="r-credito">R$ 0,00</dd></div>
                    <div class="total"><dt>Total a pagar</dt><dd id="r-total">R$ 0,00</dd></div>
                    <div><dt>Pagamentos</dt><dd id="r-pago">R$ 0,00</dd></div>
                    <div><dt>Diferença</dt><dd id="r-dif">R$ 0,00</dd></div>
                </dl>

                <button type="submit" name="acao" value="finalizar" class="botao botao-primario botao-bloco" id="finalizar">Finalizar venda</button>
                <?php if (!$emCondicao && !$emTroca): ?>
                    <button type="submit" name="acao" value="condicao" class="botao botao-secundario botao-bloco" id="condicao"
                            title="A cliente separou as peças e vai pagar depois: as peças saem do estoque e a venda fica em Condição">Condição</button>
                    <small class="texto-suave dica-botoes">Condição: separa as peças para a cliente sem pagamento.</small>
                    <small class="texto-suave dica-botoes">Crediário: escolha "Crediário" na forma de pagamento (pode dividir com uma entrada).</small>
                <?php endif; ?>
                <small class="campo-erro dica-botoes" id="aviso-cliente" hidden>Selecione a cliente e o vendedor para continuar.</small>
            </section>
        </aside>
    </div>
</form>

<script>window.NOVA_VENDA = <?= json_encode($config, JSON_UNESCAPED_UNICODE | JSON_HEX_TAG | JSON_HEX_AMP) ?>;</script>
<script src="../JS/venda.js"></script>
<?php require __DIR__ . '/includes/rodape.php'; ?>
