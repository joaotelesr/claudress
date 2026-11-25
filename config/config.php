<?php
/**
 * config.php
 * Configurações gerais do sistema. Deve ser incluído no início de toda página.
 */

// A sessão guarda os dados de demonstração enquanto o navegador estiver aberto
if (session_status() === PHP_SESSION_NONE) {
    session_start();
}

date_default_timezone_set('America/Sao_Paulo');

// Constantes gerais
define('NOME_LOJA', 'Clau Dress');
define('RAIZ_PROJETO', dirname(__DIR__));

// Carregamento dos arquivos base (a ordem importa)
require_once RAIZ_PROJETO . '/config/dados_demo.php';
require_once RAIZ_PROJETO . '/funcoes/helpers.php';
require_once RAIZ_PROJETO . '/funcoes/repositorio.php';

iniciarDadosDemo();

// Atalho para restaurar os dados de demonstração: qualquer página com ?resetar_demo=1
if (isset($_GET['resetar_demo'])) {
    resetarDadosDemo();
    definirMensagem('sucesso', 'Dados de demonstração restaurados.');
    redirecionar(strtok($_SERVER['REQUEST_URI'], '?'));
}