(function ($) {
    "use strict";

    // For APP-SIDEBAR
    if ($(".app-sidebar").length > 0) {
        const ps = new PerfectScrollbar(".app-sidebar", {
            useBothWheelAxes: true,
            suppressScrollX: true,
        });
    }

    // For Header Message dropdown
    if ($(".message-menu").length > 0) {
        const ps2 = new PerfectScrollbar(".message-menu", {
            useBothWheelAxes: true,
            suppressScrollX: true,
        });
    }

    // For Header Notification dropdown
    if ($(".notifications-menu").length > 0) {
        const ps3 = new PerfectScrollbar(".notifications-menu", {
            useBothWheelAxes: true,
            suppressScrollX: true,
        });
    }

    // For Header Cart dropdown
    if ($(".cart-menu").length > 0) {
        const ps4 = new PerfectScrollbar(".cart-menu", {
            useBothWheelAxes: true,
            suppressScrollX: true,
        });
    }
})(jQuery);
