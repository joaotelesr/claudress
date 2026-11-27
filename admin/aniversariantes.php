<?php
/**
 * aniversariantes.php - Clientes que fazem aniversário no mês escolhido + exportação para Excel (CSV).
 * Exportar: aniversariantes.php?mes=5&status=Ativo&exportar=csv
 */
require_once __DIR__ . '/../config/config.php';
require_once RAIZ_PROJETO . '/funcoes/clientes_funcoes.php';
require_once RAIZ_PROJETO . '/funcoes/aniversariantes_funcoes.php';

$filtros  = filtrosAniversariantes($_GET);
$clientes = listarAniversariantes($filtros);

if (($_GET['exportar'] ?? '') === 'csv') {
    exportarAniversariantesCsv($clientes, $filtros['mes']);
}

$nomeMes    = MESES_DO_ANO[$filtros['mes']];
$diaHoje    = (int) date('j');
$ehMesAtual = $filtros['mes'] === (int) date('n');
$urlCsv     = 'aniversariantes.php?' . http_build_query($filtros + ['exportar' => 'csv']);

$tituloPagina = 'Aniversariantes';
$cssPagina    = ['cadastros.css'];

require __DIR__ . '/includes/cabecalho.php';
?>

<div class="cabecalho-pagina">
    <p><?= count($clientes) ?> cliente(s) fazem aniversário em <?= e($nomeMes) ?></p>
    <?php if (!empty($clientes)): ?>
        <a href="<?= e($urlCsv) ?>" class="botao botao-primario"><?= icone('extrato', 18) ?> Gerar relatório Excel (CSV)</a>
    <?php endif; ?>
</div>

<div class="cartao">
    <form method="get" class="barra-filtros">
        <div class="campo">
            <label for="mes">Mês de nascimento</label>
            <select id="mes" name="mes">
                <?php foreach (MESES_DO_ANO as $numero => $nome): ?>
                    <option value="<?= $numero ?>" <?= $filtros['mes'] === $numero ? 'selected' : '' ?>><?= e($nome) ?></option>
                <?php endforeach; ?>
            </select>
        </div>
        <div class="campo">
            <label for="status">Situação</label>
            <select id="status" name="status">
                <option value="Ativo" <?= $filtros['status'] === 'Ativo' ? 'selected' : '' ?>>Ativos</option>
                <option value="Pendente" <?= $filtros['status'] === 'Pendente' ? 'selected' : '' ?>>Pendentes</option>
                <option value="Cancelado" <?= $filtros['status'] === 'Cancelado' ? 'selected' : '' ?>>Cancelados</option>
                <option value="" <?= $filtros['status'] === '' ? 'selected' : '' ?>>Todos</option>
            </select>
        </div>
        <div class="barra-filtros-botoes">
            <button type="submit" class="botao botao-primario"><?= icone('busca', 16) ?> Filtrar</button>
            <a href="aniversariantes.php" class="botao botao-secundario">Mês atual</a>
        </div>
    </form>

    <?php if (empty($clientes)): ?>
        <p class="estado-vazio">Nenhuma cliente com aniversário em <?= e($nomeMes) ?>.</p>
    <?php else: ?>
        <div class="tabela-container">
            <table class="tabela">
                <thead>
                    <tr>
                        <th>Dia</th>
                        <th>Data de nascimento</th>
                        <th>Cliente</th>
                        <th>Completa</th>
                        <th>Telefone</th>
                        <th class="texto-direita">Ações</th>
                    </tr>
                </thead>
                <tbody>
                    <?php foreach ($clientes as $c): ?>
                        <?php
                        $dia   = (int) substr((string) $c['DATA_NASCD'], 8, 2);
                        $idade = idadeQueCompleta($c['DATA_NASCD']);
                        $fone  = $c['WHATSAPP'] ?: $c['TELEFONE'];
                        ?>
                        <tr>
                            <td>
                                <strong><?= sprintf('%02d', $dia) ?></strong>
                                <?= $ehMesAtual && $dia === $diaHoje ? ' ' . badgeStatus('Hoje') : '' ?>
                            </td>
                            <td><?= formatarData($c['DATA_NASCD']) ?></td>
                            <td class="celula-nome">
                                <strong><?= e(trim((string) $c['NOME'])) ?></strong>
                                <?php if ($c['STATUS_CLI'] !== 'Ativo'): ?>
                                    <small><?= badgeStatus($c['STATUS_CLI']) ?></small>
                                <?php endif; ?>
                            </td>
                            <td><?= $idade !== null ? $idade . ' anos' : '-' ?></td>
                            <td><?= $fone ? e(formatarTelefone($fone)) : '-' ?></td>
                            <td>
                                <div class="acoes">
                                    <a href="cliente_form.php?id=<?= (int) $c['CODIGO'] ?>" class="botao botao-secundario botao-pequeno" title="Ver cadastro">
                                        <?= icone('editar', 16) ?>
                                    </a>
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
