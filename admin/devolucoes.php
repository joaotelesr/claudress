<?php
/**
 * devolucoes.php - Devolução e troca de peças.
 * 1) Pesquise e escolha a cliente (ou digite o nº da venda).
 * 2) Aparecem as compras REALIZADAS dela (só vendas 'Finalizado'; condições e canceladas ficam fora).
 * 3) Informe quantas peças de cada produto voltam, escolha Devolução (dinheiro de volta) ou Troca (crédito)
 *    e confirme: as peças voltam ao estoque e a operação fica no histórico.
 * Troca: o crédito é usado na Nova venda (nova_venda.php?troca=ID), entrando como desconto.
 */
require_once __DIR__ . '/../config/config.php';
require_once RAIZ_PROJETO . '/funcoes/devolucoes_funcoes.php';

garantirTabelasDevolucao();

// ----- Sugestões da pesquisa de cliente (JSON, usado pelo devolucao.js enquanto digita) -----
if (($_GET['acao'] ?? '') === 'buscar_cliente') {
    header('Content-Type: application/json; charset=utf-8');
    $saida = [];
    foreach (buscarClientesParaDevolucao((string) ($_GET['q'] ?? ''), 10) as $c) {
        $saida[] = [
            'id' => (int) $c['CODIGO'], 'nome' => trim((string) $c['NOME']),
            'cpf' => $c['CPF'] ? formatarCpf($c['CPF']) : '', 'compras' => (int) $c['COMPRAS'],
        ];
    }
    echo json_encode($saida, JSON_UNESCAPED_UNICODE);
    exit;
}

// ----- Gravar a devolução (somente via POST) -----
if (ehPost() && ($_POST['acao'] ?? '') === 'devolver') {
    $vendaId   = (int) ($_POST['venda_id'] ?? 0);
    $clienteId = (int) ($_POST['cliente_id'] ?? 0);
    $volta     = $clienteId > 0 ? 'devolucoes.php?cliente=' . $clienteId : 'devolucoes.php?venda=' . $vendaId;

    try {
        $devId = registrarDevolucao($_POST);
        $dev   = buscarDevolucao($devId);
        $texto = rotuloTipoDevolucao($dev['TIPO']) . ' #' . $devId . ' registrada: ' . formatarQuantidade($dev['PECAS'])
               . ' peça(s), ' . formatarMoeda($dev['VALOR']) . '. As peças voltaram ao estoque.';
        if (str_contains((string) $dev['OBSERVACAO'], 'abatido no crediário')) {
            $texto .= ' Crediário: ' . explode(' · ', (string) $dev['OBSERVACAO'])[0] . '.';
        }
        definirMensagem('sucesso', $texto);
        redirecionar($volta . '&feito=' . $devId);
    } catch (RuntimeException $ex) {
        definirMensagem('erro', $ex->getMessage());
    } catch (PDOException $ex) {
        definirMensagem('erro', 'Não foi possível registrar a devolução.' . (DB_MOSTRAR_ERROS ? ' ' . $ex->getMessage() : ''));
    }
    redirecionar($volta . '#venda-' . $vendaId);
}

// ----- O que mostrar -----
$clienteId = (int) ($_GET['cliente'] ?? 0);
$vendaBusca = (int) ($_GET['venda'] ?? 0);
$produto   = mb_substr(trim((string) ($_GET['produto'] ?? '')), 0, 60);
$dataIni   = dataValida($_GET['data_ini'] ?? '');
$dataFim   = dataValida($_GET['data_fim'] ?? '');
$ordem     = ordemValida($_GET['ordem'] ?? '');
$filtrando = $produto !== '' || $dataIni !== '' || $dataFim !== '' || $ordem !== 'recentes';
$busca     = mb_substr(trim((string) ($_GET['busca'] ?? '')), 0, 100);
$achadas   = ($clienteId === 0 && $busca !== '') ? buscarClientesParaDevolucao($busca) : [];
$cliente   = null;
$vendas    = [];
$avisoVenda = '';

// Pelo nº da venda: abre a cliente dessa venda (ou só a venda, se ela não tiver cliente)
if ($vendaBusca > 0 && $clienteId === 0) {
    $v = vendaParaDevolucao($vendaBusca);
    if ($v === null) {
        $avisoVenda = 'A venda #' . $vendaBusca . ' não existe ou não é uma venda realizada (condições e vendas canceladas não entram).';
    } elseif ((int) $v['CODIGO_CLIENTE'] > 0 && buscarCliente((int) $v['CODIGO_CLIENTE']) !== null) {
        $extra = isset($_GET['feito']) ? '&feito=' . (int) $_GET['feito'] : '';
        redirecionar('devolucoes.php?cliente=' . (int) $v['CODIGO_CLIENTE'] . $extra . '#venda-' . $vendaBusca);
    } else {
        $vendas = [$v];
    }
}

if ($clienteId > 0) {
    $cliente = buscarCliente($clienteId);
    if ($cliente === null) {
        definirMensagem('erro', 'Cliente não encontrada.');
        redirecionar('devolucoes.php');
    }
    $vendas = vendasParaDevolucao($clienteId, $produto, $dataIni, $dataFim, $ordem);
}

$historico = listarDevolucoes($clienteId, $clienteId > 0 ? 50 : 20);

// Operação que acabou de ser gravada (para oferecer o próximo passo da troca)
$feito = (int) ($_GET['feito'] ?? 0) > 0 ? buscarDevolucao((int) $_GET['feito']) : null;

$tituloPagina = 'Devolução e troca';
$cssPagina    = ['cadastros.css', 'vendas.css', 'devolucoes.css', 'crediario.css'];

require __DIR__ . '/includes/cabecalho.php';
?>

<?php if ($feito && $feito['TIPO'] === TIPO_TROCA && empty($feito['CODIGO_VENDA_TROCA'])): ?>
    <div class="alerta alerta-aviso proximo-passo">
        <span>Troca #<?= (int) $feito['ID'] ?>: a cliente tem <strong><?= formatarMoeda($feito['VALOR']) ?></strong> de crédito.
        Agora escolha as peças novas; o crédito entra como desconto na venda.</span>
        <a href="nova_venda.php?troca=<?= (int) $feito['ID'] ?>" class="botao botao-primario botao-pequeno">Escolher peças novas</a>
    </div>
<?php endif; ?>

<?php if ($avisoVenda !== ''): ?>
    <div class="alerta alerta-erro" role="alert"><?= e($avisoVenda) ?></div>
<?php endif; ?>

<!-- 1) Cliente -->
<section class="cartao">
    <div class="cartao-titulo"><h2>1. Cliente</h2></div>

    <?php if ($cliente): ?>
        <div class="cliente-escolhida">
            <div class="celula-nome">
                <strong><?= e(trim((string) $cliente['NOME'])) ?></strong>
                <small><?= $cliente['CPF'] ? 'CPF ' . e(formatarCpf($cliente['CPF'])) : 'Sem CPF' ?> · Cliente #<?= (int) $cliente['CODIGO'] ?></small>
            </div>
            <a href="devolucoes.php" class="botao botao-secundario botao-pequeno">Trocar cliente</a>
        </div>
    <?php else: ?>
        <div class="formulario-grade">
            <form method="get" class="campo campo-largo campo-busca">
                <label for="cliente-busca">Pesquisar cliente</label>
                <div class="linha-busca-venda">
                    <input type="search" id="cliente-busca" name="busca" value="<?= e($busca) ?>"
                           placeholder="Nome, CPF ou telefone (mín. 2 letras)..." autocomplete="off" autofocus>
                    <button type="submit" class="botao botao-primario"><?= icone('busca', 16) ?> Pesquisar</button>
                </div>
                <ul class="sugestoes" id="cliente-sugestoes" hidden></ul>
            </form>
            <form method="get" class="campo">
                <label for="venda">Ou nº da venda</label>
                <div class="linha-busca-venda">
                    <input type="number" id="venda" name="venda" min="1" value="<?= $vendaBusca > 0 ? $vendaBusca : '' ?>" placeholder="Ex.: 1520">
                    <button type="submit" class="botao botao-secundario"><?= icone('busca', 16) ?></button>
                </div>
            </form>
        </div>

        <?php if ($busca !== ''): ?>
            <?php if (mb_strlen($busca) < 2): ?>
                <p class="estado-vazio">Digite pelo menos 2 letras.</p>
            <?php elseif (empty($achadas)): ?>
                <p class="estado-vazio">Nenhuma cliente encontrada para "<?= e($busca) ?>".</p>
            <?php else: ?>
                <div class="tabela-container resultado-clientes">
                    <table class="tabela">
                        <thead><tr><th>Cliente</th><th>CPF</th><th>Compras realizadas</th><th></th></tr></thead>
                        <tbody>
                            <?php foreach ($achadas as $c): ?>
                                <tr>
                                    <td class="celula-nome">
                                        <strong><?= e(trim((string) $c['NOME'])) ?></strong>
                                        <small>Cliente #<?= (int) $c['CODIGO'] ?><?= trim((string) $c['STATUS_CLI']) !== 'Ativo' ? ' · ' . e(trim((string) $c['STATUS_CLI'])) : '' ?></small>
                                    </td>
                                    <td><?= $c['CPF'] ? e(formatarCpf($c['CPF'])) : '-' ?></td>
                                    <td><?= (int) $c['COMPRAS'] ?></td>
                                    <td class="texto-direita">
                                        <a href="devolucoes.php?cliente=<?= (int) $c['CODIGO'] ?>" class="botao botao-primario botao-pequeno">Escolher</a>
                                    </td>
                                </tr>
                            <?php endforeach; ?>
                        </tbody>
                    </table>
                </div>
            <?php endif; ?>
        <?php endif; ?>
    <?php endif; ?>
</section>

<?php if ($cliente || !empty($vendas)): ?>
    <!-- 2) Compras realizadas -->
    <section class="coluna-pilha">
        <div class="cabecalho-pagina">
            <h2>2. Compras realizadas</h2>
            <?php if ($cliente): ?>
                <form method="get" class="barra-filtros filtro-produto">
                    <input type="hidden" name="cliente" value="<?= (int) $cliente['CODIGO'] ?>">
                    <div class="campo">
                        <input type="search" name="produto" value="<?= e($produto) ?>" placeholder="Filtrar por produto ou referência" aria-label="Filtrar por produto">
                    </div>
                    <div class="campo campo-data">
                        <input type="date" name="data_ini" value="<?= e($dataIni) ?>" aria-label="Compras a partir de" title="De">
                    </div>
                    <div class="campo campo-data">
                        <input type="date" name="data_fim" value="<?= e($dataFim) ?>" aria-label="Compras até" title="Até">
                    </div>
                    <div class="campo campo-ordem">
                        <select name="ordem" aria-label="Ordem das compras">
                            <?php foreach (ORDENS_VENDAS as $valor => $rotulo): ?>
                                <option value="<?= e($valor) ?>" <?= $ordem === $valor ? 'selected' : '' ?>><?= e($rotulo) ?></option>
                            <?php endforeach; ?>
                        </select>
                    </div>
                    <div class="barra-filtros-botoes">
                        <button type="submit" class="botao botao-secundario"><?= icone('busca', 16) ?> Filtrar</button>
                        <?php if ($filtrando): ?><a href="devolucoes.php?cliente=<?= (int) $cliente['CODIGO'] ?>" class="botao botao-secundario">Limpar</a><?php endif; ?>
                    </div>
                </form>
            <?php endif; ?>
        </div>

        <?php if (empty($vendas)): ?>
            <div class="cartao">
                <p class="estado-vazio">
                    <?= $filtrando ? 'Nenhuma compra encontrada com esses filtros.' : 'Esta cliente não tem compras realizadas.' ?>
                </p>
            </div>
        <?php elseif (count($vendas) >= DEVOLUCAO_LIMITE_VENDAS): ?>
            <p class="texto-suave">Mostrando as <?= DEVOLUCAO_LIMITE_VENDAS ?> compras <?= $ordem === 'antigas' ? 'mais antigas' : 'mais recentes' ?>. Use o período, o filtro de produto ou o nº da venda para achar as outras.</p>
        <?php endif; ?>

        <?php foreach ($vendas as $v): ?>
            <?php $codigo = (int) $v['CODIGO_VENDA']; ?>
            <form method="post" class="cartao form-devolucao<?= $v['DISPONIVEL'] < 1 ? ' venda-devolvida' : '' ?>" id="venda-<?= $codigo ?>">
                <input type="hidden" name="acao" value="devolver">
                <input type="hidden" name="venda_id" value="<?= $codigo ?>">
                <input type="hidden" name="cliente_id" value="<?= $cliente ? (int) $cliente['CODIGO'] : 0 ?>">

                <div class="cartao-titulo">
                    <div>
                        <h2>Venda #<?= $codigo ?></h2>
                        <small class="texto-suave">
                            <?= formatarData($v['DATA']) ?> <?= e(formatarHora($v['HORA'])) ?>
                            · <?= e(formatarQuantidade($v['NUM_PECAS'])) ?> peça(s) · <?= formatarMoeda($v['TOTAL']) ?>
                            <?= (float) $v['DESCONTO'] > 0 ? '(desconto de ' . formatarMoeda($v['DESCONTO']) . ')' : '' ?>
                        </small>
                    </div>
                    <div class="acoes-condicao">
                        <?php if ($v['DISPONIVEL'] < 1): ?><span class="badge">Tudo devolvido</span><?php endif; ?>
                        <a href="venda_detalhe.php?id=<?= $codigo ?>" class="botao botao-secundario botao-pequeno">Detalhes</a>
                    </div>
                </div>

                <div class="tabela-container">
                    <table class="tabela">
                        <thead>
                            <tr>
                                <th>Produto</th><th>Tam.</th><th>Comprou</th><th>Já devolvido</th>
                                <th>Valor pago (un.)</th><th>Devolver</th><th class="texto-direita">Valor</th>
                            </tr>
                        </thead>
                        <tbody>
                            <?php foreach ($v['ITENS'] as $i): ?>
                                <tr<?= $i['DISPONIVEL'] < 1 ? ' class="linha-devolvida"' : '' ?>>
                                    <td class="celula-nome">
                                        <strong><?= e(trim((string) $i['DESCRICAO'])) ?></strong>
                                        <small>Cód. <?= (int) $i['CODIGO_PRODUTO'] ?> · estoque atual <?= e(formatarQuantidade($i['ESTOQUE'] ?? 0)) ?></small>
                                    </td>
                                    <td><?= e(trim((string) $i['TAMANHO']) ?: '-') ?></td>
                                    <td><?= e(formatarQuantidade($i['QUANT'])) ?></td>
                                    <td><?= $i['DEVOLVIDO'] > 0 ? e(formatarQuantidade($i['DEVOLVIDO'])) : '-' ?></td>
                                    <td><?= formatarMoeda($i['VALOR_PAGO_UNIT']) ?></td>
                                    <td>
                                        <?php if ($i['DISPONIVEL'] > 0): ?>
                                            <input type="number" class="campo-qtd qtd-devolver" name="qtd[<?= (int) $i['CODIGO_ITEM_VENDA'] ?>]"
                                                   min="0" max="<?= (int) $i['DISPONIVEL'] ?>" step="1" value="0"
                                                   data-valor="<?= e(paraBanco((float) $i['VALOR_PAGO_UNIT'])) ?>"
                                                   aria-label="Quantidade a devolver de <?= e(trim((string) $i['DESCRICAO'])) ?>">
                                            <small class="texto-suave">de <?= (int) $i['DISPONIVEL'] ?></small>
                                        <?php else: ?>
                                            <span class="badge">Devolvido</span>
                                        <?php endif; ?>
                                    </td>
                                    <td class="texto-direita valor-linha">-</td>
                                </tr>
                            <?php endforeach; ?>
                            <?php if (empty($v['ITENS'])): ?>
                                <tr><td colspan="7" class="estado-vazio">Venda sem itens registrados.</td></tr>
                            <?php endif; ?>
                        </tbody>
                    </table>
                </div>

                <?php if ($v['DISPONIVEL'] > 0): ?>
                    <div class="opcoes-devolucao">
                        <fieldset class="campo tipo-devolucao">
                            <legend>O que a cliente quer?</legend>
                            <label><input type="radio" name="tipo" value="<?= TIPO_DEVOLUCAO ?>" checked> Devolução (dinheiro de volta)</label>
                            <label><input type="radio" name="tipo" value="<?= TIPO_TROCA ?>"> Troca (vira crédito para outra peça)</label>
                        </fieldset>
                        <div class="campo">
                            <label for="motivo-<?= $codigo ?>">Motivo</label>
                            <select id="motivo-<?= $codigo ?>" name="motivo">
                                <?php foreach (MOTIVOS_DEVOLUCAO as $m): ?><option><?= e($m) ?></option><?php endforeach; ?>
                            </select>
                        </div>
                        <div class="campo campo-reembolso">
                            <label for="reembolso-<?= $codigo ?>">Devolver o dinheiro em</label>
                            <select id="reembolso-<?= $codigo ?>" name="reembolso">
                                <?php foreach (FORMAS_REEMBOLSO as $f): ?><option><?= e($f) ?></option><?php endforeach; ?>
                            </select>
                        </div>
                        <div class="campo">
                            <label for="obs-<?= $codigo ?>">Observação</label>
                            <input type="text" id="obs-<?= $codigo ?>" name="observacao" maxlength="255" placeholder="Opcional">
                        </div>
                    </div>

                    <?php $saldoCred = saldoCrediarioVenda($codigo); ?>
                    <?php if ($saldoCred > 0): ?>
                        <div class="alerta alerta-aviso aviso-crediario">
                            Venda no crediário com <strong><?= formatarMoeda($saldoCred) ?></strong> ainda em aberto.
                            Na <strong>devolução</strong>, o valor das peças é abatido primeiro das parcelas em aberto (da última para a primeira);
                            só o que passar disso é devolvido na forma escolhida. Na <strong>troca</strong>, as parcelas continuam iguais.
                            <a href="crediario.php?busca=<?= $codigo ?>&amp;situacao=todos">Ver parcelas</a>
                        </div>
                    <?php endif; ?>

                    <div class="rodape-devolucao">
                        <button type="button" class="botao botao-secundario botao-pequeno devolver-tudo">Marcar todas as peças</button>
                        <span class="resumo-devolucao">Nenhuma peça marcada</span>
                        <button type="submit" class="botao botao-primario confirmar-devolucao">Confirmar devolução</button>
                    </div>
                <?php endif; ?>
            </form>
        <?php endforeach; ?>
    </section>
<?php endif; ?>

<!-- 3) Histórico -->
<section class="cartao">
    <div class="cartao-titulo">
        <h2><?= $cliente ? 'Devoluções e trocas desta cliente' : 'Últimas devoluções e trocas' ?></h2>
    </div>
    <?php if (empty($historico)): ?>
        <p class="estado-vazio">Nenhuma devolução ou troca registrada<?= $cliente ? ' para esta cliente' : '' ?>.</p>
    <?php else: ?>
        <div class="tabela-container">
            <table class="tabela">
                <thead>
                    <tr>
                        <th>Nº</th><th>Data</th><?php if (!$cliente): ?><th>Cliente</th><?php endif; ?><th>Venda</th>
                        <th>Tipo</th><th>Peças</th><th class="texto-direita">Valor</th><th>Situação</th>
                    </tr>
                </thead>
                <tbody>
                    <?php foreach ($historico as $d): ?>
                        <tr<?= $feito && (int) $feito['ID'] === (int) $d['ID'] ? ' class="linha-destaque"' : '' ?>>
                            <td>#<?= (int) $d['ID'] ?></td>
                            <td><?= formatarData($d['DATA']) ?> <small class="texto-suave"><?= e(substr((string) $d['DATA'], 11, 5)) ?></small></td>
                            <?php if (!$cliente): ?>
                                <td class="celula-nome">
                                    <?php if ($d['CODIGO_CLIENTE']): ?>
                                        <a href="devolucoes.php?cliente=<?= (int) $d['CODIGO_CLIENTE'] ?>"><?= e(trim((string) $d['CLIENTE_NOME'])) ?></a>
                                    <?php else: ?>Sem cadastro<?php endif; ?>
                                </td>
                            <?php endif; ?>
                            <td><a href="venda_detalhe.php?id=<?= (int) $d['CODIGO_VENDA'] ?>">#<?= (int) $d['CODIGO_VENDA'] ?></a></td>
                            <td>
                                <span class="badge <?= $d['TIPO'] === TIPO_TROCA ? 'badge-info' : 'badge-aviso' ?>"><?= e(rotuloTipoDevolucao($d['TIPO'])) ?></span>
                                <?php if ($d['MOTIVO']): ?><small class="texto-suave bloco"><?= e($d['MOTIVO']) ?></small><?php endif; ?>
                            </td>
                            <td>
                                <ul class="lista-pecas">
                                    <?php foreach ($d['ITENS'] as $p): ?>
                                        <li>
                                            <?= e(formatarQuantidade($p['QUANT'])) ?>× <?= e(trim((string) $p['DESCRICAO'])) ?>
                                            <?php if (trim((string) $p['TAMANHO']) !== ''): ?><small>Tam. <?= e(trim((string) $p['TAMANHO'])) ?></small><?php endif; ?>
                                        </li>
                                    <?php endforeach; ?>
                                </ul>
                                <?php if ($d['OBSERVACAO']): ?><small class="texto-suave"><?= e($d['OBSERVACAO']) ?></small><?php endif; ?>
                            </td>
                            <td class="texto-direita"><strong><?= formatarMoeda($d['VALOR']) ?></strong></td>
                            <td>
                                <?php if ($d['TIPO'] === TIPO_TROCA): ?>
                                    <?php if ($d['CODIGO_VENDA_TROCA']): ?>
                                        Crédito usado na <a href="venda_detalhe.php?id=<?= (int) $d['CODIGO_VENDA_TROCA'] ?>">venda #<?= (int) $d['CODIGO_VENDA_TROCA'] ?></a>
                                    <?php else: ?>
                                        <span class="badge badge-aviso">Crédito em aberto</span>
                                        <a href="nova_venda.php?troca=<?= (int) $d['ID'] ?>" class="botao botao-primario botao-pequeno">Usar crédito</a>
                                    <?php endif; ?>
                                <?php else: ?>
                                    Reembolso: <?= e($d['REEMBOLSO'] ?: '-') ?>
                                <?php endif; ?>
                            </td>
                        </tr>
                    <?php endforeach; ?>
                </tbody>
            </table>
        </div>
    <?php endif; ?>
</section>

<script src="../JS/devolucao.js"></script>
<?php require __DIR__ . '/includes/rodape.php'; ?>
