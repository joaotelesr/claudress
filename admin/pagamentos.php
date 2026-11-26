<?php
/**
 * pagamentos.php - Formas de pagamento: cadastro (FORMA_PAGAMENTO) e quanto cada uma recebeu
 */
require_once __DIR__ . '/../config/config.php';
require_once RAIZ_PROJETO . '/funcoes/pagamentos_funcoes.php';

$ini = dataValida($_GET['data_ini'] ?? '');
$fim = dataValida($_GET['data_fim'] ?? '');
$dados   = resumoFormasPagamento($ini, $fim);
$formas  = $dados['formas'];

$tituloPagina = 'Formas de pagamento';
$cssPagina    = ['cadastros.css', 'vendas.css'];

require __DIR__ . '/includes/cabecalho.php';
?>

<div class="cabecalho-pagina">
    <p>Recebimentos nas vendas finalizadas: <strong><?= formatarMoeda($dados['liquido']) ?></strong></p>
</div>

<div class="cartao">
    <form method="get" class="barra-filtros">
        <div class="campo"><label for="data_ini">De</label><input type="date" id="data_ini" name="data_ini" value="<?= e($ini) ?>"></div>
        <div class="campo"><label for="data_fim">Até</label><input type="date" id="data_fim" name="data_fim" value="<?= e($fim) ?>"></div>
        <div class="barra-filtros-botoes">
            <button type="submit" class="botao botao-primario"><?= icone('busca', 16) ?> Filtrar</button>
            <a href="pagamentos.php" class="botao botao-secundario">Limpar</a>
        </div>
    </form>

    <div class="tabela-container">
        <table class="tabela">
            <thead>
                <tr><th>Forma</th><th>Pagamentos</th><th class="texto-direita">Total recebido</th><th>Participação</th><th>Nova venda</th></tr>
            </thead>
            <tbody>
                <?php foreach ($formas as $f): ?>
                    <tr>
                        <td><strong><?= e($f['rotulo']) ?></strong>
                            <?= !empty($f['bloqueada']) ? ' <span class="badge badge-erro">Bloqueada</span>' : '' ?></td>
                        <td><?= number_format((int) $f['qtd'], 0, ',', '.') ?></td>
                        <td class="texto-direita"><?= formatarMoeda($f['total']) ?></td>
                        <td>
                            <div class="barra-progresso" title="<?= number_format($f['percentual'], 1, ',', '.') ?>%">
                                <span style="width: <?= max(0, min(100, round($f['percentual']))) ?>%"></span>
                            </div>
                            <small><?= number_format($f['percentual'], 1, ',', '.') ?>%</small>
                        </td>
                        <td><?= !empty($f['nova_venda']) ? '<span class="badge badge-sucesso">Disponível</span>' : '<span class="texto-suave">-</span>' ?></td>
                    </tr>
                <?php endforeach; ?>
            </tbody>
        </table>
    </div>
</div>

<?php require __DIR__ . '/includes/rodape.php'; ?>
