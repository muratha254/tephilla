/**
 * Horizontal scroll helpers for wide DataTables / .table-responsive:
 * - CSS min-width:0 on columns (see table-responsive-hscroll.css) lets the container
 *   actually overflow instead of stretching the whole layout.
 * - Top mirror bar synced with the scroll container.
 * - Fixed bar at bottom of viewport so users always see a horizontal scrollbar
 *   without scrolling past long tables (e.g. /penjualan).
 */
(function ($) {
    'use strict';

    var VIEWPORT_BAR_ID = 'dt-viewport-hscroll';
    var $viewportBar;
    var $viewportInner;
    var activeScrollEl = null;
    var syncing = false;

    function debounce(fn, ms) {
        var t;
        return function () {
            var ctx = this;
            var args = arguments;
            clearTimeout(t);
            t = setTimeout(function () {
                fn.apply(ctx, args);
            }, ms);
        };
    }

    /** Prefer measuring the table vs container — more reliable than scrollWidth on wrapper */
    function containerNeedsHScroll(wrapEl) {
        var $wrap = $(wrapEl);
        var tbl = $wrap.find('table').first()[0];
        if (tbl && tbl.scrollWidth > wrapEl.clientWidth + 2) {
            return true;
        }
        return wrapEl.scrollWidth > wrapEl.clientWidth + 2;
    }

    function innerScrollWidth(wrapEl) {
        var $wrap = $(wrapEl);
        var tbl = $wrap.find('table').first()[0];
        var w = wrapEl.scrollWidth;
        if (tbl) {
            w = Math.max(w, tbl.scrollWidth, tbl.offsetWidth);
        }
        return w;
    }

    function ensureViewportBar() {
        if ($viewportBar && $viewportBar.length) {
            return;
        }
        $viewportBar = $(
            '<div id="' +
                VIEWPORT_BAR_ID +
                '" class="dt-vhscroll--with-sidebar" role="scrollbar" aria-hidden="true">' +
                '<div id="dt-viewport-hscroll-inner"></div>' +
                '</div>'
        );
        $viewportInner = $viewportBar.find('#dt-viewport-hscroll-inner');
        $('body').append($viewportBar);

        $viewportBar.on('scroll', function () {
            if (syncing || !activeScrollEl) {
                return;
            }
            syncing = true;
            activeScrollEl.scrollLeft = $viewportBar.scrollLeft();
            syncing = false;
        });
    }

    function pickPrimaryScrollContainer() {
        var $best = $();
        var bestW = 0;
        $('.table-responsive').each(function () {
            var $w = $(this);
            if ($w.parents('.table-responsive').length) {
                return;
            }
            if (!$w.is(':visible')) {
                return;
            }
            if (!containerNeedsHScroll(this)) {
                return;
            }
            var extra = innerScrollWidth(this) - this.clientWidth;
            if (extra > bestW) {
                bestW = extra;
                $best = $w;
            }
        });
        return $best;
    }

    function updateViewportBar() {
        ensureViewportBar();
        var $target = pickPrimaryScrollContainer();
        if (!$target.length) {
            $viewportBar.hide();
            activeScrollEl = null;
            return;
        }
        var el = $target[0];
        activeScrollEl = el;
        var w = innerScrollWidth(el);
        $viewportInner.width(w);
        $viewportBar.scrollLeft($target.scrollLeft());
        $viewportBar.show().attr('aria-hidden', 'false');
    }

    function refreshPair($wrap) {
        var el = $wrap[0];
        var $top = $wrap.data('hscrollTop');
        var $inner = $wrap.data('hscrollInner');
        if (!$top || !$inner || !el) {
            return;
        }

        var w = Math.max(innerScrollWidth(el), el.clientWidth);
        $inner.width(w);

        if (containerNeedsHScroll(el)) {
            $wrap.addClass('has-hscroll-pair');
            $top.show().attr('aria-hidden', 'false');
            $top.scrollLeft($wrap.scrollLeft());
        } else {
            $wrap.removeClass('has-hscroll-pair');
            $top.hide().attr('aria-hidden', 'true');
        }
    }

    function bindPair($wrap) {
        if ($wrap.data('hscrollBound')) {
            refreshPair($wrap);
            return;
        }

        var $top = $(
            '<div class="table-hscroll-top" role="scrollbar" aria-hidden="true">' +
                '<div class="table-hscroll-top-inner"></div>' +
            '</div>'
        );
        $wrap.before($top);
        var $inner = $top.find('.table-hscroll-top-inner');

        var el = $wrap[0];
        var localSync = false;
        function syncFromTop() {
            if (localSync) {
                return;
            }
            localSync = true;
            $wrap.scrollLeft($top.scrollLeft());
            localSync = false;
            if (activeScrollEl === el) {
                syncViewportFromActive();
            }
        }
        function syncFromWrap() {
            if (localSync) {
                return;
            }
            localSync = true;
            $top.scrollLeft($wrap.scrollLeft());
            localSync = false;
            if (activeScrollEl === el) {
                syncViewportFromActive();
            }
        }

        $top.on('scroll', syncFromTop);
        $wrap.on('scroll', syncFromWrap);

        $wrap.data('hscrollBound', true);
        $wrap.data('hscrollTop', $top);
        $wrap.data('hscrollInner', $inner);

        refreshPair($wrap);
    }

    function syncViewportFromActive() {
        if (!activeScrollEl || !$viewportBar || !$viewportBar.length) {
            return;
        }
        if (syncing) {
            return;
        }
        syncing = true;
        $viewportBar.scrollLeft(activeScrollEl.scrollLeft);
        syncing = false;
    }

    function isVisible($el) {
        if (!$el.length) {
            return false;
        }
        return $el.is(':visible') && $el.css('visibility') !== 'hidden' && $el.css('opacity') !== '0';
    }

    function initAll() {
        $('.table-responsive').each(function () {
            var $w = $(this);
            if ($w.parents('.table-responsive').length) {
                return;
            }
            if (!isVisible($w)) {
                return;
            }
            bindPair($w);
        });

        updateViewportBar();

        if (activeScrollEl && $viewportBar && $viewportBar.length) {
            syncing = true;
            $viewportBar.scrollLeft(activeScrollEl.scrollLeft);
            syncing = false;
        }
    }

    var debouncedInit = debounce(initAll, 120);

    $(function () {
        initAll();
        setTimeout(initAll, 200);
        setTimeout(initAll, 600);

        $(window).on('resize orientationchange', debouncedInit);
        $(document).on('draw.dt', function () {
            setTimeout(initAll, 0);
        });
        $(document).on('init.dt', function () {
            setTimeout(initAll, 50);
        });
        $(document).on('collapsed.pushMenu expanded.pushMenu', function () {
            setTimeout(initAll, 350);
        });
        $(document).ajaxComplete(function () {
            setTimeout(initAll, 50);
        });
        $(document).on('shown.bs.modal shown.bs.collapse', function () {
            setTimeout(initAll, 0);
        });
    });

    window.syncTableResponsiveScrollbars = initAll;
})(jQuery);
