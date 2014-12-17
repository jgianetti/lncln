var App = (function (my) {
    // modules templates and forms
    my.templates = {};
    my.forms = {};

    // Parses URL and stores Module & Acion
    my.request = new function() {
        this.url = this.url_base = this.module = this.action = '';
        this.parseUrl = function(url)
        {
            this.url = url;
            var path;
            // Absolute
            if (url.indexOf(":") !== -1) path = url.match(/.+?\:\/\/.+?(\/.+?)(?:#|\?|$)/)[1];
            else path = url.split('?')[0];

            // sub-folder support by stripping url_base
            if (this.url_base == path.slice(0, this.url_base.length))  path = path.slice(this.url_base.length);
            var url_parts = path.replace(/^\/|\/$/g, "").split('/');

            this.module = url_parts[0];
            this.action = url_parts[1];
        };

        this.getUrl = function() { return this.url; };
        this.getModule = function() { return this.module; };
        this.getAction = function() { return this.action; };
    };

    // response from server
    my.ajax = new function()
    {
        this.data = this.callback = null;
        // whether to call history.pushState upon request
        this.push_state = true;
        // browser's forward and back button
        this.is_popstate = false;

        this.reset = function()
        {
            (App.APP_ENV == App.APP_ENV_DEV) && console.log('App.ajax.reset()');
            this.data = this.callback = null; this.push_state = true; this.is_popstate = false;
        }
    };

    // modules functions
    my.modules = new function()
    {
        this._base = new function()
        {
            this.init = function()
            {
                // Lang
                $.getJSON(my.request.url_base + '/_pub/lang/' + Globalize.culture().name + '.json')
                    .done(function(data, textStatus, jqXHR){
                        Globalize.culture().messages["_base"] = data;
                        Globalize.culture().messages['_base'].url_base = my.request.url_base;
                        $('#cache > div[name="del-confirm"]')
                            .find('div.db-checkbox label').html(data['del_data']).end()
                            .find('div.comments label').html(data['comments']).end();
                        // add Messages to Validate Plugin
                        jQuery.extend(jQuery.validator.messages, Globalize.localize('_base'));
                        // dataTables default settings
                        jQuery.extend(jQuery.fn.dataTable.defaults.oLanguage, Globalize.localize('_base')['datatable']);
                        TableTools.DEFAULTS.aButtons = [ { "sExtends": "copy", "sButtonText": Globalize.localize('_base')['copy'] }, { "sExtends": "print", "sButtonText": Globalize.localize('_base')['print'], "sInfo": Globalize.localize('_base')['tabletool_print_info'] }, { "sExtends": "xls", "sButtonText": "Excel" } ];
                    });
            };
        };
    };

    /**
     * Common Functions
     */

    /**
     * Check needed data (js, lang, template, data)
     * If ALL has been loaded, call modules[module][action][callback](data)
     * Else load needed files (js, lang, template, data)
     */
    my.ajaxStop = function () {
        (App.APP_ENV == App.APP_ENV_DEV) && console.log('App.ajaxStop()');

        var callback = my.ajax.callback;
        if (!callback) return; // ajax call not made by App

        var module = my.request.getModule();
        var action = my.request.getAction();
        var data = my.ajax.data;

        if (data && data.textStatus && data.textStatus == 'error') {
            my.errorsDialog(data.errors, Globalize.localize(module));
            my.ajaxDataProcessComplete();
            return;
        }
        else if (!module || !action) {
            $('#main').empty();
            my.ajaxDataProcessComplete();
            return;
        }

        // TODO review this patch
        // fix [module][action]
        if (callback == 'del') { action = 'del'; callback = 'init'; }
        else if (callback == 'on_del') action = 'del';
        if (callback == 'undel') { action = 'undel'; callback = 'init'; }
        else if (callback == 'on_undel') action = 'undel';

        // Module's javascript and lang file - modules[module] is initialized in modules/[module]/_pub/app.js
        var missing_files = false;
        if (!my.modules[module]) { $.getScript(my.request.url_base + '/modules/' + module + '/_pub/app.js?v='+App.JS_VERSION); missing_files = true; }
        if (!Globalize.localize(module)) { my.loadLang(module); missing_files = true; }
        if (missing_files) return;

        // avoid history states loop
        if (!my.ajax.is_popstate && my.ajax.push_state) {
            history.pushState(null, null, my.request.getUrl());
            my.ajax.push_state = false;
        }
        my.modules[module][action][callback](data);
    };

    // once all required ajax data has been loaded and processed
    my.ajaxDataProcessComplete = function()
    {
        (App.APP_ENV == App.APP_ENV_DEV) && console.log('App.ajaxDataProcessComplete()');
        $('body').removeClass("loading");
        my.ajax.reset();
    };

    my.ajaxUrl = function(url, callback, push_state)
    {
        (App.APP_ENV == App.APP_ENV_DEV) && console.log('App.ajaxUrl(): ' + url);
        $('body').addClass("loading");

        push_state = (typeof push_state !== 'undefined') ? push_state : true;
        callback = (typeof callback !== 'undefined') ? callback : 'init';

        // change location - get and store module/action
        if (push_state) {
            // Clear all Event Handlers to be removed
            $(document).off('.to_be_removed');

            my.request.parseUrl(url);
            document.title = $("<div/>").html(Globalize.localize('_base')['document_title'] + " :: " + Globalize.localize('_base')[my.request.getModule()]+' - '+Globalize.localize('_base')[my.request.getAction()]).text();
        }

        // history.pushState on Ajax.stop();
        my.ajax.push_state = push_state;

        // callback function to execute when Ajax Stops
        my.ajax.callback = callback;

        // Get data (url) from server and store it to be used once ALL ajax calls have stopped
        $.getJSON(url + ((url.indexOf('?') === -1) ? '?':'&') + 'ajax=1&data_type=json')
            .done(function(data, textStatus, jqXHR) { my.ajax.data = data; });
    };

    // Load module's lang file and add it to Globalize
    my.loadLang = function(module)
    {
        (App.APP_ENV == App.APP_ENV_DEV) && console.log('App.loadLang(' + module + ')');

        $.getJSON(my.request.url_base + '/modules/' + module + '/_pub/lang/' + Globalize.culture().name + '.json')
            .done(function(data, textStatus, jqXHR){
                Globalize.culture().messages[module] = data;
            });
    };

    // Load dForm (json) file and cache it
    my.loadDForm = function(module, dform_name)
    {
        (App.APP_ENV == App.APP_ENV_DEV) && console.log('App.loadDForm(' + module + ',' + dform_name + ')');
//        my.ajax.callback = 'init';

        if (!my.forms[module]) my.forms[module] = {};
        $.getJSON(my.request.url_base + '/modules/' + module + '/_pub/forms/' + dform_name + '.json')
            .done(function(data, textStatus, jqXHR){
                my.forms[module][dform_name] = data;
            });
    };

    // Load template.mustache (script html) file and cache it
    my.loadTemplate = function(module, template_name)
    {
        (App.APP_ENV == App.APP_ENV_DEV) && console.log('App.loadTemplate(' + module + ',' + template_name + ')');

        if (!my.templates[module]) my.templates[module] = {};
        $.ajax(my.request.url_base + '/modules/' + module + '/_pub/templates/' + template_name + '.mustache', { dataType : 'text' })
            .done(function(data, textStatus, jqXHR){
                my.templates[module][template_name] = [];
                $(data).filter('script').each(function (i, el) {
                    my.templates[module][template_name][el.id] = $(el).html();
                });
            });
    };

    // string : [last_month|tomorrow]
    my.getDate = function(string)
    {
        var today = new Date();
        if (string == 'last_month') return Globalize.format(new Date(today.getFullYear(), today.getMonth() - 1, 1), 'd');
        else if (string == 'first_day') return Globalize.format(new Date(today.getFullYear(), today.getMonth(), 1), 'd');
        else if (string == 'yesterday') return Globalize.format(new Date(today.getFullYear(), today.getMonth(), today.getDate() - 1), 'd');
        else if (string == 'tomorrow') return Globalize.format(new Date(today.getFullYear(), today.getMonth(), today.getDate() + 1), 'd');
        else return Globalize.format(today, 'd');
    };

    // parse a date in yyyy-mm-dd format
    my.parseDate = function (input)
    {
        var parts = input.match(/(\d+)/g);
        return new Date(parts[0], parts[1]-1, parts[2]); // months are 0-based
    };

    my.setTimeOut = function(func, wait) {
        var args = Array.prototype.slice.call(arguments, 2);
        return setTimeout(function(){ return func.apply(null, args); }, wait);
    };

    /**
     * Simulates CACHE by moving the 'app-window' to #cache[display:none]
     * Specially to use with Forms
     */
    my.cacheAppWindows = function()
    {
        (App.APP_ENV == App.APP_ENV_DEV) && console.log('App.cacheAppWindows()');

        $('#main > .app-window').each(function (index){
            $this = $(this);
            if ($this.data('cache')) $this.appendTo('#cache');
        });
    };

    /**
     * Builds HTML tags to use with $().dataTables()
     * @param {string} theaders headers to use in such table
     * @param {string} lang     language array
     * @param {string} extras   m[mod]d[el]f[ooter]
     * @return {jQuery} table
     */
    my.buildDataTable = function(theaders, lang, extras)
    {
        var $table = $('<table/>').attr('width', '100%');
        var $thr = $('<tr/>'); // header row
        var $thr_filters = $('<tr/>'); // header filters row
        for (var i=0;i<theaders.length;i++) {
            $thr.append($('<th>'+lang[theaders[i]]+'</th>'));
            $thr_filters.append($('<th/>'));
        }

        extras = (typeof extras !== 'undefined') ? extras : '';
        // action column
        if (extras.indexOf('a') !== -1) {
            $thr.append($('<th class="action">'+Globalize.localize('_base')['action']+'</th>'));
            $thr_filters.append($('<th/>'));
        }
        // mod column
        if (extras.indexOf('m') !== -1) {
            $thr.append($('<th class="action">'+Globalize.localize('_base')['mod']+'</th>'));
            $thr_filters.append($('<th/>'));
        }
        // del column
        if (extras.indexOf('d') !== -1) {
            $thr.append($('<th class="action">'+Globalize.localize('_base')['del']+'</th>'));
            $thr_filters.append($('<th/>'));
        }
        // undel column
        if (extras.indexOf('u') !== -1) {
            $thr.append($('<th class="action">'+Globalize.localize('_base')['undel']+'</th>'));
            $thr_filters.append($('<th/>'));
        }
        $table.append($('<thead/>').append($thr_filters).append($thr));

        // footer
        if (extras.indexOf('f') !== -1) {
            var $tfr = $('<tr/>'); // footer row
            $tfr.append($('<th>'+Globalize.localize('_base')['total']+'</th>'));
            for (i=1;i<(theaders.length);i++) {
                $tfr.append($('<th/>'));
            }
            if (extras.indexOf('m') !== -1) $tfr.append($('<th/>'));
            if (extras.indexOf('d') !== -1) $tfr.append($('<th/>'));
            $table.append($('<tfoot/>').addClass('total').append($tfr));
        }
        return $table
    };

    my.buildButton = function(label, on_click, data)
    {
        data = (typeof data !== 'undefined') ? data : {};

        return $('#cache button[name="app-button"]').clone()
            .find('span').html(label).end()
            .click(data, on_click);
    };

    my.delConfirmDialog = function(title, text, id, show_db_del, show_comments)
    {
        (App.APP_ENV == App.APP_ENV_DEV) && console.log('App.delConfirmDialog()');

        // display 'delete from database' checkbox
        show_db_del = (typeof show_db_del !== 'undefined') ? show_db_del : true;

        // display 'comments' textarea
        show_comments = (typeof show_comments !== 'undefined') ? show_comments : false;

        // cancel callback is defined
        var cancel_callback = (typeof App.modules[App.request.getModule()].del.on_cancel === 'function') ? true : false;

        var $del_confirm = $('#cache div[name="del-confirm"]')
            .find('div.confirm-text').html(text).end()
            .find('input[name="id"]').val(id).end();

        if (show_db_del) $del_confirm.find('div.db-checkbox').show().find('input[type="checkbox"]').prop('checked', false).prop('disabled',false);
        else $del_confirm.find('div.db-checkbox').hide().find('input[type="checkbox"]').prop('checked', false).prop('disabled',true);

        if (show_comments) $del_confirm.find('div.comments').show().find('textarea').prop('disabled',false).val('');
        else $del_confirm.find('div.comments').hide().find('textarea').prop('disabled',true).val('');

        $del_confirm.dialog({
            title: title,
            buttons: [
                { text: "Ok", click: function(){ $(this).find('form').submit(); $(this).dialog( "destroy" ); } },
                { text: "Cancel", click: function(){ $(this).dialog( "destroy" ); if (cancel_callback) App.modules[App.request.getModule()].del.on_cancel() } }
            ],
            close: function( event, ui ) { $(this).dialog( "destroy" ); }
        });
    };

    my.undelConfirmDialog = function(title, text, id)
    {
        (App.APP_ENV == App.APP_ENV_DEV) && console.log('App.undelConfirmDialog()');

        $('#cache div[name="undel-confirm"]').find('p.confirm-text').html(text).end()
            .find('input[type="checkbox"]').prop('checked', false).end()
            .find('input[name="id"]').val(id).end()
            .dialog({
                title: title,
                buttons: [
                    { text: "Ok", click: function(){ $(this).find('form').submit(); $(this).dialog( "destroy" ); } },
                    { text: "Cancel", click: function(){ $(this).dialog( "destroy" ); } }
                ],
                close: function( event, ui ) { $(this).dialog( "destroy" ); }
            });
    };

    // popups a dialog
    my.errorsDialog = function(errors, lang)
    {
        var html = '';
        var lang_base = Globalize.localize('_base');

        lang = (typeof lang !== 'undefined') ? lang : [];

        jQuery.each(errors, function(key, error) {
            html += '<p class="ui-state-error-text">';

            // assoc array
            if (!$.isNumeric(key)) html += (lang[key] ? lang[key] : (lang_base[key] ? lang_base[key] : key)) + ' :: ';
            html += ((error.indexOf(' ') >= 0) ? error : (lang[error] ? lang[error] : (lang_base[error] ? lang_base[error] : error)));

            html += '.</p>';
        });

        $('<div class="ui-state-error"></div>')
            .html(html)
            .dialog({
                title: Globalize.localize('_base')['error'],
                buttons: [{ text: 'Ok', click: function(){ $(this).dialog( 'destroy' ); } }]
            });
    };

    // prepends a window to #main
    my.errorsWindow = function(errors, lang)
    {
        (App.APP_ENV == App.APP_ENV_DEV) && console.log('errorsWindow()');

        var lang_base = Globalize.localize('_base');
        var $win = $('#main > div[name="app-window-errors"]');
        if (!$win.length) {
            $win = $('#cache > div[name="app-window"]').clone().attr('name', 'app-window-errors').addClass('app-window-errors')
                .find('h1').html(lang_base['errors']).end()
                .find('div.app-window-content').html('<ul></ul>').end()
                .hide();
            $win.appendTo('#main').slideDown();
            $win.find('.app-window-content').resizable({handles: 's'});
        }

        var $ul = $win.find('ul');

        lang = (typeof lang !== 'undefined') ? lang : [];

        var html;

        jQuery.each(errors, function(key, error) {
            $li = $('<li></li>');
            html = Globalize.format(new Date(), {datetime: "short"}) + ' :: ';

            // assoc array
            if (!$.isNumeric(key)) html += (lang[key] ? lang[key] : (lang_base[key] ? lang_base[key] : key)) + ' :: ';
            html += ((error.indexOf(' ') >= 0) ? error : (lang[error] ? lang[error] : (lang_base[error] ? lang_base[error] : error)));

            $li.html(html).prependTo($ul);
        });

        $win.find('div.app-window-header').css({'background-color': '#EE7070'}).animate({'background-color': '#EFF2F9'}, 'slow');
    };

    // return options html using & translating an array|object
    my.build_select_options = function(values, lang)
    {
        if (!values) return;
        lang = (typeof lang !== 'undefined') ? lang : [];
        var html = '';
        // [value1,value2]
        if ($.isArray(values)) $.each(values, function (key, value) { html += '<option value="' + value + '">' + (lang[value] ? lang[value] : value) + '</option>'; });
        // {key1:value1,key2:value2}
        else $.each(values, function (key, value) { html += '<option value="'+ key + '">' + (lang[value] ? lang[value] : value) + '</option>'; });

        return html;
    };
    
    // fill select with data using label_str[+value_str]
    my.fill_select = function($select, data, label_str, value_str)
    {
        $select.empty().attr('size', 1).append($("<option></option>"));
        var label, value;
        if (typeof value_str === 'undefined') value_str = label_str;
        
        $.each(data, function (key, row) {
            if ($.isFunction(label_str)) label = label_str(row);
            else label = row[label_str];
            
            if ($.isFunction(value_str)) value = value_str(row);
            else value = row[value_str];            
            
            $select.append($("<option></option>").attr('value', value).html(label));
        });
        $select[0].selectedIndex = 0;
    };

    return my;

}(App || {}));

$(function(){
    $.ajaxSetup({ cache: (App.APP_ENV == App.APP_ENV_PROD) });

    // jQuery.outer function returns the object including the selector
    $.fn.outer = function(val){ if(val){ $(val).insertBefore(this); $(this).remove(); } else{ return $('<div>').append($(this).clone()).html(); } };
    
    // jQuery UI dialog to allow HTML in title
    $.widget("ui.dialog", $.extend({}, $.ui.dialog.prototype, { _title: function(title) { if (!this.options.title ) title.html("&#160;"); else title.html(this.options.title); } }));

    $.datepicker.regional[""].dateFormat = 'dd/mm/yy';
    $.datepicker.setDefaults($.datepicker.regional['']);

    // jQuery UI autocomplete render item
    jQueryAutocompleteMonkeyPatch();

    // dataTables default settings
    $.extend( $.fn.dataTable.defaults, {
        "sDom" : 'Tlrtip',
        "bLengthChange" : true,
        "iDisplayLength": 20,
        "aLengthMenu": [[-1, 20, 50, 100], ["*", 20, 50, 100]],
        "sPaginationType": "full_numbers",
        "bServerSide": true,
        "bProcessing": true,
        "bDeferRender": true,
        "fnServerData": function ( sSource, aoData, fnCallback ) {
            aoData.push( { "name": "ajax", "value": 1 } );
            aoData.push( { "name": "data_type", "value": "json" } );
            $.getJSON( sSource, aoData, function (json) {
                if (json.textStatus && json.textStatus == 'error') {
                    App.errorsDialog(json.errors);
                    App.ajaxDataProcessComplete();
                    return;
                }
                fnCallback(json)
            } );
        }
    });
    TableTools.DEFAULTS.sSwfPath = App.url_base+"/_pub/js/jquery.dataTables.TableTools.copy_csv_xls.swf";

    $(window)
        // history - back & forward buttons support
        .on('popstate', function(e) {
            (App.APP_ENV == App.APP_ENV_DEV) && console.log('window.history.event.popstate()');
            App.ajax.is_popstate = true;
            App.ajaxUrl(location.href);
        })

        // Ajax Error
        .ajaxError(function (event, jqXHR, ajaxSettings, errorThrown) {
            App.errorsDialog({"ajax_error" : errorThrown + ' :: URL : ' + ajaxSettings.url + ' :: Response text : ' + jqXHR.responseText });
            App.ajaxDataProcessComplete();
        })

        // Ajax Error
        .ajaxStop(function () { App.ajaxStop() });

    $(document)
        // jQuery UI autocomplete prevent ENTER Key
        .on('keypress', 'input.ui-autocomplete-input', function(e) {
            var code = (e.keyCode ? e.keyCode : e.which);
            if(code == 13) { return false; }
        })

        // Ajax Links
        .find('body')
            .on('click', 'a[data-ajax="1"], a[data-ajax-callback]', function(event){
                event.preventDefault();
                $this = $(this);
                App.ajaxUrl(this.href, $this.data('ajaxCallback'), $this.data('pushState'));
                return false;
            })

            // Ajax Forms
            .on('submit', 'form[data-ajax="1"], form[data-ajax-callback]', function(event){
                event.preventDefault();
                var $this = $(this);

                if (this.method != "post") App.ajaxUrl(this.action + "?" + $this.serialize(), $this.data('ajaxCallback'));
                else {
                    $('body').addClass("loading");
                    // callback function to execute when Ajax Stops
                    App.ajax.callback = $this.data('ajaxCallback');
                    // never call history.pushState() on POST forms
                    App.ajax.push_state = false;

                    var formData = new FormData(this);
                    $.ajax({
                        url: this.action + "?ajax=1&data_type=json" ,
                        type: this.method,
                        dataType : "JSON",
                        xhr: function() {  // custom xhr
                            var myXhr = $.ajaxSettings.xhr();
                            // check if upload property exists to handle the progress of the upload
                            if(myXhr.upload) myXhr.upload.addEventListener('progress', function(e){ if(e.lengthComputable) $this.find('progress').css('display','inline').attr({value:e.loaded,max:e.total}); }, false);
                            return myXhr;
                        },
                        // Form data
                        data: formData,
                        //Options to tell JQuery not to process data or worry about content-type
                        cache: false,
                        contentType: false,
                        processData: false
                    })
                    // store data so it can be used once ALL ajax calls have stopped
                    .done(function(data, textStatus, jqXHR) { App.ajax.data = data; });
                }
            })

            .find('#main')
                // Main window minimize button
                .on('click', 'button.app-window-minimize', function(event){
                    $(this).find('span').toggleClass('ui-icon-carat-1-s').end()
                        .parent().next().toggle("blind", 300);
                })
                .on('mouseenter', 'button.app-window-minimize, .app-btn', function(event){
                    $(this).addClass('ui-state-hover');
                })
                .on('mouseleave', 'button.app-window-minimize, .app-btn', function(event){
                    $(this).removeClass('ui-state-hover');
                });

    Globalize.culture(App.lang);
    App.request.url_base = App.url_base;
    App.request.parseUrl(App.init_url);
    App.request.query_string = App.query_string;
    App.modules._base.init();

    if (App.session.ajax_refresh) setInterval(function(){
        $.getJSON(App.url_base+'?ajax=1&get_version=1&rnd='+Math.random())
            .done(function( data ) {
                if (data.app_version && (data.app_version != App.JS_VERSION)) location.reload();
            })
        ;
    },(App.session.lifetime-5)*1000);

    if (App.request.getModule() && App.request.getAction()) App.ajaxUrl(App.init_url, 'init', false);
});


function jQueryAutocompleteMonkeyPatch() {
    // don't really need this, but in case I did, I could store it and chain
    var oldFn = $.ui.autocomplete.prototype._renderItem;

    $.ui.autocomplete.prototype._renderItem = function( ul, item) {
        var re = new RegExp(this.term, 'gi');
        var t = item.label.replace(re,'<span class="match">$&</span>');

        var $a = $(document.createElement('a'));
        if (item.image_src) $a.append('<img src="'+item.image_src+'">');
        $a.append('<p class="label">' + t + '</p>');
        if (item.desc) $a.append('<p class="desc">'+item.desc+'</p>');
        $a.append('<br class="clr">');

        return $(document.createElement('li'))
            .attr('class', 'ui-autocomplete-item')
            .data('item.autocomplete', item)
            .append($a)
            .appendTo(ul);
    };
}