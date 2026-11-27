<?php
/**
 * autenticacao.php - Login do sistema (um único usuário da loja).
 *
 * É carregado pelo config.php. Toda página do admin que inclui o config.php já fica protegida:
 * sem login, a pessoa é levada para admin/login.php (e os endereços que devolvem JSON
 * respondem "401 - faça login"). Só login.php fica aberto, porque define PAGINA_PUBLICA.
 *
 * A senha NÃO fica escrita aqui, só o "hash" dela (gerado com password_hash).
 * Para trocar a senha, rode no Shell do XAMPP:
 *     php -r "echo password_hash('NOVA_SENHA', PASSWORD_DEFAULT);"
 * e cole o resultado em LOGIN_SENHA_HASH (entre aspas simples).
 */

const LOGIN_USUARIO    = 'claudress';
const LOGIN_SENHA_HASH = '$2y$10$HcPnDmEWgfiuJxD4AvblZuhkHAbKI5GuhGdDaqqmOpudrmoRVF1Zq';

// Depois de quanto tempo parado o sistema pede o login de novo (em segundos): 8 horas
const LOGIN_TEMPO_INATIVO = 8 * 60 * 60;

// Depois de 5 senhas erradas seguidas, espera 60 segundos para tentar de novo
const LOGIN_MAX_TENTATIVAS  = 5;
const LOGIN_ESPERA_SEGUNDOS = 60;

/** true quando há alguém logado nesta sessão (e não passou o tempo de inatividade). */
function usuarioLogado(): bool
{
    if (empty($_SESSION['login_usuario'])) {
        return false;
    }
    $ultimo = (int) ($_SESSION['login_ultimo_acesso'] ?? 0);
    if ($ultimo > 0 && time() - $ultimo > LOGIN_TEMPO_INATIVO) {
        unset($_SESSION['login_usuario'], $_SESSION['login_ultimo_acesso'], $_SESSION['login_token']);
        $_SESSION['login_expirou'] = true;
        return false;
    }
    $_SESSION['login_ultimo_acesso'] = time();
    return true;
}

/** Bloqueia a página atual se ninguém estiver logado. */
function exigirLogin(): void
{
    if (usuarioLogado()) {
        return;
    }

    $pagina = basename($_SERVER['SCRIPT_NAME'] ?? '');

    // Pedidos feitos pelo JavaScript (fetch) ou endereços que devolvem JSON: responde 401 em JSON
    $modo   = $_SERVER['HTTP_SEC_FETCH_MODE'] ?? '';
    $ehAjax = ($modo !== '' && $modo !== 'navigate')
        || !empty($_SERVER['HTTP_X_REQUESTED_WITH'])
        || str_contains((string) ($_SERVER['HTTP_ACCEPT'] ?? ''), 'application/json')
        || in_array($pagina, ['buscar.php', 'produto_cadastro_rapido.php'], true);

    if ($ehAjax) {
        http_response_code(401);
        header('Content-Type: application/json; charset=utf-8');
        echo json_encode(['ok' => false, 'erro' => 'Sua sessão terminou. Recarregue a página e faça login de novo.'], JSON_UNESCAPED_UNICODE);
        exit;
    }

    // Guarda a página pedida para voltar a ela depois do login (só páginas do próprio admin)
    if (($_SERVER['REQUEST_METHOD'] ?? 'GET') === 'GET') {
        $_SESSION['login_voltar'] = $pagina . (empty($_GET) ? '' : '?' . http_build_query($_GET));
    }

    header('Location: login.php');
    exit;
}

/**
 * Confere usuário e senha. Devolve '' quando deu certo, ou a mensagem de erro.
 */
function tentarLogin(string $usuario, string $senha): string
{
    $bloqueadoAte = (int) ($_SESSION['login_bloqueado_ate'] ?? 0);
    if ($bloqueadoAte > time()) {
        return 'Muitas tentativas erradas. Aguarde ' . ($bloqueadoAte - time()) . ' segundos e tente de novo.';
    }

    $usuarioOk = hash_equals(LOGIN_USUARIO, mb_strtolower(trim($usuario)));
    $senhaOk   = password_verify($senha, LOGIN_SENHA_HASH);

    if (!$usuarioOk || !$senhaOk) {
        $erros = (int) ($_SESSION['login_erros'] ?? 0) + 1;
        $_SESSION['login_erros'] = $erros;
        if ($erros >= LOGIN_MAX_TENTATIVAS) {
            $_SESSION['login_erros'] = 0;
            $_SESSION['login_bloqueado_ate'] = time() + LOGIN_ESPERA_SEGUNDOS;
            return 'Muitas tentativas erradas. Aguarde ' . LOGIN_ESPERA_SEGUNDOS . ' segundos e tente de novo.';
        }
        return 'Usuário ou senha incorretos.';
    }

    // Login certo: troca o id da sessão (evita que alguém reaproveite uma sessão antiga)
    session_regenerate_id(true);
    unset($_SESSION['login_erros'], $_SESSION['login_bloqueado_ate']);
    $_SESSION['login_usuario']       = LOGIN_USUARIO;
    $_SESSION['login_ultimo_acesso'] = time();
    $_SESSION['login_token']         = bin2hex(random_bytes(16));
    return '';
}

/** Para onde ir depois do login: a página que a pessoa tinha pedido, ou o Painel. */
function paginaDepoisDoLogin(): string
{
    $voltar = (string) ($_SESSION['login_voltar'] ?? '');
    unset($_SESSION['login_voltar']);
    // Aceita só um arquivo .php desta pasta (nada de endereços de outros sites)
    if (preg_match('/^[a-z0-9_]+\.php(\?[^\r\n]*)?$/i', $voltar) && !str_starts_with($voltar, 'login.php') && !str_starts_with($voltar, 'logout.php')) {
        return $voltar;
    }
    return 'index.php';
}

/** Código que vai escondido no botão Sair (impede que outro site faça a loja sair sozinha). */
function tokenLogin(): string
{
    if (empty($_SESSION['login_token'])) {
        $_SESSION['login_token'] = bin2hex(random_bytes(16));
    }
    return $_SESSION['login_token'];
}

/** Sai do sistema: apaga os dados da sessão (as preferências do navegador, como o tema, ficam). */
function encerrarLogin(): void
{
    $_SESSION = [];
    if (ini_get('session.use_cookies')) {
        $p = session_get_cookie_params();
        setcookie(session_name(), '', time() - 42000, $p['path'], $p['domain'], $p['secure'], $p['httponly']);
    }
    session_destroy();
}
