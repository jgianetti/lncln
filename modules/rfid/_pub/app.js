var App = (function (my) {
    my.modules.rfid = new function()
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
                console.log('rfid.search.init()');

                App.cacheAppWindows();

                var theaders = ['image', 'fullname', 'date', 'time', 'is_early', 'is_entering', 'entrance', 'category', 'deleted', 'comments'];

                var $table = App.buildDataTable(theaders, Globalize.localize('rfid'),'a'); // headers, lang, extras(m[od]d[del]u[ndelete]f[ooter]
                $table.addClass('td-first-image');

                $('#main').html(
                    $('#cache > div[name="app-window"]').clone().prop('id', 'app-window-rfid-list')
                        .find('h1').html(Globalize.localize('rfid')['win_title_list']).end()
                        .find('div.app-window-content').html($table)
                        .end()
                );

                $table.dataTable({
                    "iDeferLoading": [ data.iTotalDisplayRecords, data.iTotalRecords ],
                    "aaData" : data.aaData,
                    "sAjaxSource" : App.url_base + "/rfid/search",
                    "aoColumnDefs": [
                        { "aTargets": [ 0 ], // image
                            "bSortable" : false,
                            "mData": function ( source, type, val ) {
                                if (type === 'display') {
                                    if (parseInt(source.user_img)) return '<img src="'+App.url_base+'/uploads/user/_pub/'+source.user_id+'.png'+'">';
                                    else return '<img src="'+App.url_base+'/modules/user/_pub/img/not_found.jpg">';
                                }
                                return source.user_img;
                            }
                        },
                        { "aTargets": [ 1 ], // fullname
                            "mData": function ( source, type, val ) {
                                if (type === 'display') { return '<a href="'+App.url_base+'/user/view/?id='+source.user_id+'" data-ajax="1">'+source.user_last_name+', '+source.user_name+'</a>' }
                                return source.user_last_name;
                            }
                        },
                        { "aTargets": [ 2 ], // date
                            "mData": function ( source, type, val ) {
                                return source.date.split(' ')[0];
                            }
                        },
                        { "aTargets": [ 3 ], // time
                            "mData": function ( source, type, val ) {
                                return source.date.split(' ')[1];
                            }
                        },
                        { "aTargets": [ 4 ], // is_early
                            "mData": function ( source, type, val ) {
                                var html = '';
                                if (type === 'display') {
                                    if (source.user_work_shift_id) {
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
                        { "aTargets": [ 5 ], // is_entering
                            "bSortable" : false,
                            "mData": function ( source, type, val ) {
                                if (type === 'display') { return parseInt(source.is_entering)?Globalize.localize('rfid')['entering']:Globalize.localize('rfid')['exiting'] }
                                return source.is_entering;
                            }
                        },
                        { "aTargets": [ 6 ], "mData": "entrance" },
                        { "aTargets": [ 7 ], // categories
                            "mData": function ( source, type, val ) { return source.user_cat_names.replace(',', '<br>') }
                        },
                        { "aTargets": [ 8 ], // deleted
                            "bSortable" : false,
                            "mData": function ( source, type, val ) {
                                if (type === 'display') { return parseInt(source.deleted)?Globalize.localize('_base')['yes']:Globalize.localize('_base')['no'] }
                                return source.deleted;
                            }
                        },
                        { "aTargets": [ 9 ], "mData": "comments" },
                        { "aTargets": [ 10 ], // Del
                            "bSortable" : false,
                            "mData": function ( source, type, val ) {
                                if (type === 'display') {
                                    return '<a class="ui-state-default ui-corner-all app-btn" href="'+App.url_base+'/rfid/mod/?id='+source.id+'" data-ajax="1" title="' + Globalize.localize('_base')['mod'] + '"><span class="ui-icon ui-icon-pencil">' + Globalize.localize('_base')['mod'] + '</span></a> ';
                                }
                                return source.id;
                            }
                        }
                    ],
                    "aaSorting": [[2,'desc']] // default sorting
                })
                .columnFilter({
                    sRangeFormat : "Del {from} al {to}",
                    sPlaceHolder: "head:after",
                    aoColumns: [
                        null, // image
                        { type: "text" }, // fullname
                        { type: "date-range" }, // date
                        null, // time
                        { type: "select", values: [ // is_early
                            { "value":"1","label":Globalize.localize('rfid')['early'] },
                            { "value":"0","label":Globalize.localize('rfid')['late'] }
                        ] },
                        { type: "select", values: [ // is_entering
                            { "value":"1","label":Globalize.localize('rfid')['entering'] },
                            { "value":"0","label":Globalize.localize('rfid')['exiting'] }
                        ] },
                        { type: "select", values: data.entrances },  // entrance
                        { type: "select", values: data.categories },  // category
                        { type: "select", values: [ // deleted
                            { "value":"0","label":Globalize.localize('_base')['no'] },
                            { "value":"1","label":Globalize.localize('_base')['yes'] }
                        ] },
                        null, // comments
                        null
                    ]
                });

                // default date_from = yesterday
                $('input.date_range_filter').slice(0,1).val(App.getDate('yesterday'));

                App.ajaxDataProcessComplete();
            };
        };

        this.add = new function()
        {
            this.entrance = '';

            // Accept keyboard input as code
            this.code_reading = true;

            /**
             * Select entrance
             * @param data holds form data
             */
            this.init = function(data)
            {
                console.log('rfid.add.init()');

                this.code_reading = true;

                var missing_files = false;
                if (!App.templates.rfid || !App.templates.rfid.add)  { App.loadTemplate('rfid', 'add'); missing_files = true; }
                if (missing_files) return;

                App.cacheAppWindows();

                if (data.entrance) {
                    App.ajaxDataProcessComplete();
                    _module.add.on_entrance_select(data.entrance);
                    return;
                }

                var $win_entrance_select = $('#cache > div[name="app-window"]').clone().attr('name', 'app-window-rfid-entrance-select')
                    .find('h1').html(Globalize.localize('rfid')['win_title_entrance_select']).end()
                    .find('div.app-window-content').html(Mustache.render(App.templates.rfid.add.entrance_select, data)).end();

                $('#main').html($win_entrance_select);
                App.ajaxDataProcessComplete();
            };

            // See templates\add.mustache\entrance_select
            // Accept RFID tags
            this.on_entrance_select = function(entrance)
            {
                this.entrance = entrance;

                // rfid display
                var $div_code = $('<div>').attr('class', 'rfid-add-code');
                var $win_add = $('#cache > div[name="app-window"]').clone().attr('name', 'app-window-rfid-add')
                    .find('h1').html(Globalize.localize('rfid')['win_title_add']).end()
                    .find('div.app-window-content').html($div_code).end();

                var $win_list = $('#cache > div[name="app-window"]').clone().attr('name', 'app-window-rfid-list')
                    .find('h1').html(Globalize.localize('rfid')['win_title_list']).end()
                    .find('div.app-window-content').html(Mustache.render(App.templates.rfid.add.movements_table, $.extend({},Globalize.localize('_base'), Globalize.localize('rfid')))).end();

                $('#main').html($win_add).append($win_list);

                // Keypress event handler
                // .to_be_removed Namespace : see \_pub\js\app.js
                $(document).on('keypress.to_be_removed',function(e) {

                    if (!_module.add.code_reading) return;

                    if (e.keyCode == 27) { // escape
                        $div_code.html('');
                        return;
                    }

                    // avoid any non-character key
                    if (e.which === 0 || e.which == 32) return;

                    var code = $div_code.html();

                    if (e.which == 8) { // backspace
                        e.preventDefault();
                        if (!code) return;
                        $div_code.html($div_code.html().slice(0,-1));
                        return;
                    }

                    if (e.which == 13) { // enter
                        if (!code) return;

                        // checking code - don't commit to DB
                        var checking = false;
                        if (code.charAt(0) == "-") {
                            checking = true;
                            code = code.slice(1);
                        }

                        _module.add.ajaxRfid('entrance='+entrance+'&rfid='+code+'&checking='+checking);

                        $div_code.html('');
                        return;
                    }

                    $div_code.append(String.fromCharCode(e.which));
                });
            };

            this.ajaxRfid = function(data, retry_count)
            {
                var retry_delay = 60*1000; // 1min
                var retry_max = 20;

                retry_count = (typeof retry_count !== 'undefined') ? retry_count : 1;

                $.ajax({
                    url: App.url_base + '/rfid/add'  + "?ajax=1&data_type=json" ,
                    type: 'POST',
                    dataType : "JSON",
                    data: data,
                    global: false,
                    cache: false
                })
                // server error
                .fail(function( jqXHR, textStatus, errorThrown ) {
                        // errors are appended to #main
                        App.errorsWindow({"ajax_error" : textStatus + ' - ' + errorThrown + ' :: Response text : ' + jqXHR.responseText + ' Attemp : ' + retry_count + '/' + retry_max + ((retry_count >= retry_max) ? ' - stopped' :'') });
                        // try again later
                        if (retry_count < retry_max) App.setTimeOut(_module.add.ajaxRfid, retry_delay, data, ++retry_count);
                })
                // store data so it can be used once ALL ajax calls have stopped
                .done(function(data, textStatus, jqXHR) { _module.add.on_rfid(data) });
            };

            // data = JSON { textStatus = "ok" } or { textStatus = "error", errors[form_field] = error }
            this.on_rfid = function(data)
            {
                console.log('rfid.add.on_rfid()');

                // Translate error
                if (data.textStatus != 'ok') App.errorsWindow(data.errors, Globalize.localize('rfid'));
                else {
                    if (parseInt(data.user_movement.user_img)) data.user_movement.user_img = App.url_base+'/uploads/user/_pub/'+ data.user_movement.user_id + '.png';
                    else data.user_movement.user_img = App.url_base+'/modules/user/_pub/img/not_found.jpg';

                    data.user_movement.user_cat_names = data.user_movement.user_cat_names.replace(',', '<br>');

                    if (data.user_movement.is_entering === ' ') data.user_movement.is_entering_img = App.url_base + '/_pub/css/images/app-null.png';
                    else if (parseInt(data.user_movement.is_entering)) data.user_movement.is_entering_img = App.url_base + '/_pub/css/images/app-icon_enter_64x64.jpg';
                    else data.user_movement.is_entering_img = App.url_base + '/_pub/css/images/app-icon_exit_64x64.jpg';

                    $('#main > div[name="app-window-rfid-list"] table tbody').prepend(Mustache.render(App.templates.rfid.add.movement_tr, $.extend({},Globalize.localize('rfid'), data)));
                }

                App.ajaxDataProcessComplete();
            };

            // Remove movement from list
            this.remove_movement = function($tr)
            {
                // cancel dialog
                if ($tr.data('id') && !$tr.hasClass('cancelled')) App.ajaxUrl(App.url_base+'/rfid/del?id='+$tr.data('id'),'del',0);
                // error/cancelled movement
                else $tr.remove();
            };
        };

        this.mod = new function()
        {
            /**
             * Create html app-windows parsing App.modules._base.templates.window (title, id)
             * @param data holds form data
             */
            this.init = function(data)
            {
                console.log('rfid.mod.init()');
                var missing_files = false;
                if (!App.forms.rfid || !App.forms.rfid.add_mod) { App.loadDForm('rfid', 'add_mod'); missing_files = true; }
                if (missing_files) return;

                App.cacheAppWindows();
                var $win = _module.add_mod.get_win_add_mod();
                $win.find('h1').html(Globalize.localize('rfid')['win_title_mod']);
                var $form = $win.find('form');
                _module.add_mod.resetForm($form);

                //Add 'image'
                if (data.user_movement.user_img) {
                    $form.find('#rfid-add-mod-image').html(
                        $('<fieldset><p><img></p></fieldset>')
                            .find('img').prop('src', App.url_base + '/uploads/user/_pub/' + data.user_movement.user_id + '.png').end()
                    )
                }

                // fill form
                $form.prop('action', 'mod')
                    //Add id - hidden text
                    .append($('<input>').attr('type', 'hidden').attr('id', 'id').attr('name','id').attr('value', data.user_movement.id))
                    // input values
                    .find(':input').each( function(i){
                        if (this.name && data.user_movement[this.name]) {
                            if (this.type == "checkbox") $(this).val([data.user_movement[this.name]]);
                            else $(this).val(data.user_movement[this.name]);
                        }
                    }).end()
                    //submit button lang
                    .find('input[type="submit"], button[type="submit"]').val(Globalize.localize('_base')['mod']);

                $('#main').html($win);

                App.ajaxDataProcessComplete();
            };

            // data = JSON { textStatus = "ok" }
            this.on_submit = function(data)
            {
                console.log('rfid.mod.on_submit()');
                if (data.textStatus == "ok") {
                    $('<div>'+Globalize.localize('rfid')['mod_ok']+'</div>').dialog({
                        title: Globalize.localize('rfid')['win_title_mod'],
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
                var $win = $('#cache > div[name="app-window-rfid-add-mod"]');
                if (!$win.length) {
                    $win = $('#cache > div[name="app-window"]').clone().attr('name', 'app-window-rfid-add-mod').data('cache', 1)
                        .find('div.app-window-content').html($('<form></form>').dform(App.forms.rfid.add_mod))
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
                    [0].reset();

                return $form;
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
                console.log('rfid.del.init()');
                _module.add.code_reading = false;
                App.delConfirmDialog(Globalize.localize('rfid')['win_title_del'], Globalize.localize('rfid')['del_confirm'], data.user_movement.id, false, true);
                App.ajaxDataProcessComplete();
            };

            // data = JSON { textStatus = "ok" } or { textStatus = "error", errors[form_field] = error }
            this.on_del = function(data)
            {
                console.log('rfid.del.on_del()');
                if (data.textStatus == 'ok') {
                    var $tr = $('#main > div[name="app-window-rfid-list"] table tbody tr[data-id='+data.user_movement.id+']');
                    $tr.addClass('cancelled')
                        .find(':nth-child(6)').html(data.user_movement.comments).end()
                        .find('button > span').html('-');
                }
                else App.errorsDialog(data.errors, Globalize.localize('rfid'));

                _module.add.code_reading = true;
                App.ajaxDataProcessComplete();
            };

            // User has cancelled the deletion
            this.on_cancel = function()
            {
                console.log('rfid.del.on_cancel()');
                _module.add.code_reading = true;
            }
        };
    };

    return my;

}(App || {}));