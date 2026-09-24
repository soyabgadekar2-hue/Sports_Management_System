/* =========================================================
   SPORTS MANAGEMENT SYSTEM
   MAIN JAVASCRIPT
   ========================================================= */

document.addEventListener('DOMContentLoaded', function () {

    const menuButton = document.getElementById('menuButton');
    const sidebar = document.getElementById('sidebar');
    const sidebarOverlay = document.getElementById('sidebarOverlay');


    /* =====================================================
       OPEN / CLOSE MOBILE SIDEBAR
       ===================================================== */

    function openSidebar() {

        if (!sidebar || !sidebarOverlay) {
            return;
        }

        sidebar.classList.add('open');
        sidebarOverlay.classList.add('open');

        document.body.style.overflow = 'hidden';
    }


    function closeSidebar() {

        if (!sidebar || !sidebarOverlay) {
            return;
        }

        sidebar.classList.remove('open');
        sidebarOverlay.classList.remove('open');

        document.body.style.overflow = '';
    }


    function toggleSidebar() {

        if (!sidebar) {
            return;
        }

        if (sidebar.classList.contains('open')) {
            closeSidebar();
        } else {
            openSidebar();
        }
    }


    /* =====================================================
       MENU BUTTON
       ===================================================== */

    if (menuButton) {

        menuButton.addEventListener('click', function () {

            toggleSidebar();

        });

    }


    /* =====================================================
       CLICK OUTSIDE SIDEBAR
       ===================================================== */

    if (sidebarOverlay) {

        sidebarOverlay.addEventListener('click', function () {

            closeSidebar();

        });

    }


    /* =====================================================
       CLOSE AFTER CLICKING A SIDEBAR LINK
       ===================================================== */

    if (sidebar) {

        const sidebarLinks =
            sidebar.querySelectorAll('a');

        sidebarLinks.forEach(function (link) {

            link.addEventListener('click', function () {

                closeSidebar();

            });

        });

    }


    /* =====================================================
       ESC KEY
       ===================================================== */

    document.addEventListener('keydown', function (event) {

        if (event.key === 'Escape') {

            closeSidebar();

        }

    });


    /* =====================================================
       CLOSE SIDEBAR WHEN SCREEN BECOMES DESKTOP
       ===================================================== */

    window.addEventListener('resize', function () {

        if (window.innerWidth > 900) {

            closeSidebar();

        }

    });

});