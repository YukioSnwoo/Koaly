/* ============================================================
 * perfil.js — Menú de perfil común de admin, gerente y cajero
 * (perfil/perfil.php lo pinta). Abre y cierra el menú, cambia el
 * tema, muestra las notificaciones y guarda "Mi perfil".
 *
 * El idioma se cambia con el formulario del panel (recarga la página).
 * La caja no puede recargar a media venta: cambia el idioma en el
 * navegador y llama a perfilAplicarIdioma() para este menú.
 * ============================================================ */
(function () {
    const raiz = document.querySelector('[data-perfil]');
    if (!raiz) return;

    const datos = JSON.parse(document.getElementById('perfilDatos').textContent);
    const api = raiz.dataset.api;
    const boton = document.getElementById('perfilBoton');
    const menu = document.getElementById('perfilMenu');
    const dialogo = document.getElementById('perfilDialogo');
    const form = dialogo.querySelector('[data-perfil-form]');
    const csrf = form.querySelector('[name="csrf_token"]').value;

    function pt(clave, vars = {}) {
        let texto = datos.textos[datos.idioma]?.[clave] ?? datos.textos.es[clave] ?? clave;
        for (const [k, v] of Object.entries(vars)) texto = texto.split('{' + k + '}').join(String(v));
        return texto;
    }


    /* ---------- Abrir y cerrar el menú ---------- */
    const abierto = () => !menu.hidden;

    function abrirMenu() {
        verVista('principal');
        menu.hidden = false;
        boton.setAttribute('aria-expanded', 'true');
        cargarNotificaciones().catch(() => { /* el contador se queda como estaba */ });
    }

    function cerrarMenu(devolverFoco = false) {
        if (!abierto()) return;
        menu.hidden = true;
        boton.setAttribute('aria-expanded', 'false');
        if (devolverFoco) boton.focus();
    }

    boton.addEventListener('click', () => (abierto() ? cerrarMenu() : abrirMenu()));
    document.addEventListener('click', (e) => {
        if (abierto() && !raiz.contains(e.target)) cerrarMenu();
    });
    document.addEventListener('keydown', (e) => {
        if (e.key === 'Escape' && abierto() && !dialogo.open) cerrarMenu(true);
    });

    // "Cerrar sesión" abre la confirmación del panel: el menú no se queda abierto detrás
    menu.querySelector('.perfil-item--salir').addEventListener('click', () => cerrarMenu());

    function verVista(nombre) {
        menu.querySelectorAll('[data-perfil-vista]').forEach(v => { v.hidden = v.dataset.perfilVista !== nombre; });
    }

    menu.querySelectorAll('[data-perfil-ir]').forEach(b => b.addEventListener('click', () => {
        const destino = b.dataset.perfilIr;
        verVista(destino);
        if (destino === 'notificaciones') {
            mostrarNotificaciones();
            menu.querySelector('[data-perfil-vista="notificaciones"] .perfil-volver').focus();
        } else {
            menu.querySelector('[data-perfil-ir="notificaciones"]').focus();
        }
    }));


    /* ---------- Tema claro / oscuro ----------
       <html data-tema="claro|oscuro"> si el usuario eligió; si no, sigue al
       sistema. La clave es común a los tres paneles. */
    const CLAVE_TEMA = 'koaly-tema';
    const interruptorTema = menu.querySelector('[data-perfil-tema]');
    const sistemaOscuro = window.matchMedia('(prefers-color-scheme: dark)');

    function temaActual() {
        return document.documentElement.getAttribute('data-tema') || (sistemaOscuro.matches ? 'oscuro' : 'claro');
    }

    function pintarTema() {
        interruptorTema.setAttribute('aria-checked', temaActual() === 'oscuro' ? 'true' : 'false');
    }

    interruptorTema.addEventListener('click', () => {
        const nuevo = temaActual() === 'oscuro' ? 'claro' : 'oscuro';
        document.documentElement.setAttribute('data-tema', nuevo);
        try { localStorage.setItem(CLAVE_TEMA, nuevo); } catch (err) { /* modo privado: solo esta página */ }
        pintarTema();
    });
    sistemaOscuro.addEventListener?.('change', pintarTema);
    pintarTema();


    /* ---------- Notificaciones ---------- */
    const lista = menu.querySelector('[data-perfil-notificaciones]');
    let notificaciones = null;   // última respuesta del servidor
    let cargando = null;

    function cargarNotificaciones() {
        cargando ??= fetch(api + 'notificaciones.php', { headers: { Accept: 'application/json' } })
            .then(r => r.json())
            .then(r => {
                if (!r.ok) throw new Error(r.error);
                notificaciones = r;
                pintarContador(r.nuevas);
                return r;
            })
            .finally(() => { cargando = null; });
        return cargando;
    }

    function pintarContador(n) {
        const contador = menu.querySelector('[data-perfil-contador]');
        contador.hidden = n === 0;
        contador.textContent = n > 99 ? '99+' : String(n);
        contador.title = pt('notif.nuevas', { n });
        raiz.querySelector('[data-perfil-punto]').hidden = n === 0;
    }

    function itemNotificacion(n) {
        const li = document.createElement('li');
        li.className = 'perfil-notif perfil-notif--' + n.tono + (n.nueva ? ' is-nueva' : '');
        const cuerpo = document.createElement(n.enlace ? 'a' : 'div');
        cuerpo.className = 'perfil-notif__cuerpo';
        if (n.enlace) cuerpo.href = n.enlace;

        const texto = document.createElement('span');
        texto.className = 'perfil-notif__texto';
        texto.textContent = n.texto;
        cuerpo.append(texto);
        if (n.detalle) {
            const detalle = document.createElement('span');
            detalle.className = 'perfil-notif__detalle';
            detalle.textContent = n.detalle;
            cuerpo.append(detalle);
        }
        const fecha = document.createElement('time');
        fecha.className = 'perfil-notif__fecha';
        fecha.textContent = n.fecha;
        cuerpo.append(fecha);

        li.append(cuerpo);
        return li;
    }

    function mensajeLista(clave) {
        const li = document.createElement('li');
        li.className = 'perfil-notif-vacio';
        li.textContent = pt(clave);
        lista.replaceChildren(li);
    }

    async function mostrarNotificaciones() {
        if (!notificaciones) mensajeLista('notif.cargando');
        else lista.replaceChildren(...notificaciones.items.map(itemNotificacion));

        let r;
        try {
            r = await cargarNotificaciones();
        } catch (err) {
            if (!notificaciones) mensajeLista('notif.error');
            return;
        }
        if (r.items.length === 0) mensajeLista('notif.vacio');
        else lista.replaceChildren(...r.items.map(itemNotificacion));

        // Ya se vieron: el contador se apaga (las de esta lista siguen marcadas hasta cerrar)
        if (r.nuevas > 0) {
            const cuerpo = new FormData();
            cuerpo.set('csrf_token', csrf);
            fetch(api + 'notificaciones.php', { method: 'POST', body: cuerpo, headers: { Accept: 'application/json' } })
                .then(res => res.json())
                .then(res => {
                    if (!res.ok) return;
                    r.items.forEach(n => { n.nueva = false; });
                    r.nuevas = 0;
                    pintarContador(0);
                })
                .catch(() => { /* se marcarán la próxima vez */ });
        }
    }

    cargarNotificaciones().catch(() => { /* sin contador; se reintenta al abrir el menú */ });


    /* ---------- Mi perfil ---------- */
    const mensaje = form.querySelector('[data-perfil-mensaje]');
    const inputFoto = form.querySelector('[name="foto"]');
    const quitarFoto = form.querySelector('[name="quitar_foto"]');
    const botonQuitar = form.querySelector('[data-perfil-quitar-foto]');
    const botonGuardar = form.querySelector('[data-perfil-guardar]');
    const avatarForm = form.querySelector('[data-perfil-avatar]');
    let fotoActual = avatarForm.querySelector('img').hidden ? null : avatarForm.querySelector('img').getAttribute('src');
    let urlVistaPrevia = null;

    function pintarAvatar(avatar, foto, iniciales) {
        const img = avatar.querySelector('img');
        const letras = avatar.querySelector('.perfil-avatar__iniciales');
        img.hidden = !foto;
        if (foto) img.src = foto; else img.removeAttribute('src');
        letras.hidden = !!foto;
        if (iniciales) letras.textContent = iniciales;
    }

    function avisar(texto, tipo) {
        mensaje.textContent = texto;
        mensaje.className = 'perfil-mensaje perfil-mensaje--' + tipo;
        mensaje.hidden = !texto;
    }

    // Ojo junto a cada contraseña: la muestra u oculta (el input va justo antes del botón)
    function verContrasena(boton, visible) {
        boton.previousElementSibling.type = visible ? 'text' : 'password';
        boton.setAttribute('aria-pressed', visible ? 'true' : 'false');
    }
    form.querySelectorAll('.perfil-ver').forEach(b => {
        b.addEventListener('click', () => verContrasena(b, b.getAttribute('aria-pressed') !== 'true'));
    });

    function abrirPerfil() {
        cerrarMenu();
        form.reset();
        form.querySelectorAll('.perfil-ver').forEach(b => verContrasena(b, false)); // reset() no regresa el tipo
        quitarFoto.value = '0';
        pintarAvatar(avatarForm, fotoActual);
        botonQuitar.hidden = !fotoActual;
        avisar('', 'error');
        botonGuardar.disabled = false;
        dialogo.showModal();
        form.querySelector('[name="nombre"]').focus();
    }

    menu.querySelector('[data-perfil-editar]').addEventListener('click', abrirPerfil);
    form.querySelector('[data-perfil-cerrar]').addEventListener('click', () => dialogo.close());
    // Clic en el fondo oscuro (fuera de la caja) cierra
    dialogo.addEventListener('click', (e) => { if (e.target === dialogo) dialogo.close(); });
    dialogo.addEventListener('close', () => {
        if (urlVistaPrevia) URL.revokeObjectURL(urlVistaPrevia);
        urlVistaPrevia = null;
    });

    inputFoto.addEventListener('change', () => {
        const archivo = inputFoto.files[0];
        if (!archivo) return;
        if (urlVistaPrevia) URL.revokeObjectURL(urlVistaPrevia);
        urlVistaPrevia = URL.createObjectURL(archivo);
        quitarFoto.value = '0';
        pintarAvatar(avatarForm, urlVistaPrevia);
        botonQuitar.hidden = false;
    });

    botonQuitar.addEventListener('click', () => {
        inputFoto.value = '';
        quitarFoto.value = '1';
        pintarAvatar(avatarForm, null);
        botonQuitar.hidden = true;
    });

    form.addEventListener('submit', async (e) => {
        e.preventDefault();
        botonGuardar.disabled = true;
        botonGuardar.textContent = pt('form.guardando');
        let r;
        try {
            const res = await fetch(api + 'guardar.php', { method: 'POST', body: new FormData(form), headers: { Accept: 'application/json' } });
            r = await res.json();
        } catch (err) {
            r = { ok: false, mensaje: pt('error.conexion') };
        }
        botonGuardar.disabled = false;
        botonGuardar.textContent = pt('form.guardar');

        if (!r.ok) {
            avisar(r.mensaje, 'error');
            return;
        }

        const u = r.usuario;
        fotoActual = u.foto;
        raiz.querySelectorAll('[data-perfil-avatar]').forEach(a => pintarAvatar(a, u.foto, u.iniciales));
        raiz.querySelectorAll('[data-perfil-nombre]').forEach(el => { el.textContent = u.nombre; });
        raiz.querySelectorAll('[data-perfil-email]').forEach(el => { el.textContent = u.email; });
        // Así form.reset() vuelve a lo guardado la próxima vez
        form.querySelector('[name="nombre"]').defaultValue = u.nombre;
        form.querySelector('[name="email"]').defaultValue = u.email;
        form.querySelectorAll('.perfil-contrasena input').forEach(i => { i.value = ''; }); // también las que se ven
        // auth.js guarda el usuario del inicio de sesión en sessionStorage
        try {
            const guardado = JSON.parse(sessionStorage.getItem('usuario'));
            if (guardado) sessionStorage.setItem('usuario', JSON.stringify({ ...guardado, nombre: u.nombre, email: u.email }));
        } catch (err) { /* sin sessionStorage */ }

        avisar(r.mensaje, 'ok');
        setTimeout(() => { if (dialogo.open) dialogo.close(); }, 900);
    });


    /* ---------- Idioma sin recargar (lo usa la caja) ---------- */
    window.perfilAplicarIdioma = function (codigo) {
        if (!datos.textos[codigo]) return;
        datos.idioma = codigo;
        raiz.querySelectorAll('[data-perfil-t]').forEach(el => { el.textContent = pt(el.dataset.perfilT); });
        raiz.querySelectorAll('[data-perfil-t-attr]').forEach(el => {
            const texto = pt(el.dataset.perfilTAttr);
            el.setAttribute('aria-label', texto);
            el.title = texto;
        });
        raiz.querySelectorAll('[data-perfil-idioma] button[name="idioma"]').forEach(b => {
            b.setAttribute('aria-pressed', b.value === codigo ? 'true' : 'false');
        });
        // Las notificaciones se piden de nuevo en el idioma nuevo
        notificaciones = null;
    };
})();
