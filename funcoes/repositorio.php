<?php
/**
 * repositorio.php
 * CAMADA DE ACESSO AO BANCO: funções simples em cima do PDO.
 * Todas as consultas usam parâmetros (:nome ou ?) para evitar SQL Injection:
 * nunca coloque variáveis do usuário direto dentro do texto do SQL.
 *
 * Exemplo:
 *   $linhas = consultar('SELECT * FROM PRODUTOS WHERE CODIGO = :id', [':id' => 10]);
 */

/** Prepara e executa uma consulta, ligando cada parâmetro com o tipo certo. */
function prepararConsulta(string $sql, array $params = []): PDOStatement
{
    $stmt = conectar()->prepare($sql);

    foreach ($params as $chave => $valor) {
        if ($valor === null) {
            $tipo = PDO::PARAM_NULL;
        } elseif (is_bool($valor)) {
            $tipo  = PDO::PARAM_INT;
            $valor = $valor ? 1 : 0;
        } elseif (is_int($valor)) {
            $tipo = PDO::PARAM_INT;
        } else {
            $tipo  = PDO::PARAM_STR;
            $valor = (string) $valor;
        }
        $stmt->bindValue(is_int($chave) ? $chave + 1 : $chave, $valor, $tipo);
    }

    $stmt->execute();
    return $stmt;
}

/** Devolve todas as linhas do resultado. */
function consultar(string $sql, array $params = []): array
{
    return prepararConsulta($sql, $params)->fetchAll();
}

/** Devolve só a primeira linha (ou null se não houver). */
function consultarUma(string $sql, array $params = []): ?array
{
    $linha = prepararConsulta($sql, $params)->fetch();
    return $linha === false ? null : $linha;
}

/** Devolve o valor da primeira coluna da primeira linha (útil para COUNT, SUM...). */
function consultarValor(string $sql, array $params = [])
{
    $valor = prepararConsulta($sql, $params)->fetchColumn();
    return $valor === false ? null : $valor;
}

/** Executa INSERT, UPDATE ou DELETE. Devolve quantas linhas foram afetadas. */
function executar(string $sql, array $params = []): int
{
    return prepararConsulta($sql, $params)->rowCount();
}

/** ID gerado pelo último INSERT (coluna AUTO_INCREMENT). */
function ultimoId(): int
{
    return (int) conectar()->lastInsertId();
}

/**
 * Executa várias operações como uma só: ou tudo é gravado, ou nada é.
 * Se acontecer qualquer erro dentro da função, o banco volta ao estado anterior.
 */
function emTransacao(callable $acao)
{
    $pdo = conectar();
    $pdo->beginTransaction();

    try {
        $resultado = $acao();
        $pdo->commit();
        return $resultado;
    } catch (Throwable $erro) {
        if ($pdo->inTransaction()) {
            $pdo->rollBack();
        }
        throw $erro;
    }
}

/** Calcula os dados de paginação (página atual, total de páginas e posição inicial). */
function paginar(int $total, int $porPagina, int $pagina): array
{
    $totalPaginas = max(1, (int) ceil($total / $porPagina));
    $pagina       = min(max(1, $pagina), $totalPaginas);

    return [
        'total'         => $total,
        'por_pagina'    => $porPagina,
        'pagina'        => $pagina,
        'total_paginas' => $totalPaginas,
        'offset'        => ($pagina - 1) * $porPagina,
    ];
}

/** Valor decimal em texto com ponto ("119.90"), pronto para gravar em colunas DECIMAL. */
function paraBanco(float $valor, int $casas = 2): string
{
    return number_format($valor, $casas, '.', '');
}
