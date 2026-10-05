(function () {
    'use strict';

    var STORAGE_KEY = 'smp_theme';
    var root = document.documentElement;

    function applyTheme( theme ) {
        document.querySelectorAll( '.smp-wrap' ).forEach( function ( el ) {
            el.setAttribute( 'data-theme', theme );
        } );
    }

    function getSavedTheme() {
        try {
            return localStorage.getItem( STORAGE_KEY ) || 'dark';
        } catch ( e ) {
            return 'dark';
        }
    }

    function saveTheme( theme ) {
        try {
            localStorage.setItem( STORAGE_KEY, theme );
        } catch ( e ) {}
    }

    document.addEventListener( 'DOMContentLoaded', function () {
        var theme = getSavedTheme();
        applyTheme( theme );

        var toggle = document.getElementById( 'smp-theme-toggle' );
        if ( toggle ) {
            toggle.addEventListener( 'click', function () {
                var current = document.querySelector( '.smp-wrap' ).getAttribute( 'data-theme' ) || 'dark';
                var next = current === 'dark' ? 'light' : 'dark';
                applyTheme( next );
                saveTheme( next );
            } );
        }

        // افکت ریپل ساده روی دکمه ارسال
        document.querySelectorAll( '.smp-btn-primary' ).forEach( function ( btn ) {
            btn.addEventListener( 'click', function ( e ) {
                var ripple = document.createElement( 'span' );
                ripple.className = 'smp-ripple';
                var rect = btn.getBoundingClientRect();
                ripple.style.left = ( e.clientX - rect.left ) + 'px';
                ripple.style.top = ( e.clientY - rect.top ) + 'px';
                btn.appendChild( ripple );
                setTimeout( function () { ripple.remove(); }, 600 );
            } );
        } );

        // پیش‌نمایش زنده‌ی فایل آپلودی روی دکمه آپلود
        document.querySelectorAll( '.smp-file-upload input[type=file]' ).forEach( function ( input ) {
            input.addEventListener( 'change', function () {
                var textEl = input.parentElement.querySelector( '.upload-text' );
                if ( textEl && input.files && input.files[0] ) {
                    textEl.textContent = '✓ ' + input.files[0].name;
                    input.parentElement.classList.add( 'has-file' );
                }
            } );
        } );
    } );
})();
