/*
 Template Name: Veltrix - Responsive Bootstrap 4 Admin Dashboard
 Author: Themesbrand
 File: Datatable js
 */

$(document).ready(function () {
    var defaultOptions = {
        responsive: true,
        pageLength: 25,
        lengthMenu: [10, 25, 50, 100, 200],
        ordering: true,
        autoWidth: false,
        language: {
            search: 'Filter:',
            lengthMenu: 'Show _MENU_ entries',
            info: 'Showing _START_ to _END_ of _TOTAL_ entries',
            infoEmpty: 'No entries available',
            zeroRecords: 'No matching records found'
        },
        dom: "<'row mb-3'<'col-sm-12 col-md-6'f><'col-sm-12 col-md-6 text-md-end'B>>" +
            "<'row'<'col-sm-12'tr>>" +
            "<'row mt-3'<'col-sm-12 col-md-5'i><'col-sm-12 col-md-7'p>>",
        buttons: ['copy', 'excel', 'pdf', 'colvis']
    };

    // Initialize DataTables for any table that declares js-datatable attribute or modern-table class
    $('table.js-datatable, table.modern-table').each(function () {
        var $table = $(this);

        if ($.fn.DataTable.isDataTable(this)) {
            return;
        }

        var options = $.extend(true, {}, defaultOptions);

        if ($table.data('no-export') === true || $table.data('noExport') === true) {
            options.dom = "<'row mb-3'<'col-sm-12 col-md-6'f><'col-sm-12 col-md-6 text-md-end'>>" +
                "<'row'<'col-sm-12'tr>>" +
                "<'row mt-3'<'col-sm-12 col-md-5'i><'col-sm-12 col-md-7'p>>";
            options.buttons = [];
        }

        // Allow per-table overrides via data attributes
        if ($table.data('pageLength')) {
            options.pageLength = parseInt($table.data('pageLength'), 10);
        }
        if ($table.data('ordering') === false) {
            options.ordering = false;
        }

        $table.addClass('table-striped table-hover');

        var dt = $table.DataTable(options);

        // Attach buttons container if buttons are enabled
        if (options.buttons && options.buttons.length && dt.buttons && dt.buttons().container) {
            dt.buttons().container().appendTo($table.closest('.dataTables_wrapper').find('.col-md-6:eq(1)'));
        }
    });
});