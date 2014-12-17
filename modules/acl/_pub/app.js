var App = (function (my) {
    my.modules.acl = new function()
    {
        var _module = this;

        this.cache = {};

        this.search = new function()
        {
            /**
             * Create html app-windows parsing App.modules._base.templates.window (title, id)
             * Update list - this.paginate(data)
             */
            this.init = function(data)
            {
                (App.APP_ENV == App.APP_ENV_DEV) && console.log('acl.search.init()');

                App.cacheAppWindows();

                var theaders = ['name', 'allow', 'module', 'action', 'filter_criteria', 'filter_value'];

                var $table = App.buildDataTable(theaders, Globalize.localize('acl'), 'a');

                $('#main').html(
                    $('#cache > div[name="app-window"]').clone().prop('id', 'app-window-acl-search')
                        .find('h1').html(Globalize.localize('acl')['win_title_list']).end()
                        .find('div.app-window-content').append($table).end()
                );

                $table.dataTable({
                    "iDeferLoading": [ data.iTotalDisplayRecords, data.iTotalRecords ],
                    "aaData" : data.aaData,
                    "sAjaxSource": App.url_base + "/acl/search",
                    "aoColumnDefs": [
                        { "aTargets": [ 0 ], "mData": "name" },
                        {"aTargets": [ 1 ], // allow
                            "mData": function(source, type, val) {
                                if (type === 'display') return Globalize.localize('_base')[(parseInt(source.allow) ? 'yes' : 'no')];
                                return source.allow;
                            }
                        },
                        {"aTargets": [ 2 ], // module
                            "mData": function(source, type, val) {
                                if (type === 'display') {
                                    if (source.module == '*') source.module = 'all';
                                    return Globalize.localize('_base')[source.module];
                                }
                                return source.module;
                            }
                        },
                        {"aTargets": [ 3 ], // action
                            "mData": function(source, type, val) {
                                if (!source.action) return '';
                                if (type === 'display') {
                                    if (source.action == '*') source.action = 'all';
                                    return Globalize.localize('_base')[source.action];
                                }
                                return source.action;
                            }
                        },
                        { "aTargets": [ 4 ], "mData": "filter_criteria" },
                        {"aTargets": [ 5 ], // filter_value
                            "mData": function(source, type, val) {
                                if (type === 'display' && source.filter_value == 'self') return Globalize.localize('acl')[source.filter_value];
                                return source.filter_value;
                            }
                        },
                        {"aTargets": [ 6 ], // Action
                            "bSortable": false,
                            "mData": function(source, type, val) {
                                if (type === 'display') {
                                    return '<a class="ui-state-default ui-corner-all app-btn" title="'+Globalize.localize('_base')['mod']+'" href="'+App.url_base+'/acl/mod?id='+source.id+'" data-ajax="1"><span class="ui-icon ui-icon-pencil">'+Globalize.localize('_base')['mod']+'</span></a>' +
                                        '<a class="ui-state-default ui-corner-all app-btn" title="'+Globalize.localize('_base')['del']+'" href="'+App.url_base+'/acl/del?id='+source.id+'" data-ajax-callback="del" data-push-state="0"><span class="ui-icon ui-icon-closethick">'+Globalize.localize('_base')['del']+'</span></a>'
                                    ;
                                }
                                return source.id;
                            }
                        }
                    ],
                    "aaSorting": [[0,'asc']] // default sorting
                })
                .columnFilter({
                    sPlaceHolder: "head:after",
                    aoColumns: [
                        { type: "text" }, // name
                        { type: "select", values: [ // allow
                            { "value":"0","label":Globalize.localize('_base')['no'] },
                            { "value":"1","label":Globalize.localize('_base')['yes'] }
                        ] },
                        { type: "text" }, // module
                        { type: "text" }, // action
                        { type: "text" }, // filter_criteria
                        { type: "text" }, // filter_value
                        null              // action
                    ]
                });
                
                App.ajaxDataProcessComplete();

            };

        };

        this.add = new function()
        {
            /**
             * Create html app-windows parsing App.modules._base.templates.window (title, id)
             * @param data holds form data
             */
            this.init = function(data)
            {
                (App.APP_ENV == App.APP_ENV_DEV) && console.log('acl.add.init()');
                var missing_files = false;
                if (!App.templates.acl || !App.templates.acl.add_mod) { App.loadTemplate('acl', 'add_mod'); missing_files = true; }
                if (missing_files) return;

                _module.cache.modules = data.modules;
                
                App.cacheAppWindows();
                var $win = _module.add_mod.get_win_add_mod();
                $win.find('h1').html(Globalize.localize('acl')['win_title_add']);

                var $form = $win.find('form');
                _module.add_mod.resetForm($form);

                _module.add_mod.fill_module($form.find('select[name="module"]'),data.modules);

                $form.prop('action', 'add').find('input[type="submit"], button[type="submit"]').val(Globalize.localize('_base')['add']).end();
                
                $('#main').html($win);
                
                App.ajaxDataProcessComplete();
            };

            this.on_submit = function(data)
            {
                (App.APP_ENV == App.APP_ENV_DEV) && console.log('acl.add.on_submit()');
                if (data.textStatus == "ok") {
                    $('<div>'+Globalize.localize('acl')['add_ok']+'</div>').dialog({
                        title: Globalize.localize('acl')['win_title_add'],
                        buttons: [{ text: "Ok", click: function(){ $('form', 'div[name="app-window-acl-add-mod"]')[0].reset(); $( this ).dialog( "destroy" ); } }]
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
                (App.APP_ENV == App.APP_ENV_DEV) && console.log('acl.mod.init()');
                var missing_files = false;
                if (!App.templates.acl || !App.templates.acl.add_mod) { App.loadTemplate('acl', 'add_mod'); missing_files = true; }
                if (missing_files) return;

                _module.cache.modules = data.modules;

                App.cacheAppWindows();
                var $win = _module.add_mod.get_win_add_mod(data);
                $win.find('h1').html(Globalize.localize('acl')['win_title_mod']);
                var $form = $win.find('form');
                _module.add_mod.resetForm($form);

                _module.add_mod.fill_module($form.find('select[name="module"]'),data.modules);

                // fill form
                $form.prop('action', 'mod')
                    //Add id - hidden text
                    .append($('<input>').attr('type', 'hidden').attr('id', 'id').attr('name','id').attr('value', data.acl.id))
                    // input values
                    .find(':input').each( function(i){
                        if (this.name && data.acl[this.name]) {
                            if (this.type == 'checkbox') $(this).val([data.acl[this.name]]);
                            else $(this).val(data.acl[this.name]);
                        }
                    })
                    .end()
                    //submit button lang
                    .find('input[type="submit"], button[type="submit"]').val(Globalize.localize('_base')['mod']);

                $('#main').html($win);

                App.ajaxDataProcessComplete();
            };

            // data = JSON { textStatus = "ok" }
            this.on_submit = function(data)
            {
                (App.APP_ENV == App.APP_ENV_DEV) && console.log('acl.mod.on_submit()');
                if (data.textStatus == "ok") {
                    $('<div>'+Globalize.localize('acl')['mod_ok']+'</div>').dialog({
                        title: Globalize.localize('acl')['win_title_mod'],
                        buttons: [{ text: "Ok", click: function(){ App.ajaxUrl(location.href); $(this).dialog( "destroy" ); } }]
                    });
                }
                App.ajaxDataProcessComplete();
            }
        };



        // common Add and Mod functions
        this.add_mod = new function()
        {
            this.get_win_add_mod = function()
            {
                var $win = $('#cache > div[name="app-window-acl-add-mod"]');
                if (!$win.length) {
                    var $form = $(App.templates.acl.add_mod.form);
                    $win = $('#cache > div[name="app-window"]').clone().attr('name', 'app-window-acl-add-mod').data('cache', 1)
                        .find('div.app-window-content').html($form)
                    .end();
                }
                return $win;
            };

            this.resetForm = function($form)
            {
                $form
                    .find('input[name="id"]').remove().end()
                    [0].reset();

                return $form;
            };

            this.fill_module = function($select, data)
            {
                $select.empty().attr('size',1);
                $select.append($("<option></option>").attr('value','*').html(Globalize.localize('_base')['all']));
                $.each(data, function(key, value) { $select.append($("<option></option>").attr('value',key).html(Globalize.localize('_base')[key])); });
                $select[0].selectedIndex = 0;
            };

            this.fill_action = function($module)
            {
                $select = $module.closest('form').find('select[name="module_action"]');
                $select.empty().attr('size',1);
                $select.append($("<option></option>").attr('value','*').html(Globalize.localize('_base')['all']));
                if ($module.val() != '*') $.each(_module.cache.modules[$module.val()], function(key, value) { $select.append($("<option></option>").attr('value',value).html(Globalize.localize('_base')[value])); });
                $select[0].selectedIndex = 0;
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
                (App.APP_ENV == App.APP_ENV_DEV) && console.log('acl.del.init()');
                App.delConfirmDialog(Globalize.localize('acl')['win_title_del'], Globalize.localize('acl')['del_confirm'] + '<br>' + data.acl.name, data.acl.id, false, false);
                App.ajaxDataProcessComplete();
            };

            // data = JSON { textStatus = "ok" }
            this.on_del = function(data)
            {
                (App.APP_ENV == App.APP_ENV_DEV) && console.log('acl.del.on_submit()');
                if (data.textStatus == "ok") {
                    $('<div>'+Globalize.localize('acl')['del_ok']+'</div>').dialog({
                        title: Globalize.localize('acl')['win_title_del'],
                        buttons: [{ text: "Ok", click: function(){ App.ajaxUrl(App.url_base + '/acl/search'); $(this).dialog( "destroy" ); } }]
                    });
                }
                App.ajaxDataProcessComplete();
            }
        };

    };

    return my;

}(App || {}));