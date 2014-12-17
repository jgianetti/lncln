var App = (function (my) {
    my.modules.cc_supplier = new function()
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
                console.log('cc_supplier.search.init()');
                App.cacheAppWindows();

                var theaders = ['image', 'name', 'address', 'phone', 'email', 'comments', 'deleted'];
                var $table = App.buildDataTable(theaders, Globalize.localize('cc_supplier'), 'a');
                $table.addClass('td-first-image');

                $('#main').html(
                    $('#cache > div[name="app-window"]').clone().prop('id', 'app-window-cc-supplier-search')
                        .find('h1').html(Globalize.localize('cc_supplier')['win_title_list']).end()
                        .find('div.app-window-content').html($table).end()
                );

                $table.dataTable({
                    "iDeferLoading": [ data.iTotalDisplayRecords, data.iTotalRecords ],
                    "aaData" : data.aaData,
                    "sAjaxSource": App.url_base + "/cc_supplier/search",
                    "aoColumnDefs": [
                        { "aTargets": [ 0 ], // iamge
                            "bSortable" : false,
                            "mData": function ( source, type, val ) {
                                if (type === 'display') { return '<img src="'+source.image_src+'">' }
                                return source.image_src;
                            }
                        },
                        { "aTargets": [ 1 ], // name
                            "mData": function ( source, type, val ) {
                                if (type === 'display') { return '<a href="'+App.url_base+'/cc_supplier/view/?id='+source.id+'" data-ajax="1">'+source.name+'</a>' }
                                return source.name;
                            }
                        },
                        { "aTargets": [ 2 ], "mData": "address" },
                        { "aTargets": [ 3 ], "mData": "phone" },
                        { "aTargets": [ 4 ], "mData": "email" },
                        { "aTargets": [ 5 ], "mData": "comments" },
                        { "aTargets": [ 6 ], // deleted
                            "bSortable" : false,
                            "mData": function ( source, type, val ) {
                                if (type === 'display') { return source.deleted?Globalize.localize('_base')['yes']:Globalize.localize('_base')['no'] }
                                return source.deleted;
                            }
                        },
                        { "aTargets": [ 7 ], // Mod
                            "bSortable" : false,
                            "mData": function ( source, type, val ) {
                                if (type === 'display') {
                                    return '<a class="ui-state-default ui-corner-all app-btn" href="'+App.url_base+'/cc_supplier/mod/?id='+source.id+'" data-ajax="1" title="' + Globalize.localize('_base')['mod'] + '"><span class="ui-icon ui-icon-pencil">' + Globalize.localize('_base')['mod'] + '</span></a> ' +
                                            '<a class="ui-state-default ui-corner-all app-btn" href="'+App.url_base+'/cc_supplier/del/?id='+source.id+'" data-ajax-callback="del" data-push-state="0" title="' + Globalize.localize('_base')['del'] + '"><span class="ui-icon ui-icon-closethick">' + Globalize.localize('_base')['del'] + '</span></a>';
                                }
                                return source.id;
                            }
                        }
                    ],
                    "aaSorting": [[1,'asc']], // default sorting
                })
                    .columnFilter({
                        sPlaceHolder: "head:after",
                        aoColumns: [
                            null, // image
                            { type: "text" }, // name
                            { type: "text" }, // address
                            { type: "text" }, // phone
                            { type: "text" }, // email
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

        };

        this.view = new function()
        {
            /**
             * Create html app-windows parsing App.modules._base.templates.window (title, id)
             */
            this.init = function(data)
            {
                console.log('cc_supplier.view.init()');

                // Required files
                var missing_files = false;
                if (!App.templates.cc_supplier || !App.templates.cc_supplier.view) { App.loadTemplate('cc_supplier', 'view'); missing_files = true; }
                if (!Globalize.localize('cc_purchase')) { my.loadLang('cc_purchase'); missing_files = true; }
                if (missing_files) return;

                App.cacheAppWindows();

                var theaders = ['order_num', 'opened_on', 'closed_on', 'cc_product_name', 'comments'];

                var $table = App.buildDataTable(theaders, Globalize.localize('cc_purchase'), 'f');

                $('#main').html(
                    $('#cache > div[name="app-window"]').clone().prop('id', 'app-window-cc-supplier-view')
                        .find('h1').html(Globalize.localize('cc_supplier')['win_title_view']).end()
                        .find('div.app-window-content').html(Mustache.render(App.templates.cc_supplier.view.cc_supplier, $.extend({},Globalize.localize('_base'), Globalize.localize('cc_supplier'), data))).end()
                ).append(
                    $('#cache > div[name="app-window"]').clone().prop('id', 'app-window-cc-purchase-list')
                        .find('h1').html(Globalize.localize('cc_purchase')['win_title_list']).end()
                        .find('div.app-window-content').html($table).end()
                );

                $table.dataTable({
                    "bDeferRender": false,
                    "sAjaxSource": App.url_base + "/cc_purchase/search",
                    "fnServerParams": function (aoData) {
                        aoData.push(
                            { "name": "ajax", "value": 1 },
                            { "name": "data_type", "value": "json" },
                            { "name": "theaders", "value": theaders },
                            { "name": "cc_supplier_id", "value": data.cc_supplier.id },
                            { "name": "deleted", "value": 0 }
                        );
                    },
                    "aoColumnDefs": [
                        { "aTargets": [ 0 ], // opened_on
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
                        { "aTargets": [ 3 ], // products
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
                                            .append('('+source.products[i]['quantity']+'u x $'+source.products[i]['unit_price']+')')
                                            .appendTo($ul);
                                    });
                                    $li = $('<li/>').addClass('total').append(
                                        $('<span/>').addClass('label').html(Globalize.localize('_base')['total'])
                                    ).append(': $'+source['_total']).appendTo($ul);
                                    return $ul.outer();
                                }
                                return source.id;
                            }
                        },
                        { "aTargets": [ 4 ], "mData": "comments" }
                    ],
                    "aaSorting": [[1,'desc']], // default sorting
                    "fnFooterCallback": function ( nRow, aaData, iStart, iEnd, aiDisplay ) { // total calculator
                        var iTotal = 0;
                        for (var i=iStart; i<iEnd ; i++) iTotal += aaData[aiDisplay[i]]['_total']*1;
                        var nCells = nRow.getElementsByTagName('th');
                        nCells[3].innerHTML = '$'+iTotal;
                    }
                })
                    .columnFilter({
                        sRangeFormat : "Del {from} al {to}",
                        sPlaceHolder: "head:after",
                        aoColumns: [
                            { type: "text" }, // order_num
                            { type: "date-range" }, // opened_on
                            { type: "date-range" }, // closed_on
                            { type: "text" }, // cc_product_name
                            null, // comments
                            { type: "select", values: [ // deleted
                                { "value":"0","label":Globalize.localize('_base')['no'] },
                                { "value":"1","label":Globalize.localize('_base')['yes'] }
                            ] }
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
                console.log('cc_supplier.add.init()');
                // Required files
                var required_files = true;
                if (!App.forms.cc_supplier || !App.forms.cc_supplier.add_mod)  { App.loadDForm('cc_supplier', 'add_mod'); required_files = false; }
                if (!required_files) return;

                var win_already = false;

                /**
                 * Cache
                 * div=app-window-user-add-mod may be already on #main
                 * (as when the user comes from /user/add or /user/mod)
                 * If it is
                 */
                var $win = $('#main > div[name="app-window-cc-supplier-add-mod"]');
                if ($win.length) {
                    win_already = true;

                    // cache and remove everything else
                    // avoid caching $win
                    $win.data('cache', 0);

                    App.cacheAppWindows();
                    $('#main > div').not('[name="app-window-cc-supplier-add-mod"]').remove();

                    $win.data('cache', 1);
                }
                else {
                    App.cacheAppWindows();

                    $win = $('#cache > div[name="app-window-cc-supplier-add-mod"]');
                    if (!$win.length) {
                        console.log('cc_supplier.add - window is NOT CACHED');
                        $win = $('#cache > div[name="app-window"]').clone().attr('name', 'app-window-cc-supplier-add-mod').data('cache', 1)
                            .find('div.app-window-content').html($('<form></form>').dform(App.forms.cc_supplier.add_mod)).end()
                    }
                }

                // win title
                $win.find('h1').html(Globalize.localize('cc_supplier')['win_title_add']);

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
                console.log('cc_supplier.add.on_submit()');
                if (data.textStatus == "ok") {
                    $('<div>'+Globalize.localize('cc_supplier')['add_ok']+'</div>').dialog({
                        title: Globalize.localize('cc_supplier')['win_title_add'],
                        buttons: [{ text: "Ok", click: function(){ $('#main > div[name="app-window-cc-supplier-add-mod"] > .app-window-content form')[0].reset(); $( this ).dialog( "destroy" ); } }]
                    });
                }
                else App.errorsDialog(data.errors, Globalize.localize('cc_supplier'));
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
                console.log('cc_supplier.mod.init()');
                // Required files
                var required_files = true;
                if (!App.forms.cc_supplier || !App.forms.cc_supplier.add_mod)  { App.loadDForm('cc_supplier', 'add_mod'); required_files = false; }
                if (!required_files) return;

                App.cacheAppWindows();

                // cache
                var $win = $('#cache > div[name="app-window-cc-supplier-add-mod"]');
                if (!$win.length) {
                    console.log('cc_supplier.mod - win is NOT CACHED');
                    $win = $('#cache > div[name="app-window"]').clone().attr('name', 'app-window-cc-supplier-add-mod').data('cache', 1)
                        .find('div.app-window-content').html($('<form></form>').dform(App.forms.cc_supplier.add_mod)).end()
                }

                // win title
                $win.find('h1').html(Globalize.localize('cc_supplier')['win_title_mod']);

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
                if (data.cc_supplier._has_image) {
                    // dform image cache
                    var $form_image;
                    if (_module.cache.mod && _module.cache.mod.$form_image) $form_image = _module.cache.mod.$form_image.clone();
                    else {
                        if (!_module.cache.mod) _module.cache.mod = {};
                        $form_image = $('<fieldset><p><img></p><p><input type="checkbox" id="image_delete" name="image_delete" value="1"><label for="image_delete"></label></p></fieldset> ');
                        // cache
                        _module.cache.mod.$form_image = $form_image.clone();
                    }

                    $form_image.find('label').html(Globalize.localize('cc_supplier')['image_delete']).end()
                        .find('img').prop('src', App.request.url_base + '/_pub/img/cc_supplier/' + data.cc_supplier.id + '.png').end()
                        .insertAfter($form.find('input[name="image"]').parent());
                }

                //Add 'deleted' field
                if (data.cc_supplier.deleted) {
                    // cache
                    var $form_deleted;
                    if (_module.cache.mod && _module.cache.mod.$form_deleted) $form_deleted = _module.cache.mod.$form_deleted.clone();
                    else {
                        if (!_module.cache.mod) _module.cache.mod = {};
                        $form_deleted = $('<fieldset><p><input type="checkbox" id="cc-supplier-add-mod-deleted" name="deleted" value="1"><label for="cc-supplier-add-mod-deleted"></label></p></fieldset> ');
                        // cache
                        _module.cache.mod.$form_deleted = $form_deleted.clone();
                    }

                    $form_deleted.find('label').html(Globalize.localize('cc_supplier')['deleted']).end()
                        .insertAfter($form.find('textarea[name="comments"]').parent());
                }

                // form
                $form.prop('action', 'mod')
                    //Add id - hidden text
                    .append($('<input>').attr('type', 'hidden').attr('name','id').attr('value', data.cc_supplier.id))
                    // input values
                    .find(':input').each( function(i){
                        if (this.name && data.cc_supplier[this.name]) {
                            if (this.type == "checkbox") $(this).val([data.cc_supplier[this.name]]);
                            else $(this).val(data.cc_supplier[this.name]);
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
                console.log('cc_supplier.mod.on_submit()');
                if (data.textStatus == "ok") {
                    $('<div>'+Globalize.localize('cc_supplier')['mod_ok']+'</div>').dialog({
                        title: Globalize.localize('cc_supplier')['win_title_mod'],
                        buttons: [{ text: "Ok", click: function(){ App.ajaxUrl(location.href); $(this).dialog( "destroy" ); } }]
                    });
                }
                else App.errorsDialog(data.errors, Globalize.localize('cc_supplier'));
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
                console.log('cc_supplier.del.init()');

                App.delConfirmDialog(Globalize.localize('cc_supplier')['win_title_del'], Globalize.localize('cc_supplier')['del_confirm'] + '<br>' + data.cc_supplier.name, data.cc_supplier.id);

                App.ajaxDataProcessComplete();
            };

            // data = JSON { textStatus = "ok" }  OR { textStatus = "error", errors[form_field] = error }
            this.on_del = function(data)
            {
                console.log('cc_supplier.del.on_submit()');
                if (data.textStatus == "ok") {
                    $('<div>'+Globalize.localize('cc_supplier')['del_ok']+'</div>').dialog({
                        title: Globalize.localize('cc_supplier')['win_title_del'],
                        buttons: [{ text: "Ok", click: function(){ App.ajaxUrl(App.request.url_base + '/cc_supplier/search'); $(this).dialog( "destroy" ); } }]
                    });
                }
                else App.errorsDialog(data.errors, Globalize.localize('cc_supplier'));
                App.ajaxDataProcessComplete();
            }
        };

    };

    return my;

}(App || {}));