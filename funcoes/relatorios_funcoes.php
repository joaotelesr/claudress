<?php
/**
 * relatorios_funcoes.php
 * Relatórios de vendas: evolução por período (dia, mês ou ano), vendas por estado (UF do cadastro da cliente),
 * cidades de um estado e produtos mais vendidos. Usado por admin/relatorios.php.
 *
 * Vendedor: o vendedor fica gravado em cada peça (ITENS_VENDA.CODIGO_VENDEDOR), como no sistema atual;
 *   a Nova venda já grava o vendedor escolhido em todas as peças. Se uma venda tiver peças de vendedores
 *   diferentes, cada um recebe a parte do TOTAL proporcional às suas peças (o desconto é repartido igual).
 *   Com o filtro de vendedor, todos os números da tela (gráfico, estados, cidades, produtos e devoluções)
 *   passam a contar só a parte daquele vendedor.
 *
 * Filtro de cidade: só vale com um estado escolhido (a mesma cidade pode existir em estados diferentes).
 *   A cidade é comparada em maiúsculas e sem espaços nas pontas; cliente sem cidade = '(sem cidade)'.
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

// Nome usado quando a peça não tem vendedor (ou o vendedor não existe mais no cadastro)
const VENDEDOR_SEM_NOME = 'Sem vendedor';

// Rótulo das vendas de clientes sem cidade no cadastro
const CIDADE_SEM_NOME = '(sem cidade)';

// Clientes sem comprar: opções de meses e quantas aparecem na tela (o CSV traz todas)
const MESES_SEM_COMPRAR = [3 => 'Há 3 meses ou mais', 6 => 'Há 6 meses ou mais'];
const RELATORIO_LIMITE_CLIENTES = 50;

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

    // Cidade só faz sentido dentro de um estado
    $cidade = $uf === '' ? '' : mb_strtoupper(trim((string) ($get['cidade'] ?? '')));
    $cidade = mb_substr($cidade, 0, 80);
    if ($cidade === mb_strtoupper(CIDADE_SEM_NOME)) {
        $cidade = CIDADE_SEM_NOME;
    }

    $vendedor = max(0, (int) ($get['vendedor'] ?? 0));

    // Ranking de clientes: ano inteiro (padrão = ano atual)
    $ano = (int) ($get['ano'] ?? date('Y'));
    if ($ano < 1990 || $ano > (int) date('Y')) {
        $ano = (int) date('Y');
    }

    // Clientes sem comprar há 3 ou 6 meses (padrão = 3)
    $semComprar = (int) ($get['sem_comprar'] ?? 3);
    if (!array_key_exists($semComprar, MESES_SEM_COMPRAR)) {
        $semComprar = 3;
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
        'data_ini' => $ini, 'data_fim' => $fim, 'atalho' => $atalho, 'uf' => $uf, 'cidade' => $cidade, 'vendedor' => $vendedor, 'agrupar' => $agrupar,
        'ano' => $ano, 'sem_comprar' => $semComprar,
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

/** Expressão SQL da cidade da cliente (maiúscula, sem espaços; '(sem cidade)' quando não tem). */
function sqlCidadeCliente(string $alias = 'c'): string
{
    return "COALESCE(NULLIF(UPPER(TRIM($alias.CIDADE)), ''), '" . CIDADE_SEM_NOME . "')";
}

/** Condição do filtro de estado e, opcionalmente, de cidade (para a tabela CLIENTE com o alias informado). */
function whereUfRelatorio(string $uf, array &$params, string $alias = 'c', string $cidade = ''): string
{
    if ($uf === '') {
        return '';
    }
    if ($uf === UF_SEM_ESTADO) {
        $sql = ' AND ' . sqlUfCliente($alias) . " = ''";
    } else {
        $params[':uf'] = $uf;
        $sql = ' AND ' . sqlUfCliente($alias) . ' = :uf';
    }
    if ($cidade !== '') {
        $params[':cidade'] = $cidade;
        $sql .= ' AND ' . sqlCidadeCliente($alias) . ' = :cidade';
    }
    return $sql;
}

/** Texto do local filtrado: "São Paulo", "Santos (SP)" ou "Todos os estados". */
function rotuloLocalRelatorio(string $uf, string $cidade = ''): string
{
    if ($uf === '') {
        return 'Todos os estados';
    }
    if ($cidade === '') {
        return nomeEstado($uf);
    }
    return $uf === UF_SEM_ESTADO ? $cidade . ' (sem estado)' : $cidade . ' (' . $uf . ')';
}

/**
 * Soma das peças ativas de cada venda (para repartir o total da venda entre os vendedores).
 * Fica numa tabela à parte, calculada de uma vez. ITENS_VENDA.CODIGO_VENDA é texto no banco da loja,
 * então já sai convertido para número: assim a ligação com VENDAS.CODIGO_VENDA (número) fica rápida.
 */
function sqlBrutoPorVenda(): string
{
    return "(SELECT CAST(i2.CODIGO_VENDA AS SIGNED) AS CODIGO_VENDA, SUM(i2.PRECO_TOTAL) AS BRUTO
               FROM ITENS_VENDA i2
              WHERE i2.STATUS_ITENS_VENDA = 'Ativo'
              GROUP BY CAST(i2.CODIGO_VENDA AS SIGNED))";
}

/**
 * Tabela de vendas usada nos relatórios (sempre com o alias v depois). Sem vendedor: a própria VENDAS.
 * Com vendedor: uma venda por linha com as mesmas colunas, mas TOTAL, DESCONTO e NUM_PECAS só da parte dele.
 * Usa os parâmetros :ini e :fim da consulta (para não percorrer vendas fora do período).
 */
function fonteVendasRelatorio(int $vendedor, array &$params): string
{
    if ($vendedor <= 0) {
        return 'VENDAS';
    }
    $params[':vendedor'] = $vendedor;

    // Peças de cada venda somadas de uma vez só (total da venda e a parte do vendedor), depois ligadas a VENDAS.
    // Antes era uma ligação peça a peça, que ficava muito lenta porque CODIGO_VENDA é texto em ITENS_VENDA.
    return "(SELECT vv.CODIGO_VENDA, vv.DATA, vv.CODIGO_CLIENTE, vv.STATUS_VENDA,
                    CASE WHEN p.BRUTO > 0 THEN p.PARTE * vv.TOTAL / p.BRUTO ELSE 0 END AS TOTAL,
                    CASE WHEN p.BRUTO > 0 THEN p.PARTE * vv.DESCONTO / p.BRUTO ELSE 0 END AS DESCONTO,
                    p.PECAS AS NUM_PECAS
               FROM VENDAS vv
               JOIN (SELECT CAST(i2.CODIGO_VENDA AS SIGNED) AS CODIGO_VENDA,
                            SUM(i2.PRECO_TOTAL) AS BRUTO,
                            SUM(CASE WHEN i2.CODIGO_VENDEDOR = :vendedor THEN i2.PRECO_TOTAL ELSE 0 END) AS PARTE,
                            SUM(CASE WHEN i2.CODIGO_VENDEDOR = :vendedor THEN i2.QUANT ELSE 0 END) AS PECAS,
                            SUM(CASE WHEN i2.CODIGO_VENDEDOR = :vendedor THEN 1 ELSE 0 END) AS PECAS_DELE
                       FROM ITENS_VENDA i2
                      WHERE i2.STATUS_ITENS_VENDA = 'Ativo'
                      GROUP BY CAST(i2.CODIGO_VENDA AS SIGNED)) p
                 ON p.CODIGO_VENDA = vv.CODIGO_VENDA AND p.PECAS_DELE > 0
              WHERE vv.STATUS_VENDA = 'Finalizado' AND vv.DATA >= :ini AND vv.DATA <= :fim)";
}

/**
 * Tabela de devoluções usada nos relatórios (alias d). Com vendedor: só o valor das peças devolvidas
 * que foram vendidas por ele (DEVOLUCAO_ITEM ligado a ITENS_VENDA).
 */
function fonteDevolucoesRelatorio(int $vendedor, array &$params): string
{
    garantirTabelasDevolucao();
    if ($vendedor <= 0) {
        return 'DEVOLUCAO';
    }
    $params[':vendedor'] = $vendedor;

    return "(SELECT dd.ID, dd.DATA, dd.CODIGO_CLIENTE, dd.TIPO, dd.REEMBOLSO, SUM(di.VALOR_TOTAL) AS VALOR
               FROM DEVOLUCAO dd
               JOIN DEVOLUCAO_ITEM di ON di.ID_DEVOLUCAO = dd.ID
               JOIN ITENS_VENDA iv ON iv.CODIGO_ITEM_VENDA = di.CODIGO_ITEM_VENDA
              WHERE iv.CODIGO_VENDEDOR = :vendedor
              GROUP BY dd.ID, dd.DATA, dd.CODIGO_CLIENTE, dd.TIPO, dd.REEMBOLSO)";
}

/** Nome do vendedor (FUNCIONARIOS_USUARIOS) ou 'Sem vendedor'. */
function nomeVendedor(int $codigo): string
{
    if ($codigo <= 0) {
        return VENDEDOR_SEM_NOME;
    }
    $nome = consultarValor('SELECT TRIM(NOME_FUN) FROM FUNCIONARIOS_USUARIOS WHERE CODIGO = :id', [':id' => $codigo]);
    return $nome ? (string) $nome : 'Vendedor #' . $codigo;
}

/** Dia seguinte (para comparar colunas DATETIME com "até o fim do dia"). */
function diaSeguinte(string $data): string
{
    return date('Y-m-d', strtotime($data . ' +1 day'));
}

// ---------- Totais do período ----------

/** Vendido, quantidade, peças, clientes, devolvido e líquido no período (com o filtro de estado). */
function resumoRelatorio(string $ini, string $fim, string $uf = '', string $cidade = '', int $vendedor = 0): array
{
    $params = [':ini' => $ini, ':fim' => $fim];
    $filtro = whereUfRelatorio($uf, $params, 'c', $cidade);
    $fonte  = fonteVendasRelatorio($vendedor, $params);

    $r = consultarUma(
        "SELECT COUNT(*) AS QTD, COALESCE(SUM(v.TOTAL), 0) AS TOTAL, COALESCE(SUM(v.NUM_PECAS), 0) AS PECAS,
                COALESCE(SUM(v.DESCONTO), 0) AS DESCONTO, COUNT(DISTINCT v.CODIGO_CLIENTE) AS CLIENTES
           FROM $fonte v
           LEFT JOIN CLIENTE c ON c.CODIGO = v.CODIGO_CLIENTE
          WHERE v.STATUS_VENDA = 'Finalizado' AND v.DATA >= :ini AND v.DATA <= :fim $filtro",
        $params
    ) ?? [];

    $qtd       = (int) ($r['QTD'] ?? 0);
    $total     = (float) ($r['TOTAL'] ?? 0);
    $devolvido = totalDevolvidoPeriodo($ini, $fim, $uf, $cidade, $vendedor);

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
function totalDevolvidoPeriodo(string $ini, string $fim, string $uf = '', string $cidade = '', int $vendedor = 0): float
{
    $params = [':ini' => $ini, ':ate' => diaSeguinte($fim), ':tipo' => TIPO_DEVOLUCAO];
    $filtro = whereUfRelatorio($uf, $params, 'c', $cidade);
    $fonte  = fonteDevolucoesRelatorio($vendedor, $params);

    return (float) consultarValor(
        "SELECT COALESCE(SUM(d.VALOR), 0)
           FROM $fonte d
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
function evolucaoVendas(string $ini, string $fim, string $agrupar, string $uf = '', string $cidade = '', int $vendedor = 0): array
{
    $tam    = tamanhoChaveAgrupamento($agrupar);
    $params = [':ini' => $ini, ':fim' => $fim];
    $filtro = whereUfRelatorio($uf, $params, 'c', $cidade);
    $fonte  = fonteVendasRelatorio($vendedor, $params);

    $vendas = consultar(
        "SELECT SUBSTR(v.DATA, 1, $tam) AS CHAVE, COUNT(*) AS QTD,
                COALESCE(SUM(v.TOTAL), 0) AS TOTAL, COALESCE(SUM(v.NUM_PECAS), 0) AS PECAS
           FROM $fonte v
           LEFT JOIN CLIENTE c ON c.CODIGO = v.CODIGO_CLIENTE
          WHERE v.STATUS_VENDA = 'Finalizado' AND v.DATA >= :ini AND v.DATA <= :fim $filtro
          GROUP BY SUBSTR(v.DATA, 1, $tam)",
        $params
    );
    $porChave = array_column($vendas, null, 'CHAVE');

    garantirTabelasDevolucao();
    $paramsDev = [':ini' => $ini, ':ate' => diaSeguinte($fim), ':tipo' => TIPO_DEVOLUCAO];
    $filtroDev = whereUfRelatorio($uf, $paramsDev, 'c', $cidade);
    $fonteDev  = fonteDevolucoesRelatorio($vendedor, $paramsDev);
    $devs = consultar(
        "SELECT SUBSTR(d.DATA, 1, $tam) AS CHAVE, COALESCE(SUM(d.VALOR), 0) AS VALOR
           FROM $fonteDev d
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
function vendasPorEstado(string $ini, string $fim, int $vendedor = 0): array
{
    $uf        = sqlUfCliente('c');
    $params    = [':ini' => $ini, ':fim' => $fim];
    $fonte     = fonteVendasRelatorio($vendedor, $params);
    $paramsDev = [':ini' => $ini, ':ate' => diaSeguinte($fim), ':tipo' => TIPO_DEVOLUCAO];
    $fonteDev  = fonteDevolucoesRelatorio($vendedor, $paramsDev);

    $linhas = consultar(
        "SELECT $uf AS UF, COUNT(*) AS QTD, COUNT(DISTINCT v.CODIGO_CLIENTE) AS CLIENTES,
                COALESCE(SUM(v.TOTAL), 0) AS TOTAL, COALESCE(SUM(v.NUM_PECAS), 0) AS PECAS
           FROM $fonte v
           LEFT JOIN CLIENTE c ON c.CODIGO = v.CODIGO_CLIENTE
          WHERE v.STATUS_VENDA = 'Finalizado' AND v.DATA >= :ini AND v.DATA <= :fim
          GROUP BY $uf
          ORDER BY TOTAL DESC",
        $params
    );

    garantirTabelasDevolucao();
    $devs = consultar(
        "SELECT $uf AS UF, COALESCE(SUM(d.VALOR), 0) AS VALOR
           FROM $fonteDev d
           LEFT JOIN CLIENTE c ON c.CODIGO = d.CODIGO_CLIENTE
          WHERE d.TIPO = :tipo AND COALESCE(d.REEMBOLSO, '') <> 'Sem reembolso'
            AND d.DATA >= :ini AND d.DATA < :ate
          GROUP BY $uf",
        $paramsDev
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

/**
 * Cidades de um estado (ou das vendas sem estado), da que mais vendeu para a que menos vendeu:
 * [['cidade', 'qtd', 'clientes', 'pecas', 'total', 'ticket', 'devolvido', 'liquido', 'percentual'], ...]
 * $limite = 0 traz todas.
 */
function vendasPorCidade(string $ini, string $fim, string $uf, int $limite = 15, int $vendedor = 0): array
{
    $cid    = sqlCidadeCliente('c');
    $params = [':ini' => $ini, ':fim' => $fim];
    $filtro = whereUfRelatorio($uf, $params);
    $fonte  = fonteVendasRelatorio($vendedor, $params);

    $linhas = consultar(
        "SELECT $cid AS CIDADE, COUNT(*) AS QTD, COUNT(DISTINCT v.CODIGO_CLIENTE) AS CLIENTES,
                COALESCE(SUM(v.TOTAL), 0) AS TOTAL, COALESCE(SUM(v.NUM_PECAS), 0) AS PECAS
           FROM $fonte v
           LEFT JOIN CLIENTE c ON c.CODIGO = v.CODIGO_CLIENTE
          WHERE v.STATUS_VENDA = 'Finalizado' AND v.DATA >= :ini AND v.DATA <= :fim $filtro
          GROUP BY $cid
          ORDER BY TOTAL DESC",
        $params
    );

    garantirTabelasDevolucao();
    $paramsDev = [':ini' => $ini, ':ate' => diaSeguinte($fim), ':tipo' => TIPO_DEVOLUCAO];
    $filtroDev = whereUfRelatorio($uf, $paramsDev);
    $fonteDev  = fonteDevolucoesRelatorio($vendedor, $paramsDev);
    $devs = consultar(
        "SELECT $cid AS CIDADE, COALESCE(SUM(d.VALOR), 0) AS VALOR
           FROM $fonteDev d
           LEFT JOIN CLIENTE c ON c.CODIGO = d.CODIGO_CLIENTE
          WHERE d.TIPO = :tipo AND COALESCE(d.REEMBOLSO, '') <> 'Sem reembolso'
            AND d.DATA >= :ini AND d.DATA < :ate $filtroDev
          GROUP BY $cid",
        $paramsDev
    );
    $devPorCidade = array_column($devs, 'VALOR', 'CIDADE');

    $soma  = array_sum(array_map(fn($l) => (float) $l['TOTAL'], $linhas));
    $saida = [];
    foreach ($linhas as $l) {
        $qtd   = (int) $l['QTD'];
        $total = (float) $l['TOTAL'];
        $dev   = (float) ($devPorCidade[$l['CIDADE']] ?? 0);
        $saida[] = [
            'cidade'     => (string) $l['CIDADE'],
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
    return $limite > 0 ? array_slice($saida, 0, $limite) : $saida;
}

// ---------- Por vendedor ----------

/**
 * Quanto cada vendedor vendeu no período (com os filtros de estado e cidade), do que mais vendeu para o que menos vendeu.
 * Cada linha traz também a divisão por estado ('estados', do maior para o menor) e o estado em que ele mais vendeu.
 */
function vendasPorVendedor(string $ini, string $fim, string $uf = '', string $cidade = ''): array
{
    $ufSql  = sqlUfCliente('c');
    $params = [':ini' => $ini, ':fim' => $fim];
    $filtro = whereUfRelatorio($uf, $params, 'c', $cidade);
    $bruto  = sqlBrutoPorVenda();

    // Uma linha por vendedor e estado; a parte de cada vendedor é proporcional às peças dele na venda.
    // As peças são somadas antes por venda e vendedor (p), para não ligar VENDAS peça a peça (ficava lento).
    $linhas = consultar(
        "SELECT p.VENDEDOR, $ufSql AS UF,
                COUNT(DISTINCT v.CODIGO_VENDA) AS QTD, COUNT(DISTINCT v.CODIGO_CLIENTE) AS CLIENTES,
                COALESCE(SUM(p.PECAS), 0) AS PECAS,
                COALESCE(SUM(CASE WHEN b.BRUTO > 0 THEN p.PARTE * v.TOTAL / b.BRUTO ELSE 0 END), 0) AS TOTAL
           FROM VENDAS v
           JOIN (SELECT CAST(i.CODIGO_VENDA AS SIGNED) AS CODIGO_VENDA, COALESCE(i.CODIGO_VENDEDOR, 0) AS VENDEDOR,
                        SUM(i.QUANT) AS PECAS, SUM(i.PRECO_TOTAL) AS PARTE
                   FROM ITENS_VENDA i
                  WHERE i.STATUS_ITENS_VENDA = 'Ativo'
                  GROUP BY CAST(i.CODIGO_VENDA AS SIGNED), COALESCE(i.CODIGO_VENDEDOR, 0)) p
             ON p.CODIGO_VENDA = v.CODIGO_VENDA
           LEFT JOIN $bruto b ON b.CODIGO_VENDA = v.CODIGO_VENDA
           LEFT JOIN CLIENTE c ON c.CODIGO = v.CODIGO_CLIENTE
          WHERE v.STATUS_VENDA = 'Finalizado' AND v.DATA >= :ini AND v.DATA <= :fim $filtro
          GROUP BY p.VENDEDOR, $ufSql",
        $params
    );

    garantirTabelasDevolucao();
    $paramsDev = [':ini' => $ini, ':ate' => diaSeguinte($fim), ':tipo' => TIPO_DEVOLUCAO];
    $filtroDev = whereUfRelatorio($uf, $paramsDev, 'c', $cidade);
    $devs = consultar(
        "SELECT COALESCE(i.CODIGO_VENDEDOR, 0) AS VENDEDOR, COALESCE(SUM(di.VALOR_TOTAL), 0) AS VALOR
           FROM DEVOLUCAO d
           JOIN DEVOLUCAO_ITEM di ON di.ID_DEVOLUCAO = d.ID
           JOIN ITENS_VENDA i ON i.CODIGO_ITEM_VENDA = di.CODIGO_ITEM_VENDA
           LEFT JOIN CLIENTE c ON c.CODIGO = d.CODIGO_CLIENTE
          WHERE d.TIPO = :tipo AND COALESCE(d.REEMBOLSO, '') <> 'Sem reembolso'
            AND d.DATA >= :ini AND d.DATA < :ate $filtroDev
          GROUP BY COALESCE(i.CODIGO_VENDEDOR, 0)",
        $paramsDev
    );
    $devPorVendedor = array_column($devs, 'VALOR', 'VENDEDOR');

    $nomes = [];
    foreach (consultar('SELECT CODIGO, TRIM(NOME_FUN) AS NOME FROM FUNCIONARIOS_USUARIOS') as $fu) {
        $nomes[(int) $fu['CODIGO']] = (string) $fu['NOME'];
    }

    // Junta as linhas por vendedor (uma venda tem uma cliente só, então somar os estados não conta venda em dobro)
    $porVendedor = [];
    foreach ($linhas as $l) {
        $cod = (int) $l['VENDEDOR'];
        if (!isset($nomes[$cod])) {
            $cod = 0; // vendedor apagado do cadastro conta como "sem vendedor"
        }
        $sigla = (string) $l['UF'] === '' ? UF_SEM_ESTADO : (string) $l['UF'];
        $v = &$porVendedor[$cod];
        $v['qtd']      = ($v['qtd'] ?? 0) + (int) $l['QTD'];
        $v['clientes'] = ($v['clientes'] ?? 0) + (int) $l['CLIENTES'];
        $v['pecas']    = ($v['pecas'] ?? 0) + (float) $l['PECAS'];
        $v['total']    = ($v['total'] ?? 0) + (float) $l['TOTAL'];
        $v['estados'][$sigla] = ($v['estados'][$sigla] ?? 0) + (float) $l['TOTAL'];
        unset($v);
    }

    $soma  = array_sum(array_column($porVendedor, 'total'));
    $saida = [];
    foreach ($porVendedor as $cod => $v) {
        arsort($v['estados']);
        $estados = [];
        foreach ($v['estados'] as $sigla => $valor) {
            $estados[] = [
                'uf' => $sigla, 'nome' => nomeEstado($sigla === UF_SEM_ESTADO ? '' : $sigla), 'total' => $valor,
                'percentual' => $v['total'] > 0 ? $valor / $v['total'] * 100 : 0.0,
            ];
        }
        $dev = 0.0;
        if ($cod === 0) {
            foreach ($devPorVendedor as $c => $valor) {
                if (!isset($nomes[(int) $c]) || (int) $c === 0) {
                    $dev += (float) $valor;
                }
            }
        } else {
            $dev = (float) ($devPorVendedor[$cod] ?? 0);
        }

        $saida[] = [
            'codigo'     => $cod,
            'nome'       => $cod === 0 ? VENDEDOR_SEM_NOME : ($nomes[$cod] ?: 'Vendedor #' . $cod),
            'qtd'        => $v['qtd'],
            'clientes'   => $v['clientes'],
            'pecas'      => $v['pecas'],
            'total'      => $v['total'],
            'ticket'     => $v['qtd'] > 0 ? $v['total'] / $v['qtd'] : 0.0,
            'devolvido'  => $dev,
            'liquido'    => $v['total'] - $dev,
            'percentual' => $soma > 0 ? $v['total'] / $soma * 100 : 0.0,
            'estados'    => $estados,
            'top_estado' => $estados[0] ?? null,
        ];
    }
    usort($saida, fn($a, $b) => $b['total'] <=> $a['total']);
    return $saida;
}

// ---------- Clientes ----------

/** Anos que têm venda finalizada (do mais recente para o mais antigo); sempre inclui o ano atual. */
function anosComVenda(): array
{
    $primeira = dataPrimeiraVenda();
    $inicio   = $primeira !== '' ? (int) substr($primeira, 0, 4) : (int) date('Y');
    return range((int) date('Y'), min($inicio, (int) date('Y')));
}

/** Telefone para contato: WhatsApp, senão celular (já formatado). [codigo_cliente => '(11) 98765-4321'] */
function telefonesDosClientes(array $ids): array
{
    $fones = [];
    // Em blocos, para não montar um IN gigante quando há milhares de clientes
    foreach (array_chunk(array_values(array_unique(array_map('intval', $ids))), 500) as $bloco) {
        foreach (contatosPrincipais($bloco) as $cliente => $c) {
            $fone = $c[CONTATO_WHATSAPP] ?? '';
            if (trim($fone) === '') {
                $fone = $c[CONTATO_CELULAR] ?? '';
            }
            if (trim($fone) !== '') {
                $fones[(int) $cliente] = formatarTelefone($fone);
            }
        }
    }
    return $fones;
}

/**
 * Clientes que mais compraram no ano inteiro (vendas finalizadas), com os filtros de estado, cidade e vendedor.
 * $limite = 0 traz todas. Cada linha: codigo, nome, telefone, cidade, uf, qtd, pecas, total, ticket, ultima.
 */
function clientesQueMaisCompraram(int $ano, string $uf = '', string $cidade = '', int $vendedor = 0, int $limite = 20): array
{
    $params = [':ini' => $ano . '-01-01', ':fim' => $ano . '-12-31'];
    $filtro = whereUfRelatorio($uf, $params, 'c', $cidade);
    $fonte  = fonteVendasRelatorio($vendedor, $params);
    $lim    = '';
    if ($limite > 0) {
        $lim = 'LIMIT :n';
        $params[':n'] = $limite;
    }

    $linhas = consultar(
        "SELECT v.CODIGO_CLIENTE, MAX(c.NOME) AS NOME, MAX(c.CIDADE) AS CIDADE, MAX(c.UF) AS UF,
                COUNT(*) AS QTD, COALESCE(SUM(v.NUM_PECAS), 0) AS PECAS, COALESCE(SUM(v.TOTAL), 0) AS TOTAL,
                MAX(v.DATA) AS ULTIMA
           FROM $fonte v
           JOIN CLIENTE c ON c.CODIGO = v.CODIGO_CLIENTE
          WHERE v.STATUS_VENDA = 'Finalizado' AND v.DATA >= :ini AND v.DATA <= :fim $filtro
          GROUP BY v.CODIGO_CLIENTE
          ORDER BY TOTAL DESC, QTD DESC
          $lim",
        $params
    );

    $fones = telefonesDosClientes(array_column($linhas, 'CODIGO_CLIENTE'));
    $saida = [];
    foreach ($linhas as $l) {
        $qtd = (int) $l['QTD'];
        $saida[] = [
            'codigo'   => (int) $l['CODIGO_CLIENTE'],
            'nome'     => trim((string) $l['NOME']),
            'telefone' => $fones[(int) $l['CODIGO_CLIENTE']] ?? '',
            'cidade'   => trim((string) $l['CIDADE']),
            'uf'       => mb_strtoupper(trim((string) $l['UF'])),
            'qtd'      => $qtd,
            'pecas'    => (float) $l['PECAS'],
            'total'    => (float) $l['TOTAL'],
            'ticket'   => $qtd > 0 ? (float) $l['TOTAL'] / $qtd : 0.0,
            'ultima'   => substr((string) $l['ULTIMA'], 0, 10),
        ];
    }
    return $saida;
}

/** Data limite: quem não compra desde antes dela está "sem comprar há N meses". */
function dataLimiteSemComprar(int $meses): string
{
    return date('Y-m-d', strtotime('-' . $meses . ' months'));
}

/**
 * Clientes ativas que já compraram, mas cuja última compra finalizada foi há N meses ou mais
 * (as que pararam de comprar há menos tempo aparecem primeiro). Usa os filtros de estado e cidade.
 * Retorna ['total' => quantas são, 'clientes' => lista (até $limite; 0 = todas)].
 */
function clientesSemComprar(int $meses, string $uf = '', string $cidade = '', int $limite = RELATORIO_LIMITE_CLIENTES): array
{
    $params = [':limite' => dataLimiteSemComprar($meses)];
    $filtro = whereUfRelatorio($uf, $params, 'c', $cidade);

    // Uma consulta só, agrupada por cliente: usa só VENDAS (rápida, sem ligar com as peças)
    $linhas = consultar(
        "SELECT v.CODIGO_CLIENTE, MAX(c.NOME) AS NOME, MAX(c.CIDADE) AS CIDADE, MAX(c.UF) AS UF,
                COUNT(*) AS QTD, COALESCE(SUM(v.TOTAL), 0) AS TOTAL, MAX(v.DATA) AS ULTIMA
           FROM VENDAS v
           JOIN CLIENTE c ON c.CODIGO = v.CODIGO_CLIENTE
          WHERE v.STATUS_VENDA = 'Finalizado' AND c.STATUS_CLI = 'Ativo' $filtro
          GROUP BY v.CODIGO_CLIENTE
         HAVING MAX(v.DATA) < :limite
          ORDER BY ULTIMA DESC, TOTAL DESC",
        $params
    );

    $total  = count($linhas);
    $linhas = $limite > 0 ? array_slice($linhas, 0, $limite) : $linhas;
    $fones  = telefonesDosClientes(array_column($linhas, 'CODIGO_CLIENTE'));
    $hoje   = new DateTime(date('Y-m-d'));

    $clientes = [];
    foreach ($linhas as $l) {
        $ultima = substr((string) $l['ULTIMA'], 0, 10);
        $clientes[] = [
            'codigo'   => (int) $l['CODIGO_CLIENTE'],
            'nome'     => trim((string) $l['NOME']),
            'telefone' => $fones[(int) $l['CODIGO_CLIENTE']] ?? '',
            'cidade'   => trim((string) $l['CIDADE']),
            'uf'       => mb_strtoupper(trim((string) $l['UF'])),
            'qtd'      => (int) $l['QTD'],
            'total'    => (float) $l['TOTAL'],
            'ultima'   => $ultima,
            'dias'     => (int) (new DateTime($ultima))->diff($hoje)->days,
        ];
    }
    return ['total' => $total, 'clientes' => $clientes];
}

/**
 * Baixa uma lista de clientes para o Excel: coluna A = Telefone, coluna B = Nome
 * (mesmo formato da tela de Aniversariantes: separador ponto e vírgula e BOM para os acentos).
 */
function exportarClientesCsv(array $clientes, string $nomeArquivo): void
{
    header('Content-Type: text/csv; charset=UTF-8');
    header('Content-Disposition: attachment; filename="' . $nomeArquivo . '"');
    header('Cache-Control: no-store');

    $saida = fopen('php://output', 'w');
    fwrite($saida, "\xEF\xBB\xBF");
    fputcsv($saida, ['Telefone', 'Nome'], ';', '"', '');
    foreach ($clientes as $c) {
        fputcsv($saida, [$c['telefone'], $c['nome']], ';', '"', '');
    }
    fclose($saida);
    exit;
}

// ---------- Produtos ----------

/** Produtos mais vendidos entre duas datas (itens ativos de vendas finalizadas), com o filtro de estado. */
function produtosMaisVendidosPeriodo(string $ini, string $fim, string $uf = '', int $limite = 10, string $cidade = '', int $vendedor = 0): array
{
    $params = [':ini' => $ini, ':fim' => $fim, ':n' => $limite];
    $filtro = whereUfRelatorio($uf, $params, 'c', $cidade);
    if ($vendedor > 0) {
        $filtro .= ' AND i.CODIGO_VENDEDOR = :vendedor';
        $params[':vendedor'] = $vendedor;
    }

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
function exportarRelatorioCsv(array $f, array $evolucao, array $estados, array $cidades = [], array $vendedores = []): void
{
    $nome = 'relatorio_vendas_' . $f['data_ini'] . '_a_' . $f['data_fim'] . '.csv';
    header('Content-Type: text/csv; charset=UTF-8');
    header('Content-Disposition: attachment; filename="' . $nome . '"');

    $saida = fopen('php://output', 'w');
    fwrite($saida, "\xEF\xBB\xBF"); // BOM para o Excel reconhecer os acentos
    $num = fn($v) => number_format((float) $v, 2, ',', '');

    fputcsv($saida, ['Relatório de vendas finalizadas', formatarData($f['data_ini']) . ' a ' . formatarData($f['data_fim']),
                     rotuloLocalRelatorio($f['uf'], $f['cidade'] ?? ''),
                     !empty($f['vendedor']) ? 'Vendedor: ' . nomeVendedor((int) $f['vendedor']) : 'Todos os vendedores'], ';');
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
    if (!empty($vendedores)) {
        fputcsv($saida, [], ';');
        fputcsv($saida, ['Vendedor', 'Vendas', 'Clientes', 'Peças', 'Vendido', '% do total', 'Ticket médio', 'Devolvido', 'Líquido', 'Vendas por estado'], ';');
        foreach ($vendedores as $vd) {
            $porEstado = implode(' | ', array_map(
                fn($e) => ($e['uf'] === UF_SEM_ESTADO ? 'Sem estado' : $e['uf']) . ' ' . $num($e['total']),
                $vd['estados']
            ));
            fputcsv($saida, [$vd['nome'], $vd['qtd'], $vd['clientes'], formatarQuantidade($vd['pecas']), $num($vd['total']),
                             number_format($vd['percentual'], 1, ',', ''), $num($vd['ticket']), $num($vd['devolvido']),
                             $num($vd['liquido']), $porEstado], ';');
        }
    }
    if (!empty($cidades)) {
        fputcsv($saida, [], ';');
        fputcsv($saida, ['Cidades - ' . nomeEstado($f['uf'])], ';');
        fputcsv($saida, ['Cidade', 'Vendas', 'Clientes', 'Peças', 'Vendido', '% do estado', 'Ticket médio', 'Devolvido', 'Líquido'], ';');
        foreach ($cidades as $c) {
            fputcsv($saida, [$c['cidade'], $c['qtd'], $c['clientes'], formatarQuantidade($c['pecas']), $num($c['total']),
                             number_format($c['percentual'], 1, ',', ''), $num($c['ticket']), $num($c['devolvido']), $num($c['liquido'])], ';');
        }
    }
    fclose($saida);
    exit;
}
