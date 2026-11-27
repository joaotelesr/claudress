<?php
/**
 * aniversariantes_funcoes.php
 * Clientes que fazem aniversário num mês (coluna DATA_NASCD da tabela CLIENTE).
 * Usado pela tela admin/aniversariantes.php e pela exportação CSV.
 */

const MESES_DO_ANO = [
    1 => 'Janeiro', 2 => 'Fevereiro', 3 => 'Março', 4 => 'Abril', 5 => 'Maio', 6 => 'Junho',
    7 => 'Julho', 8 => 'Agosto', 9 => 'Setembro', 10 => 'Outubro', 11 => 'Novembro', 12 => 'Dezembro',
];

/** Lê e normaliza os filtros da tela (mês 1 a 12, padrão = mês atual; situação padrão = Ativo). */
function filtrosAniversariantes(array $get): array
{
    $mes = (int) ($get['mes'] ?? date('n'));
    if ($mes < 1 || $mes > 12) {
        $mes = (int) date('n');
    }

    $status = $get['status'] ?? 'Ativo';
    if (!in_array($status, ['Ativo', 'Pendente', 'Cancelado', ''], true)) {
        $status = 'Ativo';
    }

    return ['mes' => $mes, 'status' => $status];
}

/**
 * Clientes nascidos no mês, em ordem de dia e depois de nome.
 * A data é gravada como AAAA-MM-DD, então o mês são os caracteres 6 e 7 (funciona no MySQL e no SQLite de testes).
 * Cada linha traz CODIGO, NOME, DATA_NASCD, STATUS_CLI, TELEFONE e WHATSAPP.
 */
function listarAniversariantes(array $f): array
{
    $params = [':mes' => sprintf('%02d', $f['mes'])];
    $where  = "c.DATA_NASCD IS NOT NULL AND SUBSTR(c.DATA_NASCD, 6, 2) = :mes";

    if ($f['status'] !== '') {
        $where .= ' AND c.STATUS_CLI = :status';
        $params[':status'] = $f['status'];
    }

    $clientes = consultar(
        "SELECT c.CODIGO, c.NOME, c.DATA_NASCD, c.STATUS_CLI
           FROM CLIENTE c
          WHERE $where
          ORDER BY SUBSTR(c.DATA_NASCD, 9, 2), c.NOME, c.CODIGO",
        $params
    );

    // contatosPrincipais() fica em clientes_funcoes.php (a página inclui os dois arquivos)
    $contatos = contatosPrincipais(array_map('intval', array_column($clientes, 'CODIGO')));

    foreach ($clientes as &$cliente) {
        $c = $contatos[(int) $cliente['CODIGO']] ?? [];
        $cliente['TELEFONE'] = $c[CONTATO_CELULAR] ?? '';
        $cliente['WHATSAPP'] = $c[CONTATO_WHATSAPP] ?? '';
    }
    unset($cliente);

    return $clientes;
}

/** Idade que a cliente completa no aniversário deste ano (null se a data for inválida). */
function idadeQueCompleta($dataNasc): ?int
{
    $ano = (int) substr((string) $dataNasc, 0, 4);
    return $ano > 1900 ? (int) date('Y') - $ano : null;
}

/**
 * Envia o arquivo CSV (separado por vírgula) para download e encerra a página.
 * Colunas: Data de nascimento (dd/mm/aaaa) e Nome.
 * Começa com o BOM do UTF-8 para o Excel mostrar os acentos certos.
 */
function exportarAniversariantesCsv(array $clientes, int $mes): void
{
    $nomeArquivo = 'aniversariantes_' . sprintf('%02d', $mes) . '_' . mb_strtolower(MESES_DO_ANO[$mes]) . '.csv';
    $nomeArquivo = strtr($nomeArquivo, ['ç' => 'c']);

    header('Content-Type: text/csv; charset=UTF-8');
    header('Content-Disposition: attachment; filename="' . $nomeArquivo . '"');
    header('Cache-Control: no-store');

    $saida = fopen('php://output', 'w');
    fwrite($saida, "\xEF\xBB\xBF");
    fputcsv($saida, ['Data de nascimento', 'Nome'], ',', '"', '');

    foreach ($clientes as $c) {
        fputcsv($saida, [formatarData($c['DATA_NASCD']), trim((string) $c['NOME'])], ',', '"', '');
    }

    fclose($saida);
    exit;
}
