/**
 * Handlers que antes viviam em atributos inline (onclick/onchange/onsubmit/
 * onkeyup/onload). Foram movidos para cá para que a CSP não precise de
 * 'unsafe-inline' em script-src.
 *
 * Convenções usadas no HTML:
 *   data-confirm     -> confirma antes de seguir (clique ou submit)
 *   data-print       -> window.print()
 *   data-auto-submit -> selects que enviam o formulário ao mudar
 *   data-uppercase   -> campos digitados em maiúsculas
 */
(function () {
  'use strict';

  function confirmar(mensagem, evento) {
    if (window.confirm(mensagem || 'Confirmar?') === false) {
      evento.preventDefault();
      evento.stopImmediatePropagation();
    }
  }

  document.addEventListener('click', function (evento) {
    if (!evento.target || typeof evento.target.closest !== 'function') return;

    var alvo = evento.target.closest('[data-print], [data-confirm]');
    if (!alvo) return;

    /* Não pede confirmação de links com destino externo: esses podem ser
       feedback legítimo e nunca alteram dados. */
    if (alvo.hasAttribute('data-print')) {
      evento.preventDefault();
      window.print();
      return;
    }
    /* A confirmação de um <form> é feita no evento submit, não no clique,
       para não perguntar duas vezes. */
    if (alvo.tagName === 'FORM') return;
    confirmar(alvo.getAttribute('data-confirm'), evento);
  }, true);

  document.addEventListener('submit', function (evento) {
    if (evento.target && evento.target.hasAttribute('data-confirm')) {
      confirmar(evento.target.getAttribute('data-confirm'), evento);
    }
  });

  document.addEventListener('change', function (evento) {
    var alvo = evento.target;
    if (alvo && alvo.hasAttribute && alvo.hasAttribute('data-auto-submit') && alvo.form) {
      alvo.form.submit();
    }
  });

  document.addEventListener('input', function (evento) {
    var alvo = evento.target;
    if (!alvo || !alvo.hasAttribute || !alvo.hasAttribute('data-uppercase')) return;
    var posicao = alvo.selectionStart;
    alvo.value = alvo.value.toUpperCase();
    try { alvo.setSelectionRange(posicao, posicao); } catch (erro) { /* tipo sem seleção */ }
  });

  /* Substitui o onload da <body> dos cabeçalhos. */
  document.addEventListener('DOMContentLoaded', function () {
    if (typeof applyAccessibilitySettings === 'function') {
      applyAccessibilitySettings();
    }
  });
})();
