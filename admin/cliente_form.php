<?php
/**
 * cliente_form.php - Cadastro e edição de cliente
 */
require_once __DIR__ . '/../config/config.php';
require_once RAIZ_PROJETO . '/funcoes/clientes_funcoes.php';

$id      = (int) ($_GET['id'] ?? 0);
$cliente = $id > 0 ? buscarCliente($id) : null;

if ($id > 0 && $cliente === null) {
    definirMensagem('erro', 'Cliente não encontrado.');
    redirecionar('clientes.php');
}

$dados = $cliente ?? clienteVazio();
$erros = [];

if (ehPost()) {
    $dados = montarCliente($_POST, $cliente);
    $erros = validarCliente($dados);

    if (empty($erros)) {
        salvarCliente($dados);
        definirMensagem('sucesso', $cliente ? 'Cliente atualizado com sucesso.' : 'Cliente cadastrado com sucesso.');
        redirecionar('clientes.php');
    }
}

$tituloPagina = $cliente ? 'Editar cliente' : 'Novo cliente';
$cssPagina    = ['cadastros.css'];
$jsPagina     = ['mascaras.js'];

require __DIR__ . '/includes/cabecalho.php';
?>

<?php if (!empty($erros)): ?>
    <div class="alerta alerta-erro" role="alert"><span>Corrija os campos destacados e tente novamente.</span></div>
<?php endif; ?>

<form method="post" class="cartao cartao-formulario">
    <div class="formulario-grade">

        <div class="campo campo-largo">
            <label for="nome">Nome completo *</label>
            <input type="text" id="nome" name="nome" value="<?= e($dados['nome']) ?>" required>
            <?= erroCampo($erros, 'nome') ?>
        </div>

        <div class="campo">
            <label for="cpf">CPF (opcional)</label>
            <input type="text" id="cpf" name="cpf" value="<?= e($dados['cpf']) ?>" data-mascara="cpf"
                   inputmode="numeric" placeholder="000.000.000-00">
            <?= erroCampo($erros, 'cpf') ?>
        </div>

        <div class="campo">
            <label for="telefone">Telefone</label>
            <input type="text" id="telefone" name="telefone" value="<?= e($dados['telefone']) ?>" data-mascara="telefone"
                   inputmode="tel" placeholder="(11) 0000-0000">
            <?= erroCampo($erros, 'telefone') ?>
        </div>

        <div class="campo">
            <label for="whatsapp">WhatsApp</label>
            <input type="text" id="whatsapp" name="whatsapp" value="<?= e($dados['whatsapp']) ?>" data-mascara="telefone"
                   inputmode="tel" placeholder="(11) 90000-0000">
            <?= erroCampo($erros, 'whatsapp') ?>
        </div>

        <div class="campo">
            <label for="email">E-mail (opcional)</label>
            <input type="email" id="email" name="email" value="<?= e($dados['email']) ?>">
            <?= erroCampo($erros, 'email') ?>
        </div>

        <div class="campo campo-largo">
            <label for="endereco">Endereço (opcional)</label>
            <input type="text" id="endereco" name="endereco" value="<?= e($dados['endereco']) ?>">
        </div>

    </div>

    <p class="texto-pequeno" style="margin-top:14px;">* Informe pelo menos um telefone ou WhatsApp.</p>

    <div class="formulario-acoes">
        <a href="clientes.php" class="botao botao-secundario">Cancelar</a>
        <button type="submit" class="botao botao-primario">Salvar cliente</button>
    </div>
</form>

<?php require __DIR__ . '/includes/rodape.php'; ?>