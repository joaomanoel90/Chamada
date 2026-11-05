function isValidKey(tecla) {
    // Permite apenas números, backspace e tab
    const numerosSuperior = (tecla >= 48 && tecla <= 57);
    const numerosNumerico = (tecla >= 96 && tecla <= 105);
    return numerosSuperior || numerosNumerico || tecla === 8 || tecla === 9; // Backspace e Tab
}

function MascaraCpfCnpj(campo) {
    let vr = campo.value.replace(/\D/g, "");
    let tam = vr.length;

    if (tam <= 11) { // CPF
        vr = vr.replace(/(\d{3})(\d)/, "$1.$2")
               .replace(/(\d{3})(\d)/, "$1.$2")
               .replace(/(\d{3})(\d{1,2})$/, "$1-$2");
    } else { // CNPJ
        vr = vr.replace(/^(\d{2})(\d)/, "$1.$2")
               .replace(/^(\d{2})\.(\d{3})(\d)/, "$1.$2.$3")
               .replace(/\.(\d{3})(\d)/, ".$1/$2")
               .replace(/(\d{4})(\d{1,2})$/, "$1-$2");
    }

    campo.value = vr;
}

// Aplica a máscara ao digitar ou colar
document.getElementById('cpfcnpj').addEventListener('input', function() {
    MascaraCpfCnpj(this);
});;


