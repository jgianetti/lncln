var App = (function (my) {
    my.modules.user_absence = new function()
    {
        var _module = this;

        this.cache = {};

        this.search = new function()
        {
            /**
             * Create html app-windows parsing App.modules._base.templates.window (title, id)
             */
            this.init = function(data)
            {
                console.log('user_absence.search.init()');

                App.cacheAppWindows();

                var theaders = ['image', 'fullname', 'date', 'category', 'cc_category', 'comments'];

                var $table = App.buildDataTable(theaders, Globalize.localize('user_absence'), 'a');
                $table.addClass('td-first-image');

                $('#main').html(
                    $('#cache > div[name="app-window"]').clone().prop('id', 'app-window-user-absence-list')
                        .find('h1').html(Globalize.localize('user_absence')['win_title_list']).end()
                        .find('div.app-window-content')
                            .html($table)
                        .end()
                );

                $table.dataTable({
                    "iDeferLoading": [ data.iTotalDisplayRecords, data.iTotalRecords ],
                    "aaData" : data.aaData,
                    "sAjaxSource": App.url_base + "/user_absence/search",
                    "aoColumnDefs": [
                        { "aTargets": [ 0 ], // image
                            "bSortable" : false,
                            "mData": function ( source, type, val ) {
                                if (type === 'display') {
                                    if (parseInt(source.user_img)) return '<img src="'+App.url_base+'/uploads/user/_pub/'+source.user_id+'.png">';
                                    else return '<img src="'+App.url_base+'/modules/user/_pub/img/not_found.jpg">';
                                }
                                return source.user_img;
                            }
                        },
                        { "aTargets": [ 1 ], // fullname
                            "mData": function ( source, type, val ) {
                                if (type === 'display') { return '<a href="'+App.url_base+'/user/view/?id='+source.user_id+'" data-ajax="1">'+source.last_name+', '+source.name+'</a>' }
                                return source.user_id;
                            }
                        },
                        { "aTargets": [ 2 ], "mData": "date" },
                        { "aTargets": [ 3 ], // categories
                            "mData": function ( source, type, val ) {
                                if (type === 'display') { return source.cat_names.replace(',','<br>') }
                                return source.cat_names;
                            }
                        },
                        { "aTargets": [ 4 ], "mData": "cc_cat_names" },
                        { "aTargets": [ 5 ], "mData": function ( source, type, val ) { return source.comments.replace(/\n/g, "<br/>"); } }, // comments
                        { "aTargets": [ 6 ], // Action
                            "bSortable" : false,
                            "mData": function ( source, type, val ) {
                                if (type === 'display') {
                                    return '<a class="ui-state-default ui-corner-all app-btn" href="'+App.url_base+'/user_absence/mod/?id='+source.id+'" data-ajax="1" title="' + Globalize.localize('_base')['mod'] + '"><span class="ui-icon ui-icon-pencil">' + Globalize.localize('_base')['mod'] + '</span></a> ' +
                                            '<a class="ui-state-default ui-corner-all app-btn" href="'+App.url_base+'/user_absence/del/?id='+source.id+'" data-ajax-callback="del" data-push-state="0" title="' + Globalize.localize('_base')['del'] + '"><span class="ui-icon ui-icon-closethick">' + Globalize.localize('_base')['del'] + '</span></a>';
                                }
                                return source.id;
                            }
                        },
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
                        { type: "select", values: data.categories }, // category
                        { type: "select", values: data.cc_categories },  // cc_category
                        null, // comments
                        null, // action
                    ]
                });

                // default date_from = yesterday
                $('input.date_range_filter').slice(0,1).val(App.getDate('yesterday'));

                App.ajaxDataProcessComplete();

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
                console.log('user_absence.mod.init()');
                var missing_files = false;
                if (!App.forms.user_absence || !App.forms.user_absence.add_mod) { App.loadDForm('user_absence', 'add_mod'); missing_files = true; }
                if (missing_files) return;

                App.cacheAppWindows();
                var $win = _module.add_mod.get_win_add_mod();
                $win.find('h1').html(Globalize.localize('user_absence')['win_title_mod']);
                var $form = $win.find('form');
                _module.add_mod.resetForm($form);

                //Add 'image'
                if (parseInt(data.user_absence.user_img)) {
                    $form.find('#user-absence-add-mod-image').html(
                        $('<fieldset><p><img></p></fieldset>')
                            .find('img').prop('src', App.url_base + '/_pub/img/user/' + data.user_absence.user_id + '.png').end()
                    )
                }

                // fill form
                $form.prop('action', 'mod')
                    //Add id - hidden text
                    .append($('<input>').attr('type', 'hidden').attr('id', 'id').attr('name','id').attr('value', data.user_absence.id))
                    .find('#user-absence-add-mod-fullname').val(data.user_absence.last_name+', '+data.user_absence.name).end()
                    // input values
                    .find(':input').each( function(i){
                        if (this.name && data.user_absence[this.name]) {
                            if (this.type == "checkbox") $(this).val([data.user_absence[this.name]]);
                            else $(this).val(data.user_absence[this.name]);
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
                console.log('user_absence.mod.on_submit()');
                if (data.textStatus == "ok") {
                    $('<div>'+Globalize.localize('user_absence')['mod_ok']+'</div>').dialog({
                        title: Globalize.localize('user_absence')['win_title_mod'],
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
                var $win = $('#cache > div[name="app-window-user-absence-add-mod"]');
                if (!$win.length) {
                    $win = $('#cache > div[name="app-window"]').clone().attr('name', 'app-window-user-absence-add-mod').data('cache', 1)
                        .find('div.app-window-content').html($('<form></form>').dform(App.forms.user_absence.add_mod))
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
                console.log('user_absence.del.init()');
                App.delConfirmDialog(Globalize.localize('user_absence')['win_title_del'], Globalize.localize('user_absence')['del_confirm'] + '<br>' + data.user_absence.last_name + ', ' + data.user_absence.name, data.user_absence.id);
                App.ajaxDataProcessComplete();
            };

            // data = JSON { textStatus = "ok" }
            this.on_del = function(data)
            {
                console.log('user_absence.del.on_submit()');
                if (data.textStatus == "ok") {
                    $('<div>'+Globalize.localize('user_absence')['del_ok']+'</div>').dialog({
                        title: Globalize.localize('user_absence')['win_title_del'],
                        buttons: [{ text: "Ok", click: function(){ App.ajaxUrl(App.url_base + '/user_absence/search'); $(this).dialog( "destroy" ); } }]
                    });
                }
                App.ajaxDataProcessComplete();
            }
        };

    };

    return my;

}(App || {}));