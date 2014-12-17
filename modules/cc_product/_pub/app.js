var App = (function (my) {
    my.modules.cc_product = new function()
    {
        var _module = this;

        this.cache = {};

        this.search = new function()
        {
            /**
             * Create html app-windows parsing App.modules._base.templates.window (title, id)
             * Update search result list - this.paginate(data)
             */
            this.init = function(data)
            {
                console.log('cc_product.search.init()');
                App.cacheAppWindows();

                var theaders = ['image', 'name', 'barcode_int', 'barcode', 'measurement_unit', 'stock', 'stock_price', 'comments', 'deleted'];
                var $table = App.buildDataTable(theaders, Globalize.localize('cc_product'), 'a');
                $table.addClass('td-first-image');

                $('#main').html(
                    $('#cache > div[name="app-window"]').clone().prop('id', 'app-window-cc-product-search')
                        .find('h1').html(Globalize.localize('cc_product')['win_title_list']).end()
                        .find('div.app-window-content').html($table).end()
                );

                $table.dataTable({
                    "iDeferLoading": [ data.iTotalDisplayRecords, data.iTotalRecords ],
                    "aaData" : data.aaData,
                    "sAjaxSource": App.url_base + "/cc_product/search",
                    "aoColumnDefs": [
                        { "aTargets": [ 0 ], // iamge
                            "bSortable" : false,
                            "mData": function ( source, type, val ) {
                                if (type === 'display') {
                                    if (source._image) return '<img src="'+App.url_base+'/uploads/cc_product/_pub/'+source.id+'.png">';
                                    else return '<img src="'+App.url_base+'/modules/cc_product/_pub/img/not_found.jpg">';
                                }
                                return source._image;
                            }
                        },
                        { "aTargets": [ 1 ], // name
                            "mData": function ( source, type, val ) {
                                if (type === 'display') { return '<a href="'+App.url_base+'/cc_product/view/?id='+source.id+'" data-ajax="1">'+source.name+'</a>' }
                                return source.name;
                            }
                        },
                        { "aTargets": [ 2 ], "mData": "barcode_int" },
                        { "aTargets": [ 3 ], "mData": "barcode" },
                        { "aTargets": [ 4 ], "mData": "measurement_unit" },
                        { "aTargets": [ 5 ], "mData": "stock" },
                        { "aTargets": [ 6 ], // stock_price
                            "mData": function ( source, type, val ) {
                                if (type === 'display') { return '$'+Globalize.format(source.stock_price, "n2"); }
                                return source.stock_price;
                            }
                        },
                        { "aTargets": [ 7 ], "mData": "comments" },
                        { "aTargets": [ 8 ], // deleted
                            "bSortable" : false,
                            "mData": function ( source, type, val ) {
                                if (type === 'display') { return source.deleted?Globalize.localize('_base')['yes']:Globalize.localize('_base')['no'] }
                                return source.deleted;
                            }
                        },
                        { "aTargets": [ 9 ], // Action
                            "bSortable" : false,
                            "mData": function ( source, type, val ) {
                                if (type === 'display') {
                                    return '<a class="ui-state-default ui-corner-all app-btn" href="'+App.url_base+'/cc_product/mod/?id='+source.id+'" data-ajax="1" title="' + Globalize.localize('_base')['mod'] + '"><span class="ui-icon ui-icon-pencil">' + Globalize.localize('_base')['mod'] + '</span></a> ' +
                                            '<a class="ui-state-default ui-corner-all app-btn" href="'+App.url_base+'/cc_product/del/?id='+source.id+'" data-ajax-callback="del" data-push-state="0" title="' + Globalize.localize('_base')['del'] + '"><span class="ui-icon ui-icon-closethick">' + Globalize.localize('_base')['del'] + '</span></a>';
                                }
                                return source.id;
                            }
                        },
                    ],
                    "aaSorting": [[1,'asc']] // default sorting
                })
                    .columnFilter({
                        sPlaceHolder: "head:after",
                        aoColumns: [
                            null, // image
                            { type: "text" }, // name
                            { type: "text" }, // barcode_int
                            { type: "text" }, // barcode
                            { type: "text" }, // measurement_unit
                            null, // stock
                            null, // stock_price
                            null, // comments
                            { type: "select", values: [ // deleted
                                { "value":"0","label":Globalize.localize('_base')['no'] },
                                { "value":"1","label":Globalize.localize('_base')['yes'] }
                            ] },
                            null //action
                        ]
                    });

                App.ajaxDataProcessComplete();

            };
        };

        this.view = new function()
        {
            /**
             * Create html app-windows parsing App.modules._base.templates.window (title, id)
             */
            this.init = function(data)
            {
                console.log('cc_product.view.init()');
                // Required files
                var required_files = true;
                if (!App.templates.cc_product || !App.templates.cc_product.view) { App.loadTemplate('cc_product', 'view'); required_files = false; }
                if (!Globalize.localize('cc_purchase')) { App.loadLang('cc_purchase'); required_files = false; }

                if (!required_files) return;

                App.cacheAppWindows();

                if (data.cc_product._image) data.cc_product.image_src = App.url_base+'/uploads/cc_product/_pub/'+data.cc_product.id+'.png';
                else data.cc_product.image_src = App.url_base+'/modules/cc_product/_pub/img/not_found.jpg';

                $('#main').html(
                    $('#cache > div[name="app-window"]').clone().prop('id', 'app-window-cc-product-view')
                        .find('h1').html(Globalize.localize('cc_product')['win_title_view']).end()
                        .find('div.app-window-content').html(Mustache.render(App.templates.cc_product.view.cc_product, $.extend({},Globalize.localize('_base'), Globalize.localize('cc_product'), data))).end()
                )
        
                .append(
                    $('#cache > div[name="app-window"]').clone().prop('id', 'app-window-cc-product-view-tabs')
                        .find('h1').html(Globalize.localize('cc_purchase')['win_title_info']).end()
                        .find('div.app-window-content').html(Mustache.render(App.templates.cc_product.view.tabs_script, $.extend({},Globalize.localize('_base'), Globalize.localize('cc_product'), data))).end()
                );

                $('#tabs').tabs({
                    activate: function( event, ui ) {
                        if (!ui.newPanel.html()) _module.view['load_' + ui.newPanel.attr('id')](data.cc_product.id);
                    }
                });


                $.ajax({
                    global: false,
                    dataType: "json",
                    url: App.url_base + '/cc_purchase/search?ajax=1&data_type=json&cc_product_id=' + data.cc_product.id
                })
                .done(_module.view.on_load_tab_cc_purchases);

                App.ajaxDataProcessComplete();
            };
            
            this.on_load_tab_cc_purchases = function (data)
            {
                console.log('user.view.on_load_tab_purchases()');

                var theaders = ['order_num', 'opened_on', 'closed_on', 'cc_supplier_name', 'cc_product_name', 'status', 'comments', 'deleted'];
                var $table = App.buildDataTable(theaders, Globalize.localize('cc_purchase'), 'af'); // headers, lang, build footer

                $('#tab_cc_purchases').html($table);
                var cc_product_id = $('div.row-data').data('id');

                $table.dataTable({
                    "iDeferLoading": [data.iTotalDisplayRecords, data.iTotalRecords],
                    "aaData": data.aaData,
                    "sAjaxSource": App.url_base + "/cc_purchase/search",
                    "fnServerParams": function ( aoData ) {
                        aoData.push( { "name": "ajax", "value": 1 } );
                        aoData.push( { "name": "data_type", "value": "json" } );
                        aoData.push( { "name": "cc_product_id", "value": cc_product_id } );
                        aoData.push( { "name": "theaders", "value": theaders } );
                    },

                    "aoColumnDefs": [
                        {"aTargets": [0], // order_num
                            "mData": function (source, type, val) {
                                if (type === 'display') {
                                    return '<a href="' + App.url_base + '/cc_purchase/view/?id=' + source.id + '" data-ajax="1">' + source.order_num + '</a>'
                                }
                                return source.order_num;
                            }
                        },
                        {"aTargets": [1], // opened_on
                            "mData": function (source, type, val) {
                                if (type === 'display') {
                                    return '<a href="' + App.url_base + '/cc_purchase/view/?id=' + source.id + '" data-ajax="1">' + source.opened_on + '</a>'
                                }
                                return source.opened_on;
                            }
                        },
                        {"aTargets": [2], // closed_on
                            "mData": function (source, type, val) {
                                if (type === 'display') {
                                    return '<a href="' + App.url_base + '/cc_purchase/view/?id=' + source.id + '" data-ajax="1">' + source.closed_on + '</a>'
                                }
                                return source.closed_on;
                            }
                        },
                        {"aTargets": [3], // cc_supplier
                            "mData": function (source, type, val) {
                                if (type === 'display') {
                                    return '<a href="' + App.url_base + '/cc_supplier/view/?id=' + source.cc_supplier_id + '" data-ajax="1">' + source.cc_supplier_name + '</a>'
                                }
                                return source.cc_supplier_name;
                            }
                        },
                        {"aTargets": [4], // products
                            "bSortable": false,
                            "sWidth": '20%',
                            "mData": function (source, type, val) {
                                if (type === 'display') {
                                    var $ul = $('<ul/>').addClass('cc-purchase-list-products');
                                    var $li;
                                    $.each(source.products, function (i) {
                                        $li = $('<li/>').append(
                                            $('<span/>').addClass('label').append(
                                                $('<a/>').attr('href', App.url_base + '/cc_product/view?id=' + source.products[i]['id']).text(source.products[i]['name'])
                                            )
                                        )
                                        .append('(' + source.products[i]['quantity'] + 'u x $' + Globalize.format(source.products[i]['unit_price'], "n2") + ')')
                                        .appendTo($ul);
                                    });
                                    $li = $('<li/>').addClass('total').append(
                                        $('<span/>').addClass('label').html(Globalize.localize('_base')['total'])
                                    )
                                    .append(': $' + Globalize.format(source['_total'], "n3")).appendTo($ul);
                                    return $ul.outer();
                                }
                                return source.id;
                            }
                            /*
                             quantity":100,"unit_price":"5.00","quantity_delivered":0,"id":"26","name":"Broches para abrochadora Swingline","deposit":"01","family":"01","item":"073","brand":"02","size":"00","color":"00","barcode":"74711351089","measurement_unit":"( )","comments":" ","deleted":"0","deleted_on":" ","deleted_by":" "}],"_total":500}
                             */
                        },
                        {"aTargets": [5], // status
                            "mData": function (source, type, val) {
                                if (type === 'display') {
                                    switch (source.status) {
                                        case '0':
                                            return Globalize.localize('cc_purchase')['open'];
                                        case '1':
                                            return Globalize.localize('cc_purchase')['approved'];
                                        case '2':
                                            return Globalize.localize('cc_purchase')['closed'];
                                    }
                                }
                                return source.status;
                            }
                        },
                        {"aTargets": [6], "mData": "comments"},
                        {"aTargets": [7], // deleted
                            "bSortable": false,
                            "mData": function (source, type, val) {
                                if (type === 'display') {
                                    return source.deleted ? Globalize.localize('_base')['yes'] : Globalize.localize('_base')['no']
                                }
                                return source.deleted;
                            }
                        },
                        {"aTargets": [8], // Action
                            "bSortable": false,
                            "mData": function (source, type, val) {
                                if (type === 'display') {
                                    return '<a class="ui-state-default ui-corner-all app-btn" href="' + App.url_base + '/cc_purchase/mod/?id=' + source.id + '" data-ajax="1" title="' + Globalize.localize('_base')['mod'] + '"><span class="ui-icon ui-icon-pencil">' + Globalize.localize('_base')['mod'] + '</span></a> ' +
                                            '<a class="ui-state-default ui-corner-all app-btn" href="' + App.url_base + '/cc_purchase/del/?id=' + source.id + '" data-ajax-callback="del" data-push-state="0" title="' + Globalize.localize('_base')['del'] + '"><span class="ui-icon ui-icon-closethick">' + Globalize.localize('_base')['del'] + '</span></a>';
                                }
                                return source.id;
                            }
                        },
                    ],
                    "aaSorting": [[1, 'desc']], // default sorting
                    "fnFooterCallback": function (nRow, aaData, iStart, iEnd, aiDisplay) { // total calculator
                        var iTotal = 0;
                        for (var i=0; i<aaData.length; i++) iTotal += aaData[i]['_total']*1;
                        var nCells = nRow.getElementsByTagName('th');
                        nCells[4].innerHTML = '$' + Globalize.format(iTotal, "n3");
                    }
                })
                .columnFilter({
                    sRangeFormat: "Del {from} al {to}",
                    sPlaceHolder: "head:after",
                    aoColumns: [
                        {type: "text"}, // order_num
                        {type: "date-range"}, // opened_on
                        {type: "date-range"}, // closed_on
                        {type: "text"}, // cc_supplier
                        null, // cc_products
                        {type: "select", values: [// status
                                {"value": "0", "label": Globalize.localize('cc_purchase')['open']},
                                {"value": "1", "label": Globalize.localize('cc_purchase')['approved']},
                                {"value": "2", "label": Globalize.localize('cc_purchase')['closed']}
                            ]},
                        null, // comments
                        {type: "select", values: [// deleted
                                {"value": "0", "label": Globalize.localize('_base')['no']},
                                {"value": "1", "label": Globalize.localize('_base')['yes']}
                            ]},
                        null // action
                    ]
                });

                App.ajaxDataProcessComplete();
            };
            
            this.load_tab_cc_deliveries = function(cc_product_id)
            {
                App.ajax.callback = 'load_tab_cc_deliveries';
                App.ajax.data = cc_product_id;
                if (!Globalize.localize('cc_delivery')) { App.loadLang('cc_delivery'); return; }
                $.ajax({
                    global : false,
                    dataType: "json",
                    url: App.url_base + '/cc_delivery/search?ajax=1&data_type=json&cc_product_id='+cc_product_id
                })
                .done( _module.view.on_load_tab_cc_deliveries );
            };

            this.on_load_tab_cc_deliveries = function (data)
            {
                console.log('user.view.on_load_tab_deliveries()');

                var theaders = ['opened_on', 'closed_on', 'user_name', 'cc_category', 'cc_product_name', 'comments', 'deleted'];
                var $table = App.buildDataTable(theaders, Globalize.localize('cc_delivery'), 'af'); // headers, lang, extras(m[od]d[del]u[ndelete]f[ooter]

                $('#tab_cc_deliveries').html($table);
                var cc_product_id = $('div.row-data').data('id');

                $table.dataTable({
                    "iDeferLoading": [data.iTotalDisplayRecords, data.iTotalRecords],
                    "aaData": data.aaData,
                    "sAjaxSource": App.url_base + "/cc_delivery/search",
                    "fnServerParams": function ( aoData ) {
                        aoData.push( { "name": "ajax", "value": 1 } );
                        aoData.push( { "name": "data_type", "value": "json" } );
                        aoData.push( { "name": "cc_product_id", "value": cc_product_id } );
                        aoData.push( { "name": "theaders", "value": theaders } );
                    },
                    "aoColumnDefs": [
                        {"aTargets": [0], // opened_on
                            "mData": function (source, type, val) {
                                if (type === 'display') {
                                    return '<a href="' + App.url_base + '/cc_delivery/view/?id=' + source.id + '" data-ajax="1">' + source.opened_on + '</a>'
                                }
                                return source.opened_on;
                            }
                        },
                        {"aTargets": [1], // closed_on
                            "mData": function (source, type, val) {
                                if (type === 'display') {
                                    return '<a href="' + App.url_base + '/cc_delivery/view/?id=' + source.id + '" data-ajax="1">' + source.closed_on + '</a>'
                                }
                                return source.closed_on;
                            }
                        },
                        {"aTargets": [2], // user
                            "mData": function (source, type, val) {
                                if (type === 'display') {
                                    return '<a href="' + App.url_base + '/user/view/?id=' + source.user_id + '" data-ajax="1">' + source.user_name + '</a>'
                                }
                                return source.user_name;
                            }
                        },
                        {"aTargets": [3], "mData": "cc_category_name"},
                        {"aTargets": [4], // products
                            "bSortable": false,
                            "sWidth": '20%',
                            "mData": function (source, type, val) {
                                if (type === 'display') {
                                    var $ul = $('<ul/>').addClass('cc-delivery-list-products');
                                    var $li;
                                    $.each(source.products, function (i) {
                                        $li = $('<li/>').append(
                                            $('<span/>').addClass('label').append(
                                                $('<a/>').attr('href', App.url_base + '/cc_product/view?id=' + source.products[i]['id']).text(source.products[i]['name'])
                                            )
                                        )
                                        .append('(' + source.products[i]['quantity'] + 'u x $' + Globalize.format(source.products[i]['unit_price'], "n2") + ')')
                                        .appendTo($ul);
                                    });
                                    $li = $('<li/>').addClass('total').append(
                                        $('<span/>').addClass('label').html(Globalize.localize('_base')['total'])
                                    )
                                    .append(': $' + Globalize.format(source['_total'], "n2")).appendTo($ul);
                                    return $ul.outer();
                                }
                                return source.id;
                            }
                            /*
                             quantity":100,"unit_price":"5.00","quantity_delivered":0,"id":"26","name":"Broches para abrochadora Swingline","deposit":"01","family":"01","item":"073","brand":"02","size":"00","color":"00","barcode":"74711351089","measurement_unit":"( )","comments":" ","deleted":"0","deleted_on":" ","deleted_by":" "}],"_total":500}
                             */
                        },
                        {"aTargets": [5], "mData": "comments"},
                        {"aTargets": [6], // deleted
                            "bSortable": false,
                            "mData": function (source, type, val) {
                                if (type === 'display') {
                                    return source.deleted ? Globalize.localize('_base')['yes'] : Globalize.localize('_base')['no']
                                }
                                return source.deleted;
                            }
                        },
                        {"aTargets": [7], // Action
                            "bSortable": false,
                            "mData": function (source, type, val) {
                                if (type === 'display') {
                                    var html = '<a class="ui-state-default ui-corner-all app-btn" href="' + App.url_base + '/cc_delivery/del/?id=' + source.id + '" data-ajax-callback="del" data-push-state="0" title="' + Globalize.localize('_base')['del'] + '"><span class="ui-icon ui-icon-closethick">' + Globalize.localize('_base')['del'] + '</span></a>';
                                    if (source.deleted)
                                        html += '<a class="ui-state-default ui-corner-all app-btn" href="' + App.url_base + '/cc_delivery/undel/?id=' + source.id + '" data-ajax-callback="undel" data-push-state="0" title="' + Globalize.localize('_base')['undel'] + '"><span class="ui-icon ui-icon-check">' + Globalize.localize('_base')['undel'] + '</span></a>';
                                    return html;
                                }
                                return source.id;
                            }
                        },
                    ],
                    "aaSorting": [[0, 'desc']], // default sorting
                    "fnFooterCallback": function (nRow, aaData, iStart, iEnd, aiDisplay) { // total calculator
                        var iTotal = 0;
                        for (var i=0; i<aaData.length; i++) iTotal += aaData[i]['_total']*1;
                        var nCells = nRow.getElementsByTagName('th');
                        nCells[4].innerHTML = '$' + Globalize.format(iTotal, "n3");
                    }
                })
                .columnFilter({
                    sRangeFormat: "Del {from} al {to}",
                    sPlaceHolder: "head:after",
                    aoColumns: [
                        {type: "date-range"}, // opened_on
                        {type: "date-range"}, // closed_on
                        {type: "text"}, // user_name
                        {type: "select", values: data.cc_categories}, // cc_category
                        null, // cc_product_name
                        null, // comments
                        {type: "select", values: [// deleted
                                {"value": "0", "label": Globalize.localize('_base')['no']},
                                {"value": "1", "label": Globalize.localize('_base')['yes']}
                            ]},
                        null // Action
                    ]
                });

                App.ajaxDataProcessComplete();
            };


        };

        this.add = new function()
        {
            /**
             * Add form
             * @param data holds form data
             */
            this.init = function(data)
            {
                console.log('cc_product.add.init()');
                // Required files
                var required_files = true;
                if (!App.forms.cc_product || !App.forms.cc_product.add_mod)  { App.loadDForm('cc_product', 'add_mod'); required_files = false; }
                if (!required_files) return;

                var win_already = false;

                /**
                 * Cache
                 * div=app-window-user-add-mod may be already on #main
                 * (as when the user comes from /user/add or /user/mod)
                 * If it is
                 */
                var $win = $('#main > div[name="app-window-cc-product-add-mod"]');
                if ($win.length) {
                    win_already = true;

                    // cache and remove everything else
                    // avoid caching $win
                    $win.data('cache', 0);

                    App.cacheAppWindows();
                    $('#main > div').not('[name="app-window-cc-product-add-mod"]').remove();

                    $win.data('cache', 1);
                }
                else {
                    App.cacheAppWindows();

                    $win = $('#cache > div[name="app-window-cc-product-add-mod"]');
                    if (!$win.length) {
                        console.log('cc_product.add - window is NOT CACHED');
                        $win = $('#cache > div[name="app-window"]').clone().attr('name', 'app-window-cc-product-add-mod').data('cache', 1)
                            .find('div.app-window-content').html($('<form></form>').dform(App.forms.cc_product.add_mod)).end()
                    }
                }

                // win title
                $win.find('h1').html(Globalize.localize('cc_product')['win_title_add']);

                var $form = $win.find('form');

                // reset
                $form.data('validator').resetForm();
                $form
                    .find('.error').removeClass('error').end()
                    .find('input[name="id"]').remove().end()
                    .find('input[name="image_delete"]')
                        .closest('fieldset').remove().end()
                    .end()
                    .find('input[name="deleted"]')
                        .closest('fieldset').remove().end()
                    .end()
                    [0].reset();

                // action + submit button lang
                $form.prop('action', 'add').find('input[type="submit"], button[type="submit"]').val(Globalize.localize('_base')['add']);

                if (!win_already) $('#main').html($win);

                App.ajaxDataProcessComplete();
            };

            // data = JSON { textStatus = "ok" }  OR { textStatus = "error", errors[form_field] = error }
            this.on_submit = function(data)
            {
                console.log('cc_product.add.on_submit()');
                if (data.textStatus == 'ok') {
                    $('<div>'+Globalize.localize('cc_product')['add_ok']+'</div>').dialog({
                        title: Globalize.localize('cc_product')['win_title_add'],
                        buttons: [{ text: 'Ok', click: function(){ $('#main > div[name="app-window-cc-product-add-mod"] > .app-window-content form')[0].reset(); $( this ).dialog( 'destroy' ); } }]
                    });
                }
                else App.errorsDialog(data.errors, Globalize.localize('cc_product'));
                App.ajaxDataProcessComplete();
            }
        };

        this.mod = new function()
        {
            /**
             * Create html app-windows parsing App.modules._base.templates.window (title, id)
             * @param data holds form data
             */
            this.init = function(data)
            {
                console.log('cc_product.mod.init()');
                // Required files
                var required_files = true;
                if (!App.forms.cc_product || !App.forms.cc_product.add_mod)  { App.loadDForm('cc_product', 'add_mod'); required_files = false; }
                if (!required_files) return;

                App.cacheAppWindows();

                // cache
                var $win = $('#cache > div[name="app-window-cc-product-add-mod"]');
                if (!$win.length) {
                    console.log('cc_product.mod - win is NOT CACHED');
                    $win = $('#cache > div[name="app-window"]').clone().attr('name', 'app-window-cc-product-add-mod').data('cache', 1)
                        .find('div.app-window-content').html($('<form></form>').dform(App.forms.cc_product.add_mod)).end()
                }

                // win title
                $win.find('h1').html(Globalize.localize('cc_product')['win_title_mod']);

                var $form = $win.find('form');

                // reset
                $form.data('validator').resetForm();
                $form
                    .find('.error').removeClass('error').end()
                    .find('input[name="id"]').remove().end()
                    .find('input[name="image_delete"]')
                        .closest('fieldset').remove().end()
                    .end()
                    .find('input[name="deleted"]')
                        .closest('fieldset').remove().end()
                    .end()
                    [0].reset();

                //Add 'image' and 'image_delete' fields
                if (data.cc_product._image) {
                    // dform image cache
                    var $form_image;
                    if (_module.cache.mod && _module.cache.mod.$form_image) $form_image = _module.cache.mod.$form_image.clone();
                    else {
                        if (!_module.cache.mod) _module.cache.mod = {};
                        $form_image = $('<fieldset><p><img></p><p><input type="checkbox" id="image_delete" name="image_delete" value="1"><label for="image_delete"></label></p></fieldset> ');
                        // cache
                        _module.cache.mod.$form_image = $form_image.clone();
                    }

                    $form_image.find('label').html(Globalize.localize('cc_product')['image_delete']).end()
                        .find('img').prop('src', App.url_base+'/uploads/user/_pub/' + data.cc_product.id + '.png').end()
                        .insertAfter($form.find('input[name="image"]').parent());
                }

                //Add 'deleted' field
                if (parseInt(data.cc_product.deleted)) {
                    // cache
                    var $form_deleted;
                    if (_module.cache.mod && _module.cache.mod.$form_deleted) $form_deleted = _module.cache.mod.$form_deleted.clone();
                    else {
                        if (!_module.cache.mod) _module.cache.mod = {};
                        $form_deleted = $('<fieldset><p><input type="checkbox" id="cc-product-add-mod-deleted" name="deleted" value="1"><label for="cc-product-add-mod-deleted"></label></p></fieldset> ');
                        // cache
                        _module.cache.mod.$form_deleted = $form_deleted.clone();
                    }

                    $form_deleted.find('label').html(Globalize.localize('cc_product')['deleted']).end()
                        .insertAfter($form.find('textarea[name="comments"]').parent());
                }

                // form
                $form.prop('action', 'mod')
                    //Add id - hidden text
                    .append($('<input>').attr('type', 'hidden').attr('name','id').attr('value', data.cc_product.id))
                    // input values
                    .find(':input').each( function(i){
                        if (this.name && data.cc_product[this.name]) {
                            if (this.type == "checkbox") $(this).val([data.cc_product[this.name]]);
                            else $(this).val(data.cc_product[this.name]);
                        }
                    }).end()
                    //submit button lang
                    .find('input[type="submit"], button[type="submit"]').val(Globalize.localize('_base')['mod']);

                $('#main').html($win);

                App.ajaxDataProcessComplete();
            };

            // data = JSON { textStatus = "ok" }  OR { textStatus = "error", errors[form_field] = error }
            this.on_submit = function(data)
            {
                console.log('cc_product.mod.on_submit()');
                if (data.textStatus == 'ok') {
                    $('<div>'+Globalize.localize('cc_product')['mod_ok']+'</div>').dialog({
                        title: Globalize.localize('cc_product')['win_title_mod'],
                        buttons: [{ text: 'Ok', click: function(){ App.ajaxUrl(location.href); $(this).dialog( 'destroy' ); } }]
                    });
                }
                else App.errorsDialog(data.errors, Globalize.localize('cc_product'));
                App.ajaxDataProcessComplete();
            }
        };

        this.del = new function()
        {
            /**
             * Create html app-windows parsing App.modules._base.templates.window (title, id)
             * @param data holds form data
             */
            this.init = function(data)
            {
                console.log('cc_product.del.init()');

                App.delConfirmDialog(Globalize.localize('cc_product')['win_title_del'], Globalize.localize('cc_product')['del_confirm'] + '<br>' + data.cc_product.name, data.cc_product.id);

                App.ajaxDataProcessComplete();
            };

            // data = JSON { textStatus = "ok" }  OR { textStatus = "error", errors[form_field] = error }
            this.on_del = function(data)
            {
                console.log('cc_product.del.on_submit()');
                if (data.textStatus == 'ok') {
                    $('<div>'+Globalize.localize('cc_product')['del_ok']+'</div>').dialog({
                        title: Globalize.localize('cc_product')['win_title_del'],
                        buttons: [{ text: 'Ok', click: function(){ App.ajaxUrl(App.request.url_base + '/cc_product/search'); $(this).dialog( 'destroy' ); } }]
                    });
                }
                else App.errorsDialog(data.errors, Globalize.localize('cc_product'));
                App.ajaxDataProcessComplete();
            }
        };

    };

    return my;

}(App || {}));