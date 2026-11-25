<?php
/**
 * helpers.php
 * Funções genéricas reutilizáveis em todo o sistema.
 */

/** Protege um texto antes de exibi-lo no HTML (evita XSS). */
function e($texto): string
{
    return htmlspecialchars((string) $texto, ENT_QUOTES, 'UTF-8');
}

/** Formata um número como moeda brasileira: 1234.5 → R$ 1.234,50 */
function formatarMoeda($valor): string
{
    return 'R$ ' . number_format((float) $valor, 2, ',', '.');
}

/** Formata uma data (Y-m-d ou Y-m-d H:i:s) como dd/mm/aaaa. */
function formatarData(?string $data): string
{
    if (empty($data)) {
        return '-';
    }
    return date('d/m/Y', strtotime($data));
}

/** Redireciona para outra página e encerra a execução. */
function redirecionar(string $url): void
{
    header('Location: ' . $url);
    exit;
}

/** Guarda uma mensagem para ser exibida na próxima página (tipo: sucesso, erro, aviso, info). */
function definirMensagem(string $tipo, string $texto): void
{
    $_SESSION['mensagem'] = ['tipo' => $tipo, 'texto' => $texto];
}

/** Exibe (uma única vez) a mensagem guardada por definirMensagem(). */
function exibirMensagem(): void
{
    if (empty($_SESSION['mensagem'])) {
        return;
    }

    $mensagem = $_SESSION['mensagem'];
    unset($_SESSION['mensagem']);

    echo '<div class="alerta alerta-' . e($mensagem['tipo']) . '" role="alert">';
    echo '<span>' . e($mensagem['texto']) . '</span>';
    echo '<button type="button" class="alerta-fechar" data-fechar-alerta aria-label="Fechar">' . icone('fechar', 16) . '</button>';
    echo '</div>';
}

/** Retorna " ativo" se a página atual estiver na lista (usado no menu lateral). */
function classeAtiva(array $paginas): string
{
    return in_array(basename($_SERVER['PHP_SELF']), $paginas, true) ? ' ativo' : '';
}

/** Ícones SVG embutidos (não depende de bibliotecas externas). */
function icone(string $nome, int $tamanho = 20): string
{
    $caminhos = [
        'painel'    => '<rect x="3" y="3" width="7" height="7" rx="1.5"/><rect x="14" y="3" width="7" height="7" rx="1.5"/><rect x="14" y="14" width="7" height="7" rx="1.5"/><rect x="3" y="14" width="7" height="7" rx="1.5"/>',
        'produtos'  => '<path d="M6 2 3 6v14a2 2 0 0 0 2 2h14a2 2 0 0 0 2-2V6l-3-4z"/><path d="M3 6h18"/><path d="M16 10a4 4 0 0 1-8 0"/>',
        'clientes'  => '<path d="M17 21v-2a4 4 0 0 0-4-4H5a4 4 0 0 0-4 4v2"/><circle cx="9" cy="7" r="4"/><path d="M23 21v-2a4 4 0 0 0-3-3.87"/><path d="M16 3.13a4 4 0 0 1 0 7.75"/>',
        'venda'     => '<circle cx="9" cy="21" r="1"/><circle cx="20" cy="21" r="1"/><path d="M1 1h4l2.68 13.39a2 2 0 0 0 2 1.61h9.72a2 2 0 0 0 2-1.61L23 6H6"/>',
        'historico' => '<circle cx="12" cy="12" r="10"/><path d="M12 6v6l4 2"/>',
        'pagamento' => '<rect x="1" y="4" width="22" height="16" rx="2"/><path d="M1 10h22"/>',
        'menu'      => '<path d="M3 12h18M3 6h18M3 18h18"/>',
        'mais'      => '<path d="M12 5v14M5 12h14"/>',
        'editar'    => '<path d="M12 20h9"/><path d="M16.5 3.5a2.12 2.12 0 0 1 3 3L7 19l-4 1 1-4z"/>',
        'excluir'   => '<path d="M3 6h18"/><path d="M19 6l-1 14a2 2 0 0 1-2 2H8a2 2 0 0 1-2-2L5 6"/><path d="M10 11v6M14 11v6"/><path d="M9 6V4a1 1 0 0 1 1-1h4a1 1 0 0 1 1 1v2"/>',
        'busca'     => '<circle cx="11" cy="11" r="8"/><path d="M21 21l-4.35-4.35"/>',
        'reiniciar' => '<path d="M1 4v6h6"/><path d="M3.51 15a9 9 0 1 0 2.13-9.36L1 10"/>',
        'fechar'    => '<path d="M18 6 6 18M6 6l12 12"/>',
    ];

    $conteudo = $caminhos[$nome] ?? '';

    return '<svg class="icone" width="' . $tamanho . '" height="' . $tamanho . '" viewBox="0 0 24 24" fill="none" '
         . 'stroke="currentColor" stroke-width="1.8" stroke-linecap="round" stroke-linejoin="round" aria-hidden="true">'
         . $conteudo . '</svg>';
}

/** Retorna true se a página foi aberta pelo envio de um formulário (POST). */
function ehPost(): bool
{
    return $_SERVER['REQUEST_METHOD'] === 'POST';
}

/** Converte "1.234,56" ou "1234.56" em número decimal. */
function paraDecimal($valor): float
{
    $valor = trim((string) $valor);
    if ($valor === '') {
        return 0.0;
    }
    if (strpos($valor, ',') !== false) {
        $valor = str_replace('.', '', $valor);
        $valor = str_replace(',', '.', $valor);
    }
    return is_numeric($valor) ? (float) $valor : 0.0;
}

/** Mantém somente os dígitos de um texto (CPF, telefone...). */
function somenteDigitos(string $texto): string
{
    return preg_replace('/\D/', '', $texto);
}

/** Mostra a mensagem de erro de um campo, se houver. */
function erroCampo(array $erros, string $campo): string
{
    return isset($erros[$campo]) ? '<span class="campo-erro">' . e($erros[$campo]) . '</span>' : '';
}