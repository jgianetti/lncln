var App = (function (my) {
    my.modules.error = new function()
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
                (App.APP_ENV == App.APP_ENV_DEV) && console.log('error.search.init()');

                App.cacheAppWindows();

                var theaders = ['date', 'details'];

                var $table = App.buildDataTable(theaders, Globalize.localize('error'));

                $('#main').html(
                    $('#cache > div[name="app-window"]').clone().prop('id', 'app-window-error-search')
                        .find('h1').html(Globalize.localize('error')['win_title_list']).end()
                        .find('div.app-window-content').append($table).end()
                );

                $table.dataTable({
                    "iDeferLoading": [ data.iTotalDisplayRecords, data.iTotalRecords ],
                    "aaData" : data.aaData,
                    "aoColumnDefs": [
                        { "aTargets": [ 0 ], "mData": "date" },
                        { "aTargets": [ 1 ], "mData": "details" }
                    ],
                    "aaSorting": [[0,'asc']] // default sorting
                });

                App.ajaxDataProcessComplete();

            };

        };
    };

    return my;

}(App || {}));