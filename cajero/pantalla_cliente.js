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
    const REPASO_MS_ARRIBA = 1500;      // al cobrar: pausa al principio de la lista…
    const REPASO_MS_ABAJO = 2500;       // …y al final, antes de volver a empezar
    const REPASO_ALTOS_POR_SEGUNDO = 0.06;  // velocidad de bajada: 6 % del alto de la ventana por segundo
    const REPASO_PX_POR_SEGUNDO_SUBIDA = 900;

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

        if (conKoala) {
            lineasPintadas = '';
            pintarKoala(anterior);
        } else {
            pintarVenta(indiceNuevo);
        }

        // Mientras se cobra, la lista se repasa sola; en cualquier otro momento, no
        if (vista === 'venta' && estado.cobro) iniciarRepaso();
        else detenerRepaso();

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

    /* ---------- Fotos de los productos ---------- */
    // La misma foto del catálogo; sin foto, o si no carga, la inicial del nombre.
    // Con cantidad > 1 lleva el globito ×N en la esquina.
    function fotoProducto({ nombre, imagen, cantidad }, clase) {
        const marco = document.createElement('span');
        marco.className = `foto ${clase}`;
        const ponerInicial = () => {
            marco.classList.add('sin-foto');
            const letra = document.createElement('span');
            letra.textContent = (String(nombre).trim()[0] || '?').toUpperCase();
            marco.prepend(letra);
        };
        if (imagen) {
            const img = document.createElement('img');
            img.alt = '';
            img.onerror = () => { img.remove(); ponerInicial(); };
            img.src = `../${imagen}`;
            marco.append(img);
        } else {
            ponerInicial();
        }
        if (cantidad > 1) {
            const globito = document.createElement('span');
            globito.className = 'foto-cantidad';
            globito.textContent = `×${cantidad}`;
            marco.append(globito);
        }
        return marco;
    }

    function lineaEscaneo(linea, nueva) {
        const { nombre, precio, cantidad } = linea;
        const li = document.createElement('li');
        li.className = nueva ? 'linea nueva' : 'linea';

        // En la lista el globito va siempre, también con ×1
        const etiquetaFoto = fotoProducto({ ...linea, cantidad: 0 }, 'linea-foto');
        const etiquetaCantidad = document.createElement('span');
        etiquetaCantidad.className = 'foto-cantidad';
        etiquetaCantidad.textContent = `×${cantidad}`;
        etiquetaFoto.append(etiquetaCantidad);

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

        li.append(etiquetaFoto, info, importe);
        return li;
    }

    /* ---------- "Se agregó": lo último que pasó por el escáner ---------- */
    // Entra con un salto cada vez que se agrega o aumenta algo; al quitar productos
    // se queda el que estaba (o el último de la lista). Mientras se cobra se oculta:
    // ese espacio es para lo recibido y el cambio.
    let ultimoMostrado = '';        // nombre|imagen|cantidad de lo que está en la tarjeta

    function pintarUltimo(indiceNuevo) {
        const lineas = estado.lineas;
        const agregado = lineas[indiceNuevo];
        const nombreActual = ultimoMostrado.split('|')[0];
        const linea = agregado || lineas.findLast((l) => l.nombre === nombreActual) || lineas[lineas.length - 1];

        const tarjeta = $('ultimo');
        tarjeta.hidden = !linea || !!estado.cobro;
        if (!linea) {
            ultimoMostrado = '';
            return;
        }

        const clave = `${linea.nombre}|${linea.imagen}|${linea.cantidad}`;
        if (clave !== ultimoMostrado) {
            $('ultimoFoto').replaceChildren(fotoProducto(linea, 'ultimo-foto'));
            $('ultimoNombre').textContent = linea.nombre;
            $('ultimoPrecio').textContent = dinero(linea.precio);
            ultimoMostrado = clave;
        }
        if (agregado && !tarjeta.hidden) {
            tarjeta.classList.remove('entrando');
            void tarjeta.offsetWidth;     // reinicia la animación aunque sea el mismo producto
            tarjeta.classList.add('entrando');
        }
    }

    // La lista solo se vuelve a dibujar si cambian los productos: mientras el cajero
    // escribe lo recibido llegan muchos estados y no deben brincar el repaso
    let lineasPintadas = '';

    function pintarVenta(indiceNuevo) {
        const lineasJson = JSON.stringify(estado.lineas);
        if (lineasJson !== lineasPintadas || indiceNuevo >= 0) {
            lineasPintadas = lineasJson;
            const filas = estado.lineas.map((linea, i) => lineaEscaneo(linea, i === indiceNuevo));
            $('listaEscaneo').replaceChildren(...filas);
            (filas[indiceNuevo] || filas[filas.length - 1]).scrollIntoView({ block: 'nearest' });
        }
        revisarAvisos();

        $('totalArticulos').textContent = estado.lineas.reduce((n, linea) => n + linea.cantidad, 0);
        $('totalVenta').textContent = dinero(estado.total);
        pintarUltimo(indiceNuevo);
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

    /* ---------- La lista no cabe: avisos y repaso al cobrar ---------- */
    // "▲ 5 productos más arriba / ▼ 3 más abajo", con la orilla desvanecida, para
    // que se note que la lista sigue aunque nadie la pueda tocar
    function revisarAvisos() {
        const lista = $('listaEscaneo');
        const caja = lista.getBoundingClientRect();
        let arriba = 0;
        let abajo = 0;
        for (const fila of lista.children) {
            const r = fila.getBoundingClientRect();
            if (r.bottom <= caja.top + 8) arriba += 1;
            else if (r.top >= caja.bottom - 8) abajo += 1;
        }
        const aviso = (id, clave, n) => {
            $(id).textContent = n === 1 ? tr(`${clave}_1`) : tr(clave, { n });
            $(id).classList.toggle('oculto', n === 0);
        };
        aviso('masArriba', 'cliente.mas_arriba', arriba);
        aviso('masAbajo', 'cliente.mas_abajo', abajo);
        $('listaEnvoltura').classList.toggle('hay-arriba', arriba > 0);
        $('listaEnvoltura').classList.toggle('hay-abajo', abajo > 0);
    }
    $('listaEscaneo').addEventListener('scroll', revisarAvisos, { passive: true });
    window.addEventListener('resize', () => { if (vista === 'venta') revisarAvisos(); });

    // Al cobrar, el cliente revisa su compra: la lista baja despacio hasta el final,
    // se espera y vuelve a empezar desde arriba. Va y viene en lugar de dar vueltas:
    // un carrusel que repite productos parecería un cobro doble. Se detiene al dejar
    // de cobrar. Sin animaciones (prefers-reduced-motion) no se mueve sola.
    let turnoRepaso = 0;            // cambia para cancelar el repaso en curso
    let repasando = false;

    function iniciarRepaso() {
        if (repasando || window.matchMedia('(prefers-reduced-motion: reduce)').matches) return;
        repasando = true;
        repasar(++turnoRepaso);
    }

    function detenerRepaso() {
        if (!repasando) return;
        repasando = false;
        turnoRepaso += 1;
        // De regreso al último producto, como al escanear
        const ultima = $('listaEscaneo').lastElementChild;
        if (vista === 'venta' && ultima) ultima.scrollIntoView({ block: 'nearest' });
    }

    const esperar = (ms) => new Promise((listo) => setTimeout(listo, ms));

    // Lleva la lista a `destino` (scrollTop) a cierta velocidad; se corta si cambia el turno.
    // La posición se lleva aquí, con decimales: a la velocidad de bajada avanza menos
    // de 1 px por cuadro, y Safari redondea scrollTop hacia abajo, así que sumarle
    // la fracción a scrollTop la dejaba siempre en el mismo lugar.
    function deslizar(destino, pxPorSegundo, turno) {
        const lista = $('listaEscaneo');
        return new Promise((listo) => {
            let posicion = lista.scrollTop;
            let antes = performance.now();
            const paso = (ahora) => {
                if (turno !== turnoRepaso) return listo();
                const falta = destino - posicion;
                if (Math.abs(falta) < 1) {
                    lista.scrollTop = destino;
                    return listo();
                }
                posicion += Math.sign(falta) * Math.min(Math.abs(falta), pxPorSegundo * (ahora - antes) / 1000);
                antes = ahora;
                lista.scrollTop = posicion;
                requestAnimationFrame(paso);
            };
            requestAnimationFrame(paso);
        });
    }

    async function repasar(turno) {
        const lista = $('listaEscaneo');
        while (turno === turnoRepaso) {
            const recorrido = lista.scrollHeight - lista.clientHeight;
            if (recorrido < 4) {            // todo cabe: no hay nada que repasar
                await esperar(1000);
                continue;
            }
            await deslizar(0, REPASO_PX_POR_SEGUNDO_SUBIDA, turno);
            await esperar(REPASO_MS_ARRIBA);
            if (turno !== turnoRepaso) return;
            await deslizar(recorrido, window.innerHeight * REPASO_ALTOS_POR_SEGUNDO, turno);
            await esperar(REPASO_MS_ABAJO);
        }
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
