<script src="{{ asset('backend/custom_downloaded_file/axios.min.js') }}"></script>

{{-- JQUERY JS --}}
<script src="{{ asset('backend/plugins/jquery/jquery.min.js') }}"></script>

{{-- BOOTSTRAP JS --}}
<script src="{{ asset('backend/plugins/bootstrap/js/popper.min.js') }}"></script>
<script src="{{ asset('backend/plugins/bootstrap/js/bootstrap.min.js') }}"></script>
<script src="{{ asset('backend/plugins/bootstrap-datepicker/bootstrap-datepicker.js') }}"></script>

{{-- SIDE-MENU JS --}}
<script src="{{ asset('backend/plugins/sidemenu/sidemenu.js') }}"></script>

{{-- Perfect SCROLLBAR JS --}}
<script src="{{ asset('backend/plugins/p-scroll/perfect-scrollbar.js') }}"></script>
<script src="{{ asset('backend/plugins/p-scroll/pscroll.js') }}"></script>

{{-- STICKY JS --}}
<script src="{{ asset('backend/js/sticky.js') }}"></script>

{{-- INTERNAL Summernote Editor js --}}
<script src="{{ asset('backend/plugins/summernote-editor/summernote1.js') }}"></script>
<script src="{{ asset('backend/js/summernote.js') }}"></script>

{{-- dropify js --}}
<script src="{{ asset('backend/js/dropify.min.js') }}"></script>

{{-- toaster js --}}
<script src="{{ asset('backend/js/toastr.min.js') }}"></script>

{{-- DATA TABLE JS --}}
<script src="{{ asset('backend/plugins/datatable/js/jquery.dataTables.min.js') }}"></script>
<script src="{{ asset('backend/plugins/datatable/js/dataTables.bootstrap5.js') }}"></script>
<script src="{{ asset('backend/plugins/datatable/js/dataTables.buttons.min.js') }}"></script>
{{-- <script src="{{ asset('backend/plugins/datatable/js/butsns.bootstrap5.min.js') }}"></script> --}}
<script src="{{ asset('backend/plugins/datatable/js/jszip.min.js') }}"></script>
<script src="{{ asset('backend/plugins/datatable/pdfmake/pdfmake.min.js') }}"></script>
<script src="{{ asset('backend/plugins/datatable/pdfmake/vfs_fonts.js') }}"></script>
{{-- <script src="{{ asset('backend/plugins/datatable/js/butsns.html5.min.js') }}"></script> --}}
<script src="{{ asset('backend/plugins/datatable/js/buttons.print.min.js') }}"></script>
<script src="{{ asset('backend/plugins/datatable/js/buttons.colVis.min.js') }}"></script>
<script src="{{ asset('backend/plugins/datatable/dataTables.responsive.min.js') }}"></script>
<script src="{{ asset('backend/plugins/datatable/responsive.bootstrap5.min.js') }}"></script>
<script src="{{ asset('backend/js/table-data.js') }}"></script>

{{-- APEXCHART JS --}}
<script src="{{ asset('backend/js/apexcharts.js') }}"></script>

{{-- INTERNAL SELECT2 JS --}}
<script src="{{ asset('backend/plugins/select2/select2.full.min.js') }}"></script>

{{-- BOOTSTRAP MAXLENGTH JS --}}
<script src="{{ asset('backend/plugins/bootstrap-maxlength/dist/bootstrap-maxlength.min.js') }}"></script>

{{-- FORM ELEMENTS JS --}}
<script src="{{ asset('backend/js/formelementadvnced.js') }}"></script>

{{-- CHART-CIRCLE JS --}}
<script src="{{ asset('backend/plugins/circle-progress/circle-progress.min.js') }}"></script>

{{-- INDEX JS --}}
<script src="{{ asset('backend/js/index1.js') }}"></script>
<script src="{{ asset('backend/js/index.js') }}"></script>

{{-- Reply JS --}}
<script src="{{ asset('backend/js/reply.js') }}"></script>

{{-- COLOR THEME JS --}}
<script src="{{ asset('backend/js/themeColors.js') }}"></script>

{{-- CUSTOM JS --}}
<script src="{{ asset('backend/js/custom.js') }}"></script>

{{-- SWITCHER JS --}}
<script src="{{ asset('backend/switcher/js/switcher.js') }}"></script>

{{-- SweetAlert2 JS --}}
<script src="{{ asset('backend/js/sweetalert2@11.js') }}"></script>

{{-- toastr start --}}
<script>
    $(document).ready(function() {
        toastr.options.timeOut = 10000;
        toastr.options.positionClass = 'toast-top-right';

        @if(Session::has('t-success'))
        toastr.options = {
            'closeButton': true,
            'debug': false,
            'newestOnTop': true,
            'progressBar': true,
            'positionClass': 'toast-top-right',
            'preventDuplicates': false,
            'showDuration': '1000',
            'hideDuration': '1000',
            'timeOut': '5000',
            'extendedTimeOut': '1000',
            'showEasing': 'swing',
            'hideEasing': 'linear',
            'showMethod': 'fadeIn',
            'hideMethod': 'fadeOut',
        };
        toastr.success("{{ session('t-success') }}");
        @endif

        @if(Session::has('t-error'))
        toastr.options = {
            'closeButton': true,
            'debug': false,
            'newestOnTop': true,
            'progressBar': true,
            'positionClass': 'toast-top-right',
            'preventDuplicates': false,
            'showDuration': '1000',
            'hideDuration': '1000',
            'timeOut': '5000',
            'extendedTimeOut': '1000',
            'showEasing': 'swing',
            'hideEasing': 'linear',
            'showMethod': 'fadeIn',
            'hideMethod': 'fadeOut',
        };
        toastr.error("{{ session('t-error') }}");
        @endif

        @if(Session::has('t-info'))
        toastr.options = {
            'closeButton': true,
            'debug': false,
            'newestOnTop': true,
            'progressBar': true,
            'positionClass': 'toast-top-right',
            'preventDuplicates': false,
            'showDuration': '1000',
            'hideDuration': '1000',
            'timeOut': '5000',
            'extendedTimeOut': '1000',
            'showEasing': 'swing',
            'hideEasing': 'linear',
            'showMethod': 'fadeIn',
            'hideMethod': 'fadeOut',
        };
        toastr.info("{{ session('t-info') }}");
        @endif

        @if(Session::has('t-warning'))
        toastr.options = {
            'closeButton': true,
            'debug': false,
            'newestOnTop': true,
            'progressBar': true,
            'positionClass': 'toast-top-right',
            'preventDuplicates': false,
            'showDuration': '1000',
            'hideDuration': '1000',
            'timeOut': '5000',
            'extendedTimeOut': '1000',
            'showEasing': 'swing',
            'hideEasing': 'linear',
            'showMethod': 'fadeIn',
            'hideMethod': 'fadeOut',
        };
        toastr.warning("{{ session('t-warning') }}");
        @endif
    });
</script>
{{-- toastr end --}}

{{-- dropify start --}}
<script>
    $(document).ready(function() {
        $('.dropify').dropify();

        $('#logo').on('dropify.afterClear', function(event, element) {
            $('input[name="remove_logo"]').val('1');
        });

        $('#favicon').on('dropify.afterClear', function(event, element) {
            $('input[name="remove_favicon"]').val('1');
        });
    });
</script>
{{-- dropify end --}}

{{-- summernot start --}}
<script>
    $(document).ready(function() {
        $('#summernote').summernote({
            tabsize: 2,
            height: 220,
        });
    });
</script>

{{-- Admin header dropdowns --}}
<script>
    document.addEventListener('DOMContentLoaded', function() {
        const notificationToggle = document.getElementById('adminNotificationDropdown');
        if (!notificationToggle) {
            return;
        }

        const dropdown = notificationToggle.closest('.dropdown');
        const menu = dropdown ? dropdown.querySelector('.admin-notification-menu') : null;
        if (!dropdown || !menu) {
            return;
        }

        const closeNotificationMenu = function() {
            menu.classList.remove('show');
            notificationToggle.setAttribute('aria-expanded', 'false');
        };

        const positionNotificationMenu = function() {
            const toggleRect = notificationToggle.getBoundingClientRect();
            const viewportPadding = 24;
            const menuWidth = Math.min(340, window.innerWidth - (viewportPadding * 2));
            const right = Math.max(viewportPadding, window.innerWidth - toggleRect.right);

            menu.style.position = 'fixed';
            menu.style.inset = 'auto auto auto auto';
            menu.style.top = `${toggleRect.bottom + 8}px`;
            menu.style.left = 'auto';
            menu.style.right = `${right}px`;
            menu.style.width = `${menuWidth}px`;
            menu.style.maxWidth = `calc(100vw - ${viewportPadding * 2}px)`;
            menu.style.maxHeight = `calc(100vh - ${toggleRect.bottom + 24}px)`;
            menu.style.transform = 'none';
            menu.style.zIndex = '99999';
        };

        notificationToggle.addEventListener('click', function(event) {
            event.preventDefault();
            event.stopPropagation();

            const isOpen = menu.classList.contains('show');
            document.querySelectorAll('.dropdown-menu.show').forEach(function(openMenu) {
                if (openMenu !== menu) {
                    openMenu.classList.remove('show');
                }
            });
            document.querySelectorAll('[aria-expanded="true"]').forEach(function(openToggle) {
                openToggle.setAttribute('aria-expanded', 'false');
            });

            if (isOpen) {
                closeNotificationMenu();
                return;
            }

            positionNotificationMenu();
            menu.classList.add('show');
            notificationToggle.setAttribute('aria-expanded', 'true');
        });

        menu.addEventListener('click', function(event) {
            event.stopPropagation();
        });

        document.addEventListener('click', function(event) {
            if (!dropdown.contains(event.target)) {
                closeNotificationMenu();
            }
        });

        window.addEventListener('resize', function() {
            if (menu.classList.contains('show')) {
                positionNotificationMenu();
            }
        });

        window.addEventListener('scroll', function() {
            if (menu.classList.contains('show')) {
                positionNotificationMenu();
            }
        }, true);
    });
</script>

{{-- Admin System Notifications start --}}
@php
    $isAdmin = auth()->check() && auth()->user()->role === 'Admin';
@endphp
<script>
    $(document).ready(function() {
        @if($isAdmin)
        axios.get('{{ route("admin.notifications.index") }}')
            .then(function(response) {
                let notifications = response.data.data;
                if (notifications && notifications.length > 0) {
                    toastr.options = {
                        'closeButton': true,
                        'progressBar': true,
                        'positionClass': 'toast-top-right',
                        'timeOut': '7000',
                        'preventDuplicates': true,
                    };
                    notifications.forEach(function(notification) {
                        let type = notification.data.type || 'info';
                        let title = notification.data.title || 'System Notification';
                        let message = notification.data.message || '';

                        if (typeof toastr[type] === 'function') {
                            toastr[type](message, title);
                        } else {
                            toastr.info(message, title);
                        }

                        // Mark as read immediately
                        axios.post('{{ url("admin/notifications/mark-as-read") }}/' + notification.id);
                    });
                }
            })
            .catch(function(error) {
                console.error('Error fetching admin notifications:', error);
            });
        @endif
    });
</script>
{{-- Admin System Notifications end --}}

@stack('scripts')
