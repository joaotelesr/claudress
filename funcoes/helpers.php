<?php
/**
 * helpers.php - Funções auxiliares usadas em todas as páginas
 */

/** Escapa texto para HTML (use sempre que imprimir dados do banco ou do usuário). */
function e($valor): string
{
    return htmlspecialchars((string) $valor, ENT_QUOTES, 'UTF-8');
}

function formatarMoeda($valor): string
{
    return 'R$ ' . number_format((float) $valor, 2, ',', '.');
}

function formatarData($data): string
{
    if (empty($data)) {
        return '-';
    }
    $ts = strtotime((string) $data);
    return $ts ? date('d/m/Y', $ts) : '-';
}

function redirecionar(string $url): void
{
    header('Location: ' . $url);
    exit;
}

function definirMensagem(string $tipo, string $texto): void
{
    $_SESSION['mensagem'] = ['tipo' => $tipo, 'texto' => $texto];
}

/** Mostra (uma única vez) a mensagem guardada por definirMensagem(). */
function exibirMensagem(): void
{
    if (empty($_SESSION['mensagem'])) {
        return;
    }
    $m = $_SESSION['mensagem'];
    unset($_SESSION['mensagem']);
    $classe = $m['tipo'] === 'erro' ? 'alerta-erro' : 'alerta-sucesso';
    echo '<div class="alerta ' . $classe . '" role="status">' . e($m['texto']) . '</div>';
}

/** Devolve " ativo" quando a página atual está na lista (para marcar o menu). */
function classeAtiva(array $paginas): string
{
    return in_array(basename($_SERVER['SCRIPT_NAME']), $paginas, true) ? ' ativo' : '';
}

function ehPost(): bool
{
    return ($_SERVER['REQUEST_METHOD'] ?? '') === 'POST';
}

/** "1.234,56" ou "1234.56" → 1234.56 */
function paraDecimal($valor): float
{
    $v = trim((string) $valor);
    if ($v === '') {
        return 0.0;
    }
    if (str_contains($v, ',')) {
        $v = str_replace('.', '', $v);
        $v = str_replace(',', '.', $v);
    }
    return is_numeric($v) ? (float) $v : 0.0;
}

function somenteDigitos($valor): string
{
    return preg_replace('/\D/', '', (string) $valor);
}

function erroCampo(array $erros, string $campo): string
{
    return isset($erros[$campo]) ? '<small class="campo-erro">' . e($erros[$campo]) . '</small>' : '';
}

/** Ícones SVG simples (traço). Uso: <?= icone('mais', 18) ?> */
function icone(string $nome, int $tamanho = 20): string
{
    $desenhos = [
        'painel'    => '<rect x="3" y="3" width="7" height="9" rx="1"/><rect x="14" y="3" width="7" height="5" rx="1"/><rect x="14" y="12" width="7" height="9" rx="1"/><rect x="3" y="16" width="7" height="5" rx="1"/>',
        'produtos'  => '<path d="M20 7 12 3 4 7v10l8 4 8-4V7z"/><path d="M4 7l8 4 8-4M12 11v10"/>',
        'clientes'  => '<circle cx="9" cy="8" r="4"/><path d="M2 21c0-4 3-6 7-6s7 2 7 6"/><path d="M16 4a4 4 0 0 1 0 8M18 15c2.5.6 4 2.4 4 6"/>',
        'venda'     => '<circle cx="9" cy="20" r="1.5"/><circle cx="18" cy="20" r="1.5"/><path d="M2 3h3l2.7 12.4a1 1 0 0 0 1 .8H18a1 1 0 0 0 1-.8L21 7H6"/>',
        'historico' => '<path d="M3 12a9 9 0 1 0 3-6.7L3 8"/><path d="M3 3v5h5M12 7v5l3 2"/>',
        'pagamento' => '<rect x="2" y="5" width="20" height="14" rx="2"/><path d="M2 10h20M6 15h4"/>',
        'mais'      => '<path d="M12 5v14M5 12h14"/>',
        'busca'     => '<circle cx="11" cy="11" r="7"/><path d="m21 21-4.3-4.3"/>',
        'editar'    => '<path d="M12 20h9"/><path d="M16.5 3.5a2.1 2.1 0 0 1 3 3L7 19l-4 1 1-4 12.5-12.5z"/>',
        'excluir'   => '<path d="M3 6h18M8 6V4h8v2M19 6l-1 14H6L5 6M10 11v6M14 11v6"/>',
        'menu'      => '<path d="M3 6h18M3 12h18M3 18h18"/>',
        'condicao'  => '<path d="M20.6 13.4 13.4 20.6a2 2 0 0 1-2.8 0L3 13V3h10l7.6 7.6a2 2 0 0 1 0 2.8z"/><circle cx="8" cy="8" r="1.5"/>',
        'conciliacao' => '<path d="M4 7h13l-3-3M20 17H7l3 3"/>',
        'conferido' => '<circle cx="12" cy="12" r="9"/><path d="m8 12 3 3 5-6"/>',
        'extrato'   => '<path d="M6 3h9l4 4v14H6z"/><path d="M14 3v5h5M9 12h7M9 16h7"/>',
    ];
    $d = $desenhos[$nome] ?? '';
    return '<svg class="icone" width="' . $tamanho . '" height="' . $tamanho . '" viewBox="0 0 24 24" fill="none" '
         . 'stroke="currentColor" stroke-width="1.8" stroke-linecap="round" stroke-linejoin="round" aria-hidden="true">'
         . $d . '</svg>';
}
