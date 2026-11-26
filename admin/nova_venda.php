<?php
/**
 * nova_venda.php - Registro de venda (VENDAS + ITENS_VENDA + FORMA_PAGAMENTO_VENDA + baixa de estoque)
 * O navegador só envia ids e quantidades; preços e estoque são sempre relidos do banco.
 */
require_once __DIR__ . '/../config/config.php';
require_once RAIZ_PROJETO . '/funcoes/vendas_funcoes.php';

$erros  = [];
$estado = [
    'cliente' => null, 'vendedor_id' => CODIGO_USUARIO_PADRAO, 'itens' => [],
    'pagamentos' => [], 'desconto' => 0, 'frete' => 0,
];

if (ehPost()) {
    $r      = prepararNovaVenda($_POST);
    $erros  = $r['erros'];
    $estado = $r['estado'];

    if (empty($erros)) {
        try {
            $codigo = registrarVenda($r['venda']);
            definirMensagem('sucesso', 'Venda #' . $codigo . ' registrada com sucesso.');
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
];

$tituloPagina = 'Nova venda';
$cssPagina    = ['cadastros.css', 'vendas.css'];

require __DIR__ . '/includes/cabecalho.php';
?>

<?php if (!empty($erros)): ?>
    <div class="alerta alerta-erro" role="alert">
        <ul class="lista-erros">
            <?php foreach ($erros as $m): ?><li><?= e($m) ?></li><?php endforeach; ?>
        </ul>
    </div>
<?php endif; ?>

<form method="post" id="form-venda" autocomplete="off">
    <div class="venda-layout">

        <div class="coluna-pilha">
            <section class="cartao">
                <div class="cartao-titulo"><h2>Cliente e vendedor</h2></div>
                <div class="formulario-grade">
                    <div class="campo campo-largo campo-busca">
                        <label for="cliente-busca">Cliente (opcional)</label>
                        <input type="hidden" name="cliente_id" id="cliente-id" value="">
                        <input type="search" id="cliente-busca" placeholder="Digite o nome ou CPF...">
                        <ul class="sugestoes" id="cliente-sugestoes" hidden></ul>
                        <small class="texto-suave" id="cliente-escolhido"></small>
                    </div>
                    <div class="campo">
                        <label for="vendedor_id">Vendedor</label>
                        <select id="vendedor_id" name="vendedor_id">
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
                    <input type="search" id="produto-busca" placeholder="Nome, referência ou código (mín. 2 letras)">
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

                <div id="pagamentos"></div>
                <button type="button" class="botao botao-secundario botao-pequeno" id="add-pagamento"><?= icone('mais', 16) ?> Dividir pagamento</button>

                <dl class="lista-resumo">
                    <div><dt>Subtotal</dt><dd id="r-subtotal">R$ 0,00</dd></div>
                    <div><dt>Desconto</dt><dd id="r-desconto">R$ 0,00</dd></div>
                    <div class="total"><dt>Total</dt><dd id="r-total">R$ 0,00</dd></div>
                    <div><dt>Pagamentos</dt><dd id="r-pago">R$ 0,00</dd></div>
                    <div><dt>Diferença</dt><dd id="r-dif">R$ 0,00</dd></div>
                </dl>

                <button type="submit" class="botao botao-primario botao-bloco" id="finalizar">Finalizar venda</button>
            </section>
        </aside>
    </div>
</form>

<script>window.NOVA_VENDA = <?= json_encode($config, JSON_UNESCAPED_UNICODE | JSON_HEX_TAG | JSON_HEX_AMP) ?>;</script>
<script src="../JS/venda.js"></script>
<?php require __DIR__ . '/includes/rodape.php'; ?>
