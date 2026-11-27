<?php
/**
 * produtos_funcoes.php
 * Regras do módulo de produtos, usando as tabelas PRODUTOS, GRUPOS e SUBGRUPO do banco da loja.
 *
 * Mapa dos campos do sistema para as colunas do banco:
 *   nome → DESCRICAO | categoria → COD_GRUPO (tabela GRUPOS) | estoque → QUANT
 *   custo → PRE_CUS  | venda → PRE_VEN                      | situação → STATUS_PRODUTO
 *   fornecedor → tabela de fornecedores do sistema atual, se existir (veja estruturaFornecedor())
 * Produtos não são apagados: ficam com STATUS_PRODUTO = 'Cancelado' (como no sistema atual da loja),
 * porque as vendas antigas continuam apontando para eles.
 */

const LIMITE_ESTOQUE_BAIXO = 2;

// =====================================================================
// Leitura da estrutura do banco (para trabalhar com as tabelas do sistema atual sem adivinhar colunas)
// =====================================================================

function bancoEhSqlite(): bool
{
    return conectar()->getAttribute(PDO::ATTR_DRIVER_NAME) === 'sqlite';
}

/**
 * Colunas de uma tabela: [NOME_EM_MAIUSCULAS => ['nome', 'tabela', 'tipo', 'tamanho', 'nulo', 'padrao', 'auto']].
 * Devolve [] se a tabela não existir. O resultado fica guardado durante a requisição ($limpar = true esquece).
 */
function colunasDaTabela(string $tabela, bool $limpar = false): array
{
    static $cache = [];
    $chave = strtoupper($tabela);

    if ($limpar) {
        unset($cache[$chave]);
    }
    if (isset($cache[$chave])) {
        return $cache[$chave];
    }

    $colunas = [];

    if (bancoEhSqlite()) {
        $real = consultarValor("SELECT name FROM sqlite_master WHERE type = 'table' AND UPPER(name) = :t", [':t' => $chave]);
        if ($real !== null) {
            $real = (string) $real;
            foreach (consultar('PRAGMA table_info("' . str_replace('"', '', $real) . '")') as $c) {
                $tipo = strtolower((string) $c['type']);
                $colunas[strtoupper($c['name'])] = [
                    'nome'    => $c['name'],
                    'tabela'  => $real,
                    'tipo'    => preg_replace('/\(.*/', '', $tipo),
                    'tamanho' => preg_match('/\((\d+)/', $tipo, $m) ? (int) $m[1] : null,
                    'nulo'    => (int) $c['notnull'] === 0,
                    'padrao'  => $c['dflt_value'],
                    'auto'    => (int) $c['pk'] === 1 && str_contains($tipo, 'int'),
                ];
            }
        }
    } else {
        $linhas = consultar(
            'SELECT TABLE_NAME, COLUMN_NAME, DATA_TYPE, CHARACTER_MAXIMUM_LENGTH, IS_NULLABLE, COLUMN_DEFAULT, EXTRA
               FROM INFORMATION_SCHEMA.COLUMNS
              WHERE TABLE_SCHEMA = DATABASE() AND UPPER(TABLE_NAME) = :t
              ORDER BY ORDINAL_POSITION',
            [':t' => $chave]
        );
        foreach ($linhas as $c) {
            $colunas[strtoupper($c['COLUMN_NAME'])] = [
                'nome'    => $c['COLUMN_NAME'],
                'tabela'  => $c['TABLE_NAME'],
                'tipo'    => strtolower((string) $c['DATA_TYPE']),
                'tamanho' => $c['CHARACTER_MAXIMUM_LENGTH'] === null ? null : (int) $c['CHARACTER_MAXIMUM_LENGTH'],
                'nulo'    => $c['IS_NULLABLE'] === 'YES',
                'padrao'  => $c['COLUMN_DEFAULT'],
                'auto'    => str_contains(strtolower((string) $c['EXTRA']), 'auto_increment'),
            ];
        }
    }

    return $cache[$chave] = $colunas;
}

/** Nome da tabela como está gravado no banco (ou null se não existir). */
function nomeRealTabela(string $tabela): ?string
{
    $colunas = colunasDaTabela($tabela);
    return $colunas ? (string) reset($colunas)['tabela'] : null;
}

/** A primeira coluna da lista que existir na tabela, em maiúsculas (ou null). */
function primeiraColuna(string $tabela, array $candidatas): ?string
{
    $colunas = colunasDaTabela($tabela);
    foreach ($candidatas as $c) {
        if (isset($colunas[strtoupper($c)])) {
            return strtoupper($c);
        }
    }
    return null;
}

/** Tamanho máximo de texto de uma coluna, limitado a $padrao (que vale se o banco não informar). */
function tamanhoColuna(string $tabela, string $coluna, int $padrao): int
{
    $c = colunasDaTabela($tabela)[strtoupper($coluna)] ?? null;
    return ($c && $c['tamanho']) ? min($padrao, (int) $c['tamanho']) : $padrao;
}

/**
 * Insere uma linha numa tabela do sistema atual e devolve o código criado.
 * - Só grava as colunas de $valores que existem na tabela (as outras são ignoradas).
 * - Se o código não for automático, usa o maior código + 1 (como o sistema atual numera).
 * - Colunas obrigatórias sem valor padrão recebem 0, '' ou a data de hoje.
 */
function inserirLinhaLegado(string $tabela, array $valores, string $colunaCodigo = 'CODIGO'): int
{
    $colunas = colunasDaTabela($tabela);
    $real    = nomeRealTabela($tabela);
    if ($real === null) {
        throw new RuntimeException("Tabela $tabela não encontrada.");
    }

    $dados = [];
    foreach ($valores as $col => $valor) {
        if (isset($colunas[strtoupper($col)])) {
            $dados[strtoupper($col)] = $valor;
        }
    }

    $codigo = strtoupper($colunaCodigo);
    $auto   = isset($colunas[$codigo]) && $colunas[$codigo]['auto'];

    if (isset($colunas[$codigo]) && !$auto && !array_key_exists($codigo, $dados)) {
        $dados[$codigo] = (int) consultarValor("SELECT COALESCE(MAX($codigo + 0), 0) + 1 FROM $real");
    }

    $numericos = ['int', 'integer', 'bigint', 'smallint', 'tinyint', 'mediumint', 'decimal', 'numeric', 'float', 'double', 'real', 'bit', 'money'];
    foreach ($colunas as $nome => $c) {
        if (array_key_exists($nome, $dados) || $c['nulo'] || $c['padrao'] !== null || $c['auto']) {
            continue;
        }
        if (in_array($c['tipo'], $numericos, true)) {
            $dados[$nome] = 0;
        } elseif ($c['tipo'] === 'date') {
            $dados[$nome] = date('Y-m-d');
        } elseif (in_array($c['tipo'], ['datetime', 'timestamp'], true)) {
            $dados[$nome] = date('Y-m-d H:i:s');
        } elseif ($c['tipo'] === 'time') {
            $dados[$nome] = date('H:i:s');
        } else {
            $dados[$nome] = '';
        }
    }

    $nomes  = array_keys($dados);
    $marcas = [];
    $params = [];
    foreach ($nomes as $i => $nome) {
        $marcas[] = ':v' . $i;
        $params[':v' . $i] = $dados[$nome];
    }

    executar("INSERT INTO $real (" . implode(', ', $nomes) . ') VALUES (' . implode(', ', $marcas) . ')', $params);

    return $auto ? ultimoId() : (int) $dados[$codigo];
}

/** Valida o nome digitado no cadastro rápido. Devolve a mensagem de erro ou ''. */
function validarNomeCadastro(string $nome, int $maximo, string $oque): string
{
    if (mb_strlen($nome) < 2) {
        return "Digite o nome $oque (pelo menos 2 letras).";
    }
    if (mb_strlen($nome) > $maximo) {
        return "O nome $oque pode ter no máximo $maximo caracteres.";
    }
    return '';
}

// =====================================================================
// Categorias (GRUPOS) e subgrupos (SUBGRUPO)
// =====================================================================

/** Grupos (categorias) ativos. Se estiver editando um produto de grupo cancelado, ele também entra na lista. */
function listarGrupos(int $incluirCodigo = 0): array
{
    return consultar(
        "SELECT CODIGO, TRIM(GRUPO) AS GRUPO
           FROM GRUPOS
          WHERE GRUPO IS NOT NULL AND TRIM(GRUPO) <> '' AND (STATUS_GRUPO = 'Ativo' OR CODIGO = :inc)
          ORDER BY TRIM(GRUPO)",
        [':inc' => $incluirCodigo]
    );
}

function listarSubgrupos(): array
{
    return consultar(
        "SELECT CODIGO, TRIM(DESCRICAO) AS DESCRICAO
           FROM SUBGRUPO
          WHERE DESCRICAO IS NOT NULL AND TRIM(DESCRICAO) <> '' AND STATUS_SUBGRUPO = 'Ativo'
          ORDER BY TRIM(DESCRICAO), CODIGO"
    );
}

/**
 * Cria uma categoria nova (tabela GRUPOS). Se já existir uma com o mesmo nome, devolve essa
 * (e reativa se estava cancelada). Devolve ['id', 'nome', 'existia'].
 */
function criarCategoria(string $nome): array
{
    $nome = preg_replace('/\s+/u', ' ', trim($nome));
    $erro = validarNomeCadastro($nome, tamanhoColuna('GRUPOS', 'GRUPO', 60), 'da categoria');
    if ($erro !== '') {
        throw new InvalidArgumentException($erro);
    }

    $existe = consultarUma(
        'SELECT CODIGO, TRIM(GRUPO) AS GRUPO, STATUS_GRUPO FROM GRUPOS WHERE UPPER(TRIM(GRUPO)) = :n ORDER BY CODIGO LIMIT 1',
        [':n' => mb_strtoupper($nome)]
    );
    if ($existe !== null) {
        if ($existe['STATUS_GRUPO'] !== 'Ativo') {
            executar("UPDATE GRUPOS SET STATUS_GRUPO = 'Ativo' WHERE CODIGO = :c", [':c' => (int) $existe['CODIGO']]);
        }
        return ['id' => (int) $existe['CODIGO'], 'nome' => $existe['GRUPO'], 'existia' => true];
    }

    $hoje = date('Y-m-d');
    $id = inserirLinhaLegado('GRUPOS', [
        'GRUPO' => $nome, 'STATUS_GRUPO' => 'Ativo', 'LOJA' => CODIGO_LOJA,
        'DATA_CADASTRO' => $hoje, 'DATA_ALT' => $hoje,
    ]);

    return ['id' => $id, 'nome' => $nome, 'existia' => false];
}

/**
 * Cria um subgrupo novo (tabela SUBGRUPO). Se a tabela tiver ligação com a categoria (COD_GRUPO),
 * grava a categoria escolhida no formulário. Devolve ['id', 'nome', 'existia'].
 */
function criarSubgrupo(string $nome, int $grupo = 0): array
{
    $nome = preg_replace('/\s+/u', ' ', trim($nome));
    $erro = validarNomeCadastro($nome, tamanhoColuna('SUBGRUPO', 'DESCRICAO', 60), 'do subgrupo');
    if ($erro !== '') {
        throw new InvalidArgumentException($erro);
    }

    $existe = consultarUma(
        'SELECT CODIGO, TRIM(DESCRICAO) AS DESCRICAO, STATUS_SUBGRUPO FROM SUBGRUPO WHERE UPPER(TRIM(DESCRICAO)) = :n ORDER BY CODIGO LIMIT 1',
        [':n' => mb_strtoupper($nome)]
    );
    if ($existe !== null) {
        if ($existe['STATUS_SUBGRUPO'] !== 'Ativo') {
            executar("UPDATE SUBGRUPO SET STATUS_SUBGRUPO = 'Ativo' WHERE CODIGO = :c", [':c' => (int) $existe['CODIGO']]);
        }
        return ['id' => (int) $existe['CODIGO'], 'nome' => $existe['DESCRICAO'], 'existia' => true];
    }

    $hoje    = date('Y-m-d');
    $valores = [
        'DESCRICAO' => $nome, 'STATUS_SUBGRUPO' => 'Ativo', 'LOJA' => CODIGO_LOJA,
        'DATA_CADASTRO' => $hoje, 'DATA_ALT' => $hoje,
    ];
    $colGrupo = primeiraColuna('SUBGRUPO', ['COD_GRUPO', 'CODIGO_GRUPO']);
    if ($colGrupo !== null && $grupo > 0) {
        $valores[$colGrupo] = (string) $grupo;
    }

    $id = inserirLinhaLegado('SUBGRUPO', $valores);
    return ['id' => $id, 'nome' => $nome, 'existia' => false];
}

// =====================================================================
// Fornecedores
// =====================================================================

/**
 * Descobre onde ficam os fornecedores:
 * - Se o banco do sistema atual tiver a tabela FORNECEDORES/FORNECEDOR, ela é usada (e, se PRODUTOS tiver
 *   a coluna do fornecedor, como COD_FORNECEDOR, o produto grava ali, igual ao sistema atual).
 * - Se não tiver, a tela cria sozinha FORNECEDOR_LOJA (cadastro) e, quando PRODUTOS não tem a coluna,
 *   PRODUTO_FORNECEDOR (qual fornecedor de cada produto). Nenhuma tabela do sistema atual é alterada.
 *
 * Atenção: no MySQL o CREATE TABLE encerra a transação aberta, então a primeira chamada deve ser fora de emTransacao().
 */
function estruturaFornecedor(): array
{
    static $estrutura = null;
    if ($estrutura !== null) {
        return $estrutura;
    }

    $colunaProduto = ['COD_FORNECEDOR', 'CODIGO_FORNECEDOR', 'COD_FORN', 'ID_FORNECEDOR'];
    $colunasNome   = ['NOME_FANTASIA', 'FANTASIA', 'RAZAO_SOCIAL', 'RAZAO', 'NOME', 'FORNECEDOR', 'DESCRICAO'];

    foreach (['FORNECEDORES', 'FORNECEDOR'] as $tabela) {
        $real = nomeRealTabela($tabela);
        if ($real === null) {
            continue;
        }
        $id    = primeiraColuna($tabela, ['CODIGO', 'CODIGO_FORNECEDOR', 'COD_FORNECEDOR', 'ID']);
        $nomes = array_values(array_filter($colunasNome, fn ($c) => isset(colunasDaTabela($tabela)[$c])));
        if ($id === null || !$nomes) {
            continue;
        }

        return $estrutura = [
            'tabela'      => $real,
            'chave'       => $tabela,
            'id'          => $id,
            'nomes'       => $nomes,
            'status'      => primeiraColuna($tabela, ['STATUS_FORNECEDOR', 'STATUS']),
            'propria'     => false,
            'col_produto' => primeiraColuna('PRODUTOS', $colunaProduto),
        ];
    }

    // O banco não tem cadastro de fornecedores: usa as tabelas próprias (criadas sozinhas)
    garantirTabelasFornecedor();

    return $estrutura = [
        'tabela'      => nomeRealTabela('FORNECEDOR_LOJA') ?? 'FORNECEDOR_LOJA',
        'chave'       => 'FORNECEDOR_LOJA',
        'id'          => 'CODIGO',
        'nomes'       => ['NOME'],
        'status'      => 'STATUS_FORNECEDOR',
        'propria'     => true,
        'col_produto' => null,
    ];
}

/** Cria as tabelas próprias de fornecedor (só usadas se o banco do sistema atual não tiver). */
function garantirTabelasFornecedor(): void
{
    $sqlite = bancoEhSqlite();
    $id     = $sqlite ? 'CODIGO INTEGER PRIMARY KEY AUTOINCREMENT' : 'CODIGO INT NOT NULL AUTO_INCREMENT PRIMARY KEY';
    $fim    = $sqlite ? '' : ' ENGINE=InnoDB DEFAULT CHARSET=utf8mb4';

    conectar()->exec(
        "CREATE TABLE IF NOT EXISTS FORNECEDOR_LOJA (
            $id,
            NOME              VARCHAR(100) NOT NULL,
            STATUS_FORNECEDOR VARCHAR(20)  NOT NULL,
            DATA_CADASTRO     DATE         NOT NULL
        )$fim"
    );
    colunasDaTabela('FORNECEDOR_LOJA', true);
    garantirTabelaProdutoFornecedor();
}

/** Ligação produto → fornecedor (usada quando PRODUTOS não tem coluna de fornecedor ligada a um cadastro). */
function garantirTabelaProdutoFornecedor(): void
{
    static $pronto = false;
    if ($pronto) {
        return;
    }

    $fim = bancoEhSqlite() ? '' : ' ENGINE=InnoDB DEFAULT CHARSET=utf8mb4';
    conectar()->exec(
        "CREATE TABLE IF NOT EXISTS PRODUTO_FORNECEDOR (
            CODIGO_PRODUTO    INT NOT NULL PRIMARY KEY,
            CODIGO_FORNECEDOR INT NOT NULL
        )$fim"
    );
    if (!bancoEhSqlite()) {
        try {
            conectar()->exec('CREATE INDEX idx_prodforn_forn ON PRODUTO_FORNECEDOR (CODIGO_FORNECEDOR)');
        } catch (PDOException $e) {
            // índice já existe
        }
    }
    $pronto = true;
}

/** Onde fica o código do fornecedor do produto, em SQL ("p" = PRODUTOS). */
function colunaFornecedorDoProduto(): string
{
    $s = estruturaFornecedor();
    if ($s['col_produto'] === null) {
        garantirTabelaProdutoFornecedor();
        return 'pf.CODIGO_FORNECEDOR';
    }
    return 'p.' . $s['col_produto'];
}

/** Expressão SQL com o nome do fornecedor (usa o nome fantasia e, se vazio, a razão social). */
function sqlNomeFornecedor(string $alias = 'f'): string
{
    $partes   = array_map(fn ($c) => "NULLIF(TRIM($alias.$c), '')", estruturaFornecedor()['nomes']);
    $partes[] = "''";
    return 'COALESCE(' . implode(', ', $partes) . ')';
}

/** JOINs que trazem o fornecedor de cada produto ("p" = PRODUTOS, "f" = fornecedor). */
function sqlJoinFornecedor(): string
{
    $s    = estruturaFornecedor();
    $col  = colunaFornecedorDoProduto();
    $join = $s['col_produto'] === null ? ' LEFT JOIN PRODUTO_FORNECEDOR pf ON pf.CODIGO_PRODUTO = p.CODIGO' : '';
    return $join . " LEFT JOIN {$s['tabela']} f ON f.{$s['id']} = $col";
}

/** Fornecedores ativos para os selects. $incluirCodigo entra mesmo se estiver cancelado (edição). */
function listarFornecedores(int $incluirCodigo = 0): array
{
    $s     = estruturaFornecedor();
    $nome  = sqlNomeFornecedor('f');
    $ativo = $s['status']
        ? "(f.{$s['status']} IS NULL OR f.{$s['status']} <> 'Cancelado' OR f.{$s['id']} = :inc)"
        : '(1 = 1 OR f.' . $s['id'] . ' = :inc)';

    return consultar(
        "SELECT f.{$s['id']} AS CODIGO, $nome AS NOME
           FROM {$s['tabela']} f
          WHERE $ativo AND $nome <> ''
          ORDER BY $nome",
        [':inc' => $incluirCodigo]
    );
}

function fornecedorExiste(int $codigo): bool
{
    $s = estruturaFornecedor();
    return (int) consultarValor("SELECT COUNT(*) FROM {$s['tabela']} WHERE {$s['id']} = :c", [':c' => $codigo]) > 0;
}

/** Cria um fornecedor (ou devolve o que já tem o mesmo nome). Devolve ['id', 'nome', 'existia']. */
function criarFornecedor(string $nome): array
{
    $s    = estruturaFornecedor();
    $nome = preg_replace('/\s+/u', ' ', trim($nome));

    $maximo = 100;
    foreach ($s['nomes'] as $c) {
        $maximo = tamanhoColuna($s['chave'], $c, $maximo);
    }
    $erro = validarNomeCadastro($nome, $maximo, 'do fornecedor');
    if ($erro !== '') {
        throw new InvalidArgumentException($erro);
    }

    $expr   = sqlNomeFornecedor('f');
    $status = $s['status'] ? ", f.{$s['status']} AS STATUS" : ", 'Ativo' AS STATUS";
    $existe = consultarUma(
        "SELECT f.{$s['id']} AS CODIGO, $expr AS NOME $status FROM {$s['tabela']} f WHERE UPPER($expr) = :n ORDER BY f.{$s['id']} LIMIT 1",
        [':n' => mb_strtoupper($nome)]
    );
    if ($existe !== null) {
        if ($s['status'] && $existe['STATUS'] === 'Cancelado') {
            executar("UPDATE {$s['tabela']} SET {$s['status']} = 'Ativo' WHERE {$s['id']} = :c", [':c' => (int) $existe['CODIGO']]);
        }
        return ['id' => (int) $existe['CODIGO'], 'nome' => $existe['NOME'], 'existia' => true];
    }

    $hoje    = date('Y-m-d');
    $valores = ['LOJA' => CODIGO_LOJA, 'DATA_CADASTRO' => $hoje, 'DATA_ALT' => $hoje];
    foreach ($s['nomes'] as $c) {
        $valores[$c] = $nome;
    }
    if ($s['status']) {
        $valores[$s['status']] = 'Ativo';
    }

    $id = inserirLinhaLegado($s['chave'], $valores, $s['id']);
    return ['id' => $id, 'nome' => $nome, 'existia' => false];
}

/** Código do fornecedor gravado no produto ('' = nenhum). $linha = linha de PRODUTOS, se já lida. */
function fornecedorDoProduto(int $produto, ?array $linha = null): string
{
    $s = estruturaFornecedor();

    if ($s['col_produto'] !== null) {
        $linha = $linha ?? buscarProduto($produto);
        foreach ((array) $linha as $col => $valor) {
            if (strtoupper((string) $col) === $s['col_produto']) {
                $v = trim((string) $valor);
                return ($v === '' || !is_numeric($v) || (int) $v === 0) ? '' : (string) (int) $v;
            }
        }
        return '';
    }

    garantirTabelaProdutoFornecedor();
    $v = consultarValor('SELECT CODIGO_FORNECEDOR FROM PRODUTO_FORNECEDOR WHERE CODIGO_PRODUTO = :p', [':p' => $produto]);
    return $v === null ? '' : (string) (int) $v;
}

/** Grava o fornecedor do produto ('' = nenhum). Pode ser chamada dentro de emTransacao(). */
function gravarFornecedorDoProduto(int $produto, string $fornecedor): void
{
    $s = estruturaFornecedor();

    if ($s['col_produto'] !== null) {
        $col   = colunasDaTabela('PRODUTOS')[$s['col_produto']];
        $vazio = $col['nulo'] ? null : (in_array($col['tipo'], ['int', 'integer', 'bigint', 'smallint', 'tinyint', 'mediumint', 'decimal', 'numeric'], true) ? 0 : '');
        executar(
            "UPDATE PRODUTOS SET {$s['col_produto']} = :f WHERE CODIGO = :id",
            [':f' => $fornecedor === '' ? $vazio : $fornecedor, ':id' => $produto]
        );
        return;
    }

    executar('DELETE FROM PRODUTO_FORNECEDOR WHERE CODIGO_PRODUTO = :p', [':p' => $produto]);
    if ($fornecedor !== '') {
        executar(
            'INSERT INTO PRODUTO_FORNECEDOR (CODIGO_PRODUTO, CODIGO_FORNECEDOR) VALUES (:p, :f)',
            [':p' => $produto, ':f' => (int) $fornecedor]
        );
    }
}

// =====================================================================
// Produtos
// =====================================================================

/** Lê e normaliza os filtros da tela de produtos (vindos da URL). */
function filtrosProdutos(array $get): array
{
    $status = $get['status'] ?? 'Ativo';
    if (!in_array($status, ['Ativo', 'Cancelado', ''], true)) {
        $status = 'Ativo';
    }

    $estoque = $get['estoque'] ?? '';
    if (!in_array($estoque, ['', 'com', 'sem'], true)) {
        $estoque = '';
    }

    return [
        'busca'      => trim((string) ($get['busca'] ?? '')),
        'grupo'      => (int) ($get['grupo'] ?? 0),
        'fornecedor' => (int) ($get['fornecedor'] ?? 0),
        'status'     => $status,
        'estoque'    => $estoque,
    ];
}

/**
 * Monta o WHERE a partir dos filtros e preenche $params.
 * A busca por texto também procura no nome do fornecedor (a consulta precisa de sqlJoinFornecedor()).
 * Filtros que não vierem no array (ex.: 'fornecedor') são ignorados.
 */
function whereProdutos(array $f, array &$params): string
{
    $where = ['1 = 1'];
    $busca = (string) ($f['busca'] ?? '');

    if ($busca !== '') {
        $like = '%' . $busca . '%';
        $cond = ['p.DESCRICAO LIKE :busca_desc', 'p.REFERENCIA LIKE :busca_ref', sqlNomeFornecedor('f') . ' LIKE :busca_forn'];
        $params[':busca_desc'] = $like;
        $params[':busca_ref']  = $like;
        $params[':busca_forn'] = $like;

        if (ctype_digit($busca)) {
            $cond[] = 'p.CODIGO = :busca_cod';
            $cond[] = 'p.COD_BARRAS_FORN = :busca_cb';
            $params[':busca_cod'] = (int) $busca;
            $params[':busca_cb']  = $busca;
        }
        $where[] = '(' . implode(' OR ', $cond) . ')';
    }

    if ((int) ($f['grupo'] ?? 0) > 0) {
        $where[] = 'p.COD_GRUPO = :grupo';
        $params[':grupo'] = (string) (int) $f['grupo'];
    }

    if ((int) ($f['fornecedor'] ?? 0) > 0) {
        $where[] = colunaFornecedorDoProduto() . ' = :fornecedor';
        $params[':fornecedor'] = (string) (int) $f['fornecedor'];
    }

    $status = (string) ($f['status'] ?? '');
    if ($status !== '') {
        $where[] = 'p.STATUS_PRODUTO = :status';
        $params[':status'] = $status;
    }

    $estoque = $f['estoque'] ?? '';
    if ($estoque === 'com') {
        $where[] = 'p.QUANT > 0';
    } elseif ($estoque === 'sem') {
        $where[] = '(p.QUANT <= 0 OR p.QUANT IS NULL)';
    }

    return implode(' AND ', $where);
}

function contarProdutos(array $f): int
{
    $params = [];
    $where  = whereProdutos($f, $params);
    $join   = sqlJoinFornecedor();
    return (int) consultarValor("SELECT COUNT(*) FROM PRODUTOS p $join WHERE $where", $params);
}

function listarProdutos(array $f, int $limite, int $offset): array
{
    $params = [];
    $where  = whereProdutos($f, $params);
    $join   = sqlJoinFornecedor();
    $nome   = sqlNomeFornecedor('f');
    $params[':limite']  = $limite;
    $params[':posicao'] = $offset;

    return consultar(
        "SELECT p.CODIGO, p.DESCRICAO, p.REFERENCIA, p.PRE_CUS, p.PRE_VEN, p.QUANT, p.TAMANHO,
                p.STATUS_PRODUTO, p.COD_BARRAS_FORN, TRIM(g.GRUPO) AS GRUPO, $nome AS FORNECEDOR
           FROM PRODUTOS p
           LEFT JOIN GRUPOS g ON g.CODIGO = p.COD_GRUPO
           $join
          WHERE $where
          ORDER BY p.CODIGO DESC
          LIMIT :limite OFFSET :posicao",
        $params
    );
}

function buscarProduto(int $id): ?array
{
    return consultarUma('SELECT * FROM PRODUTOS WHERE CODIGO = :id', [':id' => $id]);
}

/** Estrutura de um produto em branco (formulário de cadastro). */
function produtoVazio(): array
{
    return [
        'id' => 0, 'nome' => '', 'referencia' => '', 'grupo' => '', 'subgrupo' => '', 'fornecedor' => '', 'tamanho' => '',
        'preco_custo' => '', 'preco_venda' => '', 'estoque' => '0', 'status' => 'Ativo',
    ];
}

/** Converte a linha do banco para os campos do formulário. */
function produtoParaFormulario(array $linha): array
{
    return [
        'id'          => (int) $linha['CODIGO'],
        'nome'        => trim((string) $linha['DESCRICAO']),
        'referencia'  => trim((string) $linha['REFERENCIA']),
        'grupo'       => trim((string) $linha['COD_GRUPO']),
        'subgrupo'    => trim((string) $linha['COD_SUBGRUPO']),
        'fornecedor'  => fornecedorDoProduto((int) $linha['CODIGO'], $linha),
        'tamanho'     => trim((string) $linha['TAMANHO']),
        'preco_custo' => $linha['PRE_CUS'] === null ? '' : paraBanco((float) $linha['PRE_CUS']),
        'preco_venda' => $linha['PRE_VEN'] === null ? '' : paraBanco((float) $linha['PRE_VEN']),
        'estoque'     => $linha['QUANT'] === null ? '0' : formatarQuantidade($linha['QUANT']),
        'status'      => (string) ($linha['STATUS_PRODUTO'] ?? 'Ativo'),
    ];
}

/** Lê os campos enviados pelo formulário. */
function montarProduto(array $post, int $id = 0): array
{
    return [
        'id'          => $id,
        'nome'        => trim((string) ($post['nome'] ?? '')),
        'referencia'  => trim((string) ($post['referencia'] ?? '')),
        'grupo'       => trim((string) ($post['grupo'] ?? '')),
        'subgrupo'    => trim((string) ($post['subgrupo'] ?? '')),
        'fornecedor'  => trim((string) ($post['fornecedor'] ?? '')),
        'tamanho'     => trim((string) ($post['tamanho'] ?? '')),
        'preco_custo' => trim((string) ($post['preco_custo'] ?? '')),
        'preco_venda' => trim((string) ($post['preco_venda'] ?? '')),
        'estoque'     => trim((string) ($post['estoque'] ?? '0')),
        'status'      => (string) ($post['status'] ?? 'Ativo'),
    ];
}

/** Valida o produto. Retorna [campo => mensagem]; vazio = tudo certo. */
function validarProduto(array $d): array
{
    $erros = [];

    if (mb_strlen($d['nome']) < 2 || mb_strlen($d['nome']) > 200) {
        $erros['nome'] = 'Informe o nome do produto (até 200 caracteres).';
    }
    if (mb_strlen($d['referencia']) > 50) {
        $erros['referencia'] = 'A referência pode ter no máximo 50 caracteres.';
    }
    if ($d['grupo'] === '' || !ctype_digit($d['grupo']) || strlen($d['grupo']) > 4) {
        $erros['grupo'] = 'Escolha a categoria.';
    } elseif (consultarValor('SELECT COUNT(*) FROM GRUPOS WHERE CODIGO = :g', [':g' => (int) $d['grupo']]) < 1) {
        $erros['grupo'] = 'Categoria não encontrada.';
    }
    if ($d['subgrupo'] !== '' && (!ctype_digit($d['subgrupo']) || strlen($d['subgrupo']) > 7)) {
        $erros['subgrupo'] = 'Subgrupo inválido.';
    }
    $fornecedor = (string) ($d['fornecedor'] ?? '');
    if ($fornecedor !== '' && (!ctype_digit($fornecedor) || !fornecedorExiste((int) $fornecedor))) {
        $erros['fornecedor'] = 'Fornecedor não encontrado.';
    }
    if ($d['tamanho'] === '' || mb_strlen($d['tamanho']) > 5) {
        $erros['tamanho'] = 'Informe o tamanho (até 5 caracteres, ex.: P, M, G, 38, PLUS).';
    }
    if ($d['preco_custo'] === '' || !is_numeric($d['preco_custo']) || (float) $d['preco_custo'] < 0 || (float) $d['preco_custo'] > 99999999) {
        $erros['preco_custo'] = 'Informe um preço de custo válido (zero ou maior).';
    }
    if ($d['preco_venda'] === '' || !is_numeric($d['preco_venda']) || (float) $d['preco_venda'] <= 0 || (float) $d['preco_venda'] > 99999999) {
        $erros['preco_venda'] = 'Informe um preço de venda maior que zero.';
    }
    if (!ctype_digit($d['estoque']) || (int) $d['estoque'] > 9999999) {
        $erros['estoque'] = 'Informe a quantidade em estoque (número inteiro, zero ou maior).';
    }
    if (!in_array($d['status'], ['Ativo', 'Cancelado'], true)) {
        $erros['status'] = 'Situação inválida.';
    }

    return $erros;
}

/** Grava (insere ou atualiza) o produto e devolve o código. */
function salvarProduto(array $d): int
{
    $fornecedor = (string) ($d['fornecedor'] ?? '');
    colunaFornecedorDoProduto();   // cria as tabelas de fornecedor (se precisar) ANTES da transação

    return emTransacao(function () use ($d, $fornecedor) {
        $custo = (float) $d['preco_custo'];
        $venda = (float) $d['preco_venda'];
        $lucro = $custo > 0 ? round($venda / $custo, 2) : 0.0;   // o sistema atual grava a razão venda/custo
        $sub   = $d['subgrupo'] === '' ? null : $d['subgrupo'];
        $hoje  = date('Y-m-d');

        if ($d['id'] > 0) {
            executar(
                'UPDATE PRODUTOS
                    SET DESCRICAO = :nome, REFERENCIA = :ref, COD_GRUPO = :grupo, COD_SUBGRUPO = :sub,
                        TAMANHO = :tam, PRE_CUS = :custo, PRE_VEN = :venda, PORC_LUCRO = :lucro,
                        QUANT = :qtd, STATUS_PRODUTO = :status, DATA_ALT = :alt
                  WHERE CODIGO = :id',
                [
                    ':nome' => $d['nome'], ':ref' => $d['referencia'] === '' ? null : $d['referencia'],
                    ':grupo' => $d['grupo'], ':sub' => $sub, ':tam' => $d['tamanho'],
                    ':custo' => paraBanco($custo), ':venda' => paraBanco($venda), ':lucro' => paraBanco($lucro, 3),
                    ':qtd' => paraBanco((float) $d['estoque'], 3), ':status' => $d['status'], ':alt' => $hoje,
                    ':id' => $d['id'],
                ]
            );
            gravarFornecedorDoProduto($d['id'], $fornecedor);
            return $d['id'];
        }

        executar(
            "INSERT INTO PRODUTOS
                (LOJA, DESCRICAO, PRE_CUS, PRE_VEN, PORC_LUCRO, QUANT, COD_GRUPO, COD_SUBGRUPO, TAMANHO,
                 DATA_CADASTRO, DATA_ALT, STATUS_PRODUTO, UNIDADE_MEDIDA, COD_PUBLICO_ALVO,
                 ICMS, SUBST, ISENTO_ICMS, NAO_TRIB, PORC_DESC, VALOR_DESC, VALOR_PROMOCAO,
                 ECOMMERCE_FLAG_EXCECAO, ECOMMERCE_FLAG_HABILITADO, ECOMMERCE_FLAG_DESTAQUE)
             VALUES
                (:loja, :nome, :custo, :venda, :lucro, :qtd, :grupo, :sub, :tam,
                 :cad, :alt, :status, 'UN', '1',
                 0, '0', '0', 'N', 0, 0, 0,
                 0, 0, 0)",
            [
                ':loja' => CODIGO_LOJA, ':nome' => $d['nome'], ':custo' => paraBanco($custo), ':venda' => paraBanco($venda),
                ':lucro' => paraBanco($lucro, 3), ':qtd' => paraBanco((float) $d['estoque'], 3),
                ':grupo' => $d['grupo'], ':sub' => $sub, ':tam' => $d['tamanho'],
                ':cad' => $hoje, ':alt' => $hoje, ':status' => $d['status'],
            ]
        );
        $novoId = ultimoId();

        // Padrão do sistema atual: código de barras e referência = código do produto com 6 dígitos (ex.: 026090)
        $codigo = str_pad((string) $novoId, 6, '0', STR_PAD_LEFT);
        executar(
            'UPDATE PRODUTOS SET COD_BARRAS_FORN = :cb, REFERENCIA = :ref WHERE CODIGO = :id',
            [':cb' => $codigo, ':ref' => $d['referencia'] !== '' ? $d['referencia'] : $codigo, ':id' => $novoId]
        );
        gravarFornecedorDoProduto($novoId, $fornecedor);

        return $novoId;
    });
}

/** "Exclui" o produto: apenas marca como Cancelado (o histórico de vendas é preservado). */
function inativarProduto(int $id): bool
{
    return executar(
        "UPDATE PRODUTOS SET STATUS_PRODUTO = 'Cancelado', DATA_ALT = :alt WHERE CODIGO = :id AND STATUS_PRODUTO <> 'Cancelado'",
        [':alt' => date('Y-m-d'), ':id' => $id]
    ) > 0;
}

/** Selo colorido conforme a quantidade em estoque. */
function badgeEstoque($quantidade): string
{
    $q = (float) $quantidade;

    if ($q <= 0) {
        return '<span class="badge">Zerado</span>';
    }
    if ($q <= LIMITE_ESTOQUE_BAIXO) {
        return '<span class="badge badge-aviso">' . e(formatarQuantidade($q)) . ' un. (baixo)</span>';
    }
    return '<span class="badge badge-sucesso">' . e(formatarQuantidade($q)) . ' un.</span>';
}

// ---------- Números do painel ----------

function resumoEstoque(): array
{
    $r = consultarUma(
        "SELECT COUNT(*) AS ATIVOS,
                COALESCE(SUM(CASE WHEN QUANT > 0 THEN 1 ELSE 0 END), 0) AS COM_ESTOQUE,
                COALESCE(SUM(CASE WHEN QUANT > 0 THEN QUANT ELSE 0 END), 0) AS PECAS,
                COALESCE(SUM(CASE WHEN QUANT > 0 THEN QUANT * PRE_VEN ELSE 0 END), 0) AS VALOR_VENDA
           FROM PRODUTOS
          WHERE STATUS_PRODUTO = 'Ativo'"
    );
    return $r ?? ['ATIVOS' => 0, 'COM_ESTOQUE' => 0, 'PECAS' => 0, 'VALOR_VENDA' => 0];
}

function produtosEstoqueBaixo(int $limite = 5): array
{
    return consultar(
        "SELECT CODIGO, DESCRICAO, TAMANHO, QUANT
           FROM PRODUTOS
          WHERE STATUS_PRODUTO = 'Ativo' AND QUANT > 0 AND QUANT <= :lim
          ORDER BY QUANT, CODIGO DESC
          LIMIT :n",
        [':lim' => LIMITE_ESTOQUE_BAIXO, ':n' => $limite]
    );
}
