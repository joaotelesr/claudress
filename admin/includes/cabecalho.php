<?php
/**
 * cabecalho.php
 * Abre o HTML e monta a estrutura: sidebar + topo + área de conteúdo.
 * Variáveis esperadas (definidas pela página antes do include):
 *   $tituloPagina (string)  - título exibido no topo e na aba
 *   $cssPagina    (array)   - CSS extras da página, ex.: ['dashboard.css']
 */
$tituloPagina = $tituloPagina ?? 'Painel';
$cssPagina    = $cssPagina ?? [];
?>
<!DOCTYPE html>
<html lang="pt-BR">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title><?= e($tituloPagina) ?> · <?= e(NOME_LOJA) ?></title>

    <!-- Fontes (se estiver offline, o navegador usa as fontes alternativas) -->
    <link rel="preconnect" href="https://fonts.googleapis.com">
    <link rel="preconnect" href="https://fonts.gstatic.com" crossorigin>
    <link href="https://fonts.googleapis.com/css2?family=Inter:wght@400;500;600&family=Playfair+Display:wght@500;600&display=swap" rel="stylesheet">

    <link rel="stylesheet" href="../CSS/base.css">
    <link rel="stylesheet" href="../CSS/layout.css">
    <link rel="stylesheet" href="../CSS/componentes.css">
    <?php foreach ($cssPagina as $arquivoCss): ?>
        <link rel="stylesheet" href="../CSS/<?= e($arquivoCss) ?>">
    <?php endforeach; ?>
</head>
<body>
<div class="app">

    <?php require __DIR__ . '/sidebar.php'; ?>
    <div class="sidebar-fundo" data-fechar-menu></div>

    <div class="principal">
        <?php require __DIR__ . '/topo.php'; ?>

        <main class="conteudo">
            <?php exibirMensagem(); ?>