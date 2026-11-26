<?php
/**
 * condicoes.php - Clientes em condição: peças separadas que ainda não foram pagas.
 * Ações: finalizar (abre a nova venda preenchida para receber o pagamento) ou cancelar (peças voltam ao estoque).
 */
require_once __DIR__ . '/../config/config.php';
require_once RAIZ_PROJETO . '/funcoes/vendas_funcoes.php';

// Cancelar condição (somente via POST)
if (ehPost() && ($_POST['acao'] ?? '') === 'cancelar') {
    $id = (int) ($_POST['id'] ?? 0);
    try {
        cancelarCondicao($id);
        definirMensagem('sucesso', 'Condição #' . $id . ' cancelada. As peças voltaram ao estoque.');
    } catch (RuntimeException $ex) {
        definirMensagem('erro', $ex->getMessage());
    } catch (PDOException $ex) {
        definirMensagem('erro', 'Não foi possível cancelar a condição.' . (DB_MOSTRAR_ERROS ? ' ' . $ex->getMessage() : ''));
    }
    redirecionar('condicoes.php');
}

$busca     = mb_substr(trim((string) ($_GET['busca'] ?? '')), 0, 100);
$condicoes = listarCondicoes($busca);

$totalValor  = array_sum(array_map(fn($c) => (float) $c['TOTAL'], $condicoes));
$totalPecas  = array_sum(array_map(fn($c) => (float) $c['NUM_PECAS'], $condicoes));
$qtdClientes = count(array_unique(array_column($condicoes, 'CODIGO_CLIENTE')));

$tituloPagina = 'Condição';
$cssPagina    = ['cadastros.css', 'vendas.css'];

require __DIR__ . '/includes/cabecalho.php';
?>

<div class="cabecalho-pagina">
    <p>
        <?= $qtdClientes ?> cliente(s) em condição · <?= e(formatarQuantidade($totalPecas)) ?> peça(s) separada(s)
        · <strong><?= formatarMoeda($totalValor) ?></strong> a receber
    </p>
    <a href="nova_venda.php" class="botao botao-primario"><?= icone('venda', 18) ?> Nova venda</a>
</div>

<div class="cartao">
    <form method="get" class="barra-filtros">
        <div class="campo">
            <label for="busca">Cliente ou nº da condição</label>
            <input type="search" id="busca" name="busca" value="<?= e($busca) ?>">
        </div>
        <div class="barra-filtros-botoes">
            <button type="submit" class="botao botao-primario"><?= icone('busca', 16) ?> Filtrar</button>
            <a href="condicoes.php" class="botao botao-secundario">Limpar</a>
        </div>
    </form>

    <?php if (empty($condicoes)): ?>
        <p class="estado-vazio"><?= $busca !== '' ? 'Nenhuma condição encontrada.' : 'Nenhuma cliente em condição no momento.' ?></p>
    <?php else: ?>
        <div class="tabela-container">
            <table class="tabela">
                <thead>
                    <tr>
                        <th>Nº</th><th>Cliente</th><th>Desde</th><th>Peças separadas</th>
                        <th class="texto-direita">Total</th><th></th>
                    </tr>
                </thead>
                <tbody>
                    <?php foreach ($condicoes as $c): ?>
                        <?php $nome = $c['CLIENTE_NOME'] ? trim((string) $c['CLIENTE_NOME']) : 'Sem cadastro'; ?>
                        <tr>
                            <td>#<?= (int) $c['CODIGO_VENDA'] ?></td>
                            <td class="celula-nome">
                                <strong><?= e($nome) ?></strong>
                                <small><?= $c['TELEFONE'] !== '' ? e(formatarTelefone($c['TELEFONE'])) : '' ?></small>
                            </td>
                            <td>
                                <?= formatarData($c['INICIO']) ?><br>
                                <span class="badge <?= $c['DIAS'] >= 7 ? 'badge-erro' : 'badge-aviso' ?>">
                                    <?= $c['DIAS'] === 0 ? 'hoje' : ($c['DIAS'] === 1 ? 'há 1 dia' : 'há ' . $c['DIAS'] . ' dias') ?>
                                </span>
                            </td>
                            <td>
                                <ul class="lista-pecas">
                                    <?php foreach ($c['PECAS'] as $p): ?>
                                        <li>
                                            <?= e(formatarQuantidade($p['QUANT'])) ?>× <?= e(trim((string) $p['DESCRICAO'])) ?>
                                            <?php if (trim((string) $p['TAMANHO']) !== ''): ?><small>Tam. <?= e(trim((string) $p['TAMANHO'])) ?></small><?php endif; ?>
                                        </li>
                                    <?php endforeach; ?>
                                </ul>
                            </td>
                            <td class="texto-direita"><strong><?= formatarMoeda($c['TOTAL']) ?></strong></td>
                            <td>
                                <div class="acoes-condicao">
                                    <a href="nova_venda.php?condicao=<?= (int) $c['CODIGO_VENDA'] ?>" class="botao botao-primario botao-pequeno">Finalizar venda</a>
                                    <a href="venda_detalhe.php?id=<?= (int) $c['CODIGO_VENDA'] ?>" class="botao botao-secundario botao-pequeno">Detalhes</a>
                                    <form method="post" class="form-inline"
                                          data-confirmar="Cancelar a condição #<?= (int) $c['CODIGO_VENDA'] ?> de &quot;<?= e($nome) ?>&quot;? As peças voltam ao estoque.">
                                        <input type="hidden" name="acao" value="cancelar">
                                        <input type="hidden" name="id" value="<?= (int) $c['CODIGO_VENDA'] ?>">
                                        <button type="submit" class="botao botao-perigo botao-pequeno">Cancelar</button>
                                    </form>
                                </div>
                            </td>
                        </tr>
                    <?php endforeach; ?>
                </tbody>
            </table>
        </div>
    <?php endif; ?>
</div>

<?php require __DIR__ . '/includes/rodape.php'; ?>
