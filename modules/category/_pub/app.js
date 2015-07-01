var App = (function (my) {
    my.modules.category = new function()
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
                console.log('category.search.init()');
                // Required files
                var missing_files = false;
                if (!App.templates.category || !App.templates.category.search) { App.loadTemplate('category', 'search'); missing_files = true; }
                if (missing_files) return;

                App.cacheAppWindows();
                _module.cache.search = {};
                _module.cache.search.acl_defs = data.acl_defs;

                _module.cache.search.categories = {};
                $.each(data.categories, function(i){
                    _module.cache.search.categories[data.categories[i]['id']] = data.categories[i]['name'];
                });

                data.acl_data = _module.search.acl_parse_data(data.acl_data);

                $('#main').html(
                    $('#cache > div[name="app-window"]').clone()
                        .prop('id', 'app-window-category-search')
                        .find('h1').html(Globalize.localize('category')['win_title_list']).end()
                        .find('.app-window-content').html(Mustache.render(App.templates.category.search.list, $.extend({},Globalize.localize('_base'), Globalize.localize('category'), data), App.templates._base)).end()
                );

                $('#app-window-category-search').find('table > tbody > tr:first').addClass('selected');

                if (data.tabs) {
                    $('#main').append(
                        $('#cache > div[name="app-window"]').clone().prop('id', 'app-window-search-tabs')
                            .find('h1').html(Globalize.localize('category')['win_title_info']).end()
                            .find('div.app-window-content').html(Mustache.render(App.templates.category.search.tabs_script, $.extend({},Globalize.localize('_base'), Globalize.localize('category'), data))).end()
                    );

                    var $tabs = $('#tabs');
                    $tabs
                        .find('#tab_schedule form').prop('action', App.url_base+'/category/set_schedule')
                            .find('input[name="id"]').val(data.category.id).end()
                        .end()
                        .find('#tab_acl form').prop('action', App.url_base+'/category/acl_add')
                            .find('input[name="id"]').val(data.category.id).end()
                            .find('select[name="module"]').append(App.build_select_options(Object.keys(data.acl_defs),Globalize.localize('_base'))).end()
                            .find('table tbody').append(Mustache.render(App.templates.category.search.acl_tr, $.extend({},Globalize.localize('_base'), Globalize.localize('category'), data))).end()
                        .end()
                        .find('#tab_non_working_days form').prop('action', App.url_base+'/category/non_working_days_add')
                            .find('input[name="id"]').val(data.category.id).end()
                        .end();

                    $tabs.find('.category-schedule tr:nth-child(-n+2) input').timepicker();
                    $tabs.find('#tab_non_working_days').find('tr.add input[type="text"]').datepicker({ minDate: 0 }).datepicker('setDate', new Date());

                    $tabs.tabs();
                }

                App.ajaxDataProcessComplete();
            };

            this.on_category = function($tr)
            {
                var id = $tr.data('id');
                $.ajax({
                    url: App.url_base+'/category/view?ajax=1&data_type=json&id='+id,
                    type: 'get',
                    dataType : "JSON",
                    global : false
                })
                // store data so it can be used once ALL ajax calls have stopped
                .done(function(data, textStatus, jqXHR) {
                    if (data.textStatus == 'error') {
                        my.errorsDialog(data.errors, Globalize.localize('category'));
                        my.ajaxDataProcessComplete();
                        return;
                    }

                    data.acl_data = _module.search.acl_parse_data(data.acl_data);

                    $tr.closest('tbody').find('tr').removeClass('selected').end().end()
                        .addClass('selected');

                    $('#tab_schedule').find('input[name="id"]').val(id);
                    $('#tab_acl')
                        .find('input[name="id"]').val(id).end()
                        .find('tbody')
                            .find('tr').slice(1).remove().end().end()
                            .append(Mustache.render(App.templates.category.search.acl_tr, $.extend({},Globalize.localize('_base'), Globalize.localize('category'), data)));
                    $('#tab_non_working_days').find('input[name="id"]').val(id);

                });
            };

            this.schedule_all = function(input) {
                $(input).closest('tr').find('input').slice(1).val(input.value);
            };

            // data = JSON { textStatus = "ok" }
            this.on_schedule = function(data)
            {
                console.log('category.search.on_schedule()');
                if (data.textStatus == "ok") {
                    $('<div>'+Globalize.localize('category')['mod_ok']+'</div>').dialog({
                        title: Globalize.localize('category')['win_title_mod'],
                        buttons: [{ text: "Ok", click: function(){ $(this).dialog( "destroy" ); } }]
                    });
                }
                App.ajaxDataProcessComplete();
            };

            this.acl_parse_data = function(data)
            {
                $.each(data, function(i){
                    data[i]['allow'] = parseInt(data[i]['allow']) ? Globalize.localize('category')['allow'] : Globalize.localize('category')['deny'];
                    data[i]['module'] = (data[i]['module'] == '*' ? Globalize.localize('_base')['all'] : Globalize.localize('_base')[data[i]['module']]);
                    data[i]['action'] = (data[i]['action'] ? (data[i]['action'] == '*' ? Globalize.localize('_base')['all'] : Globalize.localize('_base')[data[i]['action']] ) : null);
                    if (!data[i]['action_filter_criteria']) data[i]['action_filter_criteria'] = ' ';
                    else {
                        if (data[i]['action_filter_criteria'] == '*') data[i]['action_filter_criteria'] = Globalize.localize('_base')['all'];
                        else data[i]['action_filter_criteria'] = Globalize.localize('category')[data[i]['action_filter_criteria']];
                    }
                    if (!data[i]['action_filter_value']) data[i]['action_filter_value'] = ' ';
                });
                return data;
            };

            this.acl_on_module = function(module)
            {
                console.log('category.search.acl_on_module()');

                var $sel_action = $('#tab_acl').find('select[name="module_action"]');
                $sel_action.empty().append(App.build_select_options(['*'], Globalize.localize('category')));
                if (module == '*') $sel_action.prop('disabled', 'disabled');
                else $sel_action.prop('disabled', false).append(App.build_select_options(_module.cache.search.acl_defs[module], Globalize.localize('_base')));

                _module.search.acl_on_action('*');
            };

            this.acl_on_action = function(action)
            {
                console.log('category.search.acl_on_action()');

                var $tab = $('#tab_acl');
                var module = $tab.find('select[name="module"]')[0].value;
                var $sel_action_filter = $tab.find('select[name="action_filter_criteria"]');
                $sel_action_filter.empty().append(App.build_select_options(['*'], Globalize.localize('category')));

                if (action == '*' || $.inArray(module, ['user', 'rfid', 'user_absence', 'cc_purchase', 'cc_delivery']) == -1) $sel_action_filter.prop('disabled', 'disabled');
                else {
                    if (action != '*') $sel_action_filter.append(App.build_select_options(['self'], Globalize.localize('category')));
                    switch (module) {
                        case 'user':
                        case 'rfid':
                        case 'user_absence':
                            $sel_action_filter.append(App.build_select_options(['category_id'], Globalize.localize('category')));
                            break;
                        case 'cc_purchase':
                            // $sel_action_filter.append(App.build_select_options(['cc_supplier_id'], Globalize.localize('category')));
                            break;
                        case 'cc_delivery':
                            // $sel_action_filter.append(App.build_select_options(['opened_by'], Globalize.localize('category')));
                            break;
                    }
                    $sel_action_filter.prop('disabled', false);
                }
                _module.search.acl_on_action_filter_criteria('*');
            };

            this.acl_on_action_filter_criteria = function(filter)
            {
                console.log('category.search.acl_on_action_filter_criteria()');

                var $sel_action_filter_value = $('#tab_acl').find('select[name="action_filter_value"]');
                $sel_action_filter_value.empty();
                if (filter == '*' || filter == 'self') $sel_action_filter_value.prop('disabled', 'disabled').prop("selectedIndex",1);
                else $sel_action_filter_value.prop('disabled', false).append('<option value="self">'+Globalize.localize('category')['self']+'</option>').append(App.build_select_options(_module.cache.search.categories));
            };

            this.acl_on_add = function(data)
            {
                console.log('category.search.on_acl_add()');
                data.acl_data = _module.search.acl_parse_data(data.acl_data);

                if (data.textStatus == "ok") {
                    var $tbody = $('#tab_acl').find('tbody');
                    $tbody.find('tr').slice(1).remove();
                    $tbody.append(Mustache.render(App.templates.category.search.acl_tr, $.extend({},Globalize.localize('_base'), Globalize.localize('category'), _module.search.acl_parse_data(data))));
                }
                App.ajaxDataProcessComplete();
            };

            this.acl_del_confirm = function(id)
            {
                console.log('category.search.on_del_confirm()');

                var $del_confirm = $('#cache div[name="del-confirm"]').clone()
                    .find('div.confirm-text').html(Globalize.localize('category')['acl_del_confirm']).end()
                    .find('input[name="id"]').val(id).end()
                    .find('form').prop('action','acl_del').data('ajaxCallback', 'acl_on_del').end();

                $del_confirm.find('div.db-checkbox').hide().find('input[type="checkbox"]').prop('checked', false).prop('disabled',true);
                $del_confirm.find('div.comments').hide().find('textarea').prop('disabled',true).val('');

                $del_confirm.dialog({
                    title: Globalize.localize('category')['acl_del'],
                    buttons: [
                        { text: "Ok", click: function(){ $(this).find('form').submit(); $(this).dialog( "destroy" ); } },
                        { text: "Cancel", click: function(){ $(this).dialog( "destroy" ); } }
                    ],
                    close: function( event, ui ) { $(this).dialog( "destroy" ); }
                });
            };

            this.acl_on_del = function(data)
            {
                console.log('category.search.on_acl_del()');
                data.acl_data = _module.search.acl_parse_data(data.acl_data);

                if (data.textStatus == "ok") {
                    var $tbody = $('#tab_acl').find('tbody');
                    $tbody.find('tr').slice(1).remove();
                    $tbody.append(Mustache.render(App.templates.category.search.acl_tr, $.extend({},Globalize.localize('_base'), Globalize.localize('category'), _module.search.acl_parse_data(data))));
                }
                App.ajaxDataProcessComplete();
            };


            this.nwd_on_add = function(data)
            {
                console.log('category.search.nwd_on_add()');

                if (data.textStatus == "ok") {
                    $('<div>'+Globalize.localize('category')['nwd_add_ok']+'</div>').dialog({
                        title: Globalize.localize('category')['win_title_add'],
                        buttons: [{ text: "Ok", click: function(){ $(this).dialog( "destroy" ); } }]
                    });
                }
                else App.errorsDialog(data.errors, Globalize.localize('category'));

                App.ajaxDataProcessComplete();
            };

            this.nwd_del_confirm = function(id)
            {
                console.log('category.search.nwd_del_confirm()');

                var $del_confirm = $('#cache div[name="del-confirm"]').clone()
                    .find('div.confirm-text').html(Globalize.localize('category')['nwd_del_confirm']).end()
                    .find('input[name="id"]').val(id).end()
                    .find('form').prop('action','non_working_days_del').data('ajaxCallback', 'nwd_on_del').end();

                $del_confirm.find('div.db-checkbox').hide().find('input[type="checkbox"]').prop('checked', false).prop('disabled',true);
                $del_confirm.find('div.comments').hide().find('textarea').prop('disabled',true).val('');

                $del_confirm.dialog({
                    title: Globalize.localize('category')['del_nwd'],
                    buttons: [
                        { text: "Ok", click: function(){ $(this).find('form').submit(); $(this).dialog( "destroy" ); } },
                        { text: "Cancel", click: function(){ $(this).dialog( "destroy" ); } }
                    ],
                    close: function( event, ui ) { $(this).dialog( "destroy" ); }
                });
            };

            this.nwd_on_del = function(data)
            {
                console.log('category.search.nwd_on_del()');

                if (data.textStatus == "ok") {
                    $('<div>'+Globalize.localize('category')['nwd_del_ok']+'</div>').dialog({
                        title: Globalize.localize('category')['win_title_del'],
                        buttons: [{ text: "Ok", click: function(){ $(this).dialog( "destroy" ); } }]
                    });
                }
                else App.errorsDialog(data.errors, Globalize.localize('category'));

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
                console.log('category.add.init()');
                // Required files
                var missing_files = false;
                if (!App.forms.category || !App.forms.category.add_mod)  { App.loadDForm('category', 'add_mod'); missing_files = true; }
                if (missing_files) return;

                var win_already = false;

                /**
                 * Cache
                 * div=app-window-user-add-mod may be already on #main
                 * (as when the user comes from /user/add or /user/mod)
                 * If it is
                 */
                var $win = $('#main > div[name="app-window-category-add-mod"]');
                if ($win.length) {
                    win_already = true;

                    // cache and remove everything else
                    // avoid caching $win
                    $win.data('cache', 0);

                    App.cacheAppWindows();
                    $('#main > div').not('[name="app-window-category-add-mod"]').remove();

                    $win.data('cache', 1);
                }
                else {
                    App.cacheAppWindows();

                    $win = $('#cache > div[name="app-window-category-add-mod"]');
                    if (!$win.length) {
                        console.log('category.add - window is NOT CACHED');
                        $win = $('#cache > div[name="app-window"]').clone().attr('name', 'app-window-category-add-mod').data('cache', 1)
                            .find('div.app-window-content').html($('<form></form>').dform(App.forms.category.add_mod)).end()
                    }
                }

                // win title
                $win.find('h1').html(Globalize.localize('category')['win_title_add']);

                var $form = $win.find('form');

                // reset
                $form.data('validator').resetForm();
                $form
                    .find('.error').removeClass('error').end()
                    .find('input[name="parent_id"]').remove().end()
                    .find('input[name="id"]').remove().end()
                    [0].reset();

                // action + submit button lang
                $form.prop('action', 'add')
                    .append( $('<input>').attr('type', 'hidden').attr('name', 'parent_id').attr('value', data.parent.id) )
                    .find('input[name="parent_category"]').val(data.parent.name).end()
                    .find('input[type="submit"], button[type="submit"]').val(Globalize.localize('_base')['add']);

                if (!win_already) $('#main').html($win);

                App.ajaxDataProcessComplete();
            };

            // data = JSON { textStatus = "ok" }  OR { textStatus = "error", errors[form_field] = error }
            this.on_submit = function(data)
            {
                console.log('category.add.on_submit()');
                if (data.textStatus == "ok") {
                    $('<div>'+Globalize.localize('category')['add_ok']+'</div>').dialog({
                        title: Globalize.localize('category')['win_title_add'],
                        buttons: [{ text: "Ok", click: function(){ App.ajaxUrl(App.request.url_base + '/category/search'); $( this ).dialog( "destroy" ); } }]
                    });
                }
                else App.errorsDialog(data.errors, Globalize.localize('category'));
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
                console.log('category.mod.init()');
                // Required files
                var missing_files = false;
                if (!App.forms.category || !App.forms.category.add_mod)  { App.loadDForm('category', 'add_mod'); missing_files = true; }
                if (missing_files) return;

                App.cacheAppWindows();

                // cache
                var $win = $('#cache > div[name="app-window-category-add-mod"]');
                if (!$win.length) {
                    $win = $('#cache > div[name="app-window"]').clone().attr('name', 'app-window-category-add-mod').data('cache', 1)
                        .find('div.app-window-content').html($('<form></form>').dform(App.forms.category.add_mod)).end()
                }

                // win title
                $win.find('h1').html(Globalize.localize('category')['win_title_mod']);

                var $form = $win.find('form');

                // reset
                $form.data('validator').resetForm();
                $form
                    .find('.error').removeClass('error').end()
                    .find('input[name="parent_id"]').remove().end()
                    .find('input[name="id"]').remove().end()
                    [0].reset();

                // form
                $form.prop('action', 'mod')
                    //Add id - hidden text
                    .append($('<input>').attr('type', 'hidden').attr('name','id').attr('value', data.category.id))
                    // input values
                    .find(':input').each( function(i){ if (this.name && data.category[this.name]) $(this).val(data.category[this.name]); }).end()
                    // parent category
                    .find('input[name="parent_category"]').val(data.parent.name).end()
                    //submit button lang
                    .find('input[type="submit"], button[type="submit"]').val(Globalize.localize('_base')['mod']);

                $('#main').html($win);

                App.ajaxDataProcessComplete();
            };

            // data = JSON { textStatus = "ok" }  OR { textStatus = "error", errors[form_field] = error }
            this.on_submit = function(data)
            {
                console.log('category.mod.on_submit()');
                if (data.textStatus == "ok") {
                    $('<div>'+Globalize.localize('category')['mod_ok']+'</div>').dialog({
                        title: Globalize.localize('category')['win_title_mod'],
                        buttons: [{ text: "Ok", click: function(){ App.ajaxUrl(location.href); $(this).dialog( "destroy" ); } }]
                    });
                }
                else App.errorsDialog(data.errors, Globalize.localize('cc_purchase'));
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
                console.log('category.del.init()');

                var $dialog = $('#cache > div[name="del-confirm"]');
                $dialog.children('div').html(Globalize.localize('category')['del_confirm']+ '<p>' + data.category.name + '</p>').end()
                    .find('input[name="id"]').val(data.category.id).end()
                    .dialog({
                        title: Globalize.localize('category')['win_title_del'],
                        buttons: [
                            { text: "Ok", click: function(){ $(this).find('form').submit(); $(this).dialog( "destroy" ); } },
                            { text: "Cancel", click: function(){ $(this).dialog( "destroy" ); } }
                        ]
                    });

                App.ajaxDataProcessComplete();
            };

            // data = JSON { textStatus = "ok" }  OR { textStatus = "error", errors[form_field] = error }
            this.on_del = function(data)
            {
                console.log('category.del.on_del()');
                if (data.textStatus == "ok") {
                    $('<div>'+Globalize.localize('category')['del_ok']+'</div>').dialog({
                        title: Globalize.localize('category')['win_title_del'],
                        buttons: [{ text: "Ok", click: function(){ App.ajaxUrl(App.request.url_base + '/category/search'); $(this).dialog( "destroy" ); } }]
                    });
                }
                else App.errorsDialog(data.errors, Globalize.localize('category'));
                App.ajaxDataProcessComplete();
            }
        };

    };

    return my;

}(App || {}));