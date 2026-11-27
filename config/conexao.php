<?php
/**
 * conexao.php - Conexão com o banco de dados MySQL usando PDO.
 *
 * Os dados do banco ficam logo abaixo: um bloco para o XAMPP e outro para a hospedagem.
 * Todas as páginas usam a função conectar(), que abre a conexão uma única vez por requisição.
 */

// O mesmo arquivo serve no seu computador (XAMPP) e na hospedagem: o sistema descobre sozinho onde está.
$rodandoNoComputador = PHP_SAPI === 'cli'
    || in_array(strtolower(explode(':', (string) ($_SERVER['HTTP_HOST'] ?? 'localhost'))[0]), ['localhost', '127.0.0.1', '::1'], true);

if ($rodandoNoComputador) {
    // ===== No seu computador (XAMPP) =====
    define('DB_HOST',    '127.0.0.1');
    define('DB_PORTA',   3306);
    define('DB_NOME',    'FaloPro');
    define('DB_USUARIO', 'root');
    define('DB_SENHA',   '');
    define('DB_MOSTRAR_ERROS', true);   // mostra o texto técnico do erro na tela
} else {
    // ===== Na hospedagem (internet) =====
    // Troque os 3 textos abaixo pelos dados que a hospedagem mostrar ao criar o banco MySQL.
    define('DB_HOST',    'localhost');
    define('DB_PORTA',   3306);
    define('DB_NOME',    'COLE_AQUI_O_NOME_DO_BANCO');
    define('DB_USUARIO', 'COLE_AQUI_O_USUARIO_DO_BANCO');
    define('DB_SENHA',   'COLE_AQUI_A_SENHA_DO_BANCO');
    define('DB_MOSTRAR_ERROS', false);  // na internet não mostra detalhes técnicos
}

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