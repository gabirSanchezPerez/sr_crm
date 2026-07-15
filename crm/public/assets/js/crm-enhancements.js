(function (window, $) {
    'use strict';

    var datatableLanguage = {
        decimal: '',
        emptyTable: 'Sin registros disponibles',
        info: 'Mostrando _START_ a _END_ de _TOTAL_ registros',
        infoEmpty: 'Mostrando 0 a 0 de 0 registros',
        infoFiltered: '(filtrado de _MAX_ registros totales)',
        lengthMenu: 'Mostrar _MENU_ registros',
        loadingRecords: 'Cargando...',
        processing: 'Procesando...',
        search: 'Buscar:',
        zeroRecords: 'No se encontraron registros',
        paginate: {
            first: 'Primero',
            last: 'Ultimo',
            next: 'Siguiente',
            previous: 'Anterior'
        }
    };

    function initDatatables(root) {
        if (!$ || !$.fn || !$.fn.DataTable) {
            return;
        }

        $('.js-datatable', root || document).each(function () {
            var table = $(this);

            if (table.data('crmDatatable')) {
                return;
            }

            if (table.find('thead th').length === 0 || table.find('tbody tr').length === 0) {
                return;
            }
            console.log(table.data('pageLength'))
            table.DataTable({
                language: datatableLanguage,
                pageLength: 10,//Number(table.data('pageLength')) || 10,
                lengthMenu: [5, 10, 25, 50, 100],
                responsive: true,
                autoWidth: false,
                order: [],
                columnDefs: [
                    {
                        targets: 'no-sort',
                        orderable: false,
                        searchable: false
                    }
                ]
            });

            table.data('crmDatatable', true);
        });
    }

    function initSelect2(root) {
        if (!$ || !$.fn || !$.fn.select2) {
            return;
        }

        $('.js-select2', root || document).each(function () {
            var select = $(this);
            var allowClear = select.data('allowClear');

            if (select.data('crmSelect2')) {
                return;
            }

            select.select2({
                theme: 'bootstrap-5',
                width: select.data('width') || '100%',
                placeholder: select.data('placeholder') || select.attr('placeholder') || 'Seleccionar',
                allowClear: allowClear === undefined ? !select.prop('required') : allowClear !== false,
                closeOnSelect: !select.prop('multiple'),
                dropdownParent: select.closest('.modal').length ? select.closest('.modal') : $(document.body)
            });

            select.data('crmSelect2', true);
        });
    }

    function init(root) {
        initDatatables(root);
        initSelect2(root);
    }

    window.CRMEnhancements = {
        init: init,
        initDatatables: initDatatables,
        initSelect2: initSelect2
    };

    $(init);
})(window, window.jQuery);
