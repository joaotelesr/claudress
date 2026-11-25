/**
 * app.js - comportamentos comuns a todas as páginas
 *  - Abrir/fechar o menu lateral no celular
 *  - Fechar alertas (e fechar sozinho os de sucesso)
 *  - Pedir confirmação antes de ações marcadas com data-confirmar
 */
document.addEventListener('DOMContentLoaded', function () {
    var corpo = document.body;

    // ----- Menu lateral (celular) -----
    document.querySelectorAll('[data-abrir-menu]').forEach(function (botao) {
        botao.addEventListener('click', function () {
            corpo.classList.add('menu-aberto');
        });
    });

    document.querySelectorAll('[data-fechar-menu]').forEach(function (elemento) {
        elemento.addEventListener('click', function () {
            corpo.classList.remove('menu-aberto');
        });
    });

    document.addEventListener('keydown', function (evento) {
        if (evento.key === 'Escape') {
            corpo.classList.remove('menu-aberto');
        }
    });

    // ----- Alertas -----
    document.querySelectorAll('[data-fechar-alerta]').forEach(function (botao) {
        botao.addEventListener('click', function () {
            botao.closest('.alerta').remove();
        });
    });

    document.querySelectorAll('.alerta-sucesso').forEach(function (alerta) {
        setTimeout(function () {
            alerta.remove();
        }, 5000);
    });

    // ----- Confirmação (links e formulários) -----
    document.querySelectorAll('a[data-confirmar]').forEach(function (link) {
        link.addEventListener('click', function (evento) {
            if (!window.confirm(link.dataset.confirmar)) {
                evento.preventDefault();
            }
        });
    });

    document.querySelectorAll('form[data-confirmar]').forEach(function (formulario) {
        formulario.addEventListener('submit', function (evento) {
            if (!window.confirm(formulario.dataset.confirmar)) {
                evento.preventDefault();
            }
        });
    });
});