/* =========================================================
   SPORTSYNC - MAIN JAVASCRIPT
   ========================================================= */

(function () {

    'use strict';


    /* =====================================================
       SIDEBAR
       ===================================================== */

    function initSidebar() {

        const menuButton = document.getElementById('menuButton');
        const sidebar = document.getElementById('sidebar');
        const sidebarOverlay = document.getElementById('sidebarOverlay');


        /*
         * Stop if sidebar elements do not exist.
         */
        if (!menuButton || !sidebar || !sidebarOverlay) {
            return;
        }


        /*
         * Prevent this function from being initialized twice.
         */
        if (menuButton.dataset.sidebarInitialized === 'true') {
            return;
        }

        menuButton.dataset.sidebarInitialized = 'true';


        /*
         * Store the original page scroll position.
         */
        let savedScrollY = 0;


        /* =================================================
           OPEN SIDEBAR
           ================================================= */

        function openSidebar() {

            /*
             * Save current page position.
             */
            savedScrollY =
                window.scrollY ||
                window.pageYOffset ||
                0;


            /*
             * Lock HTML and BODY.
             */
            document.documentElement.classList.add(
                'sidebar-menu-open'
            );

            document.body.classList.add(
                'sidebar-menu-open'
            );


            /*
             * Completely lock background scrolling.
             *
             * The page stays at the same position while
             * the sidebar is open.
             */
            document.body.style.position = 'fixed';

            document.body.style.top =
                `-${savedScrollY}px`;

            document.body.style.left = '0';

            document.body.style.right = '0';

            document.body.style.width = '100%';

            document.body.style.overflow = 'hidden';


            /*
             * Open sidebar.
             */
            sidebar.classList.add('open');


            /*
             * Show overlay.
             */
            sidebarOverlay.classList.add('is-visible');

            sidebarOverlay.setAttribute(
                'aria-hidden',
                'false'
            );


            /*
             * Update menu button.
             */
            menuButton.setAttribute(
                'aria-expanded',
                'true'
            );

            menuButton.setAttribute(
                'aria-label',
                'Close navigation menu'
            );


            /*
             * Prevent background touch scrolling.
             */
            document.addEventListener(
                'touchmove',
                preventBackgroundTouch,
                {
                    passive: false
                }
            );
        }


        /* =================================================
           CLOSE SIDEBAR
           ================================================= */

        function closeSidebar() {

            /*
             * Close sidebar.
             */
            sidebar.classList.remove('open');


            /*
             * Hide overlay.
             */
            sidebarOverlay.classList.remove(
                'is-visible'
            );

            sidebarOverlay.setAttribute(
                'aria-hidden',
                'true'
            );


            /*
             * Remove page lock classes.
             */
            document.documentElement.classList.remove(
                'sidebar-menu-open'
            );

            document.body.classList.remove(
                'sidebar-menu-open'
            );


            /*
             * Remove fixed body positioning.
             */
            document.body.style.position = '';

            document.body.style.top = '';

            document.body.style.left = '';

            document.body.style.right = '';

            document.body.style.width = '';

            document.body.style.overflow = '';


            /*
             * Remove touch-scroll blocker.
             */
            document.removeEventListener(
                'touchmove',
                preventBackgroundTouch
            );


            /*
             * Restore original page position.
             */
            window.scrollTo(
                0,
                savedScrollY
            );


            /*
             * Update menu button.
             */
            menuButton.setAttribute(
                'aria-expanded',
                'false'
            );

            menuButton.setAttribute(
                'aria-label',
                'Open navigation menu'
            );
        }


        /* =================================================
           BACKGROUND TOUCH BLOCKER
           ================================================= */

        function preventBackgroundTouch(event) {

            /*
             * Allow scrolling inside the sidebar.
             */
            if (sidebar.contains(event.target)) {
                return;
            }

            /*
             * Block touch scrolling outside sidebar.
             */
            event.preventDefault();
        }


        /* =================================================
           TOGGLE SIDEBAR
           ================================================= */

        function toggleSidebar(event) {

            if (event) {
                event.preventDefault();
                event.stopPropagation();
            }


            /*
             * If sidebar is already open,
             * close it.
             */
            if (sidebar.classList.contains('open')) {

                closeSidebar();

            }

            /*
             * Otherwise open it.
             */
            else {

                openSidebar();

            }
        }


        /* =================================================
           MENU BUTTON
           ================================================= */

        menuButton.addEventListener(
            'click',
            function (event) {

                toggleSidebar(event);

            },
            false
        );


        /* =================================================
           OVERLAY
           ================================================= */

        sidebarOverlay.addEventListener(
            'click',
            function (event) {

                event.preventDefault();

                event.stopPropagation();

                closeSidebar();

            },
            false
        );


        /* =================================================
           SIDEBAR LINKS
           ================================================= */

        const sidebarLinks =
            sidebar.querySelectorAll('a');


        sidebarLinks.forEach(function (link) {

            link.addEventListener(
                'click',
                function () {

                    /*
                     * Close sidebar only on mobile/tablet.
                     */
                    if (
                        window.matchMedia(
                            '(max-width: 1024px)'
                        ).matches
                    ) {

                        closeSidebar();

                    }

                },
                false
            );

        });


        /* =================================================
           ESC KEY
           ================================================= */

        document.addEventListener(
            'keydown',
            function (event) {

                if (
                    event.key === 'Escape' &&
                    sidebar.classList.contains('open')
                ) {

                    closeSidebar();

                }

            },
            false
        );


        /* =================================================
           WINDOW RESIZE
           ================================================= */

        window.addEventListener(
            'resize',
            function () {

                /*
                 * If screen becomes desktop size,
                 * close the mobile sidebar.
                 */
                if (
                    window.innerWidth > 1024 &&
                    sidebar.classList.contains('open')
                ) {

                    closeSidebar();

                }

            },
            false
        );


        /* =================================================
           INITIAL STATE
           ================================================= */

        /*
         * Make sure the sidebar starts closed.
         */
        sidebar.classList.remove('open');

        sidebarOverlay.classList.remove('is-visible');

        sidebarOverlay.setAttribute(
            'aria-hidden',
            'true'
        );

        menuButton.setAttribute(
            'aria-expanded',
            'false'
        );

        menuButton.setAttribute(
            'aria-label',
            'Open navigation menu'
        );

    }


    /* =====================================================
       INITIALIZE
       ===================================================== */

    if (
        document.readyState === 'loading'
    ) {

        document.addEventListener(
            'DOMContentLoaded',
            initSidebar,
            {
                once: true
            }
        );

    }

    else {

        initSidebar();

    }

})();