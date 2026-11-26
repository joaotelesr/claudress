<?php
/**
 * conexao.php - Conexão com o banco de dados MySQL usando PDO.
 *
 * Ajuste as constantes abaixo para o seu MySQL.
 * (No XAMPP o padrão é usuário "root" e senha vazia.)
 * Todas as páginas usam a função conectar(), que abre a conexão uma única vez por requisição.
 */

const DB_HOST    = '127.0.0.1';
const DB_PORTA   = 3306;
const DB_NOME    = 'FaloPro';
const DB_USUARIO = 'root';
const DB_SENHA   = '';

// Mostra o texto técnico do erro na tela. Troque para false quando o sistema for para a internet.
const DB_MOSTRAR_ERROS = true;

/** Devolve a conexão PDO (cria na primeira chamada e reaproveita nas seguintes). */
function conectar(): PDO
{
    static $pdo = null;

    if ($pdo instanceof PDO) {
        return $pdo;
    }

    try {
        // Uso interno para testes automatizados: se a variável de ambiente existir, usa um arquivo SQLite.
        $arquivoTeste = getenv('CLAUDRESS_SQLITE');

        if ($arquivoTeste !== false && $arquivoTeste !== '') {
            $pdo = new PDO('sqlite:' . $arquivoTeste, null, null, [
                PDO::ATTR_ERRMODE            => PDO::ERRMODE_EXCEPTION,
                PDO::ATTR_DEFAULT_FETCH_MODE => PDO::FETCH_ASSOC,
            ]);
            return $pdo;
        }

        $dsn = 'mysql:host=' . DB_HOST . ';port=' . DB_PORTA . ';dbname=' . DB_NOME . ';charset=utf8mb4';

        $pdo = new PDO($dsn, DB_USUARIO, DB_SENHA, [
            PDO::ATTR_ERRMODE            => PDO::ERRMODE_EXCEPTION,   // erros viram exceções
            PDO::ATTR_DEFAULT_FETCH_MODE => PDO::FETCH_ASSOC,         // linhas como arrays associativos
            PDO::ATTR_EMULATE_PREPARES   => true,                     // permite repetir o mesmo :parametro
        ]);
    } catch (PDOException $e) {
        http_response_code(500);
        $detalhe = DB_MOSTRAR_ERROS ? htmlspecialchars($e->getMessage(), ENT_QUOTES, 'UTF-8') : '';

        exit('<!DOCTYPE html><html lang="pt-BR"><meta charset="UTF-8">'
            . '<body style="font-family:sans-serif;max-width:640px;margin:60px auto;line-height:1.5">'
            . '<h1>Não foi possível conectar ao banco de dados</h1>'
            . '<p>Confira se o MySQL está ligado e se os dados em <code>config/conexao.php</code> estão corretos '
            . '(banco <strong>' . DB_NOME . '</strong>, usuário <strong>' . DB_USUARIO . '</strong>).</p>'
            . ($detalhe !== '' ? '<pre style="background:#f4f0ee;padding:12px;white-space:pre-wrap">' . $detalhe . '</pre>' : '')
            . '</body></html>');
    }

    return $pdo;
}
