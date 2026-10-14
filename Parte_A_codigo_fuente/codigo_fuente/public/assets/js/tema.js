/*
 * Quipu Noticias - Aplica el tema guardado ANTES de pintar la página.
 * Se carga sin "defer" y pesa muy poco: evita el parpadeo de tema claro → oscuro.
 * Si localStorage no está disponible (modo privado, bloqueado) el sitio sigue
 * funcionando con la preferencia del sistema (ver estilo.css).
 */
(function () {
    try {
        var guardado = localStorage.getItem('quipu-tema');
        if (guardado === 'oscuro' || guardado === 'claro') {
            document.documentElement.setAttribute('data-tema', guardado);
        }
    } catch (e) {
        /* sin almacenamiento: se usa la preferencia del sistema */
    }
})();
