var App = (function (my) {
    my.modules.cc_purchase = new function()
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
                console.log('cc_purchase.search.init()');
                App.cacheAppWindows();

                // user can approve | close
                var user_permission = [];
                if (data._user && data._user['can_approve']) user_permission['can_approve'] = 1;
                else user_permission['can_approve'] = 0;
                if (data._user && data._user['can_close']) user_permission['can_close'] = 1;
                else user_permission['can_close'] = 0;

                var theaders = ['order_num', 'opened_on', 'closed_on', 'cc_supplier_name', 'cc_product_name', /*'status',*/ 'comments', 'deleted'];
                var $table = App.buildDataTable(theaders, Globalize.localize('cc_purchase'), 'af'); // headers, lang, build footer

                $('#main').html(
                    $('#cache > div[name="app-window"]').clone().prop('id', 'app-window-cc-purchase-search')
                        .find('h1').html(Globalize.localize('cc_purchase')['win_title_list']).end()
                        .find('div.app-window-content').html($table).end()
                );
        
                $table.dataTable({
                    "iDeferLoading": [ data.iTotalDisplayRecords, data.iTotalRecords ],
                    "aaData" : data.aaData,
                    "sAjaxSource": App.url_base + "/cc_purchase/search",
                    "aoColumnDefs": [
                        { "aTargets": [ 0 ], // order_num
                            "mData": function ( source, type, val ) {
                                if (type === 'display') { return '<a href="'+App.url_base+'/cc_purchase/view/?id='+source.id+'" data-ajax="1">'+source.order_num+'</a>' }
                                return source.order_num;
                            }
                        },
                        { "aTargets": [ 1 ], // opened_on
                            "mData": function ( source, type, val ) {
                                if (type === 'display') { return '<a href="'+App.url_base+'/cc_purchase/view/?id='+source.id+'" data-ajax="1">'+source.opened_on+'</a>' }
                                return source.opened_on;
                            }
                        },
                        { "aTargets": [ 2 ], // closed_on
                            "mData": function ( source, type, val ) {
                                if (type === 'display') { return '<a href="'+App.url_base+'/cc_purchase/view/?id='+source.id+'" data-ajax="1">'+source.closed_on+'</a>' }
                                return source.closed_on;
                            }
                        },
                        { "aTargets": [ 3 ], // cc_supplier
                            "mData": function ( source, type, val ) {
                                if (type === 'display') { return '<a href="'+App.url_base+'/cc_supplier/view/?id='+source.cc_supplier_id+'" data-ajax="1">'+source.cc_supplier_name+'</a>' }
                                return source.cc_supplier_name;
                            }
                        },
                        { "aTargets": [ 4 ], // products
                            "bSortable" : false,
                            "sWidth" : '20%',
                            "mData": function ( source, type, val ) {
                                if (type === 'display') {
                                    var $ul = $('<ul/>').addClass('cc-purchase-list-products');
                                    var $li;
                                    $.each(source.products, function(i) {
                                        $li = $('<li/>').append(
                                            $('<span/>').addClass('label').append(
                                                $('<a/>').attr('href', App.url_base+'/cc_product/view?id='+source.products[i]['id']).text(source.products[i]['name'])
                                            )
                                        )
                                            .append('('+source.products[i]['quantity']+'u x $'+Globalize.format(source.products[i]['unit_price'], "n2")+')')
                                            .appendTo($ul);
                                    });
                                    $li = $('<li/>').addClass('total').append(
                                        $('<span/>').addClass('label').html(Globalize.localize('_base')['total'])
                                    ).append(': $'+Globalize.format(source['_total'], "n3")).appendTo($ul);
                                    return $ul.outer();
                                }
                                return source.id;
                            }
                            /*
                             quantity":100,"unit_price":"5.00","quantity_delivered":0,"id":"26","name":"Broches para abrochadora Swingline","deposit":"01","family":"01","item":"073","brand":"02","size":"00","color":"00","barcode":"74711351089","measurement_unit":"( )","comments":" ","deleted":"0","deleted_on":" ","deleted_by":" "}],"_total":500}
                             */
                        },
                        /*
                        { "aTargets": [ 5 ], // status
                            "mData": function ( source, type, val ) {
                                if (type === 'display') {
                                    switch (source.status) {
                                        case '0': return Globalize.localize('cc_purchase')['open'];
                                        case '1': return Globalize.localize('cc_purchase')['approved'];
                                        case '2': return Globalize.localize('cc_purchase')['closed'];
                                    }
                                }
                                return source.status;
                            }
                        },
                        */
                        { "aTargets": [ 5 ], "mData": "comments" },
                        { "aTargets": [ 6 ], // deleted
                            "bSortable" : false,
                            "mData": function ( source, type, val ) {
                                if (type === 'display') { return parseInt(source.deleted)?Globalize.localize('_base')['yes']:Globalize.localize('_base')['no'] }
                                return source.deleted;
                            }
                        },
                        { "aTargets": [ 7 ], // Action
                            "bSortable" : false,
                            "mData": function ( source, type, val ) {
                                if (type === 'display') {
                                    var html = '';
                                    /*
                                    if (parseInt(source.status) < 2 && user_permission['can_close']) html = '<a class="ui-state-default ui-corner-all app-btn" onclick="App.modules.cc_purchase.search.close('+source.id+', $(this).closest(\'tr\'))" title="'+Globalize.localize('cc_purchase')['close']+'"><span class="ui-icon ui-icon-check">'+Globalize.localize('cc_purchase')['close']+'</span></a> ';
                                    else if (parseInt(source.status) < 1 && user_permission['can_approve']) html = '<a class="ui-state-default ui-corner-all app-btn" onclick="App.modules.cc_purchase.search.approve('+source.id+', $(this).closest(\'tr\'))" title="' + Globalize.localize('cc_purchase')['approve'] + '"><span class="ui-icon ui-icon-check">' + Globalize.localize('cc_purchase')['approve'] + '</span></a> ';
                                    */
                                    html += '<a class="ui-state-default ui-corner-all app-btn" href="'+App.url_base+'/cc_purchase/mod/?id='+source.id+'" data-ajax="1" title="'+Globalize.localize('_base')['mod']+'"><span class="ui-icon ui-icon-pencil">'+Globalize.localize('_base')['mod']+'</span></a> ' + 
                                            '<a class="ui-state-default ui-corner-all app-btn" href="'+App.url_base+'/cc_purchase/del/?id='+source.id+'" data-ajax-callback="del" data-push-state="0" title="'+Globalize.localize('_base')['del']+'"><span class="ui-icon ui-icon-closethick">'+Globalize.localize('_base')['del']+'</span></a>';
                                    return html;
                                }
                                return source.id;
                            }
                        }
                    ],
                    "aaSorting": [[1,'desc']], // default sorting
                    "fnFooterCallback": function ( nRow, aaData, iStart, iEnd, aiDisplay ) { // total calculator
                        var iTotal = 0;
                        for (var i=0; i<aaData.length; i++) iTotal += aaData[i]['_total']*1;
                        var nCells = nRow.getElementsByTagName('th');
                        nCells[4].innerHTML = '$'+Globalize.format(iTotal, "n3");
                    }
                })
                    .columnFilter({
                        sRangeFormat : "Del {from} al {to}",
                        sPlaceHolder: "head:after",
                        aoColumns: [
                            { type: "text" }, // order_num
                            { type: "date-range" }, // opened_on
                            { type: "date-range" }, // closed_on
                            { type: "text" }, // cc_supplier
                            { type: "text" }, // cc_products
                            /*
                            { type: "select", values: [ // status
                                { "value":"0","label":Globalize.localize('cc_purchase')['open'] },
                                { "value":"1","label":Globalize.localize('cc_purchase')['approved'] },
                                { "value":"2","label":Globalize.localize('cc_purchase')['closed'] }
                            ] },
                            */
                            null, // comments
                            { type: "select", values: [ // deleted
                                { "value":"0","label":Globalize.localize('_base')['no'] },
                                { "value":"1","label":Globalize.localize('_base')['yes'] }
                            ] },
                            null // action
                        ]
                    });

                App.ajaxDataProcessComplete();
            };
            
            this.approve = function (id, $tr)
            {
                $('body').addClass("loading");
                $.ajax({
                    global: false, dataType: "json",
                    url: App.request.url_base + '/cc_purchase/set_status',
                    data: {ajax: '1', data_type: 'json', id: id, status: 1}
                })
                .done(function (data) {
                    if (data.textStatus == 'ok') {
                        $('<div>' + Globalize.localize('cc_purchase')['mod_ok'] + '</div>').dialog({
                            title: Globalize.localize('cc_purchase')['win_title_mod'],
                            buttons: [{text: 'Ok', click: function () {
                                $(this).dialog('destroy');
                            }}]
                        });
                        $tr.find('td:nth-child(6)').html(Globalize.localize('cc_purchase')['approved']).end()
                            .find('td:last-child').find('a:first-child').remove();
                    }
                })
                .always(function () {
                    $('body').removeClass("loading");
                });
            };

            this.close = function (id, $tr)
            {
                $('body').addClass("loading");
                $.ajax({
                    global: false, dataType: "json",
                    url: App.request.url_base + '/cc_purchase/set_status',
                    data: {ajax: '1', data_type: 'json', id: id, status: 2}
                })
                .done(function (data) {
                    if (data.textStatus == 'ok') {
                        $('<div>' + Globalize.localize('cc_purchase')['mod_ok'] + '</div>').dialog({
                            title: Globalize.localize('cc_purchase')['win_title_mod'],
                            buttons: [{text: 'Ok', click: function () {
                                $(this).dialog('destroy');
                            }}]
                        });
                        $tr.find('td:nth-child(6)').html(Globalize.localize('cc_purchase')['closed']).end()
                            .find('td:last-child').find('a:first-child').remove();
                    }
                })
                .always(function () {
                    $('body').removeClass("loading");
                });
            };


        };

        this.view = new function()
        {
            /**
             * Create html app-windows parsing App.modules._base.templates.window (title, id)
             */
            this.init = function(data)
            {
                console.log('cc_purchase.view.init()');
                // Required files
                var missing_files = false;
                if (!App.templates.cc_purchase || !App.templates.cc_purchase.view) { App.loadTemplate('cc_purchase', 'view'); missing_files = true; }
                if (missing_files) return;

                App.cacheAppWindows();

                $('#main').html(
                    $('#cache > div[name="app-window"]').clone().prop('id', 'app-window-cc-purchase-view').find('h1').html(Globalize.localize('cc_purchase')['win_title_view']).end()
                        .find('div.app-window-content').html(Mustache.render(App.templates.cc_purchase.view.cc_purchase, $.extend({},Globalize.localize('_base'), Globalize.localize('cc_purchase'), data))).end()
                );
        
                App.ajaxDataProcessComplete();
            };
            
            this.approve = function(id)
            {
                $('body').addClass("loading");
                $.ajax({
                    global : false, dataType: "json",
                    url: App.request.url_base + '/cc_purchase/set_status',
                    data: {ajax: '1', data_type: 'json',id: id, status: 1}
                })
                .done(function (data) {
                    if (data.textStatus == 'ok') {
                        $('<div>'+Globalize.localize('cc_purchase')['mod_ok']+'</div>').dialog({
                            title: Globalize.localize('cc_purchase')['win_title_mod'],
                            buttons: [{ text: 'Ok', click: function(){ $(this).dialog( 'destroy' ); } }]
                        });
                        $('li.approve').remove();
                        $('li.status').contents().filter(function() { return this.nodeType == 3; }).replaceWith(Globalize.localize('cc_purchase')['approved']);
                    }
                })
                .always(function () { $('body').removeClass("loading"); });
            };

            this.close = function(id)
            {
                $('body').addClass("loading");
                $.ajax({
                    global : false, dataType: "json",
                    url: App.request.url_base + '/cc_purchase/set_status',
                    data: {ajax: '1', data_type: 'json',id: id, status: 2}
                })
                .done(function (data) {
                    if (data.textStatus == 'ok') {
                        $('<div>'+Globalize.localize('cc_purchase')['mod_ok']+'</div>').dialog({
                            title: Globalize.localize('cc_purchase')['win_title_mod'],
                            buttons: [{ text: 'Ok', click: function(){ $(this).dialog( 'destroy' ); } }]
                        });
                        $('li.approve, li.close').remove();
                        $('li.status').contents().filter(function() { return this.nodeType == 3; }).replaceWith(Globalize.localize('cc_purchase')['closed']);
                        $('li.closed-on').contents().filter(function() { return this.nodeType == 3; }).replaceWith(data.closed_on);
                        $('li.closed-by').contents().filter(function() { return this.nodeType == 3; }).replaceWith(data.closed_by);
                    }
                })
                .always(function () { $('body').removeClass("loading"); });
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
                console.log('cc_purchase.add.init()');

                if (_module.add_mod.missing_files()) return;

                App.cacheAppWindows();

                _module.add_mod.products.length = 0;

                // CC_Purchase form
                var $win_add_mod = _module.add_mod.get_win_cc_purchase(data)
                    .find('h1').html(Globalize.localize('cc_purchase')['win_title_add']).end();

                var $form = $win_add_mod.find('form');

                // action + submit button lang
                $form.prop('action', 'add')
                    .find('input[name="opened_on"]').attr('value',App.getDate()).end()
                    .find('input[name="closed_on"]').attr('value',App.getDate()).end()
                    .find('input[type="submit"], button[type="submit"]').val(Globalize.localize('_base')['add']);

                $('#main').html($win_add_mod);
                App.ajaxDataProcessComplete();
            };

            // data = JSON { textStatus = "ok" } OR { textStatus = "error", errors[form_field] = error }
            this.on_submit = function(data)
            {
                console.log('cc_purchase.add.on_submit()');
                if (data.textStatus == 'ok') {
                    $('<div>'+Globalize.localize('cc_purchase')['add_ok']+'</div>').dialog({
                        title: Globalize.localize('cc_purchase')['win_title_add'],
                        buttons: [{ text: 'Ok', click: function(){ _module.add_mod.reset(); $( this ).dialog( 'destroy' ); } }]
                    });
                }
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
                console.log('cc_purchase.mod.init()');

                if (_module.add_mod.missing_files()) return;

                App.cacheAppWindows();

                // user can approve | close
                var user_permission = [];
                if (data._user && data._user['can_approve']) user_permission['can_approve'] = 1;
                else user_permission['can_approve'] = 0;
                if (data._user && data._user['can_close']) user_permission['can_close'] = 1;
                else user_permission['can_close'] = 0;

                _module.add_mod.products.length = 0;

                // CC_Purchase form
                var $win_add_mod = _module.add_mod.get_win_cc_purchase(data)
                    .find('h1').html(Globalize.localize('cc_purchase')['win_title_mod']).end();

                var $form = $win_add_mod.find('form');

                //Add 'status' field
                if (data.cc_purchase.status) {
                    var $form_status;
                    var $form_status_options = [];
                    $form_status_options[0] = $('<option value="0">'+Globalize.localize('cc_purchase')['open']+'</option>');
                    if (data.cc_purchase.status > 0 || user_permission['can_approve']) $form_status_options[1] = $('<option value="1">'+Globalize.localize('cc_purchase')['to_approve']+'</option>');
                    if (data.cc_purchase.status > 1 || user_permission['can_close']) $form_status_options[2] = $('<option value="2">'+Globalize.localize('cc_purchase')['closed']+'</option>');
                    
                    $form_status = $('<p><label for="cc-purchase-add-mod-status">'+Globalize.localize('cc_purchase')['status']+'</label><select id="cc-purchase-add-mod-status" name="status"></select></p>');
                    $form_status.find('select').html($form_status_options);
                    $form_status.insertAfter($form.find('textarea[name="comments"]').parent());
                }

                //Add 'deleted' field
                if (parseInt(data.cc_purchase.deleted)) {
                    // cache
                    var $form_deleted;
                    if (_module.cache.mod && _module.cache.mod.$form_deleted) $form_deleted = _module.cache.mod.$form_deleted.clone();
                    else {
                        if (!_module.cache.mod) _module.cache.mod = {};
                        $form_deleted = $('<fieldset><p><input type="checkbox" id="cc-purchase-add-mod-deleted" name="deleted" value="1" checked><label for="cc-purchase-add-mod-deleted"></label></p></fieldset> ');
                        // cache
                        _module.cache.mod.$form_deleted = $form_deleted.clone();
                    }

                    $form_deleted.find('label').html(Globalize.localize('cc_purchase')['deleted']).end()
                        .insertAfter($form.find('textarea[name="comments"]').parent());
                }

                //fill form
                $form.prop('action', 'mod')
                    //Add id - hidden text
                    .append($('<input>').attr('type', 'hidden').attr('name','id').attr('value', data.cc_purchase.id))
                    // input values
                    .find(':input').each( function(i){
                        if (this.name && data.cc_purchase[this.name]) {
                            $(this).val(data.cc_purchase[this.name]);
                        }
                    }).end()
                    //submit button lang
                    .find('input[type="submit"], button[type="submit"]').val(Globalize.localize('_base')['mod']).end()
                    // products
                    .find('tbody').html(
                            Mustache.render(App.templates.cc_purchase.add_mod.products_tbody, $.extend({},Globalize.localize('_base'), Globalize.localize('cc_product'), data.cc_purchase), App.templates._base)
                    ).parent().show();

                // add products to array to avoid duplicates
                jQuery.each(data.cc_purchase.products, function(index, cc_p) { _module.add_mod.products.push(parseInt(cc_p['id'])); });

                $('#main').html($win_add_mod);
                App.ajaxDataProcessComplete();
            };

            // data = JSON { textStatus = "ok" } or { textStatus = "error", errors[form_field] = error }
            this.on_submit = function(data)
            {
                console.log('cc_purchase.mod.on_submit()');
                if (data.textStatus == 'ok') {
                    $('<div>'+Globalize.localize('cc_purchase')['mod_ok']+'</div>').dialog({
                        title: Globalize.localize('cc_purchase')['win_title_mod'],
                        buttons: [{ text: 'Ok', click: function(){ $(this).dialog( 'destroy' ); } }]
                    });
                }
                App.ajaxDataProcessComplete();
            }
        };

        // shared functions
        this.add_mod = new function()
        {
            // hold produc list to avoid duplicates
            this.products = [];

            // check for required files
            this.missing_files = function()
            {
                var missing_files = false;
                if (!App.forms.cc_purchase || !App.forms.cc_purchase.add_mod)  { App.loadDForm('cc_purchase', 'add_mod'); missing_files = true; }
                if (!App.templates.cc_purchase || !App.templates.cc_purchase.add_mod)  { App.loadTemplate('cc_purchase', 'add_mod'); missing_files = true; }
                if (!Globalize.localize('cc_supplier')) { App.loadLang('cc_supplier'); missing_files = true; }
                if (!Globalize.localize('cc_product')) { App.loadLang('cc_product'); missing_files = true; }
                return missing_files;
            };

            // Reset form
            this.reset = function()
            {
                console.log('cc_purchase.add_mod.reset_form()');
                _module.add_mod.products.length = 0;
                $('#main > div[name="app-window-cc-purchase-add-mod"] > .app-window-content')
                    .find('table').hide()
                    .find('tbody').empty().end()
                    .end()
                    .find('form')
                    .find('input[name="cc_supplier_id"]').val('').end()
                    .find('input[name="cc_supplier_name"]').removeClass('ui-autocomplete-checked').end()
                    [0].reset()
            };

            // CC_Purchase form
            this.get_win_cc_purchase = function(data)
            {
                var $form = $('<form></form>').dform(App.forms.cc_purchase.add_mod)
                .find('input[name="cc_supplier_name"]')
                    .autocomplete( "option", {
                        source: function( request, response ) {
                            $.ajax({
                                global : false, dataType: "json",
                                url: App.request.url_base + '/cc_supplier/search/',
                                data: {
                                    ajax: '1', data_type: 'json', rows_per_pag : '10',
                                    data_format: 'datatable',
                                    name: request.term
                                },
                                success: function( data ) {
                                    response( $.map( data.aaData, function( item ) {
                                        item.label = item.name;
                                        item.desc = item.address;
                                        return item;
                                    }));
                                }
                            });
                        },
                        change : function( event, ui ) {
                            if (!ui.item) {
                                $form.find('input[name="cc_supplier_id"]').val('').end()
                                    .find('input[name="cc_supplier_name"]').removeClass('ui-autocomplete-checked');
                            }
                        },
                        select: function( event, ui ) {
                            $form.find('input[name="cc_supplier_id"]').val(ui.item.id).end()
                                .find('input[name="cc_supplier_name"]').addClass('ui-autocomplete-checked').val(ui.item.label);
                            return false;
                        }
                }).end()
                .find('input[name="cc_product_name"]')
                    .autocomplete( "option", {
                        source: function( request, response ) {
                            $.ajax({
                                global : false, dataType: "json",
                                url: App.request.url_base + '/cc_product/search/',
                                data: {
                                    ajax: '1', data_type: 'json', rows_per_pag : '10',
                                    name_barcode: request.term
                                },
                                success: function( data ) {
                                    response( $.map( data.aaData, function( item ) {
                                        item.label = item.name;
                                        item.quantity = item.unit_price = item.quantity_delivered = 0;
                                        item._removable = true;
                                        return item;
                                    }));
                                }
                            });
                        },
                        select: function( event, ui ) {
                            _module.add_mod.cc_product_add(ui.item);
                            $(this).val('');
                            return false;
                        }
                })
                .end()
                .find('div[name="products"]').html(Mustache.render(App.templates.cc_purchase.add_mod.products_table, $.extend({},Globalize.localize('_base'), Globalize.localize('cc_product'), data), App.templates._base)).end();

                return $('#cache > div[name="app-window"]').clone().attr('name', 'app-window-cc-purchase-add-mod')
                    .find('div.app-window-content').html($form).end();
            };

            // CC_Product Add - jQuery UI autocomplete item
            this.cc_product_add = function(item)
            {
                console.log('cc_purchase.add_mod.cc_product_add()');
                if (_module.add_mod.products.indexOf(parseInt(item.id)) != -1) return;
                _module.add_mod.products.push(parseInt(item.id));

                if (item._image) item.image_src = App.url_base+'/uploads/cc_product/_pub/'+item.id+'.jpg';
                else item.image_src = App.url_base+'/modules/cc_product/_pub/img/not_found.jpg';
                $('#main > div[name="app-window-cc-purchase-add-mod"] form table').show().find('tbody').append(Mustache.render(App.templates.cc_purchase.add_mod.products_tbody, $.extend({},Globalize.localize('_base'), Globalize.localize('cc_product'), {products: [item]}), App.templates._base));
            };

            // CC_Product Remove - clicked tr
            this.cc_product_remove = function($tr)
            {
                console.log('cc_purchase.add_mod.cc_product_remove()');
                // remove it from products array
                _module.add_mod.products.splice(_module.add_mod.products.indexOf(parseInt($tr.data('id'))), 1);
                $tr.remove();
                if (!_module.add_mod.products.length) $('#main > div[name="app-window-cc-purchase-add-mod"] form table').hide();
            };
        };

        this.del = new function()
        {
            /**
             * Create html app-windows parsing App.modules._base.templates.window (title, id)
             * @param data holds form data
             */
            this.init = function(data)
            {
                console.log('cc_purchase.del.init()');
                App.delConfirmDialog(Globalize.localize('cc_purchase')['win_title_del'], Globalize.localize('cc_purchase')['del_confirm'], data.cc_purchase.id);
                App.ajaxDataProcessComplete();
            };

            // data = JSON { textStatus = "ok" } or { textStatus = "error", errors[form_field] = error }
            this.on_del = function(data)
            {
                console.log('cc_purchase.del.on_del()');
                if (data.textStatus == 'ok') {
                    $('<div>'+Globalize.localize('cc_purchase')['del_ok']+'</div>').dialog({
                        title: Globalize.localize('cc_purchase')['win_title_del'],
                        buttons: [{ text: 'Ok', click: function(){ App.ajaxUrl(location.href); $(this).dialog( 'destroy' ); } }]
                    });
                }
                App.ajaxDataProcessComplete();
            }
        };

    };

    return my;

}(App || {}));