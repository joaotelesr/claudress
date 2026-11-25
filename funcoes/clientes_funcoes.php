<?php
/**
 * clientes_funcoes.php
 * Regras do módulo de clientes: listagem, busca, validação e gravação.
 */

/** Lista clientes, com busca opcional por nome ou telefone/WhatsApp. */
function listarClientes(string $busca = ''): array
{
    $clientes = repoListar('clientes');

    if ($busca === '') {
        return $clientes;
    }

    $buscaDigitos = somenteDigitos($busca);

    return array_values(array_filter($clientes, function ($c) use ($busca, $buscaDigitos) {
        if (mb_stripos($c['nome'], $busca) !== false) {
            return true;
        }
        if ($buscaDigitos !== '') {
            return strpos(somenteDigitos($c['telefone']), $buscaDigitos) !== false
                || strpos(somenteDigitos($c['whatsapp']), $buscaDigitos) !== false;
        }
        return false;
    }));
}

function buscarCliente(int $id): ?array
{
    return repoBuscar('clientes', $id);
}

function clienteVazio(): array
{
    return ['id' => 0, 'nome' => '', 'cpf' => '', 'telefone' => '', 'whatsapp' => '', 'email' => '', 'endereco' => ''];
}

function montarCliente(array $post, ?array $existente = null): array
{
    return [
        'id'       => $existente['id'] ?? 0,
        'nome'     => trim($post['nome'] ?? ''),
        'cpf'      => trim($post['cpf'] ?? ''),
        'telefone' => trim($post['telefone'] ?? ''),
        'whatsapp' => trim($post['whatsapp'] ?? ''),
        'email'    => trim($post['email'] ?? ''),
        'endereco' => trim($post['endereco'] ?? ''),
    ];
}

/** Valida o cliente. Retorna [campo => mensagem]; vazio = tudo certo. */
function validarCliente(array $dados): array
{
    $erros = [];

    if (mb_strlen($dados['nome']) < 3) {
        $erros['nome'] = 'Informe o nome completo.';
    }

    if ($dados['telefone'] === '' && $dados['whatsapp'] === '') {
        $erros['telefone'] = 'Informe ao menos um telefone ou WhatsApp.';
    }

    foreach (['telefone', 'whatsapp'] as $campo) {
        if ($dados[$campo] !== '' && !in_array(strlen(somenteDigitos($dados[$campo])), [10, 11], true)) {
            $erros[$campo] = 'Número inválido. Use DDD + número.';
        }
    }

    if ($dados['cpf'] !== '' && !cpfValido($dados['cpf'])) {
        $erros['cpf'] = 'CPF inválido.';
    }

    if ($dados['email'] !== '' && !filter_var($dados['email'], FILTER_VALIDATE_EMAIL)) {
        $erros['email'] = 'E-mail inválido.';
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

function salvarCliente(array $dados): array
{
    return repoSalvar('clientes', $dados);
}

function excluirCliente(int $id): bool
{
    return repoExcluir('clientes', $id);
}