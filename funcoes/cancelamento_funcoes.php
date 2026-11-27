<?php
/**
 * cancelamento_funcoes.php
 * Cancelamento de venda feito no Histórico de vendas (admin/venda_cancelar.php).
 *
 * Como funciona:
 *   - Só vendas 'Finalizado' podem ser canceladas. A venda NÃO é apagada: o STATUS_VENDA vira 'Cancelado'
 *     (o mesmo status que o sistema atual usa) e o cancelamento fica registrado na tabela própria
 *     VENDA_CANCELAMENTO (data, motivo, observação, valores), criada sozinha.
 *   - As peças da venda voltam ao estoque (PRODUTOS.QUANT), na mesma transação.
 *   - Como os relatórios, o painel, a conciliação e a devolução só olham vendas 'Finalizado', a venda
 *     cancelada sai de todos eles automaticamente.
 *   - Crediário: as parcelas em aberto viram 'Cancelada' (somem da cobrança). As já pagas ficam como estão
 *     e entram no "valor que a cliente já pagou".
 *   - Crédito na loja usado na venda volta para o saldo da cliente (entrada em CREDITO_CLIENTE).
 *   - O que a cliente já pagou (à vista + parcelas pagas) é informado na tela: escolhe-se como foi devolvido
 *     (dinheiro, Pix, estorno) ou se vira crédito na loja (aí entra no saldo da cliente).
 *   - Venda que já tem devolução ou troca não pode ser cancelada (as peças e o dinheiro já foram mexidos):
 *     nesse caso use a tela de devolução para as peças que faltam.
 *
 * Desempenho: as consultas usam só o número de UMA venda (ou uma lista curta, na página do histórico),
 * nunca subconsultas por peça em ITENS_VENDA / FORMA_PAGAMENTO_VENDA.
 */

require_once __DIR__ . '/vendas_funcoes.php';
require_once __DIR__ . '/devolucoes_funcoes.php';
require_once __DIR__ . '/crediario_funcoes.php';

const STATUS_VENDA_CANCELADA = 'Cancelado';
const PARCELA_CANCELADA      = 'Cancelada';

const MOTIVOS_CANCELAMENTO = [
    'Cliente desistiu da compra',
    'Venda lançada errada',
    'Pagamento não aprovado',
    'Venda em duplicidade',
    'Outro',
];

// Como o valor que a cliente já tinha pago foi devolvido
const REEMBOLSO_CANCELAMENTO_CREDITO = 'Crédito na loja';
const FORMAS_REEMBOLSO_CANCELAMENTO  = ['Dinheiro', 'Pix', 'Estorno no cartão', REEMBOLSO_CANCELAMENTO_CREDITO, 'Sem reembolso'];

// ---------- Tabela ----------

/**
 * Cria a tabela de cancelamentos se ainda não existir (MySQL ou SQLite dos testes).
 * Atenção: no MySQL o CREATE TABLE encerra a transação aberta, então chame ANTES de emTransacao().
 */
function garantirTabelaCancelamento(): void
{
    static $pronto = false;
    if ($pronto) {
        return;
    }

    $sqlite = conectar()->getAttribute(PDO::ATTR_DRIVER_NAME) === 'sqlite';
    $id     = $sqlite ? 'ID INTEGER PRIMARY KEY AUTOINCREMENT' : 'ID INT NOT NULL AUTO_INCREMENT PRIMARY KEY';
    $fim    = $sqlite ? '' : ' ENGINE=InnoDB DEFAULT CHARSET=utf8mb4';

    conectar()->exec(
        "CREATE TABLE IF NOT EXISTS VENDA_CANCELAMENTO (
            $id,
            CODIGO_VENDA        INT           NOT NULL,
            CODIGO_CLIENTE      INT           NULL,
            DATA                DATETIME      NOT NULL,
            STATUS_ANTERIOR     VARCHAR(30)   NULL,
            MOTIVO              VARCHAR(60)   NOT NULL,
            OBSERVACAO          VARCHAR(255)  NULL,
            VALOR_VENDA         DECIMAL(12,2) NOT NULL,
            PECAS               DECIMAL(12,3) NOT NULL,
            VALOR_PAGO          DECIMAL(12,2) NOT NULL DEFAULT 0,
            REEMBOLSO           VARCHAR(30)   NULL,
            CREDITO_DEVOLVIDO   DECIMAL(12,2) NOT NULL DEFAULT 0,
            PARCELAS_CANCELADAS INT           NOT NULL DEFAULT 0,
            VALOR_PARCELAS      DECIMAL(12,2) NOT NULL DEFAULT 0,
            CODIGO_USUARIO      INT           NULL
        )$fim"
    );

    // Uma venda só pode ter um cancelamento (o índice único também impede cancelar duas vezes)
    try {
        conectar()->exec('CREATE UNIQUE INDEX idx_cancel_venda ON VENDA_CANCELAMENTO (CODIGO_VENDA)');
    } catch (PDOException $e) {
        // índice já existe
    }

    $pronto = true;
}

// ---------- Leitura ----------

/** O cancelamento registrado de uma venda (ou null). */
function cancelamentoDaVenda(int $vendaId): ?array
{
    garantirTabelaCancelamento();
    return consultarUma('SELECT * FROM VENDA_CANCELAMENTO WHERE CODIGO_VENDA = :v', [':v' => $vendaId]);
}

/** Cancelamentos de várias vendas de uma vez (para a página do histórico): [codigo_venda => linha]. */
function cancelamentosPorVendas(array $ids): array
{
    $ids = array_values(array_unique(array_filter(array_map('intval', $ids))));
    if (empty($ids)) {
        return [];
    }
    garantirTabelaCancelamento();
    $marcas = implode(',', array_fill(0, count($ids), '?'));
    $mapa   = [];
    foreach (consultar("SELECT * FROM VENDA_CANCELAMENTO WHERE CODIGO_VENDA IN ($marcas)", $ids) as $c) {
        $mapa[(int) $c['CODIGO_VENDA']] = $c;
    }
    return $mapa;
}

/** Quantas vendas estão canceladas (para o atalho no histórico). */
function contarVendasCanceladas(): int
{
    return (int) consultarValor('SELECT COUNT(*) FROM VENDAS WHERE STATUS_VENDA = :s', [':s' => STATUS_VENDA_CANCELADA]);
}

/**
 * Tudo o que a tela de confirmação mostra: a venda, as peças, o que vai acontecer e se pode cancelar.
 * Devolve null se a venda não existir.
 * Chaves: venda, itens, pode, bloqueio (texto), credito_usado, parcelas_abertas, valor_parcelas_abertas,
 *         parcelas_pagas, valor_parcelas_pagas, valor_crediario, valor_pago (o que a cliente já pagou).
 */
function previaCancelamento(int $id): ?array
{
    $venda = buscarVenda($id);
    if ($venda === null) {
        return null;
    }
    garantirTabelasCrediario();
    garantirTabelasDevolucao();

    $status   = trim((string) $venda['STATUS_VENDA']);
    $bloqueio = '';
    if ($status === STATUS_VENDA_CANCELADA) {
        $bloqueio = 'Esta venda já está cancelada.';
    } elseif ($status !== STATUS_VENDA_FINALIZADA) {
        $bloqueio = 'Só vendas finalizadas podem ser canceladas por aqui (esta está como "' . $status . '").'
                  . ($status === STATUS_CONDICAO ? ' Para condição, use "Cancelar condição".' : '');
    } elseif ((int) consultarValor('SELECT COUNT(*) FROM DEVOLUCAO WHERE CODIGO_VENDA = :v', [':v' => $id]) > 0) {
        $bloqueio = 'Esta venda já tem devolução ou troca registrada, então não pode ser cancelada inteira. '
                  . 'Para as peças que ainda estão com a cliente, use "Devolver / trocar".';
    }

    $parc = consultarUma(
        'SELECT COALESCE(SUM(VALOR), 0) AS TOTAL,
                COALESCE(SUM(CASE WHEN STATUS = :a THEN 1 ELSE 0 END), 0) AS QTD_ABERTAS,
                COALESCE(SUM(CASE WHEN STATUS = :a2 THEN VALOR - VALOR_ABATIDO ELSE 0 END), 0) AS VALOR_ABERTAS,
                COALESCE(SUM(CASE WHEN STATUS = :p THEN 1 ELSE 0 END), 0) AS QTD_PAGAS,
                COALESCE(SUM(CASE WHEN STATUS = :p2 THEN COALESCE(VALOR_PAGO, 0) ELSE 0 END), 0) AS VALOR_PAGAS
           FROM CREDIARIO_PARCELA WHERE CODIGO_VENDA = :v',
        [':a' => PARCELA_ABERTA, ':a2' => PARCELA_ABERTA, ':p' => PARCELA_PAGA, ':p2' => PARCELA_PAGA, ':v' => $id]
    ) ?? [];

    $totalCent      = (int) round((float) $venda['TOTAL'] * 100);
    $crediarioCent  = (int) round((float) ($parc['TOTAL'] ?? 0) * 100);
    $pagasCent      = (int) round((float) ($parc['VALOR_PAGAS'] ?? 0) * 100);
    // O que a cliente já pagou = parte à vista (total - crediário) + parcelas já pagas
    $pagoCent       = max(0, $totalCent - $crediarioCent) + $pagasCent;

    return [
        'venda'                  => $venda,
        'itens'                  => itensDaVenda($id),
        'pode'                   => $bloqueio === '',
        'bloqueio'               => $bloqueio,
        'credito_usado'          => $status === STATUS_VENDA_FINALIZADA ? creditoUsadoNaVenda($id) : 0.0,
        'valor_crediario'        => $crediarioCent / 100,
        'parcelas_abertas'       => (int) ($parc['QTD_ABERTAS'] ?? 0),
        'valor_parcelas_abertas' => round((float) ($parc['VALOR_ABERTAS'] ?? 0), 2),
        'parcelas_pagas'         => (int) ($parc['QTD_PAGAS'] ?? 0),
        'valor_parcelas_pagas'   => $pagasCent / 100,
        'valor_pago'             => $pagoCent / 100,
    ];
}

// ---------- Cancelar ----------

/**
 * Cancela a venda. $dados = ['motivo', 'observacao', 'reembolso'] (vindos do formulário).
 * Lança RuntimeException com uma mensagem para a tela se algo não confere; nesse caso nada é gravado.
 * Devolve o texto do aviso de sucesso.
 */
function cancelarVenda(int $id, array $dados): string
{
    $motivo = trim((string) ($dados['motivo'] ?? ''));
    $obs    = mb_substr(trim((string) ($dados['observacao'] ?? '')), 0, 255);
    $reemb  = trim((string) ($dados['reembolso'] ?? ''));

    if (!in_array($motivo, MOTIVOS_CANCELAMENTO, true)) {
        throw new RuntimeException('Escolha o motivo do cancelamento.');
    }
    if ($motivo === 'Outro' && $obs === '') {
        throw new RuntimeException('Escreva na observação qual foi o motivo.');
    }

    $previa = previaCancelamento($id);   // também cria as tabelas de crediário e crédito (fora da transação)
    if ($previa === null) {
        throw new RuntimeException('Venda não encontrada.');
    }
    if (!$previa['pode']) {
        throw new RuntimeException($previa['bloqueio']);
    }

    $clienteId = (int) $previa['venda']['CODIGO_CLIENTE'];
    if ($previa['valor_pago'] > 0) {
        if (!in_array($reemb, FORMAS_REEMBOLSO_CANCELAMENTO, true)) {
            throw new RuntimeException('Informe como o valor que a cliente já pagou foi devolvido.');
        }
        if ($reemb === REEMBOLSO_CANCELAMENTO_CREDITO && $clienteId <= 0) {
            throw new RuntimeException('Venda sem cliente cadastrada não pode virar crédito na loja. Escolha outra forma.');
        }
    } else {
        $reemb = '';
    }

    garantirTabelaCancelamento();   // antes da transação (CREATE TABLE encerra a transação no MySQL)

    return emTransacao(function () use ($id, $motivo, $obs, $reemb, $clienteId) {
        // 1) Muda o status só se ainda estiver finalizada (se dois cliques chegarem juntos, só um passa)
        $mudou = executar(
            'UPDATE VENDAS SET STATUS_VENDA = :novo WHERE CODIGO_VENDA = :id AND STATUS_VENDA = :atual',
            [':novo' => STATUS_VENDA_CANCELADA, ':id' => $id, ':atual' => STATUS_VENDA_FINALIZADA]
        );
        if ($mudou !== 1) {
            throw new RuntimeException('A venda #' . $id . ' não está mais finalizada (talvez já tenha sido cancelada).');
        }

        // Confere de novo, já dentro da transação, se não entrou uma devolução nesse meio tempo
        if ((int) consultarValor('SELECT COUNT(*) FROM DEVOLUCAO WHERE CODIGO_VENDA = :v', [':v' => $id]) > 0) {
            throw new RuntimeException('Esta venda tem devolução ou troca registrada e não pode ser cancelada.');
        }

        $venda = consultarUma('SELECT TOTAL, NUM_PECAS FROM VENDAS WHERE CODIGO_VENDA = :id', [':id' => $id]);

        // 2) Peças voltam ao estoque
        foreach (itensReservados($id) as $produto => $qtd) {
            executar(
                'UPDATE PRODUTOS SET QUANT = QUANT + :qtd WHERE CODIGO = :id',
                [':qtd' => paraBanco($qtd, 3), ':id' => $produto]
            );
        }

        // 3) Crediário: parcelas em aberto são canceladas; as pagas ficam como estão
        $parc = consultarUma(
            'SELECT COUNT(*) AS QTD, COALESCE(SUM(VALOR - VALOR_ABATIDO), 0) AS VALOR
               FROM CREDIARIO_PARCELA WHERE CODIGO_VENDA = :v AND STATUS = :a',
            [':v' => $id, ':a' => PARCELA_ABERTA]
        ) ?? ['QTD' => 0, 'VALOR' => 0];
        executar(
            'UPDATE CREDIARIO_PARCELA SET STATUS = :c WHERE CODIGO_VENDA = :v AND STATUS = :a',
            [':c' => PARCELA_CANCELADA, ':v' => $id, ':a' => PARCELA_ABERTA]
        );
        $pagasCent = (int) round((float) consultarValor(
            'SELECT COALESCE(SUM(COALESCE(VALOR_PAGO, 0)), 0) FROM CREDIARIO_PARCELA WHERE CODIGO_VENDA = :v AND STATUS = :p',
            [':v' => $id, ':p' => PARCELA_PAGA]
        ) * 100);
        $crediarioCent = (int) round((float) consultarValor(
            'SELECT COALESCE(SUM(VALOR), 0) FROM CREDIARIO_PARCELA WHERE CODIGO_VENDA = :v', [':v' => $id]
        ) * 100);
        $pagoCent = max(0, (int) round((float) $venda['TOTAL'] * 100) - $crediarioCent) + $pagasCent;

        // 4) Crédito na loja usado na venda volta para o saldo da cliente
        $usos = consultarUma(
            "SELECT MAX(CODIGO_CLIENTE) AS CLIENTE, COALESCE(SUM(VALOR), 0) AS VALOR
               FROM CREDITO_CLIENTE WHERE CODIGO_VENDA = :v AND TIPO = 'Uso'",
            [':v' => $id]
        ) ?? ['CLIENTE' => null, 'VALOR' => 0];
        $creditoCent = (int) round(-(float) $usos['VALOR'] * 100);
        if ($creditoCent > 0 && (int) $usos['CLIENTE'] > 0) {
            lancarCreditoCancelamento((int) $usos['CLIENTE'], $id, $creditoCent, 'Crédito devolvido: venda #' . $id . ' cancelada');
        } else {
            $creditoCent = 0;
        }

        // 5) O que a cliente já pagou pode virar crédito na loja
        if ($pagoCent > 0 && $reemb === REEMBOLSO_CANCELAMENTO_CREDITO) {
            lancarCreditoCancelamento($clienteId, $id, $pagoCent, 'Valor pago da venda #' . $id . ' cancelada');
        }

        // 6) Registro do cancelamento
        executar(
            'INSERT INTO VENDA_CANCELAMENTO
                (CODIGO_VENDA, CODIGO_CLIENTE, DATA, STATUS_ANTERIOR, MOTIVO, OBSERVACAO, VALOR_VENDA, PECAS,
                 VALOR_PAGO, REEMBOLSO, CREDITO_DEVOLVIDO, PARCELAS_CANCELADAS, VALOR_PARCELAS, CODIGO_USUARIO)
             VALUES (:venda, :cliente, :data, :anterior, :motivo, :obs, :valor, :pecas,
                     :pago, :reemb, :credito, :qparc, :vparc, :usuario)',
            [
                ':venda' => $id, ':cliente' => $clienteId > 0 ? $clienteId : null, ':data' => date('Y-m-d H:i:s'),
                ':anterior' => STATUS_VENDA_FINALIZADA, ':motivo' => $motivo, ':obs' => $obs !== '' ? $obs : null,
                ':valor' => paraBanco((float) $venda['TOTAL']), ':pecas' => paraBanco((float) $venda['NUM_PECAS'], 3),
                ':pago' => paraBanco($pagoCent / 100), ':reemb' => $reemb !== '' ? $reemb : null,
                ':credito' => paraBanco($creditoCent / 100), ':qparc' => (int) $parc['QTD'],
                ':vparc' => paraBanco((float) $parc['VALOR']), ':usuario' => CODIGO_USUARIO_PADRAO,
            ]
        );

        // Aviso para a tela
        $partes = ['Venda #' . $id . ' cancelada. As peças voltaram ao estoque'];
        if ((int) $parc['QTD'] > 0) {
            $partes[] = (int) $parc['QTD'] . ' parcela(s) do crediário em aberto foram canceladas';
        }
        if ($creditoCent > 0) {
            $partes[] = formatarMoeda($creditoCent / 100) . ' de crédito na loja voltaram para o saldo da cliente';
        }
        if ($pagoCent > 0) {
            $partes[] = $reemb === REEMBOLSO_CANCELAMENTO_CREDITO
                ? formatarMoeda($pagoCent / 100) . ' que a cliente pagou viraram crédito na loja'
                : 'valor pago (' . formatarMoeda($pagoCent / 100) . ') devolvido em: ' . $reemb;
        }
        return implode('; ', $partes) . '.';
    });
}

/** Entrada no crédito da loja vinda de um cancelamento (dentro da transação). */
function lancarCreditoCancelamento(int $clienteId, int $vendaId, int $valorCent, string $descricao): void
{
    executar(
        "INSERT INTO CREDITO_CLIENTE (CODIGO_CLIENTE, DATA, TIPO, VALOR, CODIGO_VENDA, DESCRICAO)
         VALUES (:c, :data, 'Entrada', :valor, :venda, :desc)",
        [':c' => $clienteId, ':data' => date('Y-m-d H:i:s'), ':valor' => paraBanco($valorCent / 100),
         ':venda' => $vendaId, ':desc' => mb_substr($descricao, 0, 255)]
    );
}
