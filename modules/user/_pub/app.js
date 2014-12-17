var App = (function (my) {
    my.modules.user = new function()
    {
        var _module = this;

        this.cache = {};

        this.login = new function()
        {
            this.init = function(data)
            {
                console.log('user.login.init()');
                var missing_files = false;
                if (!App.forms.user || !App.forms.user.login)  { App.loadDForm('user', 'login'); missing_files = true; }
                if (missing_files) return;

                App.cacheAppWindows();

                $('#main').html(
                    $('#cache > div[name="app-window"]').clone().prop('id', 'app-window-login')
                        .find('h1').html(Globalize.localize('user')['win_title_login']).end()
                        .find('div.app-window-content').html($('<form></form>').dform(App.forms.user.login)).end()
                );
                App.ajaxDataProcessComplete();
            };

            // data = JSON { textStatus = "ok" }
            this.on_login = function(data)
            {
                console.log('user.login.on_login()');
                if (data.textStatus == "ok") {
                    $('<div>'+Globalize.localize('user')['login_ok']+'</div>').dialog({
                        title: Globalize.localize('user')['win_title_login'],
                        buttons: [{ text: "Ok", click: function(){ location.href= App.url_base + (data.user.homepage?data.user.homepage:''); } }]
                    });
                }
                App.ajaxDataProcessComplete();
            };
        };

        this.search = new function()
        {
            /**
             * Create html app-windows parsing App.modules._base.templates.window (title, id)
             * Update list - this.paginate(data)
             */
            this.init = function(data)
            {
                console.log('user.search.init()');

                App.cacheAppWindows();

                var theaders = ['image', 'fullname', 'category', 'cc_category', 'email', 'in_school', 'comments', 'deleted'];

                var $table = App.buildDataTable(theaders, Globalize.localize('user'), 'a');
                $table.addClass('td-first-image');

                var $table_buttons = $('<div></div>')
                    .html(App.buildButton(Globalize.localize('user')['all_in'], this.set_all, {set_all : 'in'}))
                    .append(App.buildButton(Globalize.localize('user')['all_out'], this.set_all, {set_all : 'out'}));

                $('#main').html(
                    $('#cache > div[name="app-window"]').clone().prop('id', 'app-window-user-list')
                        .find('h1').html(Globalize.localize('user')['win_title_list']).end()
                        .find('div.app-window-content')
                            .append($table_buttons)
                            .append($table)
                            .end()
                );

                $table.dataTable({
                    "iDeferLoading": [ data.iTotalDisplayRecords, data.iTotalRecords ],
                    "aaData" : data.aaData,
                    "sAjaxSource": App.url_base + "/user/search",
                    "aoColumnDefs": [
                        { "aTargets": [ 0 ], // image
                            "bSortable" : false,
                            "mData": function ( source, type, val ) {
                                if (type === 'display') {
                                    if (source._image) return '<img src="'+App.url_base+'/uploads/user/_pub/'+source.id+'.png'+'">';
                                    else return '<img src="'+App.url_base+'/modules/user/_pub/img/not_found.jpg">';
                                }
                                return source._image;
                            }
                        },
                        { "aTargets": [ 1 ], // fullname
                            "mData": function ( source, type, val ) {
                                if (type === 'display') { return '<a href="'+App.url_base+'/user/view?id='+source.id+'" data-ajax="1">'+source.last_name+', '+source.name+'</a>' }
                                return source.last_name+' '+source.name;
                            }
                        },
                        { "aTargets": [ 2 ], // categories
                            "mData": function ( source, type, val ) {
                                if (type === 'display') { return source.cat_names.replace(',','<br>') }
                                return source.cat_names;
                            }
                        },
                        { "aTargets": [ 3 ], "mData": "cc_cat_names" },
                        { "aTargets": [ 4 ], "mData": "email" },
                        { "aTargets": [ 5 ], // in_school
                            "bSortable" : false,
                            "mData": function ( source, type, val ) {
                                if (type === 'display') { return source.in_school?Globalize.localize('_base')['yes']:Globalize.localize('_base')['no'] }
                                return source.in_school;
                            }
                        },
                        { "aTargets": [ 6 ], "mData": "comments" },
                        { "aTargets": [ 7 ], // deleted
                            "bSortable" : false,
                            "mData": function ( source, type, val ) {
                                if (type === 'display') { return source.deleted?Globalize.localize('_base')['yes']:Globalize.localize('_base')['no'] }
                                return source.deleted;
                            }
                        },
                        { "aTargets": [ 8 ], // Action
                            "bSortable" : false,
                            "mData": function ( source, type, val ) {
                                if (type === 'display') {
                                    return '<a class="ui-state-default ui-corner-all app-btn" href="' + App.url_base + '/user/mod/?id=' + source.id + '" data-ajax="1" title="' + Globalize.localize('_base')['mod'] + '"><span class="ui-icon ui-icon-pencil">' + Globalize.localize('_base')['mod'] + '</span></a> ' +
                                            '<a class="ui-state-default ui-corner-all app-btn" href="' + App.url_base + '/user/del/?id=' + source.id + '" data-ajax-callback="del" data-push-state="0" title="' + Globalize.localize('_base')['del'] + '"><span class="ui-icon ui-icon-closethick">' + Globalize.localize('_base')['del'] + '</span></a>';
                                }
                                return source.id;
                            }
                        }
                    ],
                    "aaSorting": [[1,'asc']] // default sorting
                })
                .columnFilter({
                    sPlaceHolder: "head:after",
                    aoColumns: [
                        null, // image
                        { type: "text" }, // fullname
                        { type: "select", values: data.categories }, // category
                        { type: "select", values: data.cc_categories },  // cc_category
                        { type: "text" }, // email
                        { type: "select", values: [  // in_school
                            { "value":"0","label":Globalize.localize('_base')['no'] },
                            { "value":"1","label":Globalize.localize('_base')['yes'] }
                        ] },
                        null, // comments
                        { type: "select", values: [ // deleted
                            { "value":"0","label":Globalize.localize('_base')['no'] },
                            { "value":"1","label":Globalize.localize('_base')['yes'] }
                        ] },
                        null
                    ]
                });
                
                // default deleted = 0
                $('select.select_filter').slice(3,4).val('0');

                App.ajaxDataProcessComplete();

            };

            this.set_all = function(e)
            {
                console.log('user.search.set_all()');
                var theaders = ['image', 'fullname', 'category', 'cc_category', 'email', 'barcode', 'in_school', 'comments', 'deleted'];
                var query_string = '';

                // Get DataTable filters and build QueryString
                var table_filters = $('#app-window-user-list table').dataTable().fnSettings().aoPreSearchCols;
                for (var i=0;i<theaders.length;i++) if (table_filters[i].sSearch) query_string += 'filters[' + theaders[i] + ']=' + table_filters[i].sSearch + '&';

                $('<div>'+Globalize.localize('user')['all_'+e.data.set_all]+'?</div>').dialog({
                    title: Globalize.localize('user')['win_title_set_all'],
                    buttons: [{ text: "Ok", click: function() { App.ajaxUrl(App.url_base + '/user/set_all?'+query_string+'set='+e.data.set_all, 'on_set_all',false); $(this).dialog( "destroy" ); } }]
                });
            };

            this.on_set_all = function(data)
            {
                if (data.textStatus == "ok") {
                    $('<div>'+Globalize.localize('user')['set_all_ok']+'</div>').dialog({
                        title: Globalize.localize('user')['win_title_set_all'],
                        buttons: [{ text: "Ok", click: function(){ App.ajaxUrl(App.url_base + '/user/search'); $(this).dialog( "destroy" ); } }]
                    });
                }
                App.ajaxDataProcessComplete();
            };

        };

        this.view = new function()
        {
            // Create html app-windows parsing App.modules._base.templates.window (title, id)
            this.init = function(data)
            {
                console.log('user.view.init()');
                var missing_files = false;
                if (!App.templates.user || !App.templates.user.view) { App.loadTemplate('user', 'view'); missing_files = true; }
                if (missing_files) return;

                App.cacheAppWindows();
                _module.cache.view = {};
                _module.cache.view.acl_defs = data.acl_defs;
                _module.cache.view.categories = data.categories;

                if (data.user._image) data.user.image_src = App.url_base+'/uploads/user/_pub/'+data.user.id+'.png';
                else data.user.image_src = App.url_base+'/modules/user/_pub/img/not_found.jpg';

                data.user.image_class = (data.user.in_shool ? 'in' : 'out');

                data.acl_data = _module.view.acl_parse_data(data.acl_data);

                $('#main').html(
                    $('#cache > div[name="app-window"]').clone().prop('id', 'app-window-user-view')
                        .find('h1').html(Globalize.localize('user')['win_title_view']).end()
                        .find('div.app-window-content').html(Mustache.render(App.templates.user.view.user, $.extend({},Globalize.localize('_base'), Globalize.localize('user'), data))).end()
                );

                if (data.tabs) {
                    $('#main').append(
                        $('#cache > div[name="app-window"]').clone().prop('id', 'app-window-view-tabs')
                            .find('h1').html(Globalize.localize('user')['win_title_info']).end()
                            .find('div.app-window-content').html(Mustache.render(App.templates.user.view.tabs_script, $.extend({},Globalize.localize('_base'), Globalize.localize('user'), data))).end()
                    );

                    var $tabs = $('#tabs');
                    $tabs
                        .find('#tab_schedule form').prop('action', App.url_base+'/user/set_schedule')
                            .find('input[name="id"]').val(data.user.id).end()
                        .end()
                        .find('#tab_acl form').prop('action', App.url_base+'/user/acl_add')
                            .find('input[name="id"]').val(data.user.id).end()
                            .find('select[name="module"]').append(App.build_select_options(Object.keys(data.acl_defs),Globalize.localize('_base'))).end()
                            .find('table tbody').append(Mustache.render(App.templates.user.view.acl_tr, $.extend({},Globalize.localize('_base'), Globalize.localize('user'), data))).end()
                        .end()
                        .find('#tab_non_working_days form').prop('action', App.url_base+'/user/non_working_days_add')
                            .find('input[name="user_id"]').val(data.user.id).end()
                            .find('table tbody').append(Mustache.render(App.templates.user.view.nwd_tr, $.extend({},Globalize.localize('_base'), Globalize.localize('user'), _module.view.nwd_format(data)))).end()
                        .end();

                    $tabs.find('.user-schedule tr:nth-child(-n+2) input').timepicker();
                    $tabs.find('#tab_non_working_days').find('tr.add input[type="text"]').datepicker({ minDate: 0 }).datepicker('setDate', new Date());

                    $tabs.tabs({
                        activate: function( event, ui ) {
                            if (!ui.newPanel.html()) _module.view['load_' + ui.newPanel.attr('id')](data.user.id);
                        }
                    });
                }

                App.ajaxDataProcessComplete();
            };

            this.schedule_all = function(input) {
                $(input).closest('tr').find('input').slice(1).val(input.value);
            };

            // data = JSON { textStatus = "ok" }
            this.on_schedule = function(data)
            {
                console.log('user.view.on_schedule()');
                if (data.textStatus == "ok") {
                    $('<div>'+Globalize.localize('user')['mod_ok']+'</div>').dialog({
                        title: Globalize.localize('user')['win_title_mod'],
                        buttons: [{ text: "Ok", click: function(){ $(this).dialog( "destroy" ); } }]
                    });
                }
                App.ajaxDataProcessComplete();
            };

            this.acl_parse_data = function(data)
            {
                $.each(data, function(i){
                    data[i]['allow'] = parseInt(data[i]['allow']) ? Globalize.localize('user')['allow'] : Globalize.localize('user')['deny'];
                    data[i]['module'] = (data[i]['module'] == '*' ? Globalize.localize('_base')['all'] : Globalize.localize('_base')[data[i]['module']]);
                    data[i]['action'] = (data[i]['action'] ? (data[i]['action'] == '*' ? Globalize.localize('_base')['all'] : Globalize.localize('_base')[data[i]['action']] ) : null);
                    if (!data[i]['action_filter_criteria']) data[i]['action_filter_criteria'] = ' ';
                    else {
                        if (data[i]['action_filter_criteria'] == '*') data[i]['action_filter_criteria'] = Globalize.localize('_base')['all'];
                        else data[i]['action_filter_criteria'] = Globalize.localize('user')[data[i]['action_filter_criteria']];
                    }
                    if (!data[i]['action_filter_value']) data[i]['action_filter_value'] = ' ';
                });
                return data;
            };

            this.acl_on_module = function(module)
            {
                console.log('user.view.acl_on_module()');

                var $sel_action = $('#tab_acl').find('select[name="module_action"]');
                $sel_action.empty().append(App.build_select_options(['all'], Globalize.localize('_base')));
                if (module == '*') $sel_action.prop('disabled', 'disabled');
                else $sel_action.prop('disabled', false).append(App.build_select_options(_module.cache.view.acl_defs[module], Globalize.localize('_base')));

                _module.view.acl_on_action('all');
            };

            this.acl_on_action = function(action)
            {
                console.log('user.view.acl_on_action()');

                var $tab = $('#tab_acl');
                var module = $tab.find('select[name="module"]')[0].value;
                var $sel_action_filter = $tab.find('select[name="action_filter_criteria"]');
                $sel_action_filter.empty().append(App.build_select_options(['all'], Globalize.localize('_base')));

                if (action == '*' || $.inArray(module, ['user', 'rfid', 'user_absence', 'cc_purchase', 'cc_delivery']) == -1) $sel_action_filter.prop('disabled', 'disabled');
                else {
                    if (action != '*') $sel_action_filter.append(App.build_select_options(['self'], Globalize.localize('user')));
                    switch (module) {
                        case 'user':
                        case 'rfid':
                        case 'user_absence':
                            $sel_action_filter.append(App.build_select_options(['category_id'], Globalize.localize('user')));
                            break;
                        case 'cc_purchase':
                            // $sel_action_filter.append(App.build_select_options(['cc_supplier_id'], Globalize.localize('user')));
                            break;
                        case 'cc_delivery':
                            // $sel_action_filter.append(App.build_select_options(['opened_by'], Globalize.localize('user')));
                            break;
                    }
                    $sel_action_filter.prop('disabled', false);
                }
                _module.view.acl_on_action_filter_criteria('all');
            };

            this.acl_on_action_filter_criteria = function(filter)
            {
                console.log('user.view.acl_on_action_filter_criteria()');

                var $sel_action_filter_value = $('#tab_acl').find('select[name="action_filter_value"]');
                $sel_action_filter_value.empty();
                if (filter == 'all' || filter == 'self') $sel_action_filter_value.prop('disabled', 'disabled').prop("selectedIndex",1);
                else $sel_action_filter_value.prop('disabled', false).append('<option value="self">'+Globalize.localize('user')['self']+'</option>').append(App.build_select_options(_module.cache.view.categories));
            };

            this.acl_on_add = function(data)
            {
                console.log('user.view.on_acl_add()');
                data.acl_data = _module.view.acl_parse_data(data.acl_data);

                if (data.textStatus == "ok") {
                    var $tbody = $('#tab_acl').find('tbody');
                    $tbody.find('tr').slice(1).remove();
                    $tbody.append(Mustache.render(App.templates.user.view.acl_tr, $.extend({},Globalize.localize('_base'), Globalize.localize('user'), _module.view.acl_parse_data(data))));
                }
                App.ajaxDataProcessComplete();
            };

            this.acl_del_confirm = function(id)
            {
                console.log('user.view.on_del_confirm()');

                var $del_confirm = $('#cache div[name="del-confirm"]').clone()
                    .find('div.confirm-text').html(Globalize.localize('user')['acl_del_confirm']).end()
                    .find('input[name="id"]').val(id).end()
                    .find('form').prop('action','acl_del').data('ajaxCallback', 'acl_on_del').end();

                $del_confirm.find('div.db-checkbox').hide().find('input[type="checkbox"]').prop('checked', false).prop('disabled',true);
                $del_confirm.find('div.comments').hide().find('textarea').prop('disabled',true).val('');

                $del_confirm.dialog({
                    title: Globalize.localize('user')['acl_del'],
                    buttons: [
                        { text: "Ok", click: function(){ $(this).find('form').submit(); $(this).dialog( "destroy" ); } },
                        { text: "Cancel", click: function(){ $(this).dialog( "destroy" ); } }
                    ],
                    close: function( event, ui ) { $(this).dialog( "destroy" ); }
                });
            };

            this.acl_on_del = function(data)
            {
                console.log('user.view.on_acl_del()');
                data.acl_data = _module.view.acl_parse_data(data.acl_data);

                if (data.textStatus == "ok") {
                    var $tbody = $('#tab_acl').find('tbody');
                    $tbody.find('tr').slice(1).remove();
                    $tbody.append(Mustache.render(App.templates.user.view.acl_tr, $.extend({},Globalize.localize('_base'), Globalize.localize('user'), _module.view.acl_parse_data(data))));
                }
                App.ajaxDataProcessComplete();
            };

            this.load_tab_movements = function(user_id)
            {
                App.ajax.callback = 'load_tab_movements';
                App.ajax.data = user_id;
                if (!Globalize.localize('rfid')) { App.loadLang('rfid'); return; }
                $.ajax({
                    global : false,
                    dataType: "json",
                    url: App.url_base + '/rfid/search?ajax=1&data_type=json&user_id='+user_id
                })
                .done( _module.view.on_load_tab_movements );
            };

            this.on_load_tab_movements = function( data )
            {
                console.log('user.view.on_load_tab_movements()');

                var theaders = ['date', 'is_early', 'is_entering', 'entrance', 'deleted', 'comments'];

                var $table = App.buildDataTable(theaders, Globalize.localize('rfid'),'m'); // headers, lang, extras(m[od]d[del]u[ndelete]f[ooter]

                $('#tab_movements').html($table);
                var user_id = $('div.row-data').data('id');

                $table.dataTable({
                    "iDeferLoading": [ data.iTotalDisplayRecords, data.iTotalRecords ],
                    "aaData" : data.aaData,
                    "sAjaxSource": App.url_base + "/rfid/search",
                    "fnServerParams": function ( aoData ) {
                        aoData.push( { "name": "ajax", "value": 1 } );
                        aoData.push( { "name": "data_type", "value": "json" } );
                        aoData.push( { "name": "user_id", "value": user_id } );
                        aoData.push( { "name": "theaders", "value": theaders } );
                    },

                    "aoColumnDefs": [
                        { "aTargets": [ 0 ], "mData": "date" },
                        { "aTargets": [ 1 ], // is_early
                            "mData": function ( source, type, val ) {
                                var html = '';
                                if (type === 'display') {
                                    if (source.user_work_shift_id != ' ') {
                                        var expected_start  = new Date(source.shift_expected_start);
                                        var started_on      = new Date(source.shift_started_on);
                                        var expected_end    = new Date(source.shift_expected_end);
                                        var ended_on        = new Date(source.shift_ended_on);

                                        html = Globalize.format(expected_start,'t') + ' - ' + Globalize.format(expected_end,'t');
                                        if ((source.date == source.shift_started_on && started_on < expected_start)
                                            ||  (source.date == source.shift_ended_on && ended_on < expected_end)
                                            ) html += '<br>('+Globalize.localize('rfid')['early']+')';
                                        else if ((source.date == source.shift_started_on && started_on > expected_start)
                                            ||  (source.date == source.shift_ended_on && ended_on > expected_end)
                                            ) html += '<br>('+Globalize.localize('rfid')['late']+')';
                                    }
                                }
                                return html;
                            }
                        },
                        { "aTargets": [ 2 ], // is_entering
                            "bSortable" : false,
                            "mData": function ( source, type, val ) {
                                if (type === 'display') { return parseInt(source.is_entering)?Globalize.localize('rfid')['entering']:Globalize.localize('rfid')['exiting'] }
                                return source.is_entering;
                            }
                        },
                        { "aTargets": [ 3 ], "mData": "entrance" },
                        { "aTargets": [ 4 ], // deleted
                            "bSortable" : false,
                            "mData": function ( source, type, val ) {
                                if (type === 'display') { return parseInt(source.deleted)?Globalize.localize('_base')['yes']:Globalize.localize('_base')['no'] }
                                return source.deleted;
                            }
                        },
                        { "aTargets": [ 5 ], "mData": "comments" },
                        { "aTargets": [ 6 ], // Del
                            "bSortable" : false,
                            "mData": function ( source, type, val ) {
                                if (type === 'display') { return '<a href="'+App.url_base+'/rfid/mod?id='+source.id+'" data-ajax="1">'+Globalize.localize('_base')['mod']+'</a>' }
                                return source.id;
                            }
                        }
                    ],
                    "aaSorting": [[0,'desc']] // default sorting
                })
                .columnFilter({
                    sRangeFormat : "Del {from} al {to}",
                    sPlaceHolder: "head:after",
                    aoColumns: [
                        { type: "date-range" }, // date
                        { type: "select", values: [ // is_early
                            { "value":"1","label":Globalize.localize('rfid')['early'] },
                            { "value":"0","label":Globalize.localize('rfid')['late'] }
                        ] },
                        { type: "select", values: [ // is_entering
                            { "value":"1","label":Globalize.localize('rfid')['entering'] },
                            { "value":"0","label":Globalize.localize('rfid')['exiting'] }
                        ] },
                        { type: "select", values: data.entrances },  // entrance
                        { type: "select", values: [ // deleted
                            { "value":"0","label":Globalize.localize('_base')['no'] },
                            { "value":"1","label":Globalize.localize('_base')['yes'] }
                        ] },
                        null, // comments
                        null
                    ]
                });

                App.ajaxDataProcessComplete();
            };

            this.nwd_format = function(data)
            {
                $.each(data.nwd, function(i) {
                    data.nwd[i].from = Globalize.format(App.parseDate(data.nwd[i].from), 'd');
                    data.nwd[i].to   = Globalize.format(App.parseDate(data.nwd[i].to), 'd');
                    if (!data.nwd[i].comments) data.nwd[i].comments = ' ';
                });

                return data;
            };

            this.nwd_on_add = function(data)
            {
                console.log('user.view.nwd_on_add()');

                if (data.textStatus == "ok") {
                    var $tbody = $('#tab_non_working_days').find('tbody');
                    $tbody.find('tr').slice(1).remove();
                    $tbody.append(Mustache.render(App.templates.user.view.nwd_tr, $.extend({},Globalize.localize('_base'), Globalize.localize('user'), _module.view.nwd_format(data))));
                }

                App.ajaxDataProcessComplete();
            };

            this.nwd_del_confirm = function(id)
            {
                console.log('user.view.nwd_del_confirm()');

                var $del_confirm = $('#cache div[name="del-confirm"]').clone()
                    .find('div.confirm-text').html(Globalize.localize('user')['nwd_del_confirm']).end()
                    .find('input[name="id"]').val(id).end()
                    .find('form').prop('action','non_working_days_del').data('ajaxCallback', 'nwd_on_del').end();

                $del_confirm.find('div.db-checkbox').hide().find('input[type="checkbox"]').prop('checked', false).prop('disabled',true);
                $del_confirm.find('div.comments').hide().find('textarea').prop('disabled',true).val('');

                $del_confirm.dialog({
                    title: Globalize.localize('user')['nwd_del'],
                    buttons: [
                        { text: "Ok", click: function(){ $(this).find('form').submit(); $(this).dialog( "destroy" ); } },
                        { text: "Cancel", click: function(){ $(this).dialog( "destroy" ); } }
                    ],
                    close: function( event, ui ) { $(this).dialog( "destroy" ); }
                });
            };

            this.nwd_on_del = function(data)
            {
                console.log('user.view.nwd_on_del()');

                if (data.textStatus == "ok") {
                    var $tbody = $('#tab_non_working_days').find('tbody');
                    $tbody.find('tr').slice(1).remove();
                    $tbody.append(Mustache.render(App.templates.user.view.nwd_tr, $.extend({},Globalize.localize('_base'), Globalize.localize('user'), _module.view.nwd_format(data))));
                }
                App.ajaxDataProcessComplete();
            };



            this.load_tab_absences = function(user_id)
            {
                App.ajax.callback = 'load_tab_absences';
                App.ajax.data = user_id;
                if (!Globalize.localize('user_absence')) { App.loadLang('user_absence'); return; }
                $.ajax({
                    global : false,
                    dataType: "json",
                    url: App.url_base + '/user_absence/search?ajax=1&data_type=json&user_id='+user_id
                })
                    .done( _module.view.on_load_tab_absences );
            };

            this.on_load_tab_absences = function( data )
            {
                console.log('user.view.on_load_tab_absences()');

                var theaders = ['date', 'comments'];

                var $table = App.buildDataTable(theaders, Globalize.localize('user_absence'), 'md');

                $('#tab_absences').html($table);
                var user_id = $('ul.row-data').data('id');

                $table.dataTable({
                    "iDeferLoading": [ data.iTotalDisplayRecords, data.iTotalRecords ],
                    "aaData" : data.aaData,
                    "sAjaxSource": App.url_base + "/user_absence/search",
                    "fnServerParams": function ( aoData ) {
                        console.log(aoData);
                        aoData.push( { "name": "ajax", "value": 1 } );
                        aoData.push( { "name": "data_type", "value": "json" } );
                        aoData.push( { "name": "user_id", "value": user_id } );
                        aoData.push( { "name": "theaders", "value": theaders } );
                    },

                    "aoColumnDefs": [
                        { "aTargets": [ 0 ], "mData": "date" },
                        { "aTargets": [ 1 ], "mData": function ( source, type, val ) { return source.comments.replace(/\n/g, "<br/>"); } }, // comments
                        { "aTargets": [ 2 ], // Mod
                            "bSortable" : false,
                            "mData": function ( source, type, val ) {
                                if (type === 'display') { return '<a href="'+App.url_base+'/user_absence/mod?id='+source.id+'" data-ajax="1">'+Globalize.localize('_base')['mod']+'</a>' }
                                return source.id;
                            }
                        },
                        { "aTargets": [ 3 ], // Del
                            "bSortable" : false,
                            "mData": function ( source, type, val ) {
                                if (type === 'display') { return '<a href="'+App.url_base+'/user_absence/del?id='+source.id+'" data-ajax="1">'+Globalize.localize('_base')['del']+'</a>' }
                                return source.id;
                            }
                        }

                    ],
                    "aaSorting": [[0,'desc']] // default sorting
                })
                .columnFilter({
                    sRangeFormat : "Del {from} al {to}",
                    sPlaceHolder: "head:after",
                    aoColumns: [
                        { type: "date-range" }, // date
                        null, // comments
                        null, // mod
                        null // del
                    ]
                });

                App.ajaxDataProcessComplete();
            };

        };


        this.view_cc_delivery = new function()
        {
            // Create html app-windows parsing App.modules._base.templates.window (title, id)
            this.init = function(data)
            {
                console.log('user.view.init()');
                var missing_files = false;
                if (!App.templates.user || !App.templates.user.view) { App.loadTemplate('user', 'view'); missing_files = true; }
                if (!Globalize.localize('cc_delivery')) { my.loadLang('cc_delivery'); missing_files = true; }
                if (missing_files) return;

                App.cacheAppWindows();

                var theaders = ['opened_on', 'closed_on', 'cc_product_name', 'comments'];

                var $table = App.buildDataTable(theaders, Globalize.localize('cc_delivery'), 'f');

                $('#main').html(
                        $('#cache > div[name="app-window"]').clone().prop('id', 'app-window-view')
                            .find('h1').html(Globalize.localize('user')['win_title_view']).end()
                            .find('div.app-window-content').html(Mustache.render(App.templates.user.view.user, $.extend({},Globalize.localize('_base'), Globalize.localize('user'), {_data : data}))).end()
                    ).append(
                        $('#cache > div[name="app-window"]').clone().prop('id', 'app-window-cc-delivery-list')
                            .find('h1').html(Globalize.localize('cc_delivery')['win_title_list']).end()
                            .find('div.app-window-content').html($table).end()
                    );

                $table.dataTable({
                    "bDeferRender": false,
                    "sAjaxSource": App.url_base + "/cc_delivery/search",
                    "fnServerParams": function (aoData) {
                        aoData.push(
                            { "name": "ajax", "value": 1 },
                            { "name": "data_type", "value": "json" },
                            { "name": "theaders", "value": theaders },
                            { "name": "user_id", "value": data.user.id },
                            { "name": "deleted", "value": 0 }
                        );
                    },
                    "aoColumnDefs": [
                        { "aTargets": [ 0 ], // opened_on
                            "mData": function ( source, type, val ) {
                                if (type === 'display') { return '<a href="'+App.url_base+'/cc_delivery/view?id='+source.id+'" data-ajax="1">'+source.opened_on+'</a>' }
                                return source.opened_on;
                            }
                        },
                        { "aTargets": [ 1 ], // closed_on
                            "mData": function ( source, type, val ) {
                                if (type === 'display') { return '<a href="'+App.url_base+'/cc_delivery/view?id='+source.id+'" data-ajax="1">'+source.closed_on+'</a>' }
                                return source.closed_on;
                            }
                        },
                        { "aTargets": [ 2 ], // products
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
                        { "aTargets": [ 3 ], "mData": "comments" }
                    ],
                    "aaSorting": [[0,'desc']], // default sorting
                    "fnFooterCallback": function ( nRow, aaData, iStart, iEnd, aiDisplay ) { // total calculator
                        var iTotal = 0;
                        for (var i=iStart; i<iEnd ; i++) iTotal += aaData[aiDisplay[i]]['_total']*1;
                        var nCells = nRow.getElementsByTagName('th');
                        nCells[2].innerHTML = '$'+iTotal;
                    }
                })
                    .columnFilter({
                        sRangeFormat : "Del {from} al {to}",
                        sPlaceHolder: "head:after",
                        aoColumns: [
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
             * Create html app-windows parsing App.modules._base.templates.window (title, id)
             * @param data holds form data
             */
            this.init = function(data)
            {
                console.log('user.add.init()');
                var missing_files = false;
                if (!App.forms.user || !App.forms.user.add_mod) { App.loadDForm('user', 'add_mod'); missing_files = true; }
                if (missing_files) return;

                App.cacheAppWindows();
                var $win = _module.add_mod.get_win_add_mod();
                $win.find('h1').html(Globalize.localize('user')['win_title_add']);

                var $form = $win.find('form');
                _module.add_mod.resetForm($form);
                $form.prop('action', 'add').find('input[type="submit"], button[type="submit"]').val(Globalize.localize('_base')['add']);

                _module.add_mod.fill_select($form.find('select[name="cat_ids[]"]'), data.categories);
                _module.add_mod.fill_select($form.find('select[name="cc_cat_ids[]"]'), data.cc_categories);

                $('#main').html($win);

                App.ajaxDataProcessComplete();
            };

            this.on_submit = function(data)
            {
                console.log('user.add.on_submit()');
                if (data.textStatus == "ok") {
                    $('<div>'+Globalize.localize('user')['add_ok']+'</div>').dialog({
                        title: Globalize.localize('user')['win_title_add'],
                        buttons: [{ text: "Ok", click: function(){ $('#main > div[name="app-window-user-add-mod"] > .app-window-content form')[0].reset(); $( this ).dialog( "destroy" ); } }]
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
                console.log('user.mod.init()');
                var missing_files = false;
                if (!App.forms.user || !App.forms.user.add_mod) { App.loadDForm('user', 'add_mod'); missing_files = true; }
                if (missing_files) return;

                App.cacheAppWindows();
                var $win = _module.add_mod.get_win_add_mod();
                $win.find('h1').html(Globalize.localize('user')['win_title_mod']);
                var $form = $win.find('form');
                _module.add_mod.resetForm($form);

                _module.add_mod.fill_select($form.find('select[name="cat_ids[]"]'), data.categories);
                _module.add_mod.fill_select($form.find('select[name="cc_cat_ids[]"]'), data.cc_categories);

                //Add 'image' and 'image_delete' fields
                if (data.user._image) {
                    // dform image cache
                    var $form_image;
                    if (_module.cache.mod && _module.cache.mod.$form_image) $form_image = _module.cache.mod.$form_image.clone();
                    else {
                        if (!_module.cache.mod) _module.cache.mod = {};
                        $form_image = $('<fieldset><p><img></p><p><input type="checkbox" id="user-add-mod-image-delete" name="image_delete" value="1"><label for="user-add-mod-image-delete"></label></p></fieldset> ');
                        // cache
                        _module.cache.mod.$form_image = $form_image.clone();
                    }

                    $form_image.find('label').html(Globalize.localize('user')['image_delete']).end()
                        .find('img').prop('src', App.url_base+'/uploads/user/_pub/'+data.user.id+'.png').end()
                        .insertAfter($form.find('input[name="image"]').parent());
                }

                if (data.user._has_pwd) {
                    $pwd_p = $form.find('input[name="pwd"]').closest('p');
                    $pwd_old_p = $pwd_p.clone()
                        .find('label').prop('for','user-add-mod-pwd-old').html(Globalize.localize('user')['pwd_old']).end()
                        .find('input').prop('id','user-add-mod-pwd-old').prop('name','pwd_old').end()
                        .insertBefore($pwd_p);
                }

                //Add 'deleted' field
                if (data.user.deleted) {
                    // cache
                    var $form_deleted;
                    if (_module.cache.mod && _module.cache.mod.$form_deleted) $form_deleted = _module.cache.mod.$form_deleted.clone();
                    else {
                        if (!_module.cache.mod) _module.cache.mod = {};
                        $form_deleted = $('<fieldset><input type="checkbox" id="user-add-mod-deleted" name="deleted" value="1"><label for="user-add-mod-deleted"></label></fieldset>');
                        // cache
                        _module.cache.mod.$form_deleted = $form_deleted.clone();
                    }

                    $form_deleted.find('label').html(Globalize.localize('user')['deleted']).end()
                        .insertAfter($form.find('input[name="rfid"]').closest('p'));
                }


                // fill form
                $form.prop('action', 'mod')
                    //Add id - hidden text
                    .append($('<input>').attr('type', 'hidden').attr('id', 'id').attr('name','id').attr('value', data.user.id))
                    // input values
                    .find(':input').each( function(i){
                        if (this.name && data.user[this.name]) {
                            if (this.type == 'checkbox') $(this).val([data.user[this.name]]);
                            else $(this).val(data.user[this.name]);
                        }
                    })
                    .end()
                    .find('select[name="cat_ids[]"]').val(data.user.cat_ids.split(',')).end()
                    .find('select[name="cc_cat_ids[]"]').val(data.user.cc_cat_ids.split(',')).end()
                    //submit button lang
                    .find('input[type="submit"], button[type="submit"]').val(Globalize.localize('_base')['mod']);

                $('#main').html($win);

                App.ajaxDataProcessComplete();
            };

            // data = JSON { textStatus = "ok" }
            this.on_submit = function(data)
            {
                console.log('user.mod.on_submit()');
                if (data.textStatus == "ok") {
                    $('<div>'+Globalize.localize('user')['mod_ok']+'</div>').dialog({
                        title: Globalize.localize('user')['win_title_mod'],
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
                var $win = $('#cache > div[name="app-window-user-add-mod"]');
                if (!$win.length) {
                    $win = $('#cache > div[name="app-window"]').clone().attr('name', 'app-window-user-add-mod').data('cache', 1)
                        .find('div.app-window-content').html($('<form></form>').dform(App.forms.user.add_mod))
                        .end();
                }
                return $win;
            };

            this.resetForm = function($form)
            {
                $form.data('validator').resetForm();
                $form
                    .find('.error').removeClass('error').end()
                    .find('input[name="id"]').remove().end()
                    .find('input[name="pwd_old"]')
                        .closest('p').remove().end()
                    .end()
                    .find('input[name="image_delete"]')
                        .closest('fieldset').remove().end()
                    .end()
                    .find('input[name="deleted"]')
                        .closest('fieldset').remove().end()
                    .end()
                    [0].reset();

                return $form;
            };

            this.fill_select = function($select, data)
            {
                $select.empty().attr('size',0);
                if ($select.attr('id') == 'user-add-mod-cc-cat-ids') $select.append($("<option></option>")).attr('size',1);
                $.each(data, function(key, value) { $select.append($("<option></option>").attr('value',key).html(value)).attr('size',parseInt($select.attr('size'))+1); });
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
                console.log('user.del.init()');
                App.delConfirmDialog(Globalize.localize('user')['win_title_del'], Globalize.localize('user')['del_confirm'] + '<br>' + data.user.last_name + ', ' + data.user.name, data.user.id);
                App.ajaxDataProcessComplete();
            };

            // data = JSON { textStatus = "ok" }
            this.on_del = function(data)
            {
                console.log('user.del.on_submit()');
                if (data.textStatus == "ok") {
                    $('<div>'+Globalize.localize('user')['del_ok']+'</div>').dialog({
                        title: Globalize.localize('user')['win_title_del'],
                        buttons: [{ text: "Ok", click: function(){ App.ajaxUrl(App.url_base + '/user/search'); $(this).dialog( "destroy" ); } }]
                    });
                }
                App.ajaxDataProcessComplete();
            }
        };

    };

    return my;

}(App || {}));