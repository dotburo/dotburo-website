define([
    'app',
    'templates',
    'gistHandle'
], function (app, templates, gistHandle) {
    'use strict';

    var w = window,
        d = w.document,

        Page = function (options, state) {
            var el = options.el || this.getActiveItem(),
                id = el.id || 'h' + new Date().getTime(), // we need an id to be able to pass the element through history
                slug = el.getAttribute('href');

            options.id = el.id = id;
            options.slug = slug;
            options.column = options.column || app.views.grid.getContentColumn(el);

            this.options = options;
            this.el = el;

            options.column.scrollTop = 0;

            // this is already done if it was a single page load request
            if (!(app.initial.isSingle && app.initial.pageLoad)) {
                this.fetch(options, state);
                app.toggleCloseBtn(1);
            }

            // on page load and through history,
            // no need to set state.
            if (!state && !app.initial.pageLoad) {
                app.setHistory({
                    id: id,
                    title: this.getTitle(options.el),
                    url: slug
                });
            }
        };

    Page.prototype = {

        /**
         * If one page load one of the content columns is open,
         * it will have a data-slug, which can be used to find it's corresponding item.
         * @returns {Element}
         */
        getActiveItem: function () {
            var slug = d.querySelector('.open main').getAttribute('data-slug'),
                item = d.querySelector('a.item[href="' + slug + '"]');

            if (item) {
                item.classList.add('active');
            }

            return item;
        },

        getTitle: function (el) {
            return el.getAttribute('data-title') || el.getElementsByTagName('h2')[0].textContent
        },

        fetch: function (options, state) {
            var self = this;

            this.options.column.innerHTML = templates.main(
                this.el.getAttribute('data-title'),
                options.slug,
                this.el.getAttribute('data-img'),
                this.el.querySelector('time') ? this.el.querySelector('time').textContent : null
            );

            if (state) {
                return this.render(app.data[options.id]);
            }

            w.fetch(options.slug.replace(/\/$/, '') + '?get')
                .then(function (r) {
                    return r.json()
                })
                .then(function (r) {
                    app.data[options.id] = r;
                    self.render(r);
                })
                .catch(function (error) {
                    self.render({
                        content: '<p class="err-xhr"> ¡NETWORK ERROR [' + error.status + '] &mdash; ' + error.toString().toUpperCase() + '! </p>'
                    });
                })
        },

        render: function (data) {
            var parent = this.options.column.getElementsByClassName('xhr-root')[0];
            parent.innerHTML = templates.content(data);
            this.evalGists(parent);
        },

        evalGists: function (parent) {
            var tags = parent.getElementsByClassName('gist');
            app.util.elementArray(tags).forEach(function(tag) {
                gistHandle(tag.src);
            })
        },

        destroy: function () {
            this.options.column.innerHTML = '';
        },
    };
    return Page;
});