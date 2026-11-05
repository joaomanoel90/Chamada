// Ordenação personalizada ignorando acento + HTML
jQuery.extend(jQuery.fn.dataTable.ext.type.order, {
    "portuguese-pre": function (data) {
        return data
            .toString()
            .toLowerCase()
            .normalize("NFD")
            .replace(/[\u0300-\u036f]/g, "") // remove acentos
            .replace(/<.*?>/g, "") // remove HTML
            .trim();
    }
});

$(document).ready(function () {
    var otable = $('#item').DataTable({
        order: [[1, 'asc']],
        responsive: true,
        columnDefs: [
            { targets: 1, type: 'portuguese' }
        ],
        language: {
            "url": "//cdn.datatables.net/plug-ins/9dcbecd42ad/i18n/Portuguese-Brasil.json"
        }
    });

    new $.fn.dataTable.FixedHeader(otable);
});