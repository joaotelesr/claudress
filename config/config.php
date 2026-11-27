<?php
/**
 * config.php
 * Configurações gerais do sistema. Deve ser incluído no início de toda página.
 */

// A sessão guarda o login e as mensagens de aviso (sucesso/erro) entre uma página e outra
if (session_status() === PHP_SESSION_NONE) {
    session_name('CLAUDRESS');
    // Com HTTPS (cadeado no navegador) o cookie do login só viaja criptografado
    $usandoHttps = (!empty($_SERVER['HTTPS']) && $_SERVER['HTTPS'] !== 'off')
        || (($_SERVER['HTTP_X_FORWARDED_PROTO'] ?? '') === 'https');
    session_set_cookie_params(['path' => '/', 'httponly' => true, 'samesite' => 'Lax', 'secure' => $usandoHttps]);
    ini_set('session.use_strict_mode', '1');
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
require_once RAIZ_PROJETO . '/config/autenticacao.php';

// Toda página do admin exige login. Só a tela de login (que define PAGINA_PUBLICA) fica aberta.
// Na linha de comando (testes automatizados) não há navegador nem sessão, então não pede login.
if (!defined('PAGINA_PUBLICA') && PHP_SAPI !== 'cli') {
    exigirLogin();
}