<?php
/**
 * clientes_funcoes.php
 * Regras do módulo de clientes, usando as tabelas CLIENTE e CLIENTE_CONTATOS do banco da loja.
 *
 * Telefones, WhatsApp, Instagram e e-mail ficam em CLIENTE_CONTATOS (um registro por contato),
 * como no sistema atual da loja. Tipos de contato (tabela TIPO_CONTATOS):
 *   1 = Celular | 2 = Whatsapp | 7 = Instagram | 8 = Email
 * Clientes não são apagados: ficam com STATUS_CLI = 'Cancelado'.
 */

const CONTATO_CELULAR   = 1;
const CONTATO_WHATSAPP  = 2;
const CONTATO_INSTAGRAM = 7;
const CONTATO_EMAIL     = 8;

/**
 * Campos que o cadastro pede (todos menos celular, e-mail e complemento).
 * Se faltar algum, a tela avisa e pergunta se quer salvar mesmo assim, e o cliente
 * aparece no filtro "Dados incompletos" da lista para ser completado depois.
 */
const CLIENTE_CAMPOS_PEDIDOS = [
    'cpf'       => 'CPF',
    'data_nasc' => 'Nascimento',
    'sexo'      => 'Sexo',
    'whatsapp'  => 'WhatsApp',
    'instagram' => 'Instagram',
    'cep'       => 'CEP',
    'endereco'  => 'Endereço',
    'numero'    => 'Número',
    'bairro'    => 'Bairro',
    'cidade'    => 'Cidade',
    'uf'        => 'UF',
];

/** Converte o sexo gravado ('F', 'M' ou as palavras antigas) para 'F'/'M'/''. */
function normalizarSexo(?string $sexo): string
{
    $s = mb_strtoupper(trim((string) $sexo));
    if ($s === '') {
        return '';
    }
    if ($s[0] === 'F') {
        return 'F';
    }
    if ($s[0] === 'M') {
        return 'M';
    }
    return '';
}

/** Nomes dos campos pedidos que estão vazios no cliente (dados do formulário). */
function camposFaltandoCliente(array $d): array
{
    $faltando = [];
    foreach (CLIENTE_CAMPOS_PEDIDOS as $campo => $rotulo) {
        if (trim((string) ($d[$campo] ?? '')) === '') {
            $faltando[] = $rotulo;
        }
    }
    return $faltando;
}

/** Lê e normaliza os filtros da tela de clientes. */
function filtrosClientes(array $get): array
{
    $status = $get['status'] ?? 'Ativo';
    if (!in_array($status, ['Ativo', 'Pendente', 'Cancelado', ''], true)) {
        $status = 'Ativo';
    }

    $filtros = [
        'busca'  => trim((string) ($get['busca'] ?? '')),
        'status' => $status,
    ];
    if (($get['incompletos'] ?? '') === '1') {
        $filtros['incompletos'] = '1';
    }
    return $filtros;
}

function whereClientes(array $f, array &$params): string
{
    $where = ['1 = 1'];

    if ($f['busca'] !== '') {
        $digitos = somenteDigitos($f['busca']);
        $cond    = ['c.NOME LIKE :busca_nome'];
        $params[':busca_nome'] = '%' . $f['busca'] . '%';

        if (strlen($digitos) >= 3) {
            $cond[] = "REPLACE(REPLACE(c.CPF, '.', ''), '-', '') LIKE :busca_cpf";
            $cond[] = "EXISTS (SELECT 1 FROM CLIENTE_CONTATOS x
                                WHERE x.CODIGO_CLIENTE = c.CODIGO
                                  AND REPLACE(REPLACE(REPLACE(REPLACE(x.VALOR_CONTATO, ' ', ''), '-', ''), '(', ''), ')', '') LIKE :busca_tel)";
            $params[':busca_cpf'] = '%' . $digitos . '%';
            $params[':busca_tel'] = '%' . $digitos . '%';
        }
        if (ctype_digit($f['busca'])) {
            $cond[] = 'c.CODIGO = :busca_cod';
            $params[':busca_cod'] = (int) $f['busca'];
        }
        $where[] = '(' . implode(' OR ', $cond) . ')';
    }

    if ($f['status'] !== '') {
        $where[] = 'c.STATUS_CLI = :status';
        $params[':status'] = $f['status'];
    }

    if (($f['incompletos'] ?? '') === '1') {
        $vazio = fn(string $col): string => "COALESCE(TRIM(c.$col), '') = ''";
        $semContato = fn(int $tipo): string => "NOT EXISTS (SELECT 1 FROM CLIENTE_CONTATOS y
                                   WHERE y.CODIGO_CLIENTE = c.CODIGO AND y.CODIGO_TIPO_CONTATO = $tipo
                                     AND COALESCE(TRIM(y.VALOR_CONTATO), '') <> '')";
        $where[] = '(' . implode(' OR ', [
            $vazio('CPF'), 'c.DATA_NASCD IS NULL', $vazio('SEXO_CLI'), $vazio('CEP'), $vazio('ENDERECO'),
            'c.NUMERO IS NULL', $vazio('BAIRRO'), $vazio('CIDADE'), $vazio('UF'),
            $semContato(CONTATO_WHATSAPP), $semContato(CONTATO_INSTAGRAM),
        ]) . ')';
    }

    return implode(' AND ', $where);
}

function contarClientes(array $f): int
{
    $params = [];
    $where  = whereClientes($f, $params);
    return (int) consultarValor("SELECT COUNT(*) FROM CLIENTE c WHERE $where", $params);
}

/** Lista clientes já com o celular e o WhatsApp de cada um (chaves TELEFONE e WHATSAPP). */
function listarClientes(array $f, int $limite, int $offset): array
{
    $params = [];
    $where  = whereClientes($f, $params);
    $params[':limite']  = $limite;
    $params[':posicao'] = $offset;

    $clientes = consultar(
        "SELECT c.CODIGO, c.NOME, c.CPF, c.CIDADE, c.BAIRRO, c.STATUS_CLI, c.DATA_CADASTRO,
                c.DATA_NASCD, c.SEXO_CLI, c.CEP, c.ENDERECO, c.NUMERO, c.UF
           FROM CLIENTE c
          WHERE $where
          ORDER BY c.NOME, c.CODIGO
          LIMIT :limite OFFSET :posicao",
        $params
    );

    $contatos = contatosPrincipais(array_map('intval', array_column($clientes, 'CODIGO')));

    foreach ($clientes as &$cliente) {
        $c = $contatos[(int) $cliente['CODIGO']] ?? [];
        $cliente['TELEFONE'] = $c[CONTATO_CELULAR] ?? '';
        $cliente['WHATSAPP'] = $c[CONTATO_WHATSAPP] ?? '';
        $cliente['FALTANDO'] = camposFaltandoCliente([
            'cpf' => $cliente['CPF'], 'data_nasc' => $cliente['DATA_NASCD'], 'sexo' => $cliente['SEXO_CLI'],
            'whatsapp' => $cliente['WHATSAPP'], 'instagram' => $c[CONTATO_INSTAGRAM] ?? '',
            'cep' => $cliente['CEP'], 'endereco' => $cliente['ENDERECO'],
            'numero' => $cliente['NUMERO'] === null ? '' : (string) $cliente['NUMERO'],
            'bairro' => $cliente['BAIRRO'], 'cidade' => $cliente['CIDADE'], 'uf' => $cliente['UF'],
        ]);
    }
    unset($cliente);

    return $clientes;
}

/** Primeiro celular, WhatsApp e Instagram de cada cliente: [codigo_cliente => [tipo => valor]]. */
function contatosPrincipais(array $ids): array
{
    if (empty($ids)) {
        return [];
    }

    $marcas = implode(',', array_fill(0, count($ids), '?'));
    $linhas = consultar(
        "SELECT CODIGO_CLIENTE, CODIGO_TIPO_CONTATO, VALOR_CONTATO
           FROM CLIENTE_CONTATOS
          WHERE CODIGO_CLIENTE IN ($marcas) AND CODIGO_TIPO_CONTATO IN (1, 2, 7) AND COALESCE(TRIM(VALOR_CONTATO), '') <> ''
          ORDER BY CODIGO",
        array_values($ids)
    );

    $mapa = [];
    foreach ($linhas as $l) {
        $cliente = (int) $l['CODIGO_CLIENTE'];
        $tipo    = (int) $l['CODIGO_TIPO_CONTATO'];
        if (!isset($mapa[$cliente][$tipo])) {
            $mapa[$cliente][$tipo] = (string) $l['VALOR_CONTATO'];
        }
    }
    return $mapa;
}

function buscarCliente(int $id): ?array
{
    return consultarUma('SELECT * FROM CLIENTE WHERE CODIGO = :id', [':id' => $id]);
}

/** Todos os contatos do cliente, o primeiro de cada tipo: [tipo => valor]. */
function contatosDoCliente(int $id): array
{
    $linhas = consultar(
        'SELECT CODIGO_TIPO_CONTATO, VALOR_CONTATO FROM CLIENTE_CONTATOS WHERE CODIGO_CLIENTE = :id ORDER BY CODIGO',
        [':id' => $id]
    );

    $mapa = [];
    foreach ($linhas as $l) {
        $tipo = (int) $l['CODIGO_TIPO_CONTATO'];
        if (!isset($mapa[$tipo])) {
            $mapa[$tipo] = (string) $l['VALOR_CONTATO'];
        }
    }
    return $mapa;
}

function clienteVazio(): array
{
    return [
        'id' => 0, 'nome' => '', 'cpf' => '', 'data_nasc' => '', 'sexo' => '', 'cep' => '', 'endereco' => '',
        'numero' => '', 'complemento' => '', 'bairro' => '', 'cidade' => '', 'uf' => 'SP', 'status' => 'Ativo',
        'celular' => '', 'whatsapp' => '', 'instagram' => '', 'email' => '',
    ];
}

/** Converte a linha do banco (+ contatos) para os campos do formulário. */
function clienteParaFormulario(array $linha, array $contatos): array
{
    return [
        'id'          => (int) $linha['CODIGO'],
        'nome'        => trim((string) $linha['NOME']),
        'cpf'         => somenteDigitos((string) $linha['CPF']),
        'data_nasc'   => (string) ($linha['DATA_NASCD'] ?? ''),
        'sexo'        => normalizarSexo($linha['SEXO_CLI'] ?? ''),
        'cep'         => formatarCep($linha['CEP'] ?? ''),
        'endereco'    => trim((string) $linha['ENDERECO']),
        'numero'      => $linha['NUMERO'] === null ? '' : (string) $linha['NUMERO'],
        'complemento' => trim((string) $linha['COMPLEMENTO']),
        'bairro'      => trim((string) $linha['BAIRRO']),
        'cidade'      => trim((string) $linha['CIDADE']),
        'uf'          => trim((string) $linha['UF']),
        'status'      => (string) ($linha['STATUS_CLI'] ?? 'Ativo'),
        'celular'     => somenteDigitos($contatos[CONTATO_CELULAR] ?? ''),
        'whatsapp'    => somenteDigitos($contatos[CONTATO_WHATSAPP] ?? ''),
        'instagram'   => trim($contatos[CONTATO_INSTAGRAM] ?? ''),
        'email'       => trim($contatos[CONTATO_EMAIL] ?? ''),
    ];
}

/** Lê os campos enviados pelo formulário. CPF e telefones ficam só com dígitos, como no banco. */
function montarCliente(array $post, int $id = 0): array
{
    $t = fn(string $campo): string => trim((string) ($post[$campo] ?? ''));

    return [
        'id'          => $id,
        'nome'        => $t('nome'),
        'cpf'         => somenteDigitos($t('cpf')),
        'data_nasc'   => $t('data_nasc'),
        'sexo'        => normalizarSexo($t('sexo')),
        'cep'         => formatarCep($t('cep')),
        'endereco'    => $t('endereco'),
        'numero'      => $t('numero'),
        'complemento' => $t('complemento'),
        'bairro'      => $t('bairro'),
        'cidade'      => $t('cidade'),
        'uf'          => mb_strtoupper($t('uf')),
        'status'      => $t('status') === '' ? 'Ativo' : $t('status'),
        'celular'     => somenteDigitos($t('celular')),
        'whatsapp'    => somenteDigitos($t('whatsapp')),
        'instagram'   => ltrim($t('instagram'), '@ '),
        'email'       => $t('email'),
    ];
}

/** Valida o cliente. $cpfAtual = CPF já gravado (se não mudou, não é revalidado). */
function validarCliente(array $d, string $cpfAtual = ''): array
{
    $erros = [];

    if (mb_strlen($d['nome']) < 3 || mb_strlen($d['nome']) > 255) {
        $erros['nome'] = 'Informe o nome completo.';
    }
    if ($d['cpf'] !== '' && $d['cpf'] !== $cpfAtual && !cpfValido($d['cpf'])) {
        $erros['cpf'] = 'CPF inválido.';
    }
    if ($d['data_nasc'] !== '') {
        $data = dataValida($d['data_nasc']);
        if ($data === '' || $data < '1900-01-01' || $data > date('Y-m-d')) {
            $erros['data_nasc'] = 'Data de nascimento inválida.';
        }
    }
    if (!in_array($d['sexo'], ['', 'F', 'M'], true)) {
        $erros['sexo'] = 'Valor inválido.';
    }
    if ($d['cep'] !== '' && !preg_match('/^\d{5}-\d{3}$/', $d['cep'])) {
        $erros['cep'] = 'CEP inválido (use 8 números).';
    }
    if ($d['numero'] !== '' && (!ctype_digit($d['numero']) || (int) $d['numero'] > 2147483647)) {
        $erros['numero'] = 'Use apenas números.';
    }
    if ($d['uf'] !== '' && !preg_match('/^[A-Z]{2}$/', $d['uf'])) {
        $erros['uf'] = 'Use a sigla do estado (ex.: SP).';
    }
    foreach (['endereco' => 255, 'complemento' => 225, 'bairro' => 100, 'cidade' => 100] as $campo => $max) {
        if (mb_strlen($d[$campo]) > $max) {
            $erros[$campo] = "Máximo de $max caracteres.";
        }
    }
    foreach (['celular', 'whatsapp'] as $campo) {
        if ($d[$campo] !== '' && !in_array(strlen($d[$campo]), [10, 11], true)) {
            $erros[$campo] = 'Número inválido. Use DDD + número.';
        }
    }
    if (mb_strlen($d['instagram']) > 100) {
        $erros['instagram'] = 'Máximo de 100 caracteres.';
    }
    if ($d['email'] !== '' && (!filter_var($d['email'], FILTER_VALIDATE_EMAIL) || mb_strlen($d['email']) > 150)) {
        $erros['email'] = 'E-mail inválido.';
    }
    if (!in_array($d['status'], ['Ativo', 'Pendente', 'Cancelado'], true)) {
        $erros['status'] = 'Situação inválida.';
    }

    return $erros;
}

/** Confere os dígitos verificadores do CPF. */
function cpfValido(string $cpf): bool
{
    $cpf = somenteDigitos($cpf);

    if (strlen($cpf) !== 11 || preg_match('/^(\d)\1{10}$/', $cpf)) {
        return false;
    }

    for ($posicao = 9; $posicao < 11; $posicao++) {
        $soma = 0;
        for ($i = 0; $i < $posicao; $i++) {
            $soma += (int) $cpf[$i] * (($posicao + 1) - $i);
        }
        $digito = ((10 * $soma) % 11) % 10;
        if ((int) $cpf[$posicao] !== $digito) {
            return false;
        }
    }
    return true;
}

/** Grava o cliente e seus contatos numa única transação. Devolve o código do cliente. */
function salvarCliente(array $d): int
{
    return emTransacao(function () use ($d) {
        $hoje = date('Y-m-d');
        $params = [
            ':nome' => $d['nome'], ':cpf' => $d['cpf'] === '' ? null : $d['cpf'],
            ':nasc' => $d['data_nasc'] === '' ? null : $d['data_nasc'],
            ':sexo' => $d['sexo'] === '' ? null : $d['sexo'],
            ':cep' => $d['cep'] === '' ? null : $d['cep'],
            ':end' => $d['endereco'] === '' ? null : $d['endereco'],
            ':num' => $d['numero'] === '' ? null : (int) $d['numero'],
            ':comp' => $d['complemento'] === '' ? null : $d['complemento'],
            ':bairro' => $d['bairro'] === '' ? null : $d['bairro'],
            ':cidade' => $d['cidade'] === '' ? null : $d['cidade'],
            ':uf' => $d['uf'] === '' ? null : $d['uf'],
            ':status' => $d['status'], ':atual' => $hoje,
        ];

        if ($d['id'] > 0) {
            $params[':id'] = $d['id'];
            executar(
                'UPDATE CLIENTE
                    SET NOME = :nome, CPF = :cpf, DATA_NASCD = :nasc, SEXO_CLI = :sexo, CEP = :cep, ENDERECO = :end,
                        NUMERO = :num, COMPLEMENTO = :comp, BAIRRO = :bairro, CIDADE = :cidade, UF = :uf,
                        STATUS_CLI = :status, DTATUALIZA = :atual
                  WHERE CODIGO = :id',
                $params
            );
            $id = $d['id'];
        } else {
            $params[':loja'] = CODIGO_LOJA;
            executar(
                'INSERT INTO CLIENTE
                    (LOJA, NOME, CPF, DATA_NASCD, SEXO_CLI, CEP, ENDERECO, NUMERO, COMPLEMENTO, BAIRRO, CIDADE, UF,
                     STATUS_CLI, DATA_CADASTRO, DTATUALIZA)
                 VALUES
                    (:loja, :nome, :cpf, :nasc, :sexo, :cep, :end, :num, :comp, :bairro, :cidade, :uf,
                     :status, :atual, :atual2)',
                $params + [':atual2' => $hoje]
            );
            $id = ultimoId();
        }

        sincronizarContato($id, CONTATO_CELULAR, 'Celular', $d['celular']);
        sincronizarContato($id, CONTATO_WHATSAPP, 'Whatsapp', $d['whatsapp']);
        sincronizarContato($id, CONTATO_INSTAGRAM, 'Instagram', $d['instagram']);
        sincronizarContato($id, CONTATO_EMAIL, 'Email', $d['email']);

        return $id;
    });
}

/** Atualiza, cria ou remove o contato de um tipo (valor vazio = remove). */
function sincronizarContato(int $clienteId, int $tipo, string $nomeTipo, string $valor): void
{
    $existente = consultarValor(
        'SELECT CODIGO FROM CLIENTE_CONTATOS WHERE CODIGO_CLIENTE = :c AND CODIGO_TIPO_CONTATO = :t ORDER BY CODIGO LIMIT 1',
        [':c' => $clienteId, ':t' => $tipo]
    );

    if ($valor === '') {
        if ($existente !== null) {
            executar('DELETE FROM CLIENTE_CONTATOS WHERE CODIGO = :id', [':id' => (int) $existente]);
        }
        return;
    }

    if ($existente !== null) {
        executar(
            "UPDATE CLIENTE_CONTATOS SET VALOR_CONTATO = :v, STATUS_CONTATO = 'Ativo' WHERE CODIGO = :id",
            [':v' => $valor, ':id' => (int) $existente]
        );
        return;
    }

    executar(
        "INSERT INTO CLIENTE_CONTATOS (LOJA, CODIGO_CLIENTE, CODIGO_TIPO_CONTATO, TIPO_CONTATO, VALOR_CONTATO, STATUS_CONTATO)
         VALUES (:loja, :c, :t, :nome, :v, 'Ativo')",
        [':loja' => CODIGO_LOJA, ':c' => $clienteId, ':t' => $tipo, ':nome' => $nomeTipo, ':v' => $valor]
    );
}

/** "Exclui" o cliente: apenas marca como Cancelado (as vendas antigas continuam ligadas a ele). */
function inativarCliente(int $id): bool
{
    return executar(
        "UPDATE CLIENTE SET STATUS_CLI = 'Cancelado', DTATUALIZA = :atual WHERE CODIGO = :id AND COALESCE(STATUS_CLI, '') <> 'Cancelado'",
        [':atual' => date('Y-m-d'), ':id' => $id]
    ) > 0;
}

/** Resumo de compras do cliente (somente vendas finalizadas). */
function resumoComprasCliente(int $id): array
{
    $resumo = consultarUma(
        "SELECT COUNT(*) AS QTD, COALESCE(SUM(TOTAL), 0) AS TOTAL, MAX(DATA) AS ULTIMA
           FROM VENDAS
          WHERE CODIGO_CLIENTE = :id AND STATUS_VENDA = 'Finalizado'",
        [':id' => $id]
    ) ?? ['QTD' => 0, 'TOTAL' => 0, 'ULTIMA' => null];

    $ultimas = consultar(
        "SELECT CODIGO_VENDA, DATA, TOTAL, NUM_PECAS
           FROM VENDAS
          WHERE CODIGO_CLIENTE = :id AND STATUS_VENDA = 'Finalizado'
          ORDER BY DATA DESC, CODIGO_VENDA DESC
          LIMIT 5",
        [':id' => $id]
    );

    return ['resumo' => $resumo, 'ultimas' => $ultimas];
}

function totalClientesAtivos(): int
{
    return (int) consultarValor("SELECT COUNT(*) FROM CLIENTE WHERE STATUS_CLI = 'Ativo'");
}
