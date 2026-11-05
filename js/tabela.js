/* ============================================================================
   DataTables + menu "Opções" das listagens
   ----------------------------------------------------------------------------
   - Tradução para pt-BR embutida: antes vinha de
     //cdn.datatables.net/plug-ins/9dcbecd42ad/i18n/Portuguese-Brasil.json
     e a tabela aparecia em inglês ("Show entries", "Search", "Previous").
     Embutir elimina a dependência de rede e funciona offline.
   - O menu suspenso usa Popper em estratégia "fixed" para não ser recortado
     pelo invólucro rolável da tabela (.table-responsive*) nem pelo
     overflow:hidden do .content.
   ============================================================================ */

// Ordenação personalizada ignorando acento + HTML
jQuery.extend(jQuery.fn.dataTable.ext.type.order, {
    "portuguese-pre": function (data) {
        return data
            .toString()
            .toLowerCase()
            .normalize("NFD")
            .replace(/[\u0300-\u036f]/g, "") // remove acentos
            .replace(/<.*?>/g, "")             // remove HTML
            .trim();
    }
});

/* Tradução oficial do DataTables para português do Brasil. */
var CH_DATATABLES_PTBR = {
    emptyTable: "Nenhum registro encontrado",
    info: "Mostrando de _START_ até _END_ de _TOTAL_ registros",
    infoEmpty: "Mostrando 0 até 0 de 0 registros",
    infoFiltered: "(filtrados de _MAX_ registros)",
    infoPostFix: "",
    thousands: ".",
    lengthMenu: "Mostrar _MENU_ registros",
    loadingRecords: "Carregando...",
    processing: "Processando...",
    zeroRecords: "Nenhum registro encontrado",
    search: "Buscar:",
    searchPlaceholder: "",
    url: "",
    paginate: {
        first: "Primeiro",
        previous: "Anterior",
        next: "Próximo",
        last: "Último"
    },
    aria: {
        sortAscending: ": ordenar de forma crescente",
        sortDescending: ": ordenar de forma decrescente"
    },
    select: {
        rows: { _: "%d linhas selecionadas", 0: "Nenhuma linha selecionada", 1: "1 linha selecionada" }
    },
    buttons: {
        copy: "Copiar",
        copyTitle: "Copiado para a área de transferência",
        copySuccess: { _: "%d linhas copiadas", 1: "1 linha copiada" },
        print: "Imprimir",
        csv: "CSV",
        excel: "Excel",
        pdf: "PDF",
        colvis: "Colunas"
    }
};

/* Prepara os menus "Opções". O posicionamento fica com o Popper do Bootstrap:
   com uma única cópia do Bootstrap carregada (ver inc/tema.php) ele funciona
   corretamente. Antes havia três cópias e uma sobrescrevia a posição da outra.
   Reaplicado a cada redesenho do DataTables, pois as linhas são recriadas. */
function ch_prepararDropdowns(raiz) {
    if (!window.bootstrap || !bootstrap.Dropdown) return;
    var escopo = raiz || document;
    escopo.querySelectorAll('[data-bs-toggle="dropdown"]').forEach(function (el) {
        bootstrap.Dropdown.getOrCreateInstance(el, { autoClose: true });
    });
}

$(document).ready(function () {
    var $tabela = $('#item');

    if ($tabela.length && $.fn.DataTable) {
        /* As listagens trazem a mensagem de "sem registros" em data-vazio.
           Antes essa mensagem era uma linha <tr><td colspan=N> dentro do
           tbody, que conflitava com o DataTables (contagem de colunas) e
           gerava "Cannot set properties of undefined (_DT_CellIndex)". */
        var msgVazio = $tabela.attr('data-vazio') || CH_DATATABLES_PTBR.emptyTable;
        var idioma = $.extend(true, {}, CH_DATATABLES_PTBR, {
            emptyTable: msgVazio,
            zeroRecords: msgVazio
        });

        var otable = $tabela.DataTable({
            order: [[1, 'asc']],
            responsive: true,
            autoWidth: false,
            columnDefs: [
                { targets: 1, type: 'portuguese', responsivePriority: 2 },
                /* A coluna de ações não ordena nem busca: só botões.
                   responsivePriority 1 + classe 'all' impedem o Responsive de
                   escondê-la em telas estreitas — sem isso o botão "Opções"
                   desaparecia dentro da linha de detalhes e ficava inalcançável. */
                { targets: -1, orderable: false, searchable: false,
                  responsivePriority: 1, className: 'all' }
            ],
            language: idioma,
            drawCallback: function () {
                ch_prepararDropdowns(this.api().table().container());
                ch_prepararDropdowns(this.api().table().body());
            }
        });

        new $.fn.dataTable.FixedHeader(otable);

        // O FixedHeader recalcula a largura quando o menu lateral expande.
        $('.sidebar').on('mouseenter mouseleave', function () {
            setTimeout(function () {
                if (otable.fixedHeader) { otable.fixedHeader.adjust(); }
                otable.columns.adjust();
            }, 280);
        });
        $(window).on('resize', function () {
            if (otable.fixedHeader) { otable.fixedHeader.adjust(); }
        });
    }

    ch_prepararDropdowns(document);
});
