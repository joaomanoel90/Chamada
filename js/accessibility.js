var currentZoom = 100;
var usuarioLogado = "<?php echo $_SESSION['usuarioId']; ?>";

var projeto1Prefixo = "<?php echo BASEURL; ?>";

function zoom(factor) {
  currentZoom *= factor;
  document.body.style.zoom = currentZoom + "%";
  setCookieZoom(currentZoom, 7); // Armazena o nível de zoom em um cookie com validade de 7 dias
}

function resetZoom() {
  currentZoom = 100;
  document.body.style.zoom = currentZoom + "%";
  setCookieZoom(currentZoom, 7);
}

function toggleContrast() {
  document.body.classList.toggle("high-contrast");
  var buttons = document.querySelectorAll(".contrast-btn");
  buttons.forEach(function(button) {
    button.classList.toggle("high-contrast");
  });
  setCookieContraste(document.body.classList.contains("high-contrast"), 7); // Armazena o estado do contraste em um cookie com validade de 7 dias
}

function applyAccessibilitySettings() {
  var zoomLevel = getCookieZoom();
  if (zoomLevel) {
    currentZoom = parseInt(zoomLevel);
    document.body.style.zoom = currentZoom + "%";
  }

  var highContrast = getCookieContraste();
  if (highContrast === "true") {
    document.body.classList.add("high-contrast");
    var buttons = document.querySelectorAll(".contrast-btn");
    buttons.forEach(function(button) {
      button.classList.add("high-contrast");
    });
  }
}

// Funções para manipulação de cookies
function setCookie(name, value, days) {
  var expires = "";
  if (days) {
    var date = new Date();
    date.setTime(date.getTime() + days * 24 * 60 * 60 * 1000);
    expires = "; expires=" + date.toUTCString();
  }
  document.cookie = name + "=" + (value || "") + expires + "; path=/";
}

function setCookieZoom(value, days) {
  var nomeCookie = projeto1Prefixo + "zoomLevel/" + usuarioLogado;
  setCookie(nomeCookie, value, days);
}

function setCookieContraste(value, days) {
  var nomeCookie = projeto1Prefixo + "highContrast/" + usuarioLogado;
  setCookie(nomeCookie, value, days);
}

function getCookie(name) {
  var nameEQ = name + "=";
  var ca = document.cookie.split(";");
  for (var i = 0; i < ca.length; i++) {
    var c = ca[i];
    while (c.charAt(0) === " ") {
      c = c.substring(1, c.length);
    }
    if (c.indexOf(nameEQ) === 0) {
      return c.substring(nameEQ.length, c.length);
    }
  }
  return null;
}

function getCookieZoom() {
  var nomeCookie = projeto1Prefixo + "zoomLevel/" + usuarioLogado;
  return getCookie(nomeCookie);
}

function getCookieContraste() {
  var nomeCookie = projeto1Prefixo + "highContrast/" + usuarioLogado;
  return getCookie(nomeCookie);
}

// Para permitir que os atalhos de teclado "Ctrl +", "Ctrl -" e "Ctrl 0" interajam com os botões de zoom
document.addEventListener("keydown", function(event) {
  if (event.ctrlKey) {
    if (event.key === "+") {
      zoom(1.25); // Chama a função para aumentar o zoom
      event.preventDefault(); // Impede o comportamento padrão do navegador
    } else if (event.key === "-") {
      zoom(0.8); // Chama a função para diminuir o zoom
      event.preventDefault(); // Impede o comportamento padrão do navegador
    } else if (event.key === "0") {
      resetZoom(); // Chama a função para restaurar o zoom padrão
      event.preventDefault(); // Impede o comportamento padrão do navegador
    }
  }
});

applyAccessibilitySettings();
