<?php
/**
 * rodape.php
 * Fecha a estrutura aberta pelo cabecalho.php e carrega os scripts.
 * Variável opcional: $jsPagina (array) - ex.: ['venda.js']
 */
$jsPagina = $jsPagina ?? [];
?>
        </main>
    </div><!-- /.principal -->
</div><!-- /.app -->

<script src="../JS/app.js"></script>
<?php foreach ($jsPagina as $arquivoJs): ?>
    <script src="../JS/<?= e($arquivoJs) ?>"></script>
<?php endforeach; ?>
</body>
</html>