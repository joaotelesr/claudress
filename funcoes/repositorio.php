<?php
/**
 * repositorio.php
 * CAMADA DE DADOS: único lugar que sabe "onde" os dados ficam.
 *
 * Hoje: os dados ficam em $_SESSION (demonstração).
 * Futuro: trocar o corpo destas funções por consultas ao banco de dados.
 * As demais páginas e funções só usam as funções repo...() e não precisam mudar.
 */

const CHAVE_DADOS = 'clau_dress_demo';

/** Carrega os dados fictícios na sessão, caso ainda não existam. */
function iniciarDadosDemo(): void
{
    if (!isset($_SESSION[CHAVE_DADOS])) {
        $_SESSION[CHAVE_DADOS] = obterDadosDemo();
    }
}

/** Descarta tudo o que foi alterado e recarrega os dados fictícios originais. */
function resetarDadosDemo(): void
{
    unset($_SESSION[CHAVE_DADOS]);
    iniciarDadosDemo();
}

/** Lista todos os registros de uma coleção ('produtos', 'clientes', 'vendas', 'formas_pagamento'). */
function repoListar(string $colecao): array
{
    return array_values($_SESSION[CHAVE_DADOS][$colecao] ?? []);
}

/** Busca um registro pelo ID. Retorna null se não existir. */
function repoBuscar(string $colecao, int $id): ?array
{
    foreach (repoListar($colecao) as $registro) {
        if ((int) $registro['id'] === $id) {
            return $registro;
        }
    }
    return null;
}

/** Calcula o próximo ID disponível (o banco real fará isso com AUTO_INCREMENT). */
function repoProximoId(string $colecao): int
{
    $maior = 0;
    foreach (repoListar($colecao) as $registro) {
        $maior = max($maior, (int) $registro['id']);
    }
    return $maior + 1;
}

/**
 * Salva um registro: se tiver 'id', atualiza; se não tiver, insere um novo.
 * Retorna o registro salvo (já com o ID).
 */
function repoSalvar(string $colecao, array $registro): array
{
    $registros = repoListar($colecao);

    // Atualização
    if (!empty($registro['id'])) {
        foreach ($registros as $indice => $existente) {
            if ((int) $existente['id'] === (int) $registro['id']) {
                $registros[$indice] = $registro;
                $_SESSION[CHAVE_DADOS][$colecao] = $registros;
                return $registro;
            }
        }
    }

    // Inserção
    $registro['id'] = repoProximoId($colecao);
    $registros[] = $registro;
    $_SESSION[CHAVE_DADOS][$colecao] = $registros;

    return $registro;
}

/** Exclui um registro pelo ID. Retorna true se algo foi removido. */
function repoExcluir(string $colecao, int $id): bool
{
    $registros = repoListar($colecao);
    $restantes = array_filter($registros, fn($r) => (int) $r['id'] !== $id);

    $_SESSION[CHAVE_DADOS][$colecao] = array_values($restantes);

    return count($restantes) < count($registros);
}