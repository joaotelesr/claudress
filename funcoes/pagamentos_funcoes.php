<?php
/**
 * pagamentos_funcoes.php
 * Formas de pagamento: tabela FORMA_PAGAMENTO (cadastro) e FORMA_PAGAMENTO_VENDA (pagamentos de cada venda).
 *
 * A loja usa 13 formas. Na tela de NOVA VENDA liberamos dinheiro, cartões, transferência, depósito e o
 * CREDIÁRIO (parcelas controladas em crediario_funcoes.php / admin/crediario.php).
 * Cheque, boleto, vale-presente, brinde e crédito de cliente continuam no sistema atual.
 * "TRANSFERÊNCIA" é a forma usada para PIX e transferências.
 */

const FORMAS_NOVA_VENDA = ['DINHEIRO', 'C. DEBITO', 'C. CREDITO', 'TRANSFERÊNCIA', 'DEPÓSITO', 'CREDIÁRIO'];

/** Nome amigável de uma forma de pagamento. */
function rotuloFormaPagamento(?string $tipo): string
{
    $chave = mb_strtoupper(trim((string) $tipo));
    $nomes = [
        'DINHEIRO'      => 'Dinheiro',
        'C. DEBITO'     => 'Cartão de débito',
        'C. CREDITO'    => 'Cartão de crédito',
        'TRANSFERÊNCIA' => 'Transferência / PIX',
        'DEPÓSITO'      => 'Depósito',
        'CREDIÁRIO'     => 'Crediário',
        'CREDITO'       => 'Crédito da cliente',
        'CHEQUE'        => 'Cheque',
        'BOLETO'        => 'Boleto',
        'BRINDE'        => 'Brinde',
        'VALEPRESENTE'  => 'Vale-presente',
        'CONDIÇÕES'     => 'Condições',
        'TROCO'         => 'Troco',
    ];

    if ($chave === '') {
        return '-';
    }
    return $nomes[$chave] ?? mb_convert_case(mb_strtolower($chave), MB_CASE_TITLE);
}

/** Formas de pagamento disponíveis na tela de nova venda, já com adquirente e bandeiras dos cartões. */
function formasNovaVenda(): array
{
    // O crediário entra mesmo que esteja bloqueado no cadastro antigo (agora ele é controlado por este sistema)
    $linhas = consultar(
        'SELECT CODIGO_PAGAMENTO, TIPO_PAGAMENTO, COALESCE(FLAG_BLOQUEIO, 0) AS BLOQUEADA
           FROM FORMA_PAGAMENTO
          WHERE TIPO_PAGAMENTO IS NOT NULL
          ORDER BY CODIGO_PAGAMENTO'
    );

    $formas       = [];
    $temCrediario = false;
    foreach ($linhas as $l) {
        $tipo = mb_strtoupper(trim((string) $l['TIPO_PAGAMENTO']));
        if ($tipo === 'CREDIARIO') {
            $tipo = 'CREDIÁRIO';
        }
        if (!in_array($tipo, FORMAS_NOVA_VENDA, true)) {
            continue;
        }
        $crediario = $tipo === 'CREDIÁRIO';
        if ($crediario && $temCrediario) {
            continue;   // só uma forma crediário na lista
        }
        if (!$crediario && (int) $l['BLOQUEADA'] === 1) {
            continue;
        }
        $temCrediario = $temCrediario || $crediario;

        $cartao = in_array($tipo, ['C. DEBITO', 'C. CREDITO'], true);
        $forma = [
            'id'         => (int) $l['CODIGO_PAGAMENTO'],
            'tipo'       => trim((string) $l['TIPO_PAGAMENTO']),
            'rotulo'     => rotuloFormaPagamento($tipo),
            'cartao'     => $cartao,
            'crediario'  => $crediario,
            'adquirente' => 0,
            'bandeiras'  => [],
            // Vencimento padrão observado no sistema atual: crédito 30 dias, débito 2 dias, demais no mesmo dia
            'dias'       => $tipo === 'C. CREDITO' ? 30 : ($tipo === 'C. DEBITO' ? 2 : 0),
        ];

        if ($cartao) {
            $adq = consultarValor(
                "SELECT CODIGO_ADQUIRENTE FROM FORMA_PAGAMENTO_ADQUIRENTE
                  WHERE CODIGO_FORMA_PAGAMENTO = :f AND FPA_STATUS = 'Ativo'
                  ORDER BY FLAG_PADRAO DESC, CODIGO_ADQUIRENTE LIMIT 1",
                [':f' => $forma['id']]
            );
            if ($adq !== null) {
                $forma['adquirente'] = (int) $adq;
                $bandeiras = consultar(
                    "SELECT CODIGO_ADQUIRENTE_BANDEIRA AS ID, DESCRICAO AS NOME
                       FROM FORMA_PAGAMENTO_ADQUIRENTE_BANDEIRA
                      WHERE CODIGO_ADQUIRENTE = :a AND FPAB_STATUS = 'Ativo'
                      ORDER BY FLAG_PADRAO DESC, CODIGO_ADQUIRENTE_BANDEIRA",
                    [':a' => (int) $adq]
                );
                foreach ($bandeiras as $b) {
                    $forma['bandeiras'][] = ['id' => (int) $b['ID'], 'nome' => trim((string) $b['NOME'])];
                }
            }
        }

        $formas[$forma['id']] = $forma;
    }

    // Loja sem a forma CREDIÁRIO cadastrada: oferece assim mesmo (grava com o nome 'CREDIÁRIO')
    if (!$temCrediario) {
        $formas[-1] = [
            'id' => -1, 'tipo' => 'CREDIÁRIO', 'rotulo' => 'Crediário', 'cartao' => false, 'crediario' => true,
            'adquirente' => 0, 'bandeiras' => [], 'dias' => 0,
        ];
    }

    return $formas;
}

/**
 * Resumo das formas de pagamento: cadastro + quanto cada uma recebeu nas vendas finalizadas.
 * Retorna uma lista de ['rotulo', 'tipo', 'qtd', 'total', 'percentual', 'nova_venda', 'cadastrada', 'bloqueada'].
 */
function resumoFormasPagamento(string $dataIni = '', string $dataFim = ''): array
{
    $where  = "v.STATUS_VENDA = 'Finalizado' AND f.TIPO_MOVIMENTO = 'Pagamento'";
    $params = [];
    if ($dataIni !== '') {
        $where .= ' AND v.DATA >= :ini';
        $params[':ini'] = $dataIni;
    }
    if ($dataFim !== '') {
        $where .= ' AND v.DATA <= :fim';
        $params[':fim'] = $dataFim;
    }

    $estat = consultar(
        "SELECT f.TIPO_FORMA_PGTO AS FORMA, COUNT(*) AS QTD, COALESCE(SUM(f.VALOR), 0) AS TOTAL
           FROM FORMA_PAGAMENTO_VENDA f
           JOIN VENDAS v ON v.CODIGO_VENDA = f.CODIGO_VENDA
          WHERE $where
          GROUP BY f.TIPO_FORMA_PGTO",
        $params
    );

    // Junta variações de maiúsculas/minúsculas (ex.: "Credito" e "CREDITO")
    $porChave = [];
    foreach ($estat as $e) {
        $chave = mb_strtoupper(trim((string) $e['FORMA']));
        if (!isset($porChave[$chave])) {
            $porChave[$chave] = ['qtd' => 0, 'total' => 0.0];
        }
        $porChave[$chave]['qtd']   += (int) $e['QTD'];
        $porChave[$chave]['total'] += (float) $e['TOTAL'];
    }

    $liquido = array_sum(array_column($porChave, 'total'));
    $lista   = [];

    $cadastro = consultar(
        'SELECT CODIGO_PAGAMENTO, TIPO_PAGAMENTO, FLAG_BLOQUEIO
           FROM FORMA_PAGAMENTO
          WHERE TIPO_PAGAMENTO IS NOT NULL
          ORDER BY CODIGO_PAGAMENTO'
    );
    foreach ($cadastro as $c) {
        $chave = mb_strtoupper(trim((string) $c['TIPO_PAGAMENTO']));
        $dados = $porChave[$chave] ?? ['qtd' => 0, 'total' => 0.0];
        unset($porChave[$chave]);

        $lista[] = [
            'rotulo'     => rotuloFormaPagamento($chave),
            'tipo'       => trim((string) $c['TIPO_PAGAMENTO']),
            'qtd'        => $dados['qtd'],
            'total'      => $dados['total'],
            'percentual' => $liquido > 0 ? $dados['total'] * 100 / $liquido : 0.0,
            'nova_venda' => in_array($chave, FORMAS_NOVA_VENDA, true),
            'cadastrada' => true,
            'bloqueada'  => (int) $c['FLAG_BLOQUEIO'] === 1,
        ];
    }

    // Lançamentos que não são formas cadastradas (ex.: TROCO)
    foreach ($porChave as $chave => $dados) {
        $lista[] = [
            'rotulo'     => rotuloFormaPagamento($chave),
            'tipo'       => $chave,
            'qtd'        => $dados['qtd'],
            'total'      => $dados['total'],
            'percentual' => $liquido > 0 ? $dados['total'] * 100 / $liquido : 0.0,
            'nova_venda' => false,
            'cadastrada' => false,
            'bloqueada'  => false,
        ];
    }

    return ['formas' => $lista, 'liquido' => $liquido];
}

/** Nomes das formas cadastradas (para os filtros). */
function listarTiposPagamento(): array
{
    return array_column(
        consultar('SELECT TIPO_PAGAMENTO FROM FORMA_PAGAMENTO WHERE TIPO_PAGAMENTO IS NOT NULL ORDER BY CODIGO_PAGAMENTO'),
        'TIPO_PAGAMENTO'
    );
}
