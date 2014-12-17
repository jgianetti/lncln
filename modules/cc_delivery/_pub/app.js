var App = (function (my) {
    my.modules.cc_delivery = new function()
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
                console.log('cc_delivery.search.init()');

                App.cacheAppWindows();
                var theaders = ['opened_on', 'closed_on', 'user_name', 'cc_category', 'cc_product_name', 'comments', 'deleted'];
                var $table = App.buildDataTable(theaders, Globalize.localize('cc_delivery'), 'af'); // headers, lang, extras(m[od]d[del]u[ndelete]f[ooter]

                $('#main').html(
                    $('#cache > div[name="app-window"]').clone().prop('id', 'app-window-cc-delivery-search')
                        .find('h1').html(Globalize.localize('cc_delivery')['win_title_list']).end()
                        .find('div.app-window-content').html($table).end()
                );

                $table.dataTable({
                    "iDeferLoading": [ data.iTotalDisplayRecords, data.iTotalRecords ],
                    "aaData" : data.aaData,
                    "sAjaxSource": App.url_base + "/cc_delivery/search",
                    "aoColumnDefs": [
                        { "aTargets": [ 0 ], // opened_on
                            "mData": function ( source, type, val ) {
                                if (type === 'display') { return '<a href="'+App.url_base+'/cc_delivery/view/?id='+source.id+'" data-ajax="1">'+source.opened_on+'</a>' }
                                return source.opened_on;
                            }
                        },
                        { "aTargets": [ 1 ], // closed_on
                            "mData": function ( source, type, val ) {
                                if (type === 'display') { return '<a href="'+App.url_base+'/cc_delivery/view/?id='+source.id+'" data-ajax="1">'+source.closed_on+'</a>' }
                                return source.closed_on;
                            }
                        },
                        { "aTargets": [ 2 ], // user
                            "mData": function ( source, type, val ) {
                                if (type === 'display') { return '<a href="'+App.url_base+'/user/view/?id='+source.user_id+'" data-ajax="1">'+source.user_name+'</a>' }
                                return source.user_name;
                            }
                        },
                        { "aTargets": [ 3 ], "mData": "cc_category_name" },
                        { "aTargets": [ 4 ], // products
                            "bSortable" : false,
                            "sWidth" : '20%',
                            "mData": function ( source, type, val ) {
                                if (type === 'display') {
                                    var $ul = $('<ul/>').addClass('cc-delivery-list-products');
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
                                    ).append(': $'+Globalize.format(source['_total'], "n2")).appendTo($ul);
                                    return $ul.outer();
                                }
                                return source.id;
                            }
                            /*
                             quantity":100,"unit_price":"5.00","quantity_delivered":0,"id":"26","name":"Broches para abrochadora Swingline","deposit":"01","family":"01","item":"073","brand":"02","size":"00","color":"00","barcode":"74711351089","measurement_unit":"( )","comments":" ","deleted":"0","deleted_on":" ","deleted_by":" "}],"_total":500}
                             */
                        },
                        { "aTargets": [ 5 ], "mData": "comments" },
                        { "aTargets": [ 6 ], // deleted
                            "bSortable" : false,
                            "mData": function ( source, type, val ) {
                                if (type === 'display') { return source.deleted?Globalize.localize('_base')['yes']:Globalize.localize('_base')['no'] }
                                return source.deleted;
                            }
                        },
                        { "aTargets": [ 7 ], // Action
                            "bSortable" : false,
                            "mData": function ( source, type, val ) {
                                if (type === 'display') {
                                    var html = '<a class="ui-state-default ui-corner-all app-btn" href="'+App.url_base+'/cc_delivery/del/?id='+source.id+'" data-ajax-callback="del" data-push-state="0" title="' + Globalize.localize('_base')['del'] + '"><span class="ui-icon ui-icon-closethick">' + Globalize.localize('_base')['del'] + '</span></a>';
                                    if (source.deleted) html += '<a class="ui-state-default ui-corner-all app-btn" href="'+App.url_base+'/cc_delivery/undel/?id='+source.id+'" data-ajax-callback="undel" data-push-state="0" title="' + Globalize.localize('_base')['undel'] + '"><span class="ui-icon ui-icon-check">' + Globalize.localize('_base')['undel'] + '</span></a>';
                                    return html;
                                }
                                return source.id;
                            }
                        },
                    ],
                    "aaSorting": [[0,'desc']], // default sorting
                    "fnFooterCallback": function ( nRow, aaData, iStart, iEnd, aiDisplay ) { // total calculator
                        var iTotal = 0;
                        console.log(aaData);
                        console.log(aiDisplay);
                        for (var i=0; i<aaData.length; i++) iTotal += aaData[i]['_total']*1;
                        var nCells = nRow.getElementsByTagName('th');
                        nCells[4].innerHTML = '$'+Globalize.format(iTotal, "n3");
                    }
                })
                    .columnFilter({
                        sRangeFormat : "Del {from} al {to}",
                        sPlaceHolder: "head:after",
                        aoColumns: [
                            { type: "date-range" }, // opened_on
                            { type: "date-range" }, // closed_on
                            { type: "text" }, // user_name
                            { type: "select", values: data.cc_categories },  // cc_category
                            { type: "text" }, // cc_product_name
                            null, // comments
                            { type: "select", values: [ // deleted
                                { "value":"0","label":Globalize.localize('_base')['no'] },
                                { "value":"1","label":Globalize.localize('_base')['yes'] }
                            ] },
                            null // Action
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
                console.log('cc_delivery.view.init()');
                // Required files
                var missing_files = false;
                if (!App.templates.cc_delivery || !App.templates.cc_delivery.view) { App.loadTemplate('cc_delivery', 'view'); missing_files = true; }
                if (missing_files) return;

                App.cacheAppWindows();

                $('#main').html(
                    $('#cache > div[name="app-window"]').clone().prop('id', 'app-window-cc-delivery-view').find('h1').html(Globalize.localize('cc_delivery')['win_title_view']).end()
                        .find('div.app-window-content').html(Mustache.render(App.templates.cc_delivery.view.cc_delivery, $.extend({},Globalize.localize('_base'), Globalize.localize('cc_delivery'), data))).end()
                );

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
                console.log('cc_delivery.add.init()');

                if (_module.add_mod.missing_files()) return;

                App.cacheAppWindows();

                // CC_Delivery form
                var $win_add_mod = _module.add_mod.get_win_cc_delivery(data)
                    .find('h1').html(Globalize.localize('cc_delivery')['win_title_add']).end();

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
                console.log('cc_delivery.add.on_submit()');
                if (data.textStatus == 'ok') {
                    $('<div>'+Globalize.localize('cc_delivery')['add_ok']+'</div>').dialog({
                        title: Globalize.localize('cc_delivery')['win_title_add'],
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
                console.log('cc_delivery.mod.init()');

                if (_module.add_mod.missing_files()) return;

                App.cacheAppWindows();

                // CC_Delivery form
                var $win_add_mod = _module.add_mod.get_win_cc_delivery(data)
                    .find('h1').html(Globalize.localize('cc_delivery')['win_title_mod']).end();

                var $form = $win_add_mod.find('form');

                //Add 'deleted' field
                if (data.cc_delivery.deleted) {
                    // cache
                    var $form_deleted;
                    if (_module.cache.mod && _module.cache.mod.$form_deleted) $form_deleted = _module.cache.mod.$form_deleted.clone();
                    else {
                        if (!_module.cache.mod) _module.cache.mod = {};
                        $form_deleted = $('<fieldset><p><input type="checkbox" id="cc-delivery-add-mod-deleted" name="deleted" value="1" checked><label for="cc-delivery-add-mod-deleted"></label></p></fieldset> ');
                        // cache
                        _module.cache.mod.$form_deleted = $form_deleted.clone();
                    }

                    $form_deleted.find('label').html(Globalize.localize('cc_delivery')['deleted']).end()
                        .insertAfter($form.find('textarea[name="comments"]').parent());
                }

                //fill form
                $form.prop('action', 'mod')
                    //Add id - hidden text
                    .append($('<input>').attr('type', 'hidden').attr('name','id').attr('value', data.cc_delivery.id))
                    // input values
                    .find(':input').each( function(i){
                        if (this.name && data.cc_delivery[this.name]) {
                            $(this).val(data.cc_delivery[this.name]);
                        }
                    }).end()
                    //submit button lang
                    .find('input[type="submit"], button[type="submit"]').val(Globalize.localize('_base')['mod']).end()
                    // products
                    .find('tbody').html(
                            Mustache.render(App.templates.cc_delivery.add_mod.product, $.extend({},Globalize.localize('_base'), Globalize.localize('cc_product'), data.cc_delivery), App.templates._base)
                    ).parent().show();

                // add products to array to avoid duplicates
                jQuery.each(data.cc_delivery.products, function(index, cc_p) { _module.add_mod.products.push(parseInt(cc_p['id'])); });

                $('#main').html($win_add_mod);
                App.ajaxDataProcessComplete();
            };

            // data = JSON { textStatus = "ok" } or { textStatus = "error", errors[form_field] = error }
            this.on_submit = function(data)
            {
                console.log('cc_delivery.mod.on_submit()');
                if (data.textStatus == 'ok') {
                    $('<div>'+Globalize.localize('cc_delivery')['mod_ok']+'</div>').dialog({
                        title: Globalize.localize('cc_delivery')['win_title_mod'],
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
                if (!App.forms.cc_delivery || !App.forms.cc_delivery.add_mod)  { App.loadDForm('cc_delivery', 'add_mod'); missing_files = true; }
                if (!Globalize.localize('user')) { App.loadLang('user'); missing_files = true; }
                if (!App.templates.cc_delivery || !App.templates.cc_delivery.add_mod)  { App.loadTemplate('cc_delivery', 'add_mod'); missing_files = true; }
                if (!Globalize.localize('cc_product')) { App.loadLang('cc_product'); missing_files = true; }
                return missing_files;
            };

            // CC_Delivery form
            this.get_win_cc_delivery = function(data)
            {
                var $form = $('<form></form>').dform(App.forms.cc_delivery.add_mod)
                    .find('input[name="user_name"]')
                    .autocomplete( "option", {
                        source: function( request, response ) {
                            $.ajax({
                                global : false, dataType: "json",
                                url: App.request.url_base + '/user/search/',
                                data: {
                                    ajax: '1', data_type: 'json', rows_per_pag : '10',
                                    filters: {'fullname' : request.term}
                                },
                                success: function( data ) {
                                    response( $.map( data.users, function( item ) {
                                        item.label = item.last_name + ', ' + item.name;
                                        item.desc = item.dni;
                                        return item;
                                    }));
                                }
                            });
                        },
                        change : function( event, ui ) {
                            if (!ui.item) {
                                $form.find('input[name="user_id"]').val('').end()
                                    .find('input[name="user_name"]').removeClass('ui-autocomplete-checked');
                            }
                        },
                        select: function( event, ui ) {
                            $form.find('input[name="user_id"]').val(ui.item.id).end()
                                .find('input[name="user_name"]').addClass('ui-autocomplete-checked').val(ui.item.label);
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
                    .find('div[name="products"]')
                    .html(Mustache.render(App.templates.cc_delivery.add_mod.products_table, $.extend({},Globalize.localize('_base'), Globalize.localize('cc_product'), data), App.templates._base))
                    .end();

                return $('#cache > div[name="app-window"]').clone().attr('name', 'app-window-cc-delivery-add-mod')
                    .find('div.app-window-content').html($form).end();
            };

            this.reset = function()
            {
                console.log('cc_delivery.add_mod.reset_form()');
                _module.add_mod.products = [];
                $('#main > div[name="app-window-cc-delivery-add-mod"] > .app-window-content')
                    .find('table').hide()
                        .find('tbody').empty().end()
                    .end()
                    .find('form')
                        .find('input[name="user_id"]').val('').end()
                        .find('input[name="user_name"]').removeClass('ui-autocomplete-checked').end()
                        [0].reset()
            };


            // CC_Product Add - jQuery UI autocomplete item
            this.cc_product_add = function(item)
            {
                console.log('cc_delivery.add_mod.cc_product_add()');
                if (_module.add_mod.products.indexOf(parseInt(item.id)) != -1) return;
                _module.add_mod.products.push(parseInt(item.id));

                if (item._image) item.image_src = App.url_base+'/uploads/cc_product/_pub/'+item.id+'.jpg';
                else item.image_src = App.url_base+'/modules/cc_product/_pub/img/not_found.jpg';
                $('#main > div[name="app-window-cc-delivery-add-mod"] form table').show().find('tbody').append(Mustache.render(App.templates.cc_delivery.add_mod.products_tbody, $.extend({},Globalize.localize('_base'), Globalize.localize('cc_product'), {products: [item]}), App.templates._base));
            };

            // CC_Product Remove - clicked tr
            this.cc_product_remove = function($tr)
            {
                console.log('cc_delivery.add_mod.cc_product_remove()');
                // remove it from products array
                _module.add_mod.products.splice(_module.add_mod.products.indexOf(parseInt($tr.data('id'))), 1);
                $tr.remove();
                if (!_module.add_mod.products.length) $('#main > div[name="app-window-cc-delivery-add-mod"] form table').hide();
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
                console.log('cc_delivery.del.init()');
                App.delConfirmDialog(Globalize.localize('cc_delivery')['win_title_del'], Globalize.localize('cc_delivery')['del_confirm'], data.cc_delivery.id);
                App.ajaxDataProcessComplete();
            };

            // data = JSON { textStatus = "ok" } or { textStatus = "error", errors[form_field] = error }
            this.on_del = function(data)
            {
                console.log('cc_delivery.del.on_del()');
                if (data.textStatus == 'ok') {
                    $('<div>'+Globalize.localize('cc_delivery')['del_ok']+'</div>').dialog({
                        title: Globalize.localize('cc_delivery')['win_title_del'],
                        buttons: [{ text: 'Ok', click: function(){ App.ajaxUrl(location.href); $(this).dialog( 'destroy' ); } }]
                    });
                }
                else App.errorsDialog(data.errors, Globalize.localize('cc_delivery'));
                App.ajaxDataProcessComplete();
            }
        };

        this.undel = new function()
        {
            /**
             * Create html app-windows parsing App.modules._base.templates.window (title, id)
             * @param data holds form data
             */
            this.init = function(data)
            {
                console.log('cc_delivery.del.init()');
                App.undelConfirmDialog(Globalize.localize('cc_delivery')['win_title_undel'], Globalize.localize('cc_delivery')['undel_confirm'], data.cc_delivery.id);
                App.ajaxDataProcessComplete();
            };

            // data = JSON { textStatus = "ok" } or { textStatus = "error", errors[form_field] = error }
            this.on_undel = function(data)
            {
                console.log('cc_delivery.del.on_undel()');
                if (data.textStatus == 'ok') {
                    $('<div>'+Globalize.localize('cc_delivery')['undel_ok']+'</div>').dialog({
                        title: Globalize.localize('cc_delivery')['win_title_undel'],
                        buttons: [{ text: 'Ok', click: function(){ App.ajaxUrl(location.href); $(this).dialog( 'destroy' ); } }]
                    });
                }
                else App.errorsDialog(data.errors, Globalize.localize('cc_delivery'));
                App.ajaxDataProcessComplete();
            }
        };

    };

    return my;

}(App || {}));