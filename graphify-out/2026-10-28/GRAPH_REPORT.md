# Graph Report - Koaly  (2026-10-22)

## Corpus Check
- 104 files · ~128,055 words
- Verdict: corpus is large enough that graph structure adds value.
- Unclassified: 9 file(s) not represented in the graph (top: .css 8, (none) 1)

## Summary
- 379 nodes · 675 edges · 73 communities (22 shown, 51 thin omitted)
- Extraction: 93% EXTRACTED · 7% INFERRED · 0% AMBIGUOUS · INFERRED: 46 edges (avg confidence: 0.85)
- Token cost: 0 input · 0 output

## Community Hubs (Navigation)
- i18n Gerente
- Cajero Index UI
- Caja Backend
- Turnos y Corte
- Modales de Pago
- Apertura y Corte
- Carrito y Catalogo
- Registro y Contrasena
- Gerentes Admin
- Busqueda Productos
- Core Admin JS
- Paginas Admin JS
- Inventario
- Ventas
- Login
- Catalogo Cajero
- Registrar Venta
- Componentes Select
- Resumen de Venta
- Cajas Gerente
- Sucursal Gerente
- Plantilla Fondo
- Avisos y Toasts
- Filtro Groserias
- Cajeros
- Correo SMTP
- Cortes

## God Nodes (most connected - your core abstractions)
1. `tr()` - 50 edges
2. `formatoDinero()` - 24 edges
3. `renderCarrito()` - 19 edges
4. `cerrarModal()` - 15 edges
5. `abrirModal()` - 13 edges
6. `t()` - 12 edges
7. `aCentavos()` - 11 edges
8. `agregarAlCarrito()` - 11 edges
9. `mostrarAviso()` - 11 edges
10. `pintarResultados()` - 10 edges

## Surprising Connections (you probably didn't know these)
- `validarCajeroDeCaja()` --calls--> `t()`  [INFERRED]
  gerente/cajas.php → gerente/includes/i18n.php
- `{closure#3}()` --calls--> `t()`  [INFERRED]
  gerente/ventas_sucursal.php → gerente/includes/i18n.php
- `{closure#2}()` --calls--> `tValor()`  [INFERRED]
  gerente/ventas_sucursal.php → gerente/includes/i18n.php
- `{closure#8}()` --calls--> `tValor()`  [INFERRED]
  gerente/ventas_sucursal.php → gerente/includes/i18n.php
- `leerDesglose()` --calls--> `t()`  [INFERRED]
  cajero/includes/caja.php → cajero/includes/i18n.php

## Import Cycles
- None detected.

## Communities (73 total, 51 thin omitted)

### Community 0 - "i18n Gerente"
Cohesion: 0.07
Nodes (29): dinero(), e(), fecha(), i18nParaJs(), i18nReemplazar(), idiomaActual(), numero(), t() (+21 more)

### Community 1 - "Cajero Index UI"
Cohesion: 0.08
Nodes (28): botonTema, carrito, catalogo, categorias, conteoCorte, DENOMINACIONES, escaparHtml(), FORMATO_PESOS (+20 more)

### Community 2 - "Caja Backend"
Cohesion: 0.13
Nodes (9): CajaCerradaException, cajeroConCaja(), leerDesglose(), SinCambioException, turnoAbierto(), ultimoTurnoCerrado(), usarHoraLocal(), i18nReemplazar() (+1 more)

### Community 3 - "Turnos y Corte"
Cohesion: 0.19
Nodes (20): abrirCorte(), aceptarCorte(), actualizarBotonTema(), aplicarIdioma(), avisoAgotado(), cargarTurno(), confirmarApertura(), confirmarCierre() (+12 more)

### Community 4 - "Modales de Pago"
Cohesion: 0.22
Nodes (19): abrirLista(), abrirModal(), abrirModalEfectivo(), abrirModalVarios(), aCentavos(), actualizarCambioPreview(), actualizarVariosPreview(), autollenarTarjeta() (+11 more)

### Community 5 - "Apertura y Corte"
Cohesion: 0.25
Nodes (15): abrirModalApertura(), actualizarAvisoApertura(), actualizarEntrega(), actualizarTotalCorte(), animarTarjeta(), centavosDeTexto(), continuarCierre(), crearTarjetaDinero() (+7 more)

### Community 6 - "Carrito y Catalogo"
Cohesion: 0.23
Nodes (15): actualizarCatalogo(), actualizarStockProducto(), agregarAlCarrito(), avisoInsuficiente(), cambiarCantidad(), cantidadDeProducto(), crearBotonCantidad(), crearTarjeta() (+7 more)

### Community 7 - "Registro y Contrasena"
Cohesion: 0.22
Nodes (12): api(), CONTRASENAS_COMUNES, enviarCodigo(), evaluarFuerza(), limpiarMensaje(), mostrarAviso(), mostrarMensaje(), mostrarVista() (+4 more)

### Community 8 - "Gerentes Admin"
Cohesion: 0.18
Nodes (5): BADGES_ESTADO, confirmarCambio(), HISTORIAL_ESTADOS, parrafo(), registrarCambioEstado()

### Community 9 - "Busqueda Productos"
Cohesion: 0.26
Nodes (12): activarResultado(), buscando(), buscarProducto(), cerrarResultados(), consultarProductos(), mostrarResultados(), ocultarResultados(), pintarMensaje() (+4 more)

### Community 10 - "Core Admin JS"
Cohesion: 0.29
Nodes (6): actualizarBotonTema(), crearModalConfirmacion(), logoutConfirmado(), pedirConfirmacion(), temaActual(), tj()

### Community 11 - "Paginas Admin JS"
Cohesion: 0.24
Nodes (3): abrirModalEditarCaja(), abrirModalNuevaCaja(), _actualizarAvisoReasignacion()

### Community 12 - "Inventario"
Cohesion: 0.31
Nodes (5): detener(), iniciar(), obtenerInventario(), refrescar(), urlActual()

### Community 13 - "Ventas"
Cohesion: 0.33
Nodes (6): detener(), iniciar(), obtenerStatsVentas(), obtenerVentas(), refrescar(), urlActual()

### Community 14 - "Login"
Cohesion: 0.36
Nodes (5): buscarUsuarioPorCorreo(), {closure#1}(), error(), guardarContrasena(), responder()

### Community 15 - "Catalogo Cajero"
Cohesion: 0.43
Nodes (7): aplicarFiltroCategoria(), cargarCatalogo(), finalizarVenta(), mensajeCatalogo(), nombreCategoria(), renderCatalogo(), renderFiltroCategorias()

### Community 17 - "Componentes Select"
Cohesion: 0.38
Nodes (5): _cerrarTodosLosSelects(), initCustomSelect(), initCustomSelects(), _selectCustomActivos, VISTAS_VALIDAS

### Community 18 - "Resumen de Venta"
Cohesion: 0.33
Nodes (6): abrirModalPago(), actualizarExistencias(), agruparCarrito(), calcularTotales(), lineaResumen(), renderResumen()

### Community 20 - "Sucursal Gerente"
Cohesion: 0.70
Nodes (3): asignarSucursalAGerente(), gerenteAsignable(), sucursalSinGerente()

### Community 21 - "Plantilla Fondo"
Cohesion: 0.40
Nodes (5): abrirConfirmacion(), cargarPlantillaFondo(), limpiarTabla(), pedirLimpiarCorte(), pedirPlantillaFondo()

### Community 22 - "Avisos y Toasts"
Cohesion: 0.50
Nodes (5): ajustarADisponible(), avisoFaltantes(), mostrarToast(), trn(), trReemplazar()

### Community 24 - "Filtro Groserias"
Cohesion: 0.83
Nodes (3): consultarProfanityFilter(), obtenerApiVerveKey(), rechazarSiProfano()

## Knowledge Gaps
- **51 thin communities (<3 nodes) omitted from report** — run `graphify query` to explore isolated nodes.

## Suggested Questions
_Questions this graph is uniquely positioned to answer:_

- **Why does `t()` connect `i18n Gerente` to `Cajas Gerente`?**
  _High betweenness centrality (0.009) - this node is a cross-community bridge._
- **Should `i18n Gerente` be split into smaller, more focused modules?**
  _Cohesion score 0.07358156028368794 - nodes in this community are weakly interconnected._
- **Why does `tr()` connect `Turnos y Corte` to `Cajero Index UI`, `Modales de Pago`, `Apertura y Corte`, `Carrito y Catalogo`, `Busqueda Productos`, `Catalogo Cajero`, `Resumen de Venta`, `Plantilla Fondo`, `Avisos y Toasts`?**
  _High betweenness centrality (0.007) - this node is a cross-community bridge._
- **Should `Cajero Index UI` be split into smaller, more focused modules?**
  _Cohesion score 0.07526881720430108 - nodes in this community are weakly interconnected._
- **Should `Caja Backend` be split into smaller, more focused modules?**
  _Cohesion score 0.12631578947368421 - nodes in this community are weakly interconnected._