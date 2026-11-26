<?php
/**
 * devolucoes_funcoes.php
 * Devolução e troca de peças de vendas já realizadas (STATUS_VENDA = 'Finalizado').
 *
 * Como funciona:
 *   - A venda original NÃO é alterada (continua como foi registrada). Cada devolução fica registrada
 *     nas tabelas próprias DEVOLUCAO (cabeçalho) e DEVOLUCAO_ITEM (peças), criadas sozinhas.
 *   - As peças devolvidas voltam ao estoque (PRODUTOS.QUANT) na mesma transação.
 *   - Pode devolver só parte da quantidade; o sistema não deixa devolver mais do que foi comprado
 *     (somando as devoluções anteriores do mesmo item).
 *   - O valor de cada peça é o valor PAGO: preço do item com o desconto da venda repartido proporcionalmente.
 *   - Tipo 'Devolucao': a cliente devolve e recebe o dinheiro de volta (forma informada em REEMBOLSO).
 *   - Tipo 'Troca': o valor vira crédito para a cliente. O crédito é usado na Nova venda
 *     (nova_venda.php?troca=ID), entrando como desconto; a venda nova fica ligada à troca (CODIGO_VENDA_TROCA).
 */

require_once __DIR__ . '/produtos_funcoes.php';
require_once __DIR__ . '/clientes_funcoes.php';

const TIPO_DEVOLUCAO = 'Devolucao';
const TIPO_TROCA     = 'Troca';

const MOTIVOS_DEVOLUCAO = ['Tamanho não serviu', 'Defeito na peça', 'Não gostou / desistiu', 'Cor / modelo diferente', 'Outro'];
const FORMAS_REEMBOLSO  = ['Dinheiro', 'Pix', 'Estorno no cartão', 'Sem reembolso'];

// Quantas vendas da cliente aparecem na tela (as mais recentes primeiro)
const DEVOLUCAO_LIMITE_VENDAS = 60;

// ---------- Tabelas ----------

/** Cria as tabelas de devolução se ainda não existirem (MySQL ou SQLite dos testes). */
function garantirTabelasDevolucao(): void
{
    static $pronto = false;
    if ($pronto) {
        return;
    }

    $sqlite = conectar()->getAttribute(PDO::ATTR_DRIVER_NAME) === 'sqlite';
    $id     = $sqlite ? 'ID INTEGER PRIMARY KEY AUTOINCREMENT' : 'ID INT NOT NULL AUTO_INCREMENT PRIMARY KEY';
    $fim    = $sqlite ? '' : ' ENGINE=InnoDB DEFAULT CHARSET=utf8mb4';

    conectar()->exec(
        "CREATE TABLE IF NOT EXISTS DEVOLUCAO (
            $id,
            DATA               DATETIME      NOT NULL,
            CODIGO_VENDA       INT           NOT NULL,
            CODIGO_CLIENTE     INT           NULL,
            TIPO               VARCHAR(20)   NOT NULL,
            MOTIVO             VARCHAR(60)   NULL,
            REEMBOLSO          VARCHAR(30)   NULL,
            OBSERVACAO         VARCHAR(255)  NULL,
            PECAS              DECIMAL(12,3) NOT NULL,
            VALOR              DECIMAL(12,2) NOT NULL,
            CODIGO_VENDA_TROCA INT           NULL,
            CODIGO_USUARIO     INT           NULL
        )$fim"
    );
    conectar()->exec(
        "CREATE TABLE IF NOT EXISTS DEVOLUCAO_ITEM (
            $id,
            ID_DEVOLUCAO      INT           NOT NULL,
            CODIGO_ITEM_VENDA INT           NOT NULL,
            CODIGO_PRODUTO    INT           NOT NULL,
            DESCRICAO         VARCHAR(255)  NULL,
            TAMANHO           VARCHAR(30)   NULL,
            QUANT             DECIMAL(12,3) NOT NULL,
            VALOR_UNIT        DECIMAL(12,2) NOT NULL,
            VALOR_TOTAL       DECIMAL(12,2) NOT NULL
        )$fim"
    );

    $pronto = true;
}

// ---------- Leitura ----------

/**
 * Pesquisa de cliente para a tela de devolução: nome, CPF, telefone ou código, em QUALQUER status
 * (uma cliente inativa também pode devolver), com quantas compras realizadas ela tem.
 * As clientes com compras aparecem primeiro.
 */
function buscarClientesParaDevolucao(string $busca, int $limite = 20): array
{
    $busca = trim($busca);
    if (mb_strlen($busca) < 2) {
        return [];
    }

    $params = [];
    $where  = whereClientes(['busca' => $busca, 'status' => ''], $params);
    $params[':limite'] = $limite;

    $clientes = consultar(
        "SELECT c.CODIGO, c.NOME, c.CPF, c.STATUS_CLI,
                (SELECT COUNT(*) FROM VENDAS v
                  WHERE v.CODIGO_CLIENTE = c.CODIGO AND v.STATUS_VENDA = 'Finalizado') AS COMPRAS
           FROM CLIENTE c
          WHERE $where
          ORDER BY c.NOME, c.CODIGO
          LIMIT :limite",
        $params
    );

    usort($clientes, fn($a, $b) => ((int) $b['COMPRAS'] > 0) <=> ((int) $a['COMPRAS'] > 0));
    return $clientes;
}

/** Quanto já foi devolvido de cada item de venda: [codigo_item_venda => quantidade]. */
function devolvidoPorItem(array $itensVenda): array
{
    garantirTabelasDevolucao();
    $itensVenda = array_values(array_unique(array_map('intval', $itensVenda)));
    if (empty($itensVenda)) {
        return [];
    }

    $marcas = implode(',', array_fill(0, count($itensVenda), '?'));
    $mapa   = [];
    foreach (consultar(
        "SELECT CODIGO_ITEM_VENDA, SUM(QUANT) AS QTD FROM DEVOLUCAO_ITEM
          WHERE CODIGO_ITEM_VENDA IN ($marcas) GROUP BY CODIGO_ITEM_VENDA",
        $itensVenda
    ) as $l) {
        $mapa[(int) $l['CODIGO_ITEM_VENDA']] = (float) $l['QTD'];
    }
    return $mapa;
}

/**
 * Itens ativos das vendas informadas, já com o valor pago por peça e o saldo que ainda pode ser devolvido.
 * Devolve [codigo_venda => [itens...]].
 */
function itensParaDevolucao(array $vendas): array
{
    if (empty($vendas)) {
        return [];
    }

    $ids    = array_map(fn($v) => (string) $v['CODIGO_VENDA'], $vendas);
    $marcas = implode(',', array_fill(0, count($ids), '?'));

    // Vendas feitas com crédito de troca: o crédito também foi "pago" pela cliente (entrou como desconto)
    garantirTabelasDevolucao();
    $credito = [];
    foreach (consultar(
        "SELECT CODIGO_VENDA_TROCA, SUM(VALOR) AS VALOR FROM DEVOLUCAO
          WHERE CODIGO_VENDA_TROCA IN ($marcas) GROUP BY CODIGO_VENDA_TROCA",
        array_map('intval', $ids)
    ) as $c) {
        $credito[(int) $c['CODIGO_VENDA_TROCA']] = (float) $c['VALOR'];
    }

    // Fator do desconto de cada venda ((TOTAL + crédito de troca) / VALOR): o desconto é repartido entre as peças
    $fator = [];
    foreach ($vendas as $v) {
        $codigo = (int) $v['CODIGO_VENDA'];
        $valor  = (float) $v['VALOR'];
        $pago   = (float) $v['TOTAL'] + ($credito[$codigo] ?? 0);
        $fator[$codigo] = $valor > 0 ? min(1, max(0, $pago / $valor)) : 1;
    }
    $linhas = consultar(
        "SELECT i.CODIGO_ITEM_VENDA, i.CODIGO_VENDA, i.CODIGO_PRODUTO, i.DESCRICAO, i.QUANT, i.PRECO_UNIT, i.PRECO_TOTAL,
                p.TAMANHO, p.QUANT AS ESTOQUE
           FROM ITENS_VENDA i
           LEFT JOIN PRODUTOS p ON p.CODIGO = i.CODIGO_PRODUTO
          WHERE i.CODIGO_VENDA IN ($marcas) AND i.STATUS_ITENS_VENDA = 'Ativo'
          ORDER BY i.CODIGO_ITEM_VENDA",
        $ids
    );

    $devolvido = devolvidoPorItem(array_column($linhas, 'CODIGO_ITEM_VENDA'));

    $porVenda = [];
    foreach ($linhas as $l) {
        $venda = (int) $l['CODIGO_VENDA'];
        $item  = (int) $l['CODIGO_ITEM_VENDA'];
        $qtd   = (float) $l['QUANT'];
        $pago  = $qtd > 0 ? round((float) $l['PRECO_TOTAL'] * ($fator[$venda] ?? 1) / $qtd, 2) : 0;
        $jaDev = $devolvido[$item] ?? 0;

        $l['VALOR_PAGO_UNIT'] = $pago;
        $l['DEVOLVIDO']       = $jaDev;
        $l['DISPONIVEL']      = max(0, (int) floor($qtd - $jaDev + 0.0001));
        $porVenda[$venda][]   = $l;
    }
    return $porVenda;
}

/**
 * Vendas realizadas (Finalizado) de uma cliente, as mais recentes primeiro, com os itens de cada uma.
 * $produto filtra pelas peças (nome ou referência).
 */
function vendasParaDevolucao(int $clienteId, string $produto = ''): array
{
    $params = [':cliente' => $clienteId, ':status' => 'Finalizado', ':limite' => DEVOLUCAO_LIMITE_VENDAS];
    $filtro = '';
    if ($produto !== '') {
        $filtro = " AND EXISTS (SELECT 1 FROM ITENS_VENDA i
                                 WHERE i.CODIGO_VENDA = v.CODIGO_VENDA AND i.STATUS_ITENS_VENDA = 'Ativo'
                                   AND (i.DESCRICAO LIKE :prod_nome OR i.REFERENCIA LIKE :prod_ref))";
        $params[':prod_nome'] = '%' . $produto . '%';
        $params[':prod_ref']  = '%' . $produto . '%';
    }

    $vendas = consultar(
        "SELECT v.CODIGO_VENDA, v.DATA, v.HORA, v.CODIGO_CLIENTE, v.VALOR, v.DESCONTO, v.TOTAL, v.NUM_PECAS
           FROM VENDAS v
          WHERE v.CODIGO_CLIENTE = :cliente AND v.STATUS_VENDA = :status $filtro
          ORDER BY v.DATA DESC, v.CODIGO_VENDA DESC
          LIMIT :limite",
        $params
    );

    $itens = itensParaDevolucao($vendas);
    foreach ($vendas as &$v) {
        $v['ITENS']      = $itens[(int) $v['CODIGO_VENDA']] ?? [];
        $v['DISPONIVEL'] = array_sum(array_column($v['ITENS'], 'DISPONIVEL'));
    }
    unset($v);

    return $vendas;
}

/** Uma venda realizada (Finalizado) com os itens, ou null. */
function vendaParaDevolucao(int $vendaId): ?array
{
    $v = consultarUma(
        "SELECT v.CODIGO_VENDA, v.DATA, v.HORA, v.CODIGO_CLIENTE, v.VALOR, v.DESCONTO, v.TOTAL, v.NUM_PECAS, v.STATUS_VENDA
           FROM VENDAS v WHERE v.CODIGO_VENDA = :id",
        [':id' => $vendaId]
    );
    if ($v === null || trim((string) $v['STATUS_VENDA']) !== 'Finalizado') {
        return null;
    }
    $v['ITENS']      = itensParaDevolucao([$v])[$vendaId] ?? [];
    $v['DISPONIVEL'] = array_sum(array_column($v['ITENS'], 'DISPONIVEL'));
    return $v;
}

/** Peças de cada devolução: [id_devolucao => [itens...]]. */
function itensDasDevolucoes(array $ids): array
{
    $ids = array_values(array_map('intval', $ids));
    if (empty($ids)) {
        return [];
    }
    $marcas = implode(',', array_fill(0, count($ids), '?'));
    $mapa   = [];
    foreach (consultar("SELECT * FROM DEVOLUCAO_ITEM WHERE ID_DEVOLUCAO IN ($marcas) ORDER BY ID", $ids) as $i) {
        $mapa[(int) $i['ID_DEVOLUCAO']][] = $i;
    }
    return $mapa;
}

/**
 * Histórico de devoluções e trocas (as mais recentes primeiro), com cliente e peças.
 * $clienteId = 0 lista de todas as clientes; $vendaId > 0 só as daquela venda.
 */
function listarDevolucoes(int $clienteId = 0, int $limite = 30, int $vendaId = 0): array
{
    garantirTabelasDevolucao();

    $where  = ['1 = 1'];
    $params = [':limite' => $limite];
    if ($clienteId > 0) {
        $where[] = 'd.CODIGO_CLIENTE = :cliente';
        $params[':cliente'] = $clienteId;
    }
    if ($vendaId > 0) {
        $where[] = 'd.CODIGO_VENDA = :venda';
        $params[':venda'] = $vendaId;
    }

    $lista = consultar(
        'SELECT d.*, c.NOME AS CLIENTE_NOME
           FROM DEVOLUCAO d
           LEFT JOIN CLIENTE c ON c.CODIGO = d.CODIGO_CLIENTE
          WHERE ' . implode(' AND ', $where) . '
          ORDER BY d.DATA DESC, d.ID DESC
          LIMIT :limite',
        $params
    );

    $itens = itensDasDevolucoes(array_column($lista, 'ID'));
    foreach ($lista as &$d) {
        $d['ITENS'] = $itens[(int) $d['ID']] ?? [];
    }
    unset($d);
    return $lista;
}

/** Devoluções/trocas registradas para uma venda (usado no detalhe da venda). */
function devolucoesDaVenda(int $vendaId): array
{
    return listarDevolucoes(0, 100, $vendaId);
}

function buscarDevolucao(int $id): ?array
{
    garantirTabelasDevolucao();
    $d = consultarUma(
        'SELECT d.*, c.NOME AS CLIENTE_NOME FROM DEVOLUCAO d LEFT JOIN CLIENTE c ON c.CODIGO = d.CODIGO_CLIENTE WHERE d.ID = :id',
        [':id' => $id]
    );
    if ($d !== null) {
        $d['ITENS'] = itensDasDevolucoes([$id])[$id] ?? [];
    }
    return $d;
}

/** A troca com crédito ainda não usado (ou null se não existir, não for troca ou já virou venda). */
function trocaEmAberto(int $id): ?array
{
    $d = buscarDevolucao($id);
    if ($d === null || $d['TIPO'] !== TIPO_TROCA || !empty($d['CODIGO_VENDA_TROCA'])) {
        return null;
    }
    return $d;
}

/** Texto para mostrar o tipo. */
function rotuloTipoDevolucao(string $tipo): string
{
    return $tipo === TIPO_TROCA ? 'Troca' : 'Devolução';
}

// ---------- Gravação ----------

/**
 * Valida o formulário e grava a devolução (cabeçalho + peças + volta ao estoque) numa única transação.
 * Lança RuntimeException com mensagem amigável quando algo não confere. Devolve o ID da devolução.
 */
function registrarDevolucao(array $post): int
{
    garantirTabelasDevolucao();

    $vendaId = (int) ($post['venda_id'] ?? 0);
    $tipo    = ($post['tipo'] ?? '') === TIPO_TROCA ? TIPO_TROCA : TIPO_DEVOLUCAO;
    $motivo  = in_array($post['motivo'] ?? '', MOTIVOS_DEVOLUCAO, true) ? $post['motivo'] : null;
    $obs     = mb_substr(trim((string) ($post['observacao'] ?? '')), 0, 255);
    $reemb   = $tipo === TIPO_DEVOLUCAO
        ? (in_array($post['reembolso'] ?? '', FORMAS_REEMBOLSO, true) ? $post['reembolso'] : FORMAS_REEMBOLSO[0])
        : null;

    // Quantidades pedidas por item: qtd[CODIGO_ITEM_VENDA] = n
    $pedidas = [];
    foreach ((is_array($post['qtd'] ?? null) ? $post['qtd'] : []) as $item => $qtd) {
        $qtd = (int) $qtd;
        if ((int) $item > 0 && $qtd > 0) {
            $pedidas[(int) $item] = $qtd;
        }
    }
    if (empty($pedidas)) {
        throw new RuntimeException('Informe a quantidade de ao menos uma peça para devolver.');
    }

    return emTransacao(function () use ($vendaId, $tipo, $motivo, $obs, $reemb, $pedidas) {
        // Trava a venda enquanto grava (evita duas devoluções simultâneas da mesma peça no MySQL)
        $sqlite = conectar()->getAttribute(PDO::ATTR_DRIVER_NAME) === 'sqlite';
        consultarUma('SELECT CODIGO_VENDA FROM VENDAS WHERE CODIGO_VENDA = :id' . ($sqlite ? '' : ' FOR UPDATE'), [':id' => $vendaId]);

        $venda = vendaParaDevolucao($vendaId);
        if ($venda === null) {
            throw new RuntimeException('Só é possível devolver peças de vendas realizadas (finalizadas).');
        }

        $itens = array_column($venda['ITENS'], null, 'CODIGO_ITEM_VENDA');
        $grava = [];
        $pecas = 0;
        $valorCent = 0;
        foreach ($pedidas as $itemId => $qtd) {
            $item = $itens[$itemId] ?? null;
            if ($item === null) {
                throw new RuntimeException('Uma das peças não pertence à venda #' . $vendaId . '.');
            }
            $nome = trim((string) $item['DESCRICAO']);
            if ($qtd > $item['DISPONIVEL']) {
                throw new RuntimeException('"' . $nome . '": só ' . $item['DISPONIVEL'] . ' peça(s) ainda podem ser devolvidas.');
            }
            $unitCent = (int) round((float) $item['VALOR_PAGO_UNIT'] * 100);
            $grava[]  = ['item' => $item, 'qtd' => $qtd, 'unit_cent' => $unitCent];
            $pecas   += $qtd;
            $valorCent += $unitCent * $qtd;
        }

        executar(
            'INSERT INTO DEVOLUCAO (DATA, CODIGO_VENDA, CODIGO_CLIENTE, TIPO, MOTIVO, REEMBOLSO, OBSERVACAO, PECAS, VALOR, CODIGO_USUARIO)
             VALUES (:data, :venda, :cliente, :tipo, :motivo, :reemb, :obs, :pecas, :valor, :usuario)',
            [
                ':data' => date('Y-m-d H:i:s'), ':venda' => $vendaId,
                ':cliente' => $venda['CODIGO_CLIENTE'] ? (int) $venda['CODIGO_CLIENTE'] : null,
                ':tipo' => $tipo, ':motivo' => $motivo, ':reemb' => $reemb, ':obs' => $obs !== '' ? $obs : null,
                ':pecas' => paraBanco((float) $pecas, 3), ':valor' => paraBanco($valorCent / 100),
                ':usuario' => CODIGO_USUARIO_PADRAO,
            ]
        );
        $devId = ultimoId();

        foreach ($grava as $g) {
            $item = $g['item'];
            executar(
                'INSERT INTO DEVOLUCAO_ITEM (ID_DEVOLUCAO, CODIGO_ITEM_VENDA, CODIGO_PRODUTO, DESCRICAO, TAMANHO, QUANT, VALOR_UNIT, VALOR_TOTAL)
                 VALUES (:dev, :item, :produto, :desc, :tam, :qtd, :unit, :total)',
                [
                    ':dev' => $devId, ':item' => (int) $item['CODIGO_ITEM_VENDA'], ':produto' => (int) $item['CODIGO_PRODUTO'],
                    ':desc' => trim((string) $item['DESCRICAO']), ':tam' => trim((string) $item['TAMANHO']) ?: null,
                    ':qtd' => paraBanco((float) $g['qtd'], 3), ':unit' => paraBanco($g['unit_cent'] / 100),
                    ':total' => paraBanco($g['unit_cent'] * $g['qtd'] / 100),
                ]
            );

            // A peça volta ao estoque
            executar(
                'UPDATE PRODUTOS SET QUANT = QUANT + :qtd WHERE CODIGO = :id',
                [':qtd' => paraBanco((float) $g['qtd'], 3), ':id' => (int) $item['CODIGO_PRODUTO']]
            );
        }

        return $devId;
    });
}

/**
 * Liga a venda nova à troca cujo crédito foi usado. Deve rodar dentro da transação da venda.
 * Se a troca já tiver sido usada, lança RuntimeException (nada é gravado).
 */
function vincularTroca(int $devolucaoId, int $vendaId): void
{
    // (sem garantirTabelasDevolucao aqui: CREATE TABLE no MySQL encerra a transação; trocaEmAberto() já criou as tabelas)
    $mudou = executar(
        'UPDATE DEVOLUCAO SET CODIGO_VENDA_TROCA = :venda WHERE ID = :id AND TIPO = :tipo AND CODIGO_VENDA_TROCA IS NULL',
        [':venda' => $vendaId, ':id' => $devolucaoId, ':tipo' => TIPO_TROCA]
    );
    if ($mudou !== 1) {
        throw new RuntimeException('O crédito da troca #' . $devolucaoId . ' já foi usado em outra venda.');
    }
}
