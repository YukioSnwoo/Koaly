/* Pantalla del cliente: muestra lo que le manda la caja (cajero/index.php).
   Las dos son ventanas del mismo navegador y se hablan por BroadcastChannel.

   Esta ventana le pregunta a la caja cada pocos segundos ("hola") y la caja
   responde con su estado. Si deja de responder, o avisa que se va ("adios"),
   se muestra "Caja cerrada". Pregunta esta ventana y no la caja porque el
   navegador frena los temporizadores de una ventana oculta o minimizada, pero
   sí le entrega sus mensajes: así una caja minimizada no parece cerrada. */
(() => {
    'use strict';

    const CARAS_ESPERA = ['normal', 'feliz', 'pensando', 'secreto'];
    const MS_ENTRE_CARAS = 8000;
    const MS_ENTRE_SALUDOS = 2500;
    const SALUDOS_SIN_RESPUESTA = 3;    // cuántos se toleran antes de dar la caja por cerrada
    const MS_GRACIAS_MINIMO = 9000;     // aunque el cajero acepte la venta de inmediato: da tiempo de ver el ticket
    const TICKET_PX_POR_MS = 0.32;      // velocidad a la que sale el papel
    const TICKET_MS_MINIMO = 1800;
    const TICKET_MS_MAXIMO = 6000;      // un ticket muy largo sale más rápido
    const TICKET_MARGEN_ABAJO = 40;
    const TICKET_ESCALA_MINIMA = 1.2;   // menos que esto ya no se lee bien de lejos

    const $ = (id) => document.getElementById(id);
    const koala = $('koala');
    const burbuja = $('burbuja');

    const textos = window.TEXTOS_CLIENTE;
    let idioma = window.IDIOMA_CLIENTE;

    const FORMATO_PESOS = new Intl.NumberFormat('es-MX', { minimumFractionDigits: 2, maximumFractionDigits: 2 });
    const dinero = (n) => `$${FORMATO_PESOS.format(Number(n) || 0)}`;

    function tr(clave, vars = {}) {
        return String(textos[idioma]?.[clave] ?? clave).replace(/\{(\w+)\}/g, (m, k) => (k in vars ? vars[k] : m));
    }

    function pintarTextosFijos() {
        document.documentElement.lang = idioma;
        document.title = `${tr('cliente.titulo')} - Koaly`;
        document.querySelectorAll('[data-i18n]').forEach((el) => { el.textContent = tr(el.dataset.i18n); });
    }

    /* ---------- Koala ---------- */
    const imagenCara = (cara) => `../Imagenes/koala/koala-${cara}.png`;
    [...CARAS_ESPERA, 'fiesta'].forEach((cara) => { new Image().src = imagenCara(cara); });   // sin parpadeo al cambiar

    let caraActual = 'pensando';

    function ponerCara(cara, animacion = '') {
        caraActual = cara;
        koala.src = imagenCara(cara);
        koala.classList.remove('saltando', 'emocionada');
        void koala.offsetWidth;     // reinicia la animación
        if (animacion) koala.classList.add(animacion);
    }

    // Solo en espera, y solo mientras la caja responde: en "Caja cerrada" se queda quieto
    setInterval(() => {
        if (vista !== 'espera') return;
        const otras = CARAS_ESPERA.filter((cara) => cara !== caraActual);
        ponerCara(otras[Math.floor(Math.random() * otras.length)], 'saltando');
    }, MS_ENTRE_CARAS);

    /* ---------- Qué se muestra ---------- */
    let estado = null;              // último estado de la caja; null: no hay caja
    let estadoJson = '';            // para ignorar las respuestas que no traen nada nuevo
    let vista = null;               // cerrada | espera | venta | gracias
    let ultimaVenta = null;         // la del "gracias", que sigue a la vista al vaciarse la caja
    let graciasHasta = 0;
    let temporizadorGracias = null;

    function calcularVista() {
        if (!estado || !estado.abierta) return 'cerrada';
        if (estado.venta) return 'gracias';
        if (estado.lineas.length > 0) return 'venta';
        return Date.now() < graciasHasta ? 'gracias' : 'espera';
    }

    function pintar(indiceNuevo = -1) {
        const anterior = vista;
        vista = calcularVista();

        const conKoala = vista !== 'venta';
        $('vistaKoala').hidden = !conKoala;
        $('vistaVenta').hidden = conKoala;

        if (conKoala) pintarKoala(anterior);
        else pintarVenta(indiceNuevo);

        // Terminó el mínimo de "gracias" sin que llegara nada nuevo: de regreso a espera
        clearTimeout(temporizadorGracias);
        if (vista === 'gracias' && !estado?.venta) {
            temporizadorGracias = setTimeout(() => pintar(), graciasHasta - Date.now());
        }
    }

    function pintarKoala(anterior) {
        const conTicket = vista === 'gracias' && !!ultimaVenta?.ticket;
        $('vistaKoala').classList.toggle('con-ticket', conTicket);
        $('impresion').hidden = !conTicket;

        if (vista === 'cerrada') {
            burbuja.textContent = tr('cliente.caja_cerrada');
            if (anterior !== vista) ponerCara('pensando');
        } else if (vista === 'espera') {
            burbuja.textContent = tr('cliente.bienvenida');
            if (anterior !== vista) ponerCara('normal', 'saltando');
        } else {
            burbuja.textContent = tr('ticket.gracias');
            if (anterior !== vista) ponerCara('fiesta', 'emocionada');
            if (conTicket) imprimirTicket(ultimaVenta.ticket);
        }
    }

    /* ---------- Ticket ---------- */
    // Llega ya armado: es el mismo HTML que la caja manda a la impresora (htmlTicket).
    // Va en un iframe para que sus estilos de 80 mm no se mezclen con los de esta página.
    let ticketImpreso = null;

    function imprimirTicket(html) {
        if (html === ticketImpreso) return;
        ticketImpreso = html;
        $('impresion').classList.remove('imprimiendo');
        const papel = $('ticketPapel');
        papel.onload = () => desenrollarTicket(papel);
        papel.srcdoc = html;
    }

    function desenrollarTicket(papel) {
        const impresion = $('impresion');
        const salida = $('ticketSalida');
        const marco = $('ticketMarco');
        const hoja = papel.contentDocument.body.getBoundingClientRect();

        salida.style.width = '';
        const disponible = window.innerHeight - marco.getBoundingClientRect().top - TICKET_MARGEN_ABAJO;

        // El ticket mide 80 mm: se amplía para leerse de lejos. Uno normal se ajusta para
        // verse completo; uno que ni así cabe va a todo lo ancho y avanza hasta llegar al total
        const escalaAncho = marco.clientWidth / hoja.width;
        const escalaCabe = disponible / hoja.height;
        const escala = escalaCabe >= TICKET_ESCALA_MINIMA ? Math.min(escalaAncho, escalaCabe) : escalaAncho;
        const alto = hoja.height * escala;
        salida.style.width = `${hoja.width * escala + marco.offsetWidth - marco.clientWidth}px`;
        papel.style.height = `${hoja.height}px`;
        papel.style.transform = `scale(${escala})`;
        $('ticketHoja').style.height = `${alto}px`;

        const visible = Math.min(alto, disponible);
        const sinMovimiento = window.matchMedia('(prefers-reduced-motion: reduce)').matches;
        const ms = sinMovimiento ? 0 : Math.min(TICKET_MS_MAXIMO, Math.max(TICKET_MS_MINIMO, alto / TICKET_PX_POR_MS));

        impresion.style.setProperty('--alto-visible', `${visible}px`);
        impresion.style.setProperty('--sobra', `${visible - alto}px`);
        impresion.style.setProperty('--ms-desenrollar', `${ms * visible / alto}ms`);
        impresion.style.setProperty('--ms-avanzar', `${ms * (alto - visible) / alto}ms`);
        impresion.style.setProperty('--ms-total', `${ms}ms`);

        void impresion.offsetWidth;     // arranca la animación desde el principio
        impresion.classList.add('imprimiendo');
    }

    function lineaEscaneo({ nombre, precio, cantidad }, nueva) {
        const li = document.createElement('li');
        li.className = nueva ? 'linea nueva' : 'linea';

        const etiquetaCantidad = document.createElement('span');
        etiquetaCantidad.className = 'linea-cantidad';
        etiquetaCantidad.textContent = `×${cantidad}`;

        const info = document.createElement('span');
        info.className = 'linea-info';
        const etiquetaNombre = document.createElement('span');
        etiquetaNombre.className = 'linea-nombre';
        etiquetaNombre.textContent = nombre;
        const etiquetaPrecio = document.createElement('small');
        etiquetaPrecio.textContent = tr('resumen.precio_unidad', { precio: dinero(precio) });
        info.append(etiquetaNombre, etiquetaPrecio);

        const importe = document.createElement('span');
        importe.className = 'linea-importe';
        importe.textContent = dinero(cantidad * precio);

        li.append(etiquetaCantidad, info, importe);
        return li;
    }

    function pintarVenta(indiceNuevo) {
        const filas = estado.lineas.map((linea, i) => lineaEscaneo(linea, i === indiceNuevo));
        $('listaEscaneo').replaceChildren(...filas);
        (filas[indiceNuevo] || filas[filas.length - 1]).scrollIntoView({ block: 'nearest' });

        $('totalArticulos').textContent = estado.lineas.reduce((n, linea) => n + linea.cantidad, 0);
        $('totalVenta').textContent = dinero(estado.total);
        pintarCobro(estado.cobro);
    }

    // Mientras el cajero captura el pago: lo recibido y lo que falta o el cambio
    function pintarCobro(cobro) {
        const filas = [];
        if (cobro) {
            const hayPago = cobro.tarjeta > 0 || cobro.recibido > 0;
            if (cobro.tarjeta > 0) filas.push([tr('varios.tarjeta'), cobro.tarjeta, '']);
            if (cobro.recibido > 0) filas.push([tr('efectivo.recibido'), cobro.recibido, '']);
            if (hayPago && cobro.restante > 0) filas.push([tr('varios.restante'), cobro.restante, 'is-falta']);
            else if (cobro.recibido > 0) filas.push([tr('efectivo.cambio'), cobro.cambio, 'is-cambio']);
        }

        $('cobro').hidden = filas.length === 0;
        $('cobro').replaceChildren(...filas.map(([etiqueta, monto, clase]) => {
            const fila = document.createElement('div');
            fila.className = `cobro-fila ${clase}`.trim();
            const nombre = document.createElement('span');
            nombre.textContent = etiqueta;
            const valor = document.createElement('strong');
            valor.textContent = dinero(monto);
            fila.append(nombre, valor);
            return fila;
        }));
    }

    // Qué línea se acaba de agregar o aumentar, para resaltarla (-1: ninguna).
    // Al quitar productos no se resalta nada.
    function indiceCambiado(antes, ahora) {
        if (ahora.length < antes.length) return -1;
        for (let i = ahora.length - 1; i >= 0; i--) {
            const previa = antes[i];
            if (!previa || previa.nombre !== ahora[i].nombre || ahora[i].cantidad > previa.cantidad) return i;
        }
        return -1;
    }

    /* ---------- Comunicación con la caja ---------- */
    function recibirEstado(nuevo) {
        const json = JSON.stringify(nuevo);
        if (json === estadoJson) return;
        estadoJson = json;

        const indiceNuevo = estado ? indiceCambiado(estado.lineas, nuevo.lineas) : -1;
        estado = nuevo;

        if (nuevo.venta) {
            ultimaVenta = nuevo.venta;
            graciasHasta = Date.now() + MS_GRACIAS_MINIMO;
        }
        if (textos[nuevo.idioma] && nuevo.idioma !== idioma) {
            idioma = nuevo.idioma;
            pintarTextosFijos();
        }
        pintar(indiceNuevo);
    }

    function perderCaja() {
        if (!estado) return;
        estado = null;
        estadoJson = '';
        graciasHasta = 0;
        pintar();
    }

    pintarTextosFijos();
    pintar();

    if ('BroadcastChannel' in window) {
        const canal = new BroadcastChannel('koaly-pantalla-cliente');
        let saludosSinRespuesta = 0;

        canal.onmessage = ({ data }) => {
            if (data?.tipo === 'adios') {
                perderCaja();
            } else if (data?.tipo === 'estado') {
                saludosSinRespuesta = 0;
                recibirEstado(data);
            }
        };

        const saludar = () => {
            if (saludosSinRespuesta >= SALUDOS_SIN_RESPUESTA) perderCaja();
            saludosSinRespuesta += 1;
            canal.postMessage({ tipo: 'hola' });
        };
        saludar();
        setInterval(saludar, MS_ENTRE_SALUDOS);
    }

    /* ---------- Preparar la ventana (solo al abrirla) ---------- */
    $('btnListo').addEventListener('click', () => { $('preparar').hidden = true; });

    $('btnPantallaCompleta').addEventListener('click', () => {
        $('preparar').hidden = true;
        const raiz = document.documentElement;
        const entrar = raiz.requestFullscreen || raiz.webkitRequestFullscreen;     // Safari anterior a 16.4
        Promise.resolve(entrar?.call(raiz)).catch(() => { /* sin permiso: se queda en ventana */ });
    });
})();
