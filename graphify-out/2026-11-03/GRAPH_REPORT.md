# Graph Report - Koaly  (2026-10-28)

## Corpus Check
- 106 files · ~179,614 words
- Verdict: corpus is large enough that graph structure adds value.
- Unclassified: 12 file(s) not represented in the graph (top: .css 11, (none) 1)

## Summary
- 594 nodes · 1112 edges · 82 communities (30 shown, 52 thin omitted)
- Extraction: 91% EXTRACTED · 9% INFERRED · 0% AMBIGUOUS · INFERRED: 95 edges (avg confidence: 0.85)
- Token cost: 0 input · 0 output

## Graph Freshness
- Built from commit: `5df2f91c`
- Run `git rev-parse HEAD` and compare to check if the graph is stale.
- Run `graphify update .` after code changes (no API cost).

## Community Hubs (Navigation)
- gerente/includes/i18n.php
- Cajero Index UI
- Caja Backend
- Turnos y Corte
- Modales de Pago
- actualizarEntrega
- Carrito y Catalogo
- Registro y Contrasena
- Gerentes Admin
- perfil.php
- Core Admin JS
- paginas.js
- inventario.php
- Ventas
- Login
- renderCatalogo
- Registrar Venta
- componentes.js
- Imagenes
- Cajas Gerente
- formatoDinero
- solicitudes_comun.php
- perfil.js
- Filtro Groserias
- e
- aviso.php
- ventas_sucursal.php
- filtros.php
- Cortes
- t
- {closure#3}
- includes/productos.php
- reglas_contrasena.php
- privacidad/index.php
- initBarraLateral

## God Nodes (most connected - your core abstractions)
1. `tr()` - 56 edges
2. `formatoDinero()` - 30 edges
3. `renderCarrito()` - 19 edges
4. `t()` - 18 edges
5. `cerrarModal()` - 17 edges
6. `abrirModal()` - 14 edges
7. `mostrarAviso()` - 14 edges
8. `aCentavos()` - 13 edges
9. `renderCatalogo()` - 12 edges
10. `confirmarRetiro()` - 12 edges

## Surprising Connections (you probably didn't know these)
- `menuPerfil()` --calls--> `csrfToken()`  [INFERRED]
  perfil/perfil.php → admin/csrf.php
- `perfilIdioma()` --calls--> `idiomaGuardado()`  [INFERRED]
  perfil/perfil.php → admin/preferencia_idioma.php
- `{closure#2}()` --calls--> `csrfToken()`  [INFERRED]
  gerente/cajeros.php → admin/csrf.php
- `{closure#1}()` --calls--> `t()`  [INFERRED]
  gerente/cajeros.php → gerente/includes/i18n.php
- `_firma()` --calls--> `urlSitio()`  [INFERRED]
  gerente/includes/correos_empleado.php → privacidad/aviso.php

## Import Cycles
- None detected.

## Communities (82 total, 52 thin omitted)

### Community 0 - "gerente/includes/i18n.php"
Cohesion: 0.23
Nodes (10): textoRangoActividad(), dinero(), fecha(), i18nReemplazar(), idiomaActual(), numero(), tEs(), tn() (+2 more)

### Community 1 - "Cajero Index UI"
Cohesion: 0.07
Nodes (30): abrirModalPago(), aceptarCorte(), actualizarExistencias(), agruparCarrito(), avisoFaltantes(), calcularTotales(), carrito, catalogo (+22 more)

### Community 2 - "Caja Backend"
Cohesion: 0.11
Nodes (15): arqueoEnPesos(), arqueoTurno(), bdACentavos(), CajaCerradaException, cajeroConCaja(), leerDesglose(), nombreCaja(), retirosDelTurno() (+7 more)

### Community 3 - "Turnos y Corte"
Cohesion: 0.22
Nodes (23): abrirApertura(), abrirArqueo(), abrirCorte(), abrirRetiro(), aplicarIdioma(), avisarExcedente(), avisoAgotado(), cargarTurno() (+15 more)

### Community 4 - "Modales de Pago"
Cohesion: 0.17
Nodes (21): abrirLista(), abrirModal(), abrirModalVarios(), aCentavos(), actualizarVariosPreview(), autollenarTarjeta(), bloquearCobro(), centavosDe() (+13 more)

### Community 5 - "actualizarEntrega"
Cohesion: 0.24
Nodes (13): abrirConfirmacion(), actualizarAvisoConteo(), actualizarEntrega(), actualizarTotalCorte(), cargarPlantillaFondo(), centavosDeTexto(), continuarCierre(), crearTarjetaDinero() (+5 more)

### Community 6 - "Carrito y Catalogo"
Cohesion: 0.16
Nodes (21): actualizarStockProducto(), agregarAlCarrito(), agregarYLimpiar(), ajustarADisponible(), animarTarjeta(), avisoInsuficiente(), cambiarCantidad(), cantidadDeProducto() (+13 more)

### Community 7 - "Registro y Contrasena"
Cohesion: 0.22
Nodes (17): api(), aplicarIdioma(), CONTRASENAS_COMUNES, enviarCodigo(), evaluarFuerza(), iniciarEsperaReenvio(), limpiarMensaje(), mostrarAviso() (+9 more)

### Community 8 - "Gerentes Admin"
Cohesion: 0.17
Nodes (6): BADGES_ESTADO, confirmarCambio(), HISTORIAL_ESTADOS, NOMBRES_ESTADO, parrafo(), registrarCambioEstado()

### Community 9 - "perfil.php"
Cohesion: 0.16
Nodes (20): idiomaGuardado(), perfilError(), perfilResponder(), {closure#3}(), menuPerfil(), perfilAvatar(), perfilCaja(), perfilConsultar() (+12 more)

### Community 10 - "Core Admin JS"
Cohesion: 0.31
Nodes (5): crearModalConfirmacion(), logoutConfirmado(), marcarCampoInvalido(), pedirConfirmacion(), tj()

### Community 11 - "paginas.js"
Cohesion: 0.18
Nodes (15): abrirModalEditarCaja(), abrirModalEditarCajero(), abrirModalEditarSolicitud(), abrirModalNuevaCaja(), abrirModalNuevoCajero(), abrirModalSolicitarProducto(), _actualizarAvisoReasignacion(), _actualizarAyudaEstado() (+7 more)

### Community 12 - "inventario.php"
Cohesion: 0.09
Nodes (24): {closure#2}(), e(), fecha(), i18nParaJs(), i18nReemplazar(), idiomaActual(), t(), tn() (+16 more)

### Community 13 - "Ventas"
Cohesion: 0.39
Nodes (6): detener(), iniciar(), obtenerStatsVentas(), obtenerVentas(), refrescar(), urlActual()

### Community 14 - "Login"
Cohesion: 0.23
Nodes (7): t(), buscarUsuarioPorCorreo(), {closure#1}(), error(), guardarContrasena(), motivoContrasena(), responder()

### Community 15 - "renderCatalogo"
Cohesion: 0.23
Nodes (18): actualizarCatalogo(), agregarDesdeBusqueda(), aplicarFiltros(), buscando(), cargarCatalogo(), coincideBusqueda(), crearSeccion(), disponiblesDe() (+10 more)

### Community 17 - "componentes.js"
Cohesion: 0.25
Nodes (13): _aTexto(), _cerrarTodosLosSelects(), _diasEntre(), _fechaUTC(), initCustomSelect(), initCustomSelects(), initFiltros(), cerrar() (+5 more)

### Community 18 - "Imagenes"
Cohesion: 0.08
Nodes (23): Administrador General, Base de datos, Cajas, Cajero, Características de la Plataforma, Contacto, Descripción, 🇲🇽 Español. (+15 more)

### Community 20 - "formatoDinero"
Cohesion: 0.17
Nodes (21): abrirModalEfectivo(), actualizarCambioPreview(), denominacion(), escaparHtml(), fechaHoraCorta(), filaResumen(), filasDesglose(), filaTicket() (+13 more)

### Community 21 - "solicitudes_comun.php"
Cohesion: 0.17
Nodes (12): actualizarSolicitud(), contarSolicitudesParaAdmin(), crearProductoDesdeSolicitud(), diferenciasSolicitud(), guardarImagenProducto(), guardarImagenSolicitud(), historialSolicitudes(), leerFichaSolicitud() (+4 more)

### Community 22 - "perfil.js"
Cohesion: 0.24
Nodes (15): abrirMenu(), abrirPerfil(), avisar(), cargarNotificaciones(), cerrarMenu(), itemNotificacion(), mensajeLista(), mostrarNotificaciones() (+7 more)

### Community 24 - "Filtro Groserias"
Cohesion: 0.83
Nodes (3): consultarProfanityFilter(), obtenerApiVerveKey(), rechazarSiProfano()

### Community 25 - "e"
Cohesion: 0.17
Nodes (6): csrfToken(), {closure#1}(), {closure#2}(), liberarCajasDeCajero(), e(), selectorVistas()

### Community 26 - "aviso.php"
Cohesion: 0.15
Nodes (17): _bloqueAcceso(), _correoContacto(), datosParaCorreos(), enviarAvisoCambioCorreo(), enviarAvisoCambioEstado(), enviarAvisoPasswordRestablecida(), enviarAvisoPrivacidad(), _firma() (+9 more)

### Community 27 - "ventas_sucursal.php"
Cohesion: 0.18
Nodes (4): tValor(), {closure#2}(), {closure#4}(), {closure#8}()

### Community 28 - "filtros.php"
Cohesion: 0.27
Nodes (7): atajosDePeriodo(), diasConActividad(), leerFechaFiltro(), leerPeriodo(), rangosDeActividad(), {closure#1}(), {closure#1}()

### Community 37 - "t"
Cohesion: 0.33
Nodes (5): i18nParaJs(), t(), {closure#1}(), {closure#3}(), {closure#3}()

### Community 49 - "{closure#3}"
Cohesion: 0.31
Nodes (6): esCambioPrecio(), etiquetaTipo(), {closure#2}(), {closure#3}(), {closure#5}(), {closure#7}()

### Community 54 - "includes/productos.php"
Cohesion: 0.48
Nodes (5): agregarProductoAInventario(), badgeStock(), generarCodigoUnico(), leerStockParaActualizar(), registrarMovimiento()

### Community 61 - "reglas_contrasena.php"
Cohesion: 0.47
Nodes (3): generarPasswordTemporal(), passwordTemporalVencida(), ponerPasswordTemporal()

## Knowledge Gaps
- **52 thin communities (<3 nodes) omitted from report** — run `graphify query` to explore isolated nodes.

## Suggested Questions
_Questions this graph is uniquely positioned to answer:_

- **Why does `t()` connect `t` to `gerente/includes/i18n.php`, `{closure#3}`, `Cajas Gerente`, `includes/productos.php`, `e`, `filtros.php`?**
  _High betweenness centrality (0.018) - this node is a cross-community bridge._
- **Are the 15 inferred relationships involving `t()` (e.g. with `validarCajeroDeCaja()` and `{closure#1}()`) actually correct?**
  _`t()` has 15 INFERRED edges - model-reasoned connections that need verification._
- **Should `Cajero Index UI` be split into smaller, more focused modules?**
  _Cohesion score 0.06890756302521009 - nodes in this community are weakly interconnected._
- **Why does `{closure#2}()` connect `e` to `t`?**
  _High betweenness centrality (0.014) - this node is a cross-community bridge._
- **Should `Caja Backend` be split into smaller, more focused modules?**
  _Cohesion score 0.11076923076923077 - nodes in this community are weakly interconnected._
- **Why does `csrfToken()` connect `e` to `perfil.php`?**
  _High betweenness centrality (0.013) - this node is a cross-community bridge._
- **Should `inventario.php` be split into smaller, more focused modules?**
  _Cohesion score 0.09269162210338681 - nodes in this community are weakly interconnected._