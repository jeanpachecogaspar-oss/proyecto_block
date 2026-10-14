/*
 * Quipu Noticias - Mejoras progresivas.
 * Todo el sitio funciona sin JavaScript; este archivo solo agrega comodidad:
 *   1. Interruptor de modo oscuro (función extra N.º 2).
 *   2. Foco en el resumen de errores de un formulario.
 *   3. Confirmación antes de acciones destructivas.
 *   4. Contador de caracteres accesible.
 *   5. Casilla "Mostrar contraseña".
 * No hay código en línea en el HTML: la política CSP lo prohíbe.
 */
(function () {
    'use strict';

    var raiz = document.documentElement;

    /* ---------- 1. Modo oscuro ---------- */
    function temaEfectivo() {
        var explicito = raiz.getAttribute('data-tema');
        if (explicito === 'oscuro' || explicito === 'claro') {
            return explicito;
        }
        return window.matchMedia && window.matchMedia('(prefers-color-scheme: dark)').matches ? 'oscuro' : 'claro';
    }

    function iniciarInterruptor() {
        var boton = document.querySelector('[data-tema-interruptor]');
        if (!boton) { return; }
        boton.hidden = false; // sin JS el botón no se muestra porque no podría funcionar

        function pintar() {
            boton.setAttribute('aria-pressed', temaEfectivo() === 'oscuro' ? 'true' : 'false');
        }
        pintar();

        boton.addEventListener('click', function () {
            var nuevo = temaEfectivo() === 'oscuro' ? 'claro' : 'oscuro';
            raiz.setAttribute('data-tema', nuevo);
            try { localStorage.setItem('quipu-tema', nuevo); } catch (e) { /* ignorar */ }
            pintar();
        });
    }

    /* ---------- 2. Foco en el resumen de errores ---------- */
    function enfocarErrores() {
        var resumen = document.querySelector('[data-resumen-errores]');
        if (resumen) { resumen.focus(); }
    }

    /* ---------- 3. Confirmaciones ---------- */
    function iniciarConfirmaciones() {
        document.addEventListener('submit', function (evento) {
            var formulario = evento.target;
            if (!(formulario instanceof HTMLFormElement)) { return; }
            var mensaje = formulario.getAttribute('data-confirmar');
            if (mensaje && !window.confirm(mensaje)) {
                evento.preventDefault();
            }
        });
    }

    /* ---------- 4. Contador de caracteres ----------
     * El número visible se actualiza siempre; el lector de pantalla solo recibe
     * aviso en umbrales (100, 50, 10 y 0 restantes) para no anunciar cada tecla. */
    function iniciarContadores() {
        var campos = document.querySelectorAll('textarea[data-maximo]');
        Array.prototype.forEach.call(campos, function (campo) {
            var maximo = parseInt(campo.getAttribute('data-maximo'), 10);
            var visible = document.getElementById(campo.id + '-contador');
            var aviso = document.getElementById(campo.id + '-aviso');
            if (!visible || !maximo) { return; }
            var umbrales = [100, 50, 10, 0];
            var ultimo = null;

            function actualizar() {
                var restantes = maximo - campo.value.length;
                visible.textContent = restantes + ' caracteres restantes';
                if (aviso && umbrales.indexOf(restantes) !== -1 && restantes !== ultimo) {
                    aviso.textContent = restantes === 0
                        ? 'Llegaste al límite de caracteres.'
                        : 'Quedan ' + restantes + ' caracteres.';
                    ultimo = restantes;
                }
            }
            campo.addEventListener('input', actualizar);
            actualizar();
        });
    }

    /* ---------- 5. Mostrar contraseña ---------- */
    function iniciarMostrarPassword() {
        var cajas = document.querySelectorAll('[data-mostrar-password-caja]');
        Array.prototype.forEach.call(cajas, function (caja) {
            caja.hidden = false; // sin JS la casilla no se muestra porque no funcionaría
            var casilla = caja.querySelector('[data-mostrar-password]');
            if (!casilla) { return; }
            var ids = casilla.getAttribute('data-mostrar-password').split(' ');
            casilla.addEventListener('change', function () {
                ids.forEach(function (id) {
                    var campo = document.getElementById(id);
                    if (campo) { campo.type = casilla.checked ? 'text' : 'password'; }
                });
            });
        });
    }

    function iniciar() {
        iniciarMostrarPassword();
        iniciarInterruptor();
        enfocarErrores();
        iniciarConfirmaciones();
        iniciarContadores();
    }

    if (document.readyState === 'loading') {
        document.addEventListener('DOMContentLoaded', iniciar);
    } else {
        iniciar();
    }
})();
