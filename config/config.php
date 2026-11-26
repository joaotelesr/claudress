<?php
/**
 * config.php
 * Configurações gerais do sistema. Deve ser incluído no início de toda página.
 */

// A sessão guarda apenas as mensagens de aviso (sucesso/erro) entre uma página e outra
if (session_status() === PHP_SESSION_NONE) {
    session_start();
}

date_default_timezone_set('America/Sao_Paulo');

define('NOME_LOJA', 'Clau Dress');
define('RAIZ_PROJETO', dirname(__DIR__));

// Convenções do banco da loja (os mesmos valores que o sistema atual grava nas vendas e nos cadastros)
const CODIGO_LOJA           = 1;
const CODIGO_CAIXA_PADRAO   = 1;
const CODIGO_USUARIO_PADRAO = 1;

// Quantidade de linhas por página nas listagens
const ITENS_POR_PAGINA = 20;

require_once RAIZ_PROJETO . '/config/conexao.php';
require_once RAIZ_PROJETO . '/funcoes/helpers.php';
require_once RAIZ_PROJETO . '/funcoes/formatadores.php';
require_once RAIZ_PROJETO . '/funcoes/repositorio.php';
