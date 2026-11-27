<?php
/**
 * relatorios_funcoes.php
 * Relatórios de vendas: evolução por período (dia, mês ou ano), vendas por estado (UF do cadastro da cliente),
 * cidades de um estado e produtos mais vendidos. Usado por admin/relatorios.php.
 *
 * Regras:
 *   - Só vendas com STATUS_VENDA = 'Finalizado' entram (condições e canceladas ficam fora).
 *   - O estado é o campo UF do cadastro da cliente (CLIENTE.UF). Vendas sem cliente ou com cliente sem UF
 *     aparecem como "Sem estado no cadastro".
 *   - Devoluções: a venda original nunca é alterada, então o "Vendido" é sempre o valor bruto das vendas.
 *     A coluna "Devolvido" soma só as devoluções em DINHEIRO (tipo 'Devolucao', menos as 'Sem reembolso'),
 *     pela data em que a devolução foi feita. Trocas não entram: o crédito da troca já aparece como desconto
 *     na venda nova, então descontar de novo contaria em dobro. Líquido = Vendido - Devolvido.
 *
 * Este arquivo só ACRESCENTA funções novas; nenhuma função existente foi alterada.
 */

require_once __DIR__ . '/vendas_funcoes.php';
require_once __DIR__ . '/devolucoes_funcoes.php';

const AGRUPAMENTOS_RELATORIO = ['dia' => 'Por dia', 'mes' => 'Por mês', 'ano' => 'Por ano'];

const ATALHOS_RELATORIO = [
    '6m'      => 'Últimos 6 meses',
    '12m'     => 'Últimos 12 meses',
    'ano'     => 'Este ano',
    'ano_ant' => 'Ano passado',
    'tudo'    => 'Desde a primeira venda',
];

const ESTADOS_BRASIL = [
    'AC' => 'Acre', 'AL' => 'Alagoas', 'AP' => 'Amapá', 'AM' => 'Amazonas', 'BA' => 'Bahia', 'CE' => 'Ceará',
    'DF' => 'Distrito Federal', 'ES' => 'Espírito Santo', 'GO' => 'Goiás', 'MA' => 'Maranhão', 'MT' => 'Mato Grosso',
    'MS' => 'Mato Grosso do Sul', 'MG' => 'Minas Gerais', 'PA' => 'Pará', 'PB' => 'Paraíba', 'PR' => 'Paraná',
    'PE' => 'Pernambuco', 'PI' => 'Piauí', 'RJ' => 'Rio de Janeiro', 'RN' => 'Rio Grande do Norte',
    'RS' => 'Rio Grande do Sul', 'RO' => 'Rondônia', 'RR' => 'Roraima', 'SC' => 'Santa Catarina', 'SP' => 'São Paulo',
    'SE' => 'Sergipe', 'TO' => 'Tocantins',
];

// Valor usado no filtro para "vendas sem estado no cadastro"
const UF_SEM_ESTADO = 'SEM';

// Até quantos pontos o gráfico mostra (por dia num período longo ficaria ilegível)
const RELATORIO_MAX_PONTOS = 120;

// ---------- Filtros ----------

/** Data da primeira venda finalizada ('' se não houver vendas). */
function dataPrimeiraVenda(): string
{
    $d = consultarValor("SELECT MIN(DATA) FROM VENDAS WHERE STATUS_VENDA = 'Finalizado'");
    return $d ? substr((string) $d, 0, 10) : '';
}

/** Período de um atalho: ['ini' => 'AAAA-MM-DD', 'fim' => 'AAAA-MM-DD']. */
function periodoDoAtalho(string $atalho): array
{
    $hoje = date('Y-m-d');
    switch ($atalho) {
        case '6m':
            return ['ini' => date('Y-m-d', strtotime(date('Y-m-01') . ' -5 months')), 'fim' => $hoje];
        case 'ano':
            return ['ini' => date('Y-01-01'), 'fim' => $hoje];
        case 'ano_ant':
            $ano = (int) date('Y') - 1;
            return ['ini' => $ano . '-01-01', 'fim' => $ano . '-12-31'];
        case 'tudo':
            return ['ini' => dataPrimeiraVenda() ?: date('Y-m-01'), 'fim' => $hoje];
        default: // 12m
            return ['ini' => date('Y-m-d', strtotime(date('Y-m-01') . ' -11 months')), 'fim' => $hoje];
    }
}

/** Lê os filtros da tela. Sem datas, usa os últimos 12 meses. */
function filtrosRelatorio(array $get): array
{
    $atalho = trim((string) ($get['atalho'] ?? ''));
    $ini    = dataValida($get['data_ini'] ?? '');
    $fim    = dataValida($get['data_fim'] ?? '');

    if (array_key_exists($atalho, ATALHOS_RELATORIO)) {
        $p   = periodoDoAtalho($atalho);
        $ini = $p['ini'];
        $fim = $p['fim'];
    } else {
        $atalho = '';
        if ($ini === '' && $fim === '') {
            $atalho = '12m';
            $p      = periodoDoAtalho('12m');
            $ini    = $p['ini'];
            $fim    = $p['fim'];
        }
        if ($ini === '') {
            $ini = dataPrimeiraVenda() ?: $fim;
        }
        if ($fim === '') {
            $fim = date('Y-m-d');
        }
    }
    if ($ini > $fim) {
        [$ini, $fim] = [$fim, $ini];
    }

    $uf = mb_strtoupper(trim((string) ($get['uf'] ?? '')));
    if ($uf !== UF_SEM_ESTADO && !preg_match('/^[A-Z]{2}$/', $uf)) {
        $uf = '';
    }

    $agrupar = trim((string) ($get['agrupar'] ?? ''));
    if (!array_key_exists($agrupar, AGRUPAMENTOS_RELATORIO)) {
        $agrupar = agrupamentoAutomatico($ini, $fim);
    }

    // Muitos pontos deixam o gráfico ilegível: passa para o agrupamento maior e avisa na tela
    $ajustado = false;
    while ($agrupar !== 'ano' && count(chavesDoPeriodo($ini, $fim, $agrupar)) > RELATORIO_MAX_PONTOS) {
        $agrupar  = $agrupar === 'dia' ? 'mes' : 'ano';
        $ajustado = true;
    }

    return [
        'data_ini' => $ini, 'data_fim' => $fim, 'atalho' => $atalho, 'uf' => $uf, 'agrupar' => $agrupar,
        'agrupar_ajustado' => $ajustado,
    ];
}

/** Até ~2 meses: por dia; até 8 anos: por mês; acima disso: por ano. */
function agrupamentoAutomatico(string $ini, string $fim): string
{
    $dias = (int) (new DateTime($ini))->diff(new DateTime($fim))->days;
    if ($dias <= 62) {
        return 'dia';
    }
    return $dias <= 366 * 8 ? 'mes' : 'ano';
}

/** Nome do estado: 'SP' → 'São Paulo'; UF desconhecida volta como está. */
function nomeEstado(string $uf): string
{
    if ($uf === '' || $uf === UF_SEM_ESTADO) {
        return 'Sem estado no cadastro';
    }
    return ESTADOS_BRASIL[$uf] ?? $uf;
}

/** Expressão SQL da UF da cliente (maiúscula, sem espaços; vazia quando não tem). */
function sqlUfCliente(string $alias = 'c'): string
{
    return "COALESCE(UPPER(TRIM($alias.UF)), '')";
}

/** Condição do filtro de estado (para a tabela CLIENTE com o alias informado). */
function whereUfRelatorio(string $uf, array &$params, string $alias = 'c'): string
{
    if ($uf === '') {
        return '';
    }
    if ($uf === UF_SEM_ESTADO) {
        return ' AND ' . sqlUfCliente($alias) . " = ''";
    }
    $params[':uf'] = $uf;
    return ' AND ' . sqlUfCliente($alias) . ' = :uf';
}

/** Dia seguinte (para comparar colunas DATETIME com "até o fim do dia"). */
function diaSeguinte(string $data): string
{
    return date('Y-m-d', strtotime($data . ' +1 day'));
}

// ---------- Totais do período ----------

/** Vendido, quantidade, peças, clientes, devolvido e líquido no período (com o filtro de estado). */
function resumoRelatorio(string $ini, string $fim, string $uf = ''): array
{
    $params = [':ini' => $ini, ':fim' => $fim];
    $filtro = whereUfRelatorio($uf, $params);

    $r = consultarUma(
        "SELECT COUNT(*) AS QTD, COALESCE(SUM(v.TOTAL), 0) AS TOTAL, COALESCE(SUM(v.NUM_PECAS), 0) AS PECAS,
                COALESCE(SUM(v.DESCONTO), 0) AS DESCONTO, COUNT(DISTINCT v.CODIGO_CLIENTE) AS CLIENTES
           FROM VENDAS v
           LEFT JOIN CLIENTE c ON c.CODIGO = v.CODIGO_CLIENTE
          WHERE v.STATUS_VENDA = 'Finalizado' AND v.DATA >= :ini AND v.DATA <= :fim $filtro",
        $params
    ) ?? [];

    $qtd       = (int) ($r['QTD'] ?? 0);
    $total     = (float) ($r['TOTAL'] ?? 0);
    $devolvido = totalDevolvidoPeriodo($ini, $fim, $uf);

    return [
        'qtd'       => $qtd,
        'total'     => $total,
        'pecas'     => (float) ($r['PECAS'] ?? 0),
        'desconto'  => (float) ($r['DESCONTO'] ?? 0),
        'clientes'  => (int) ($r['CLIENTES'] ?? 0),
        'ticket'    => $qtd > 0 ? $total / $qtd : 0.0,
        'devolvido' => $devolvido,
        'liquido'   => $total - $devolvido,
    ];
}

/** Soma das devoluções em dinheiro feitas no período (trocas e 'Sem reembolso' não entram). */
function totalDevolvidoPeriodo(string $ini, string $fim, string $uf = ''): float
{
    garantirTabelasDevolucao();
    $params = [':ini' => $ini, ':ate' => diaSeguinte($fim), ':tipo' => TIPO_DEVOLUCAO];
    $filtro = whereUfRelatorio($uf, $params);

    return (float) consultarValor(
        "SELECT COALESCE(SUM(d.VALOR), 0)
           FROM DEVOLUCAO d
           LEFT JOIN CLIENTE c ON c.CODIGO = d.CODIGO_CLIENTE
          WHERE d.TIPO = :tipo AND COALESCE(d.REEMBOLSO, '') <> 'Sem reembolso'
            AND d.DATA >= :ini AND d.DATA < :ate $filtro",
        $params
    );
}

/** Mesmo intervalo de dias logo antes do período (para comparar). */
function periodoAnterior(string $ini, string $fim): array
{
    $dias = (int) (new DateTime($ini))->diff(new DateTime($fim))->days;
    $fimAnt = date('Y-m-d', strtotime($ini . ' -1 day'));
    $iniAnt = date('Y-m-d', strtotime($fimAnt . ' -' . $dias . ' days'));
    return ['ini' => $iniAnt, 'fim' => $fimAnt];
}

/** Variação percentual (null quando não dá para comparar). */
function variacaoPercentual(float $atual, float $anterior): ?float
{
    if ($anterior <= 0) {
        return null;
    }
    return ($atual - $anterior) / $anterior * 100;
}

// ---------- Evolução (gráfico) ----------

/** Tamanho do texto da data para cada agrupamento: dia AAAA-MM-DD, mês AAAA-MM, ano AAAA. */
function tamanhoChaveAgrupamento(string $agrupar): int
{
    return ['dia' => 10, 'mes' => 7, 'ano' => 4][$agrupar] ?? 7;
}

/** Todas as chaves do período, em ordem (inclui as sem venda). */
function chavesDoPeriodo(string $ini, string $fim, string $agrupar): array
{
    $chaves = [];
    $d      = new DateTime($ini);
    $limite = new DateTime($fim);

    if ($agrupar === 'ano') {
        for ($a = (int) $d->format('Y'); $a <= (int) $limite->format('Y'); $a++) {
            $chaves[] = (string) $a;
        }
        return $chaves;
    }
    if ($agrupar === 'mes') {
        $d->modify('first day of this month');
        while ($d->format('Y-m') <= $limite->format('Y-m')) {
            $chaves[] = $d->format('Y-m');
            $d->modify('+1 month');
        }
        return $chaves;
    }
    while ($d <= $limite) {
        $chaves[] = $d->format('Y-m-d');
        $d->modify('+1 day');
    }
    return $chaves;
}

/**
 * Vendas agrupadas por dia, mês ou ano, com zero nos períodos sem venda:
 * [['chave' => '2026-11', 'rotulo' => 'Nov/26', 'ini' => ..., 'fim' => ..., 'qtd', 'pecas', 'total', 'ticket', 'devolvido', 'liquido'], ...]
 */
function evolucaoVendas(string $ini, string $fim, string $agrupar, string $uf = ''): array
{
    $tam    = tamanhoChaveAgrupamento($agrupar);
    $params = [':ini' => $ini, ':fim' => $fim];
    $filtro = whereUfRelatorio($uf, $params);

    $vendas = consultar(
        "SELECT SUBSTR(v.DATA, 1, $tam) AS CHAVE, COUNT(*) AS QTD,
                COALESCE(SUM(v.TOTAL), 0) AS TOTAL, COALESCE(SUM(v.NUM_PECAS), 0) AS PECAS
           FROM VENDAS v
           LEFT JOIN CLIENTE c ON c.CODIGO = v.CODIGO_CLIENTE
          WHERE v.STATUS_VENDA = 'Finalizado' AND v.DATA >= :ini AND v.DATA <= :fim $filtro
          GROUP BY SUBSTR(v.DATA, 1, $tam)",
        $params
    );
    $porChave = array_column($vendas, null, 'CHAVE');

    garantirTabelasDevolucao();
    $paramsDev = [':ini' => $ini, ':ate' => diaSeguinte($fim), ':tipo' => TIPO_DEVOLUCAO];
    $filtroDev = whereUfRelatorio($uf, $paramsDev);
    $devs = consultar(
        "SELECT SUBSTR(d.DATA, 1, $tam) AS CHAVE, COALESCE(SUM(d.VALOR), 0) AS VALOR
           FROM DEVOLUCAO d
           LEFT JOIN CLIENTE c ON c.CODIGO = d.CODIGO_CLIENTE
          WHERE d.TIPO = :tipo AND COALESCE(d.REEMBOLSO, '') <> 'Sem reembolso'
            AND d.DATA >= :ini AND d.DATA < :ate $filtroDev
          GROUP BY SUBSTR(d.DATA, 1, $tam)",
        $paramsDev
    );
    $devPorChave = array_column($devs, 'VALOR', 'CHAVE');

    $saida = [];
    foreach (chavesDoPeriodo($ini, $fim, $agrupar) as $chave) {
        $qtd   = (int) ($porChave[$chave]['QTD'] ?? 0);
        $total = (float) ($porChave[$chave]['TOTAL'] ?? 0);
        $dev   = (float) ($devPorChave[$chave] ?? 0);
        [$pIni, $pFim] = limitesDaChave($chave, $agrupar, $ini, $fim);

        $saida[] = [
            'chave'     => $chave,
            'rotulo'    => rotuloChave($chave, $agrupar),
            'ini'       => $pIni,
            'fim'       => $pFim,
            'qtd'       => $qtd,
            'pecas'     => (float) ($porChave[$chave]['PECAS'] ?? 0),
            'total'     => $total,
            'ticket'    => $qtd > 0 ? $total / $qtd : 0.0,
            'devolvido' => $dev,
            'liquido'   => $total - $dev,
        ];
    }
    return $saida;
}

/** Primeiro e último dia de uma chave, sem sair do período filtrado. */
function limitesDaChave(string $chave, string $agrupar, string $ini, string $fim): array
{
    if ($agrupar === 'dia') {
        return [$chave, $chave];
    }
    if ($agrupar === 'mes') {
        $a = $chave . '-01';
        $b = date('Y-m-t', strtotime($a));
    } else {
        $a = $chave . '-01-01';
        $b = $chave . '-12-31';
    }
    return [max($a, $ini), min($b, $fim)];
}

/** Rótulo curto: dia "27/11", mês "Nov/26", ano "2026". */
function rotuloChave(string $chave, string $agrupar): string
{
    if ($agrupar === 'dia') {
        return substr($chave, 8, 2) . '/' . substr($chave, 5, 2);
    }
    if ($agrupar === 'mes') {
        return rotuloMes($chave);
    }
    return $chave;
}

// ---------- Por estado ----------

/** Vendas por estado (UF da cliente), do que mais vendeu para o que menos vendeu. */
function vendasPorEstado(string $ini, string $fim): array
{
    $uf = sqlUfCliente('c');

    $linhas = consultar(
        "SELECT $uf AS UF, COUNT(*) AS QTD, COUNT(DISTINCT v.CODIGO_CLIENTE) AS CLIENTES,
                COALESCE(SUM(v.TOTAL), 0) AS TOTAL, COALESCE(SUM(v.NUM_PECAS), 0) AS PECAS
           FROM VENDAS v
           LEFT JOIN CLIENTE c ON c.CODIGO = v.CODIGO_CLIENTE
          WHERE v.STATUS_VENDA = 'Finalizado' AND v.DATA >= :ini AND v.DATA <= :fim
          GROUP BY $uf
          ORDER BY TOTAL DESC",
        [':ini' => $ini, ':fim' => $fim]
    );

    garantirTabelasDevolucao();
    $devs = consultar(
        "SELECT $uf AS UF, COALESCE(SUM(d.VALOR), 0) AS VALOR
           FROM DEVOLUCAO d
           LEFT JOIN CLIENTE c ON c.CODIGO = d.CODIGO_CLIENTE
          WHERE d.TIPO = :tipo AND COALESCE(d.REEMBOLSO, '') <> 'Sem reembolso'
            AND d.DATA >= :ini AND d.DATA < :ate
          GROUP BY $uf",
        [':ini' => $ini, ':ate' => diaSeguinte($fim), ':tipo' => TIPO_DEVOLUCAO]
    );
    $devPorUf = array_column($devs, 'VALOR', 'UF');

    $soma = array_sum(array_map(fn($l) => (float) $l['TOTAL'], $linhas));

    $saida = [];
    foreach ($linhas as $l) {
        $sigla = (string) $l['UF'];
        $qtd   = (int) $l['QTD'];
        $total = (float) $l['TOTAL'];
        $dev   = (float) ($devPorUf[$sigla] ?? 0);

        $saida[] = [
            'uf'         => $sigla === '' ? UF_SEM_ESTADO : $sigla,
            'nome'       => nomeEstado($sigla),
            'qtd'        => $qtd,
            'clientes'   => (int) $l['CLIENTES'],
            'pecas'      => (float) $l['PECAS'],
            'total'      => $total,
            'ticket'     => $qtd > 0 ? $total / $qtd : 0.0,
            'devolvido'  => $dev,
            'liquido'    => $total - $dev,
            'percentual' => $soma > 0 ? $total / $soma * 100 : 0.0,
        ];
    }
    return $saida;
}

/** Cidades que mais compraram num estado (ou nas vendas sem estado). */
function vendasPorCidade(string $ini, string $fim, string $uf, int $limite = 15): array
{
    $params = [':ini' => $ini, ':fim' => $fim, ':n' => $limite];
    $filtro = whereUfRelatorio($uf, $params);

    return consultar(
        "SELECT COALESCE(NULLIF(UPPER(TRIM(c.CIDADE)), ''), '(sem cidade)') AS CIDADE,
                COUNT(*) AS QTD, COUNT(DISTINCT v.CODIGO_CLIENTE) AS CLIENTES, COALESCE(SUM(v.TOTAL), 0) AS TOTAL
           FROM VENDAS v
           LEFT JOIN CLIENTE c ON c.CODIGO = v.CODIGO_CLIENTE
          WHERE v.STATUS_VENDA = 'Finalizado' AND v.DATA >= :ini AND v.DATA <= :fim $filtro
          GROUP BY COALESCE(NULLIF(UPPER(TRIM(c.CIDADE)), ''), '(sem cidade)')
          ORDER BY TOTAL DESC
          LIMIT :n",
        $params
    );
}

// ---------- Produtos ----------

/** Produtos mais vendidos entre duas datas (itens ativos de vendas finalizadas), com o filtro de estado. */
function produtosMaisVendidosPeriodo(string $ini, string $fim, string $uf = '', int $limite = 10): array
{
    $params = [':ini' => $ini, ':fim' => $fim, ':n' => $limite];
    $filtro = whereUfRelatorio($uf, $params);

    return consultar(
        "SELECT i.CODIGO_PRODUTO, MAX(i.DESCRICAO) AS DESCRICAO, SUM(i.QUANT) AS QTD, SUM(i.PRECO_TOTAL) AS TOTAL
           FROM VENDAS v
           JOIN ITENS_VENDA i ON i.CODIGO_VENDA = v.CODIGO_VENDA
           LEFT JOIN CLIENTE c ON c.CODIGO = v.CODIGO_CLIENTE
          WHERE v.STATUS_VENDA = 'Finalizado' AND v.DATA >= :ini AND v.DATA <= :fim
            AND i.STATUS_ITENS_VENDA = 'Ativo' $filtro
          GROUP BY i.CODIGO_PRODUTO
          ORDER BY QTD DESC, TOTAL DESC
          LIMIT :n",
        $params
    );
}

// ---------- Exportar ----------

/** Baixa o relatório (evolução + estados) como CSV para abrir no Excel. */
function exportarRelatorioCsv(array $f, array $evolucao, array $estados): void
{
    $nome = 'relatorio_vendas_' . $f['data_ini'] . '_a_' . $f['data_fim'] . '.csv';
    header('Content-Type: text/csv; charset=UTF-8');
    header('Content-Disposition: attachment; filename="' . $nome . '"');

    $saida = fopen('php://output', 'w');
    fwrite($saida, "\xEF\xBB\xBF"); // BOM para o Excel reconhecer os acentos
    $num = fn($v) => number_format((float) $v, 2, ',', '');

    fputcsv($saida, ['Relatório de vendas finalizadas', formatarData($f['data_ini']) . ' a ' . formatarData($f['data_fim']),
                     $f['uf'] !== '' ? 'Estado: ' . nomeEstado($f['uf']) : 'Todos os estados'], ';');
    fputcsv($saida, [], ';');
    fputcsv($saida, ['Período', 'Vendas', 'Peças', 'Vendido', 'Ticket médio', 'Devolvido', 'Líquido'], ';');
    foreach ($evolucao as $p) {
        fputcsv($saida, [$p['rotulo'], $p['qtd'], formatarQuantidade($p['pecas']), $num($p['total']),
                         $num($p['ticket']), $num($p['devolvido']), $num($p['liquido'])], ';');
    }
    fputcsv($saida, [], ';');
    fputcsv($saida, ['Estado', 'UF', 'Vendas', 'Clientes', 'Peças', 'Vendido', '% do total', 'Ticket médio', 'Devolvido', 'Líquido'], ';');
    foreach ($estados as $e) {
        fputcsv($saida, [$e['nome'], $e['uf'] === UF_SEM_ESTADO ? '' : $e['uf'], $e['qtd'], $e['clientes'],
                         formatarQuantidade($e['pecas']), $num($e['total']), number_format($e['percentual'], 1, ',', ''),
                         $num($e['ticket']), $num($e['devolvido']), $num($e['liquido'])], ';');
    }
    fclose($saida);
    exit;
}
