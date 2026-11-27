<?php
/**
 * vendas_funcoes.php
 * Regras do módulo de vendas, usando VENDAS, ITENS_VENDA e FORMA_PAGAMENTO_VENDA do banco da loja.
 *
 * Convenções do sistema atual que seguimos ao gravar:
 *   - TOTAL = VALOR (soma dos itens) - DESCONTO. O frete é informado à parte e não entra no TOTAL.
 *   - Só vendas com STATUS_VENDA = 'Finalizado' entram nos totais (as canceladas ficam fora).
 *   - HORA no formato "HH:MM:SS:cc" (centésimos no final).
 *   - Cada pagamento é uma linha em FORMA_PAGAMENTO_VENDA; o estoque do produto é baixado na venda.
 *
 * Condição (cliente separou as peças e ainda não pagou), com os mesmos status que o sistema atual usa:
 *   - 'Condicao': gravada como uma venda sem pagamentos; as peças saem do estoque (ficam reservadas).
 *   - 'Condicao Cancelada': a cliente desistiu; as peças voltam ao estoque.
 *   - 'Condicao Finalizada': virou venda. É criada uma venda 'Finalizado' nova (com a data do pagamento)
 *     e a condição é encerrada; as peças que a cliente não levou voltam ao estoque.
 *   Nenhum desses status entra nos totais (só 'Finalizado').
 *
 * Crédito na loja (ver devolucoes_funcoes.php): campo "Pagar com crédito da loja" (credito_usar). O valor usado
 * entra no DESCONTO da venda (TOTAL = VALOR - DESCONTO) e o uso fica em CREDITO_CLIENTE. Se o crédito cobre a
 * venda inteira, ela fecha com total zero e sem pagamento. nova_venda.php?troca=ID abre a venda com a cliente
 * da troca e o crédito pronto para usar.
 *
 * Crediário (ver crediario_funcoes.php): é uma forma de pagamento da nova venda. A venda fica 'Finalizado';
 * em FORMA_PAGAMENTO_VENDA vai uma linha por parcela e as parcelas ficam em CREDIARIO_PARCELA para dar baixa.
 */

require_once __DIR__ . '/pagamentos_funcoes.php';
require_once __DIR__ . '/produtos_funcoes.php';
require_once __DIR__ . '/clientes_funcoes.php';
require_once __DIR__ . '/devolucoes_funcoes.php';
require_once __DIR__ . '/crediario_funcoes.php';

const STATUS_VENDA_FINALIZADA   = 'Finalizado';
const STATUS_CONDICAO           = 'Condicao';
const STATUS_CONDICAO_CANCELADA = 'Condicao Cancelada';
const STATUS_CONDICAO_FINALIZADA = 'Condicao Finalizada';

// ---------- Histórico ----------

/** Ordens aceitas na lista de vendas: valor do filtro => rótulo da tela. */
const ORDENS_VENDAS = ['recentes' => 'Mais recentes primeiro', 'antigas' => 'Mais antigas primeiro'];

/** 'recentes' ou 'antigas' (qualquer outro valor vira 'recentes'). */
function ordemValida(?string $ordem): string
{
    $ordem = trim((string) $ordem);
    return array_key_exists($ordem, ORDENS_VENDAS) ? $ordem : 'recentes';
}

/** Trecho do ORDER BY de VENDAS (alias v) conforme a ordem escolhida. */
function sqlOrdemVendas(string $ordem): string
{
    return $ordem === 'antigas'
        ? 'v.DATA ASC, v.HORA ASC, v.CODIGO_VENDA ASC'
        : 'v.DATA DESC, v.HORA DESC, v.CODIGO_VENDA DESC';
}

function filtrosVendas(array $get): array
{
    return [
        'busca'     => trim((string) ($get['busca'] ?? '')),
        'data_ini'  => dataValida($get['data_ini'] ?? ''),
        'data_fim'  => dataValida($get['data_fim'] ?? ''),
        'status'    => mb_substr(trim((string) ($get['status'] ?? STATUS_VENDA_FINALIZADA)), 0, 30),
        'pagamento' => mb_substr(trim((string) ($get['pagamento'] ?? '')), 0, 100),
        'ordem'     => ordemValida($get['ordem'] ?? ''),
    ];
}

function whereVendas(array $f, array &$params): string
{
    $where = ['1 = 1'];

    if ($f['busca'] !== '') {
        $cond = ['c.NOME LIKE :busca_nome'];
        $params[':busca_nome'] = '%' . $f['busca'] . '%';
        if (ctype_digit($f['busca'])) {
            $cond[] = 'v.CODIGO_VENDA = :busca_cod';
            $params[':busca_cod'] = (int) $f['busca'];
        }
        $where[] = '(' . implode(' OR ', $cond) . ')';
    }
    if ($f['data_ini'] !== '') {
        $where[] = 'v.DATA >= :data_ini';
        $params[':data_ini'] = $f['data_ini'];
    }
    if ($f['data_fim'] !== '') {
        $where[] = 'v.DATA <= :data_fim';
        $params[':data_fim'] = $f['data_fim'];
    }
    if ($f['status'] !== '') {
        $where[] = 'v.STATUS_VENDA = :status';
        $params[':status'] = $f['status'];
    }
    if ($f['pagamento'] !== '') {
        $where[] = "EXISTS (SELECT 1 FROM FORMA_PAGAMENTO_VENDA fp
                             WHERE fp.CODIGO_VENDA = v.CODIGO_VENDA
                               AND fp.TIPO_MOVIMENTO = 'Pagamento' AND fp.TIPO_FORMA_PGTO = :pagamento)";
        $params[':pagamento'] = $f['pagamento'];
    }

    return implode(' AND ', $where);
}

/** Quantidade de vendas da busca e soma das FINALIZADAS (canceladas nunca entram no valor). */
function resumoListaVendas(array $f): array
{
    $params = [];
    $where  = whereVendas($f, $params);

    $r = consultarUma(
        "SELECT COUNT(*) AS QTD,
                COALESCE(SUM(CASE WHEN v.STATUS_VENDA = 'Finalizado' THEN v.TOTAL ELSE 0 END), 0) AS TOTAL
           FROM VENDAS v
           LEFT JOIN CLIENTE c ON c.CODIGO = v.CODIGO_CLIENTE
          WHERE $where",
        $params
    );
    return $r ?? ['QTD' => 0, 'TOTAL' => 0];
}

/** Uma página do histórico, já com o nome do cliente e as formas de pagamento de cada venda. */
function listarVendas(array $f, int $limite, int $offset): array
{
    $params = [];
    $where  = whereVendas($f, $params);
    $params[':limite']  = $limite;
    $params[':posicao'] = $offset;

    $vendas = consultar(
        "SELECT v.CODIGO_VENDA, v.DATA, v.HORA, v.CODIGO_CLIENTE, v.VALOR, v.DESCONTO, v.TOTAL,
                v.NUM_PECAS, v.STATUS_VENDA, c.NOME AS CLIENTE_NOME
           FROM VENDAS v
           LEFT JOIN CLIENTE c ON c.CODIGO = v.CODIGO_CLIENTE
          WHERE $where
          ORDER BY " . sqlOrdemVendas(ordemValida($f['ordem'] ?? '')) . "
          LIMIT :limite OFFSET :posicao",
        $params
    );

    $formas = formasPorVenda(array_map('intval', array_column($vendas, 'CODIGO_VENDA')));
    foreach ($vendas as &$venda) {
        $venda['FORMAS'] = $formas[(int) $venda['CODIGO_VENDA']] ?? [];
    }
    unset($venda);

    return $vendas;
}

/** Formas de pagamento usadas em cada venda: [codigo_venda => ['Dinheiro', 'Cartão de crédito']]. */
function formasPorVenda(array $ids): array
{
    if (empty($ids)) {
        return [];
    }

    $marcas = implode(',', array_fill(0, count($ids), '?'));
    $linhas = consultar(
        "SELECT DISTINCT CODIGO_VENDA, TIPO_FORMA_PGTO
           FROM FORMA_PAGAMENTO_VENDA
          WHERE CODIGO_VENDA IN ($marcas) AND TIPO_MOVIMENTO = 'Pagamento'
          ORDER BY CODIGO_VENDA",
        array_values($ids)
    );

    $mapa = [];
    foreach ($linhas as $l) {
        if (mb_strtoupper(trim((string) $l['TIPO_FORMA_PGTO'])) === 'TROCO') {
            continue;
        }
        $rotulo = rotuloFormaPagamento($l['TIPO_FORMA_PGTO']);
        $venda  = (int) $l['CODIGO_VENDA'];
        if (!in_array($rotulo, $mapa[$venda] ?? [], true)) {
            $mapa[$venda][] = $rotulo;
        }
    }
    return $mapa;
}

function listarStatusVendas(): array
{
    return array_column(
        consultar('SELECT DISTINCT STATUS_VENDA FROM VENDAS WHERE STATUS_VENDA IS NOT NULL ORDER BY STATUS_VENDA'),
        'STATUS_VENDA'
    );
}

// ---------- Detalhe ----------

function buscarVenda(int $id): ?array
{
    return consultarUma(
        'SELECT v.*, c.NOME AS CLIENTE_NOME
           FROM VENDAS v
           LEFT JOIN CLIENTE c ON c.CODIGO = v.CODIGO_CLIENTE
          WHERE v.CODIGO_VENDA = :id',
        [':id' => $id]
    );
}

function itensDaVenda(int $id): array
{
    return consultar(
        'SELECT i.*, fu.NOME_FUN AS VENDEDOR
           FROM ITENS_VENDA i
           LEFT JOIN FUNCIONARIOS_USUARIOS fu ON fu.CODIGO = i.CODIGO_VENDEDOR
          WHERE i.CODIGO_VENDA = :id
          ORDER BY i.CODIGO_ITEM_VENDA',
        [':id' => $id]
    );
}

function pagamentosDaVenda(int $id): array
{
    return consultar(
        'SELECT * FROM FORMA_PAGAMENTO_VENDA WHERE CODIGO_VENDA = :id ORDER BY CODIGO',
        [':id' => $id]
    );
}

function listarVendedores(): array
{
    return consultar(
        "SELECT CODIGO, TRIM(NOME_FUN) AS NOME FROM FUNCIONARIOS_USUARIOS
          WHERE STATUS = 'Ativo' AND NOME_FUN IS NOT NULL ORDER BY TRIM(NOME_FUN)"
    );
}

// ---------- Números do painel ----------

function resumoPeriodoVendas(string $ini, string $fim): array
{
    $r = consultarUma(
        "SELECT COUNT(*) AS QTD, COALESCE(SUM(TOTAL), 0) AS TOTAL, COALESCE(SUM(NUM_PECAS), 0) AS PECAS
           FROM VENDAS
          WHERE STATUS_VENDA = 'Finalizado' AND DATA >= :ini AND DATA <= :fim",
        [':ini' => $ini, ':fim' => $fim]
    );
    return $r ?? ['QTD' => 0, 'TOTAL' => 0, 'PECAS' => 0];
}

/** Total vendido nos últimos N meses (inclui meses sem venda, com zero): [['mes' => '2026-11', 'qtd' => 10, 'total' => 1234.5], ...] */
function vendasPorMes(int $meses = 6): array
{
    $primeiro = new DateTime('first day of this month');
    $primeiro->modify('-' . ($meses - 1) . ' months');

    $linhas = consultar(
        "SELECT SUBSTR(DATA, 1, 7) AS MES, COUNT(*) AS QTD, COALESCE(SUM(TOTAL), 0) AS TOTAL
           FROM VENDAS
          WHERE STATUS_VENDA = 'Finalizado' AND DATA >= :ini
          GROUP BY SUBSTR(DATA, 1, 7)
          ORDER BY MES",
        [':ini' => $primeiro->format('Y-m-d')]
    );
    $porMes = array_column($linhas, null, 'MES');

    $saida = [];
    for ($i = 0; $i < $meses; $i++) {
        $chave   = $primeiro->format('Y-m');
        $saida[] = [
            'mes'   => $chave,
            'qtd'   => (int) ($porMes[$chave]['QTD'] ?? 0),
            'total' => (float) ($porMes[$chave]['TOTAL'] ?? 0),
        ];
        $primeiro->modify('+1 month');
    }
    return $saida;
}

/** Produtos mais vendidos desde uma data (somente vendas finalizadas e itens não devolvidos). */
function produtosMaisVendidos(string $ini, int $limite = 5): array
{
    return consultar(
        "SELECT i.CODIGO_PRODUTO, MAX(i.DESCRICAO) AS DESCRICAO, SUM(i.QUANT) AS QTD, SUM(i.PRECO_TOTAL) AS TOTAL
           FROM VENDAS v
           JOIN ITENS_VENDA i ON i.CODIGO_VENDA = v.CODIGO_VENDA
          WHERE v.STATUS_VENDA = 'Finalizado' AND v.DATA >= :ini AND i.STATUS_ITENS_VENDA = 'Ativo'
          GROUP BY i.CODIGO_PRODUTO
          ORDER BY QTD DESC, TOTAL DESC
          LIMIT :n",
        [':ini' => $ini, ':n' => $limite]
    );
}

// ---------- Nova venda ----------

/**
 * Lê e valida o formulário de nova venda. Os preços vêm SEMPRE do banco (nunca do navegador).
 * Retorna ['erros' => [...mensagens], 'venda' => dados prontos para gravar, 'estado' => dados para repovoar a tela].
 */
function prepararNovaVenda(array $post): array
{
    $erros = [];

    // ----- O que gravar: venda paga ('finalizar') ou condição ('condicao') -----
    $ehCondicao = ($post['acao'] ?? '') === 'condicao';

    // Finalizando uma condição já existente: as peças dela estão reservadas e contam como disponíveis
    $condicaoId = (int) ($post['condicao_id'] ?? 0);
    $reservado  = [];
    if ($condicaoId > 0) {
        if ($ehCondicao) {
            $erros[] = 'Esta condição já está gravada. Use "Finalizar venda" para receber o pagamento.';
        } elseif (buscarCondicao($condicaoId) === null) {
            $erros[] = 'Esta condição não está mais em aberto (já foi finalizada ou cancelada).';
            $condicaoId = 0;
        } else {
            $reservado = itensReservados($condicaoId);
        }
    }

    // ----- Cliente (obrigatório) e vendedor -----
    $clienteId = (int) ($post['cliente_id'] ?? 0);
    $cliente   = null;
    if ($clienteId <= 0) {
        $erros[] = 'Selecione a cliente da venda.';
    } else {
        $cliente = consultarUma('SELECT CODIGO, NOME FROM CLIENTE WHERE CODIGO = :id', [':id' => $clienteId]);
        if ($cliente === null) {
            $erros[] = 'Cliente não encontrado.';
        }
    }

    $vendedorId = (int) ($post['vendedor_id'] ?? CODIGO_USUARIO_PADRAO);
    if ((int) consultarValor('SELECT COUNT(*) FROM FUNCIONARIOS_USUARIOS WHERE CODIGO = :id', [':id' => $vendedorId]) < 1) {
        $erros[] = 'Vendedor não encontrado.';
    }

    // ----- Itens (se o mesmo produto vier duas vezes, soma as quantidades) -----
    $pedidos = [];
    $ids     = is_array($post['produto_id'] ?? null) ? $post['produto_id'] : [];
    $qtds    = is_array($post['quantidade'] ?? null) ? $post['quantidade'] : [];
    foreach ($ids as $i => $pid) {
        $pid = (int) $pid;
        if ($pid > 0) {
            $pedidos[$pid] = ($pedidos[$pid] ?? 0) + (int) ($qtds[$i] ?? 0);
        }
    }

    if (empty($pedidos)) {
        $erros[] = 'Adicione ao menos um produto à venda.';
    }

    $itens        = [];
    $estadoItens  = [];
    $subtotalCent = 0;
    $pecas        = 0;

    foreach ($pedidos as $pid => $qtd) {
        $p = buscarProduto($pid);
        if ($p === null || $p['STATUS_PRODUTO'] !== 'Ativo') {
            $erros[] = "O produto #$pid não está disponível para venda.";
            continue;
        }

        $nome       = trim((string) $p['DESCRICAO']);
        $precoCent  = (int) round((float) $p['PRE_VEN'] * 100);
        $disponivel = (float) $p['QUANT'] + ($reservado[$pid] ?? 0);
        $estadoItens[] = [
            'id' => $pid, 'nome' => $nome, 'tamanho' => trim((string) $p['TAMANHO']),
            'preco' => $precoCent / 100, 'estoque' => $disponivel, 'qtd' => max(1, $qtd),
        ];

        if ($qtd < 1 || $qtd > 9999) {
            $erros[] = "Quantidade inválida para \"$nome\".";
            continue;
        }
        if ($disponivel < $qtd) {
            $erros[] = "Estoque insuficiente para \"$nome\" (disponível: " . formatarQuantidade($disponivel) . ').';
            continue;
        }

        $itens[] = [
            'id' => $pid, 'nome' => $nome, 'referencia' => $p['REFERENCIA'], 'cod_barras' => $p['COD_BARRAS_FORN'],
            'qtd' => $qtd, 'preco_cent' => $precoCent,
        ];
        $subtotalCent += $precoCent * $qtd;
        $pecas        += $qtd;
    }

    // ----- Desconto e frete -----
    $descontoCent = (int) round(paraDecimal($post['desconto'] ?? '') * 100);
    $freteCent    = (int) round(paraDecimal($post['frete'] ?? '') * 100);

    if ($descontoCent < 0 || $descontoCent > $subtotalCent) {
        $erros[] = 'O desconto não pode ser negativo nem maior que o valor dos produtos.';
    }
    if ($freteCent < 0) {
        $erros[] = 'O frete não pode ser negativo.';
    }

    // ----- Crédito na loja -----
    $saldoCredito = $cliente ? saldoCredito((int) $cliente['CODIGO']) : 0.0;
    $creditoCent  = $ehCondicao ? 0 : (int) round(paraDecimal($post['credito_usar'] ?? '') * 100);   // condição não usa crédito
    if ($creditoCent < 0) {
        $erros[] = 'O crédito usado não pode ser negativo.';
        $creditoCent = 0;
    } elseif ($creditoCent > 0) {
        $maxVendaCent = max(0, $subtotalCent - max(0, $descontoCent));
        if ($creditoCent > (int) round($saldoCredito * 100)) {
            $erros[] = 'A cliente tem só ' . formatarMoeda($saldoCredito) . ' de crédito na loja.';
        } elseif ($creditoCent > $maxVendaCent) {
            $erros[] = 'O crédito usado (' . formatarMoeda($creditoCent / 100) . ') não pode passar do valor da venda ('
                     . formatarMoeda($maxVendaCent / 100) . '). O que sobrar continua no saldo da cliente.';
        }
    }
    $totalCent = $subtotalCent - $descontoCent - $creditoCent;   // o que a cliente ainda paga

    // ----- Pagamentos -----
    $formas      = formasNovaVenda();
    $formaIds    = is_array($post['forma_id'] ?? null) ? $post['forma_id'] : [];
    $valores     = is_array($post['valor_pagto'] ?? null) ? $post['valor_pagto'] : [];
    $bandeiraIds = is_array($post['bandeira_id'] ?? null) ? $post['bandeira_id'] : [];
    $qtdParcelas = is_array($post['parcelas_pagto'] ?? null) ? $post['parcelas_pagto'] : [];
    $vencimentos = is_array($post['venc_pagto'] ?? null) ? $post['venc_pagto'] : [];
    $crediarios  = 0;
    $pagamentos  = [];
    $estadoPagtos = [];
    $somaCent    = 0;

    // Condição não tem pagamento; venda que o crédito da loja cobre inteira também não: o que vier nos campos é ignorado
    $semPagamento = $ehCondicao || ($creditoCent > 0 && $totalCent === 0 && !empty($itens));
    if ($semPagamento) {
        $formaIds = [];
    }

    foreach ($formaIds as $i => $fid) {
        $fid       = (int) $fid;
        $valorCent = (int) round(paraDecimal($valores[$i] ?? '') * 100);
        $estadoPagtos[] = [
            'forma_id' => $fid, 'valor' => $valorCent / 100, 'bandeira_id' => (int) ($bandeiraIds[$i] ?? 0), 'auto' => false,
            'parcelas' => (int) ($qtdParcelas[$i] ?? 0), 'vencimento' => dataValida($vencimentos[$i] ?? ''),
        ];

        if (!isset($formas[$fid])) {
            $erros[] = 'Forma de pagamento inválida.';
            continue;
        }
        if ($valorCent <= 0) {
            $erros[] = 'Informe o valor de cada pagamento (maior que zero).';
            continue;
        }

        $forma = $formas[$fid];
        $bandeiraId = 0;
        $bandeiraNome = null;
        if ($forma['cartao'] && !empty($forma['bandeiras'])) {
            $bandeiraId   = (int) ($bandeiraIds[$i] ?? 0);
            $escolhida    = null;
            foreach ($forma['bandeiras'] as $b) {
                if ($b['id'] === $bandeiraId) {
                    $escolhida = $b;
                }
            }
            $escolhida    = $escolhida ?? $forma['bandeiras'][0];     // padrão: primeira bandeira da lista
            $bandeiraId   = $escolhida['id'];
            $bandeiraNome = $escolhida['nome'];
        }

        // Crediário: número de parcelas e 1º vencimento
        $parcelas = [];
        if (!empty($forma['crediario'])) {
            $crediarios++;
            $n     = (int) ($qtdParcelas[$i] ?? 0);
            $venc1 = dataValida($vencimentos[$i] ?? '');
            $limite = date('Y-m-d', strtotime('+' . CREDIARIO_MAX_DIAS_1VENC . ' days'));
            if ($n < 1 || $n > CREDIARIO_MAX_PARCELAS) {
                $erros[] = 'Crediário: escolha de 1 a ' . CREDIARIO_MAX_PARCELAS . ' parcelas.';
                $somaCent += $valorCent;   // evita o aviso de soma, o erro já explica
                continue;
            }
            if ($venc1 === '' || $venc1 < date('Y-m-d') || $venc1 > $limite) {
                $erros[] = 'Crediário: o 1º vencimento precisa ser entre hoje e ' . formatarData($limite) . '.';
                $somaCent += $valorCent;   // evita o aviso de soma, o erro já explica
                continue;
            }
            if ($valorCent < $n) {
                $erros[] = 'Crediário: o valor é pequeno demais para ' . $n . ' parcelas.';
                $somaCent += $valorCent;   // evita o aviso de soma, o erro já explica
                continue;
            }
            $parcelas = calcularParcelas($valorCent, $n, $venc1);
        }

        $pagamentos[] = [
            'tipo' => $forma['tipo'], 'valor_cent' => $valorCent, 'adquirente' => $forma['adquirente'],
            'bandeira_id' => $bandeiraId, 'bandeira' => $bandeiraNome, 'dias' => $forma['dias'],
            'parcelas' => $parcelas,
        ];
        $somaCent += $valorCent;
    }

    if ($crediarios > 1) {
        $erros[] = 'Use o crediário em uma linha só (escolha ali o número de parcelas).';
    }

    if ($semPagamento) {
        // sem pagamento
    } elseif (empty($formaIds)) {
        $erros[] = 'Informe a forma de pagamento.';
    } elseif ($somaCent !== $totalCent && empty(array_filter($erros, fn($e) => str_contains($e, 'pagamento')))) {
        $erros[] = 'A soma dos pagamentos (' . formatarMoeda($somaCent / 100) . ') precisa ser igual ao total da venda ('
                 . formatarMoeda($totalCent / 100) . ').';
    }

    return [
        'erros' => $erros,
        'venda' => [
            'cliente_id' => $cliente ? (int) $cliente['CODIGO'] : null, 'vendedor_id' => $vendedorId,
            'itens' => $itens, 'pecas' => $pecas, 'subtotal_cent' => $subtotalCent,
            // O crédito usado entra no DESCONTO gravado (TOTAL = VALOR - DESCONTO)
            'desconto_cent' => $descontoCent + $creditoCent, 'frete_cent' => $freteCent, 'total_cent' => $totalCent,
            'credito_cent' => $creditoCent,
            'pagamentos' => $pagamentos,
            'status' => $ehCondicao ? STATUS_CONDICAO : STATUS_VENDA_FINALIZADA,
            'condicao_id' => $condicaoId > 0 ? $condicaoId : null,
        ],
        'estado' => [
            'cliente' => $cliente ? ['id' => (int) $cliente['CODIGO'], 'nome' => trim((string) $cliente['NOME']), 'credito' => $saldoCredito] : null,
            'vendedor_id' => $vendedorId, 'itens' => $estadoItens, 'pagamentos' => $estadoPagtos,
            'desconto' => $descontoCent / 100, 'frete' => $freteCent / 100,
            'condicao_id' => $condicaoId > 0 ? $condicaoId : null,
            'credito_usar' => $creditoCent / 100,
        ],
    ];
}

/**
 * Grava a venda inteira (venda + itens + pagamentos + baixa de estoque) numa única transação.
 * $v['status'] = 'Finalizado' (venda paga) ou 'Condicao' (peças separadas, sem pagamento).
 * $v['condicao_id'] = condição que está sendo paga: ela é encerrada e suas peças voltam ao estoque
 * antes de baixar as peças da venda nova (tudo na mesma transação).
 * $v['credito_cent'] = crédito da loja usado: sai do saldo da cliente (conferido de novo dentro da transação).
 */
function registrarVenda(array $v): int
{
    // Crediário: a tabela de parcelas precisa existir ANTES da transação (CREATE TABLE encerra a transação no MySQL)
    foreach ($v['pagamentos'] ?? [] as $pg) {
        if (!empty($pg['parcelas'])) {
            garantirTabelasCrediario();
        }
    }

    if (!empty($v['credito_cent'])) {
        garantirTabelasDevolucao();   // tabela do crédito, antes da transação
    }

    return emTransacao(function () use ($v) {
        $status = $v['status'] ?? STATUS_VENDA_FINALIZADA;

        if (!empty($v['condicao_id'])) {
            encerrarCondicao((int) $v['condicao_id'], STATUS_CONDICAO_FINALIZADA);
        }

        $dinheiro = fn(int $centavos): string => paraBanco($centavos / 100);
        $agora    = microtime(true);
        $hoje     = date('Y-m-d');
        $hora     = date('H:i:s') . ':' . str_pad((string) (int) (fmod($agora, 1) * 100), 2, '0', STR_PAD_LEFT);
        $porcDesc = $v['subtotal_cent'] > 0 ? round($v['desconto_cent'] * 100 / $v['subtotal_cent'], 3) : 0;

        executar(
            "INSERT INTO VENDAS
                (LOJA, DATA, HORA, CODIGO_CAIXA, CODIGO_CLIENTE, VALOR, DESCONTO, PORC_DESC, TOTAL, NUM_PECAS,
                 TIPO_VENDA, STATUS_VENDA, CODIGO_USUARIO, DATA_INICIO_CONDICAO, COD_RECEITA,
                 TAXA_CARTAO, TAXA_REPASSE, TAXA_FIXA, TAXA_CARTAO_ADIANTAMENTO, VALOR_ACRESCIMO_OUTROS,
                 VALOR_FRETE, CODIGO_TRANSPORTADORA, FRETE_PAGO_CLIENTE)
             VALUES
                (:loja, :data, :hora, :caixa, :cliente, :valor, :desc, :porc, :total, :pecas,
                 'Loja', :status, :usuario, :inicio, 0,
                 0, 0, 0, 0, 0,
                 :frete, 0, :fretepago)",
            [
                ':loja' => CODIGO_LOJA, ':data' => $hoje, ':hora' => $hora, ':caixa' => CODIGO_CAIXA_PADRAO,
                ':cliente' => $v['cliente_id'], ':valor' => $dinheiro($v['subtotal_cent']),
                ':desc' => $dinheiro($v['desconto_cent']), ':porc' => paraBanco((float) $porcDesc, 3),
                ':total' => $dinheiro($v['total_cent']), ':pecas' => paraBanco((float) $v['pecas'], 3),
                ':status' => $status, ':usuario' => CODIGO_USUARIO_PADRAO, ':inicio' => $hoje,
                ':frete' => $dinheiro($v['frete_cent']), ':fretepago' => $v['frete_cent'] > 0 ? 1 : 0,
            ]
        );
        $vendaId = ultimoId();

        foreach ($v['itens'] as $item) {
            executar(
                "INSERT INTO ITENS_VENDA
                    (LOJA, CODIGO_VENDA, CODIGO_PRODUTO, CODIGO_BARRAS, QUANT, PRECO_UNIT, PRECO_TOTAL, DESCRICAO,
                     STATUS_ITENS_VENDA, CODIGO_VENDEDOR, REFERENCIA, PORC_DESC, VALOR_DESC)
                 VALUES
                    (:loja, :venda, :produto, :cb, :qtd, :unit, :total, :desc, 'Ativo', :vendedor, :ref, 0, 0)",
                [
                    ':loja' => CODIGO_LOJA, ':venda' => (string) $vendaId, ':produto' => $item['id'],
                    ':cb' => $item['cod_barras'], ':qtd' => paraBanco((float) $item['qtd'], 3),
                    ':unit' => $dinheiro($item['preco_cent']), ':total' => $dinheiro($item['preco_cent'] * $item['qtd']),
                    ':desc' => $item['nome'], ':vendedor' => $v['vendedor_id'], ':ref' => $item['referencia'],
                ]
            );

            // Baixa de estoque protegida: só baixa se ainda houver saldo (evita estoque negativo por venda simultânea)
            $baixou = executar(
                'UPDATE PRODUTOS SET QUANT = QUANT - :qtd, DAT_ULT_SAID = :data WHERE CODIGO = :id AND QUANT >= :minimo',
                [':qtd' => paraBanco((float) $item['qtd'], 3), ':data' => $hoje, ':id' => $item['id'],
                 ':minimo' => paraBanco((float) $item['qtd'], 3)]
            );
            if ($baixou !== 1) {
                throw new RuntimeException('Estoque insuficiente para "' . $item['nome'] . '". Ninguém foi cobrado: a venda não foi gravada.');
            }
        }

        foreach ($v['pagamentos'] as $pg) {
            // Crediário: uma linha por parcela, com o vencimento de cada uma
            if (!empty($pg['parcelas'])) {
                $idsPagamento = [];
                foreach ($pg['parcelas'] as $parc) {
                    $idsPagamento[$parc['numero']] = inserirPagamentoVenda($vendaId, $v['cliente_id'], $pg, $parc['valor_cent'], $parc['vencimento'], $parc['numero']);
                }
                gravarParcelasCrediario($vendaId, $v['cliente_id'], $pg['parcelas'], $idsPagamento);
                continue;
            }

            $vencimento = date('Y-m-d', strtotime("+{$pg['dias']} days"));
            executar(
                "INSERT INTO FORMA_PAGAMENTO_VENDA
                    (LOJA, COD_CAIXA, CODIGO_CLIENTE, CODIGO_VENDA, TIPO_FORMA_PGTO, TIPO_MOVIMENTO, BANDEIRA,
                     NUMERO_DOCUMENTO, DAT_VENC, VALOR, CODIGO_USUARIO, PARCELA, TAXA_PARCELA_CARTAO,
                     TAXA_PARCELA_REPASSE, CODIGO_ADQUIRENTE, CODIGO_ADQUIRENTE_BANDEIRA,
                     TAXA_CARTAO_ADIANTAMENTO, TAXA_FIXA)
                 VALUES
                    (:loja, :caixa, :cliente, :venda, :tipo, 'Pagamento', :bandeira,
                     :doc, :venc, :valor, :usuario, 1, 0,
                     0, :adq, :adqband,
                     0, 0)",
                [
                    ':loja' => CODIGO_LOJA, ':caixa' => CODIGO_CAIXA_PADRAO, ':cliente' => $v['cliente_id'],
                    ':venda' => (string) $vendaId, ':tipo' => $pg['tipo'], ':bandeira' => $pg['bandeira'],
                    ':doc' => str_pad((string) $vendaId, 10, '0', STR_PAD_LEFT) . '\\01',
                    ':venc' => $vencimento, ':valor' => $dinheiro($pg['valor_cent']),
                    ':usuario' => (string) CODIGO_USUARIO_PADRAO,
                    ':adq' => $pg['adquirente'], ':adqband' => $pg['bandeira_id'],
                ]
            );
        }

        if (!empty($v['credito_cent'])) {
            usarCredito((int) $v['cliente_id'], $vendaId, (int) $v['credito_cent']);
        }

        return $vendaId;
    });
}

/** Grava uma linha de FORMA_PAGAMENTO_VENDA (usada nas parcelas do crediário). Devolve o CODIGO gerado. */
function inserirPagamentoVenda(int $vendaId, ?int $clienteId, array $pg, int $valorCent, string $vencimento, int $parcela): int
{
    executar(
        "INSERT INTO FORMA_PAGAMENTO_VENDA
            (LOJA, COD_CAIXA, CODIGO_CLIENTE, CODIGO_VENDA, TIPO_FORMA_PGTO, TIPO_MOVIMENTO, BANDEIRA,
             NUMERO_DOCUMENTO, DAT_VENC, VALOR, CODIGO_USUARIO, PARCELA, TAXA_PARCELA_CARTAO,
             TAXA_PARCELA_REPASSE, CODIGO_ADQUIRENTE, CODIGO_ADQUIRENTE_BANDEIRA,
             TAXA_CARTAO_ADIANTAMENTO, TAXA_FIXA)
         VALUES
            (:loja, :caixa, :cliente, :venda, :tipo, 'Pagamento', :bandeira,
             :doc, :venc, :valor, :usuario, :parcela, 0,
             0, :adq, :adqband,
             0, 0)",
        [
            ':loja' => CODIGO_LOJA, ':caixa' => CODIGO_CAIXA_PADRAO, ':cliente' => $clienteId,
            ':venda' => (string) $vendaId, ':tipo' => $pg['tipo'], ':bandeira' => $pg['bandeira'],
            ':doc' => str_pad((string) $vendaId, 10, '0', STR_PAD_LEFT) . '\\' . str_pad((string) $parcela, 2, '0', STR_PAD_LEFT),
            ':venc' => $vencimento, ':valor' => paraBanco($valorCent / 100),
            ':usuario' => (string) CODIGO_USUARIO_PADRAO, ':parcela' => $parcela,
            ':adq' => $pg['adquirente'], ':adqband' => $pg['bandeira_id'],
        ]
    );
    return ultimoId();
}

/**
 * Dados para abrir a nova venda depois de uma troca: a cliente da troca e o crédito dela já em
 * "Pagar com crédito da loja" (no máximo o saldo atual). Null se a troca não existir ou o crédito já acabou.
 */
function estadoDaTroca(int $id): ?array
{
    $troca = trocaEmAberto($id);
    if ($troca === null || !$troca['CODIGO_CLIENTE']) {
        return null;
    }
    $cliente = (int) $troca['CODIGO_CLIENTE'];
    $saldo   = saldoCredito($cliente);
    if ($saldo <= 0) {
        return null;
    }
    $estado = estadoComCliente($cliente);
    $estado['credito_usar'] = min((float) $troca['VALOR'], $saldo);
    $estado['troca_id']     = $id;
    return $estado;
}

/** Dados para abrir a nova venda já com a cliente escolhida (e o saldo de crédito dela). */
function estadoComCliente(int $clienteId): ?array
{
    $c = consultarUma('SELECT CODIGO, NOME FROM CLIENTE WHERE CODIGO = :id', [':id' => $clienteId]);
    if ($c === null) {
        return null;
    }
    return [
        'cliente' => ['id' => (int) $c['CODIGO'], 'nome' => trim((string) $c['NOME']), 'credito' => saldoCredito($clienteId)],
        'vendedor_id' => CODIGO_USUARIO_PADRAO, 'itens' => [], 'pagamentos' => [],
        'desconto' => 0, 'frete' => 0, 'condicao_id' => null, 'credito_usar' => 0,
    ];
}

// ---------- Condição (peças separadas, ainda sem pagamento) ----------

/** A condição em aberto com esse código (ou null se não existir / já foi finalizada ou cancelada). */
function buscarCondicao(int $id): ?array
{
    return consultarUma(
        'SELECT v.*, c.NOME AS CLIENTE_NOME
           FROM VENDAS v
           LEFT JOIN CLIENTE c ON c.CODIGO = v.CODIGO_CLIENTE
          WHERE v.CODIGO_VENDA = :id AND v.STATUS_VENDA = :status',
        [':id' => $id, ':status' => STATUS_CONDICAO]
    );
}

/** Peças reservadas por uma condição: [codigo_produto => quantidade]. */
function itensReservados(int $id): array
{
    $linhas = consultar(
        "SELECT CODIGO_PRODUTO, SUM(QUANT) AS QTD
           FROM ITENS_VENDA
          WHERE CODIGO_VENDA = :id AND STATUS_ITENS_VENDA = 'Ativo'
          GROUP BY CODIGO_PRODUTO",
        [':id' => (string) $id]
    );
    $mapa = [];
    foreach ($linhas as $l) {
        $mapa[(int) $l['CODIGO_PRODUTO']] = (float) $l['QTD'];
    }
    return $mapa;
}

/**
 * Fecha uma condição em aberto (novo status: cancelada ou finalizada) e devolve as peças ao estoque.
 * Deve rodar dentro de uma transação. Se a condição já não estiver em aberto, lança RuntimeException.
 */
function encerrarCondicao(int $id, string $novoStatus): void
{
    $mudou = executar(
        'UPDATE VENDAS SET STATUS_VENDA = :novo WHERE CODIGO_VENDA = :id AND STATUS_VENDA = :atual',
        [':novo' => $novoStatus, ':id' => $id, ':atual' => STATUS_CONDICAO]
    );
    if ($mudou !== 1) {
        throw new RuntimeException('A condição #' . $id . ' não está mais em aberto (já foi finalizada ou cancelada).');
    }

    foreach (itensReservados($id) as $produto => $qtd) {
        executar(
            'UPDATE PRODUTOS SET QUANT = QUANT + :qtd WHERE CODIGO = :id',
            [':qtd' => paraBanco($qtd, 3), ':id' => $produto]
        );
    }
}

/** A cliente desistiu: a condição vira 'Condicao Cancelada' e as peças voltam ao estoque. */
function cancelarCondicao(int $id): void
{
    emTransacao(function () use ($id) {
        encerrarCondicao($id, STATUS_CONDICAO_CANCELADA);
    });
}

/**
 * Dados para abrir a tela de nova venda já preenchida com uma condição (para receber o pagamento).
 * Os preços são os atuais do cadastro (iguais aos que o servidor vai usar ao gravar).
 */
function estadoDaCondicao(int $id): ?array
{
    $cond = buscarCondicao($id);
    if ($cond === null) {
        return null;
    }

    $itens    = [];
    $vendedor = CODIGO_USUARIO_PADRAO;
    $linhas   = consultar(
        "SELECT CODIGO_PRODUTO, SUM(QUANT) AS QTD, MAX(CODIGO_VENDEDOR) AS VENDEDOR
           FROM ITENS_VENDA
          WHERE CODIGO_VENDA = :id AND STATUS_ITENS_VENDA = 'Ativo'
          GROUP BY CODIGO_PRODUTO",
        [':id' => (string) $id]
    );
    foreach ($linhas as $l) {
        $p = buscarProduto((int) $l['CODIGO_PRODUTO']);
        if ($p === null) {
            continue;
        }
        $qtd = (float) $l['QTD'];
        $itens[] = [
            'id' => (int) $p['CODIGO'], 'nome' => trim((string) $p['DESCRICAO']), 'tamanho' => trim((string) $p['TAMANHO']),
            'preco' => round((float) $p['PRE_VEN'], 2), 'estoque' => (float) $p['QUANT'] + $qtd, 'qtd' => (int) $qtd,
        ];
        if ((int) $l['VENDEDOR'] > 0) {
            $vendedor = (int) $l['VENDEDOR'];
        }
    }

    return [
        'cliente' => $cond['CODIGO_CLIENTE'] ? ['id' => (int) $cond['CODIGO_CLIENTE'], 'nome' => trim((string) $cond['CLIENTE_NOME'])] : null,
        'vendedor_id' => $vendedor, 'itens' => $itens, 'pagamentos' => [],
        'desconto' => (float) $cond['DESCONTO'], 'frete' => (float) $cond['VALOR_FRETE'],
        'condicao_id' => $id,
    ];
}

/** Todas as condições em aberto (as mais antigas primeiro), com cliente, telefone e as peças de cada uma. */
function listarCondicoes(string $busca = ''): array
{
    $params = [':status' => STATUS_CONDICAO];
    $filtro = '';
    if ($busca !== '') {
        $cond = ['c.NOME LIKE :busca_nome'];
        $params[':busca_nome'] = '%' . $busca . '%';
        if (ctype_digit($busca)) {
            $cond[] = 'v.CODIGO_VENDA = :busca_cod';
            $params[':busca_cod'] = (int) $busca;
        }
        $filtro = ' AND (' . implode(' OR ', $cond) . ')';
    }

    $condicoes = consultar(
        "SELECT v.CODIGO_VENDA, v.DATA, v.HORA, v.DATA_INICIO_CONDICAO, v.CODIGO_CLIENTE, v.NUM_PECAS, v.TOTAL,
                c.NOME AS CLIENTE_NOME
           FROM VENDAS v
           LEFT JOIN CLIENTE c ON c.CODIGO = v.CODIGO_CLIENTE
          WHERE v.STATUS_VENDA = :status $filtro
          ORDER BY v.DATA, v.CODIGO_VENDA",
        $params
    );
    if (empty($condicoes)) {
        return [];
    }

    // Peças de cada condição
    $ids    = array_map(fn($c) => (string) $c['CODIGO_VENDA'], $condicoes);
    $marcas = implode(',', array_fill(0, count($ids), '?'));
    $pecas  = [];
    foreach (consultar(
        "SELECT CODIGO_VENDA, DESCRICAO, TAMANHO, QUANT FROM ITENS_VENDA
          WHERE CODIGO_VENDA IN ($marcas) AND STATUS_ITENS_VENDA = 'Ativo'
          ORDER BY CODIGO_ITEM_VENDA",
        $ids
    ) as $i) {
        $pecas[(int) $i['CODIGO_VENDA']][] = $i;
    }

    // Celular / WhatsApp de cada cliente
    $contatos = contatosPrincipais(array_values(array_unique(array_filter(array_map('intval', array_column($condicoes, 'CODIGO_CLIENTE'))))));

    $hoje = new DateTime('today');
    foreach ($condicoes as &$c) {
        $codigo      = (int) $c['CODIGO_VENDA'];
        $inicio      = $c['DATA_INICIO_CONDICAO'] ?: $c['DATA'];
        $c['INICIO'] = $inicio;
        $c['DIAS']   = $inicio ? (int) (new DateTime(substr((string) $inicio, 0, 10)))->diff($hoje)->format('%a') : 0;
        $c['PECAS']  = $pecas[$codigo] ?? [];
        $tel         = $contatos[(int) $c['CODIGO_CLIENTE']] ?? [];
        $c['TELEFONE'] = $tel[CONTATO_WHATSAPP] ?? $tel[CONTATO_CELULAR] ?? '';
    }
    unset($c);

    return $condicoes;
}
