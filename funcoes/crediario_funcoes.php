<?php
/**
 * crediario_funcoes.php
 * Crediário da loja: a cliente leva as peças e paga em parcelas mensais (carnê).
 *
 * Como funciona:
 *   - Na Nova venda, "Crediário" é uma forma de pagamento: escolhe o número de parcelas (1 a 12) e o 1º vencimento.
 *     Pode ser combinado com outra forma (ex.: entrada em dinheiro + resto no crediário).
 *   - A venda é gravada como 'Finalizado' (as peças saem do estoque e a venda conta no dia em que foi feita,
 *     igual ao cartão de crédito). Em FORMA_PAGAMENTO_VENDA fica uma linha por parcela (forma CREDIÁRIO,
 *     PARCELA = nº da parcela, DAT_VENC = vencimento), como o sistema atual faz com parcelas.
 *   - O controle do que já foi pago fica na tabela própria CREDIARIO_PARCELA (criada sozinha).
 *     Situações: 'Aberta' (a receber), 'Paga' (baixa feita) e 'Abatida' (cancelada por devolução das peças).
 *   - Baixa: marca a parcela como paga, com a data e a forma em que a cliente pagou. Dá para desfazer.
 *   - Atrasada = parcela em aberto com vencimento antes de hoje. Não há juros nem multa automáticos.
 *   - Devolução (tipo 'Devolucao') de uma venda no crediário: o valor devolvido é abatido das parcelas
 *     em aberto, da última para a primeira; só o que passar disso é devolvido em dinheiro/Pix.
 *     Troca não mexe nas parcelas (o crédito vai para a venda nova e a cliente continua devendo o carnê).
 *   - Vendas no crediário feitas no sistema antigo não têm parcelas nesta tabela e não aparecem na tela.
 */

require_once __DIR__ . '/pagamentos_funcoes.php';
require_once __DIR__ . '/clientes_funcoes.php';

const TIPO_CREDIARIO           = 'CREDIÁRIO';   // nome da forma em FORMA_PAGAMENTO (igual ao sistema atual)
const CREDIARIO_FORMA_VIRTUAL  = -1;            // id usado se a loja não tiver a forma CREDIÁRIO cadastrada
const CREDIARIO_MAX_PARCELAS   = 12;
const CREDIARIO_MAX_DIAS_1VENC = 90;            // o 1º vencimento pode ser no máximo 90 dias depois da venda

const PARCELA_ABERTA  = 'Aberta';
const PARCELA_PAGA    = 'Paga';
const PARCELA_ABATIDA = 'Abatida';

const FORMAS_RECEBIMENTO_CREDIARIO = ['Dinheiro', 'Pix', 'Cartão de débito', 'Cartão de crédito', 'Depósito'];

const SITUACOES_CREDIARIO = [
    'aberto'   => 'Em aberto',
    'atrasado' => 'Com parcela atrasada',
    'quitado'  => 'Quitados',
    'todos'    => 'Todos',
];

// Reembolso gravado na devolução quando todo o valor foi abatido das parcelas
const REEMBOLSO_ABATIDO_CREDIARIO = 'Abatido no crediário';

// ---------- Tabela ----------

/**
 * Cria a tabela de parcelas se ainda não existir (MySQL ou SQLite dos testes).
 * Atenção: no MySQL o CREATE TABLE encerra a transação aberta, então chame ANTES de emTransacao().
 */
function garantirTabelasCrediario(): void
{
    static $pronto = false;
    if ($pronto) {
        return;
    }

    $sqlite = conectar()->getAttribute(PDO::ATTR_DRIVER_NAME) === 'sqlite';
    $id     = $sqlite ? 'ID INTEGER PRIMARY KEY AUTOINCREMENT' : 'ID INT NOT NULL AUTO_INCREMENT PRIMARY KEY';
    $fim    = $sqlite ? '' : ' ENGINE=InnoDB DEFAULT CHARSET=utf8mb4';

    conectar()->exec(
        "CREATE TABLE IF NOT EXISTS CREDIARIO_PARCELA (
            $id,
            CODIGO_VENDA           INT           NOT NULL,
            CODIGO_CLIENTE         INT           NULL,
            NUMERO                 INT           NOT NULL,
            TOTAL_PARCELAS         INT           NOT NULL,
            VENCIMENTO             DATE          NOT NULL,
            VALOR                  DECIMAL(12,2) NOT NULL,
            VALOR_ABATIDO          DECIMAL(12,2) NOT NULL DEFAULT 0,
            STATUS                 VARCHAR(20)   NOT NULL,
            DATA_PAGAMENTO         DATE          NULL,
            VALOR_PAGO             DECIMAL(12,2) NULL,
            FORMA_RECEBIMENTO      VARCHAR(30)   NULL,
            OBSERVACAO             VARCHAR(255)  NULL,
            BAIXADO_EM             DATETIME      NULL,
            ID_DEVOLUCAO           INT           NULL,
            CODIGO_PAGAMENTO_VENDA INT           NULL,
            CRIADO_EM              DATETIME      NOT NULL
        )$fim"
    );

    if (!$sqlite) {
        // Índices (ignora o erro se já existirem)
        foreach ([
            'CREATE INDEX idx_cred_venda ON CREDIARIO_PARCELA (CODIGO_VENDA)',
            'CREATE INDEX idx_cred_cliente ON CREDIARIO_PARCELA (CODIGO_CLIENTE)',
            'CREATE INDEX idx_cred_status_venc ON CREDIARIO_PARCELA (STATUS, VENCIMENTO)',
        ] as $sql) {
            try {
                conectar()->exec($sql);
            } catch (PDOException $e) {
                // índice já existe
            }
        }
    }

    $pronto = true;
}

// ---------- Regras de cálculo ----------

/** É a forma crediário? (aceita com e sem acento) */
function ehFormaCrediario(?string $tipo): bool
{
    $t = mb_strtoupper(trim((string) $tipo));
    return $t === TIPO_CREDIARIO || $t === 'CREDIARIO';
}

/** Soma meses a uma data mantendo o dia (31/01 + 1 mês = 28 ou 29/02). */
function somarMeses(string $data, int $meses): string
{
    $d   = new DateTime($data);
    $dia = (int) $d->format('d');
    $d->modify('first day of this month')->modify("+$meses months");
    $ultimo = (int) $d->format('t');
    $d->setDate((int) $d->format('Y'), (int) $d->format('m'), min($dia, $ultimo));
    return $d->format('Y-m-d');
}

/** 1º vencimento sugerido: um mês depois de hoje. */
function primeiroVencimentoPadrao(): string
{
    return somarMeses(date('Y-m-d'), 1);
}

/**
 * Divide o valor em parcelas mensais. Os centavos que sobram ficam na 1ª parcela.
 * Ex.: R$ 100,00 em 3x → 33,34 + 33,33 + 33,33.
 * Devolve [['numero' => 1, 'valor_cent' => 3334, 'vencimento' => 'AAAA-MM-DD'], ...]
 */
function calcularParcelas(int $totalCent, int $quantidade, string $primeiroVencimento): array
{
    $quantidade = max(1, $quantidade);
    $base  = intdiv($totalCent, $quantidade);
    $resto = $totalCent - $base * $quantidade;

    $parcelas = [];
    for ($n = 1; $n <= $quantidade; $n++) {
        $parcelas[] = [
            'numero'     => $n,
            'valor_cent' => $base + ($n === 1 ? $resto : 0),
            'vencimento' => somarMeses($primeiroVencimento, $n - 1),
        ];
    }
    return $parcelas;
}

/** Saldo de uma parcela (valor menos o que foi abatido por devolução). */
function saldoParcela(array $p): float
{
    return round((float) $p['VALOR'] - (float) $p['VALOR_ABATIDO'], 2);
}

/** Dias de atraso de uma parcela em aberto (0 se ainda não venceu ou se não está em aberto). */
function diasDeAtraso(array $p, ?string $hoje = null): int
{
    if ($p['STATUS'] !== PARCELA_ABERTA) {
        return 0;
    }
    $hoje = $hoje ?? date('Y-m-d');
    $venc = substr((string) $p['VENCIMENTO'], 0, 10);
    if ($venc >= $hoje) {
        return 0;
    }
    return (int) (new DateTime($venc))->diff(new DateTime($hoje))->days;
}

// ---------- Gravação (chamadas dentro da transação da venda) ----------

/**
 * Grava as parcelas de uma venda. Deve rodar dentro da transação de registrarVenda()
 * (a tabela já precisa existir: garantirTabelasCrediario() é chamada antes da transação).
 * $parcelas = saída de calcularParcelas(); $idsPagamento = [numero => FORMA_PAGAMENTO_VENDA.CODIGO].
 */
function gravarParcelasCrediario(int $vendaId, ?int $clienteId, array $parcelas, array $idsPagamento = []): void
{
    $total = count($parcelas);
    foreach ($parcelas as $p) {
        executar(
            'INSERT INTO CREDIARIO_PARCELA
                (CODIGO_VENDA, CODIGO_CLIENTE, NUMERO, TOTAL_PARCELAS, VENCIMENTO, VALOR, VALOR_ABATIDO, STATUS,
                 CODIGO_PAGAMENTO_VENDA, CRIADO_EM)
             VALUES (:venda, :cliente, :num, :total, :venc, :valor, 0, :status, :fpv, :criado)',
            [
                ':venda' => $vendaId, ':cliente' => $clienteId, ':num' => $p['numero'], ':total' => $total,
                ':venc' => $p['vencimento'], ':valor' => paraBanco($p['valor_cent'] / 100),
                ':status' => PARCELA_ABERTA, ':fpv' => $idsPagamento[$p['numero']] ?? null,
                ':criado' => date('Y-m-d H:i:s'),
            ]
        );
    }
}

/**
 * Devolução de peças de uma venda no crediário: abate o valor das parcelas em aberto, da última para a primeira.
 * Deve rodar dentro da transação da devolução. Devolve quantos centavos foram abatidos (0 se a venda
 * não é crediário ou se já está tudo pago).
 */
function abaterCrediarioPorDevolucao(int $vendaId, int $valorCent, int $devolucaoId): int
{
    if ($valorCent <= 0) {
        return 0;
    }

    $abertas = consultar(
        'SELECT * FROM CREDIARIO_PARCELA WHERE CODIGO_VENDA = :v AND STATUS = :s ORDER BY NUMERO DESC',
        [':v' => $vendaId, ':s' => PARCELA_ABERTA]
    );

    $restante = $valorCent;
    foreach ($abertas as $p) {
        if ($restante <= 0) {
            break;
        }
        $saldoCent = (int) round(saldoParcela($p) * 100);
        if ($saldoCent <= 0) {
            continue;
        }
        $abate     = min($saldoCent, $restante);
        $restante -= $abate;
        $novoAbatido = (int) round((float) $p['VALOR_ABATIDO'] * 100) + $abate;

        executar(
            'UPDATE CREDIARIO_PARCELA
                SET VALOR_ABATIDO = :abatido, STATUS = :status, ID_DEVOLUCAO = :dev
              WHERE ID = :id AND STATUS = :aberta',
            [
                ':abatido' => paraBanco($novoAbatido / 100),
                ':status' => $abate === $saldoCent ? PARCELA_ABATIDA : PARCELA_ABERTA,
                ':dev' => $devolucaoId, ':id' => (int) $p['ID'], ':aberta' => PARCELA_ABERTA,
            ]
        );
    }

    return $valorCent - $restante;
}

// ---------- Baixa ----------

/**
 * Dá baixa numa parcela em aberto (a cliente pagou). Lança RuntimeException se algo não confere.
 * $data = 'AAAA-MM-DD' (não pode ser no futuro); $forma = uma de FORMAS_RECEBIMENTO_CREDIARIO.
 */
function baixarParcela(int $id, string $data, string $forma, string $obs = ''): void
{
    garantirTabelasCrediario();

    $data = dataValida($data);
    if ($data === '') {
        throw new RuntimeException('Informe a data em que a cliente pagou.');
    }
    if ($data > date('Y-m-d')) {
        throw new RuntimeException('A data do pagamento não pode ser no futuro.');
    }
    if (!in_array($forma, FORMAS_RECEBIMENTO_CREDIARIO, true)) {
        throw new RuntimeException('Escolha como a cliente pagou a parcela.');
    }

    $p = buscarParcela($id);
    if ($p === null) {
        throw new RuntimeException('Parcela não encontrada.');
    }
    if ($p['STATUS'] !== PARCELA_ABERTA) {
        throw new RuntimeException('A parcela ' . $p['NUMERO'] . '/' . $p['TOTAL_PARCELAS'] . ' da venda #' . $p['CODIGO_VENDA'] . ' já não está em aberto.');
    }

    $obs   = mb_substr(trim($obs), 0, 255);
    $mudou = executar(
        'UPDATE CREDIARIO_PARCELA
            SET STATUS = :paga, DATA_PAGAMENTO = :data, VALOR_PAGO = :valor, FORMA_RECEBIMENTO = :forma,
                OBSERVACAO = :obs, BAIXADO_EM = :agora
          WHERE ID = :id AND STATUS = :aberta',
        [
            ':paga' => PARCELA_PAGA, ':data' => $data, ':valor' => paraBanco(saldoParcela($p)), ':forma' => $forma,
            ':obs' => $obs !== '' ? $obs : null, ':agora' => date('Y-m-d H:i:s'), ':id' => $id, ':aberta' => PARCELA_ABERTA,
        ]
    );
    if ($mudou !== 1) {
        throw new RuntimeException('A parcela já foi baixada por outra pessoa. Atualize a página.');
    }
}

/** Desfaz a baixa (a parcela volta a ficar em aberto). */
function desfazerBaixaParcela(int $id): void
{
    garantirTabelasCrediario();

    $mudou = executar(
        'UPDATE CREDIARIO_PARCELA
            SET STATUS = :aberta, DATA_PAGAMENTO = NULL, VALOR_PAGO = NULL, FORMA_RECEBIMENTO = NULL,
                OBSERVACAO = NULL, BAIXADO_EM = NULL
          WHERE ID = :id AND STATUS = :paga',
        [':aberta' => PARCELA_ABERTA, ':id' => $id, ':paga' => PARCELA_PAGA]
    );
    if ($mudou !== 1) {
        throw new RuntimeException('Esta parcela não está paga, então não há baixa para desfazer.');
    }
}

// ---------- Leitura ----------

function buscarParcela(int $id): ?array
{
    garantirTabelasCrediario();
    return consultarUma('SELECT * FROM CREDIARIO_PARCELA WHERE ID = :id', [':id' => $id]);
}

/** Parcelas de uma venda (vazio se a venda não é crediário). */
function parcelasDaVenda(int $vendaId): array
{
    garantirTabelasCrediario();
    return consultar('SELECT * FROM CREDIARIO_PARCELA WHERE CODIGO_VENDA = :v ORDER BY NUMERO', [':v' => $vendaId]);
}

/** Quanto ainda falta receber de uma venda no crediário. */
function saldoCrediarioVenda(int $vendaId): float
{
    garantirTabelasCrediario();
    return round((float) consultarValor(
        'SELECT COALESCE(SUM(VALOR - VALOR_ABATIDO), 0) FROM CREDIARIO_PARCELA WHERE CODIGO_VENDA = :v AND STATUS = :s',
        [':v' => $vendaId, ':s' => PARCELA_ABERTA]
    ), 2);
}

function filtrosCrediario(array $get): array
{
    $situacao = trim((string) ($get['situacao'] ?? ''));
    return [
        'busca'    => mb_substr(trim((string) ($get['busca'] ?? '')), 0, 100),
        'situacao' => array_key_exists($situacao, SITUACOES_CREDIARIO) ? $situacao : 'aberto',
    ];
}

/** Números do topo da tela: a receber, vencido, clientes devendo, recebido no mês e vencendo nos próximos 7 dias. */
function resumoCrediario(): array
{
    garantirTabelasCrediario();
    $hoje = date('Y-m-d');

    $r = consultarUma(
        "SELECT COALESCE(SUM(VALOR - VALOR_ABATIDO), 0) AS ABERTO,
                COALESCE(SUM(CASE WHEN VENCIMENTO < :hoje THEN VALOR - VALOR_ABATIDO ELSE 0 END), 0) AS VENCIDO,
                COALESCE(SUM(CASE WHEN VENCIMENTO >= :hoje2 AND VENCIMENTO <= :semana THEN VALOR - VALOR_ABATIDO ELSE 0 END), 0) AS SEMANA,
                COUNT(DISTINCT CODIGO_CLIENTE) AS CLIENTES,
                COUNT(DISTINCT CASE WHEN VENCIMENTO < :hoje3 THEN CODIGO_CLIENTE END) AS CLIENTES_ATRASO
           FROM CREDIARIO_PARCELA
          WHERE STATUS = :aberta",
        [':hoje' => $hoje, ':hoje2' => $hoje, ':hoje3' => $hoje, ':semana' => date('Y-m-d', strtotime('+7 days')),
         ':aberta' => PARCELA_ABERTA]
    ) ?? [];

    $recebido = consultarValor(
        'SELECT COALESCE(SUM(VALOR_PAGO), 0) FROM CREDIARIO_PARCELA WHERE STATUS = :paga AND DATA_PAGAMENTO >= :ini AND DATA_PAGAMENTO <= :fim',
        [':paga' => PARCELA_PAGA, ':ini' => date('Y-m-01'), ':fim' => $hoje]
    );

    return [
        'aberto'          => (float) ($r['ABERTO'] ?? 0),
        'vencido'         => (float) ($r['VENCIDO'] ?? 0),
        'semana'          => (float) ($r['SEMANA'] ?? 0),
        'clientes'        => (int) ($r['CLIENTES'] ?? 0),
        'clientes_atraso' => (int) ($r['CLIENTES_ATRASO'] ?? 0),
        'recebido_mes'    => (float) $recebido,
    ];
}

/**
 * Carnês (uma venda no crediário = um carnê) agrupados por cliente, conforme o filtro.
 * Cada cliente: ['CODIGO_CLIENTE', 'NOME', 'TELEFONE', 'ABERTO', 'VENCIDO', 'ATRASO_MAX', 'PROXIMO', 'CARNES' => [...]]
 * Cada carnê: ['CODIGO_VENDA', 'DATA', 'TOTAL_VENDA', 'TOTAL_CREDIARIO', 'ABERTO', 'PAGO', 'PARCELAS' => [...]]
 * Clientes com atraso aparecem primeiro, depois pelo próximo vencimento.
 */
function listarCrediarios(array $f, int $limiteCarnes = 200): array
{
    garantirTabelasCrediario();
    $hoje = date('Y-m-d');

    // 1) Quais vendas (carnês) entram
    $params = [];
    $where  = ['1 = 1'];
    if ($f['busca'] !== '') {
        $cond = ['c.NOME LIKE :busca_nome'];
        $params[':busca_nome'] = '%' . $f['busca'] . '%';
        $digitos = somenteDigitos($f['busca']);
        if (ctype_digit($f['busca'])) {
            $cond[] = 'p.CODIGO_VENDA = :busca_cod';
            $params[':busca_cod'] = (int) $f['busca'];
        }
        if (strlen($digitos) >= 6) {
            $cond[] = 'c.CPF LIKE :busca_cpf';
            $params[':busca_cpf'] = '%' . $digitos . '%';
        }
        $where[] = '(' . implode(' OR ', $cond) . ')';
    }

    $having = '';
    switch ($f['situacao']) {
        case 'aberto':
            $having = "HAVING SUM(CASE WHEN p.STATUS = 'Aberta' THEN 1 ELSE 0 END) > 0";
            break;
        case 'atrasado':
            $having = "HAVING SUM(CASE WHEN p.STATUS = 'Aberta' AND p.VENCIMENTO < :hoje THEN 1 ELSE 0 END) > 0";
            $params[':hoje'] = $hoje;
            break;
        case 'quitado':
            $having = "HAVING SUM(CASE WHEN p.STATUS = 'Aberta' THEN 1 ELSE 0 END) = 0";
            break;
    }
    $params[':limite'] = $limiteCarnes;

    $vendas = consultar(
        "SELECT p.CODIGO_VENDA, MAX(v.DATA) AS DATA
           FROM CREDIARIO_PARCELA p
           JOIN VENDAS v ON v.CODIGO_VENDA = p.CODIGO_VENDA
           LEFT JOIN CLIENTE c ON c.CODIGO = p.CODIGO_CLIENTE
          WHERE " . implode(' AND ', $where) . "
          GROUP BY p.CODIGO_VENDA
          $having
          ORDER BY MAX(v.DATA) DESC, p.CODIGO_VENDA DESC
          LIMIT :limite",
        $params
    );
    if (empty($vendas)) {
        return [];
    }

    // 2) Parcelas e dados dessas vendas
    $ids    = array_map(fn($v) => (int) $v['CODIGO_VENDA'], $vendas);
    $marcas = implode(',', array_fill(0, count($ids), '?'));
    $linhas = consultar(
        "SELECT p.*, v.DATA AS DATA_VENDA, v.TOTAL AS TOTAL_VENDA, c.NOME AS CLIENTE_NOME
           FROM CREDIARIO_PARCELA p
           JOIN VENDAS v ON v.CODIGO_VENDA = p.CODIGO_VENDA
           LEFT JOIN CLIENTE c ON c.CODIGO = p.CODIGO_CLIENTE
          WHERE p.CODIGO_VENDA IN ($marcas)
          ORDER BY p.CODIGO_VENDA, p.NUMERO",
        $ids
    );

    $clientes = [];
    foreach ($linhas as $l) {
        $cli   = (int) $l['CODIGO_CLIENTE'];
        $venda = (int) $l['CODIGO_VENDA'];
        if (!isset($clientes[$cli])) {
            $clientes[$cli] = [
                'CODIGO_CLIENTE' => $cli, 'NOME' => trim((string) $l['CLIENTE_NOME']) ?: 'Sem cadastro', 'TELEFONE' => '',
                'ABERTO' => 0.0, 'VENCIDO' => 0.0, 'ATRASO_MAX' => 0, 'PROXIMO' => null, 'CARNES' => [],
            ];
        }
        $c = &$clientes[$cli];
        if (!isset($c['CARNES'][$venda])) {
            $c['CARNES'][$venda] = [
                'CODIGO_VENDA' => $venda, 'DATA' => $l['DATA_VENDA'], 'TOTAL_VENDA' => (float) $l['TOTAL_VENDA'],
                'TOTAL_CREDIARIO' => 0.0, 'ABERTO' => 0.0, 'PAGO' => 0.0, 'ABATIDO' => 0.0, 'PARCELAS' => [],
            ];
        }
        $carne = &$c['CARNES'][$venda];

        $l['SALDO']  = saldoParcela($l);
        $l['ATRASO'] = diasDeAtraso($l, $hoje);
        $carne['TOTAL_CREDIARIO'] += (float) $l['VALOR'];
        $carne['ABATIDO']         += (float) $l['VALOR_ABATIDO'];
        if ($l['STATUS'] === PARCELA_ABERTA) {
            $carne['ABERTO'] += $l['SALDO'];
            $c['ABERTO']     += $l['SALDO'];
            if ($l['ATRASO'] > 0) {
                $c['VENCIDO']   += $l['SALDO'];
                $c['ATRASO_MAX'] = max($c['ATRASO_MAX'], $l['ATRASO']);
            }
            $venc = substr((string) $l['VENCIMENTO'], 0, 10);
            if ($c['PROXIMO'] === null || $venc < $c['PROXIMO']) {
                $c['PROXIMO'] = $venc;
            }
        } elseif ($l['STATUS'] === PARCELA_PAGA) {
            $carne['PAGO'] += (float) $l['VALOR_PAGO'];
        }
        $carne['PARCELAS'][] = $l;
        unset($carne, $c);
    }

    // Telefone / WhatsApp
    $contatos = contatosPrincipais(array_values(array_filter(array_keys($clientes))));
    foreach ($clientes as $cli => &$c) {
        $tel = $contatos[$cli] ?? [];
        $c['TELEFONE'] = $tel[CONTATO_WHATSAPP] ?? $tel[CONTATO_CELULAR] ?? '';
        $c['CARNES']   = array_values($c['CARNES']);
    }
    unset($c);

    $lista = array_values($clientes);
    usort($lista, function ($a, $b) {
        if (($a['ATRASO_MAX'] > 0) !== ($b['ATRASO_MAX'] > 0)) {
            return $a['ATRASO_MAX'] > 0 ? -1 : 1;
        }
        if ($a['ATRASO_MAX'] !== $b['ATRASO_MAX']) {
            return $b['ATRASO_MAX'] <=> $a['ATRASO_MAX'];
        }
        return strcmp($a['PROXIMO'] ?? '9999', $b['PROXIMO'] ?? '9999') ?: strcmp($a['NOME'], $b['NOME']);
    });

    return $lista;
}

/** Link do WhatsApp com uma mensagem de lembrete (vazio se a cliente não tem telefone). */
function linkWhatsappCobranca(string $telefone, string $nome, float $valor, int $atraso): string
{
    $numero = somenteDigitos($telefone);
    if (strlen($numero) < 10) {
        return '';
    }
    if (strlen($numero) <= 11) {
        $numero = '55' . $numero;
    }
    $primeiro = trim(explode(' ', trim($nome))[0] ?? '');
    $texto = 'Olá' . ($primeiro !== '' ? ', ' . mb_convert_case(mb_strtolower($primeiro), MB_CASE_TITLE) : '') . '! Aqui é da ' . NOME_LOJA . '. '
           . ($atraso > 0
               ? 'Passando para lembrar que há ' . formatarMoeda($valor) . ' do seu crediário em atraso. Qualquer dúvida estamos à disposição.'
               : 'Passando para lembrar da próxima parcela do seu crediário. Qualquer dúvida estamos à disposição.');
    return 'https://wa.me/' . $numero . '?text=' . rawurlencode($texto);
}
