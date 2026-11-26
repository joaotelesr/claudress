<?php
/**
 * cabecalho.php - Abre a página (head, menu lateral e topo).
 * Variáveis opcionais definidas pela página antes do require:
 *   $tituloPagina (string) | $cssPagina (array de arquivos em CSS/)
 */
$tituloPagina = $tituloPagina ?? 'Painel';
$cssPagina    = $cssPagina ?? [];
?>
<!DOCTYPE html>
<html lang="pt-BR">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <title><?= e($tituloPagina) ?> · <?= e(NOME_LOJA) ?></title>
    <link rel="stylesheet" href="../CSS/base.css">
    <link rel="stylesheet" href="../CSS/layout.css">
    <link rel="stylesheet" href="../CSS/componentes.css">
    <?php foreach ($cssPagina as $arquivo): ?>
        <link rel="stylesheet" href="../CSS/<?= e($arquivo) ?>">
    <?php endforeach; ?>
</head>
<body>
<div class="app">
    <?php require __DIR__ . '/sidebar.php'; ?>
    <div class="conteudo-area">
        <?php require __DIR__ . '/topo.php'; ?>
        <main class="conteudo">
            <?php exibirMensagem(); ?>
