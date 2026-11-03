# Graph Report - Koaly  (2026-11-03)

## Corpus Check
- 146 files · ~198,451 words
- Verdict: corpus is large enough that graph structure adds value.
- Unclassified: 12 file(s) not represented in the graph (top: .css 11, (none) 1)

## Summary
- 836 nodes · 1591 edges · 102 communities (37 shown, 65 thin omitted)
- Extraction: 89% EXTRACTED · 11% INFERRED · 0% AMBIGUOUS · INFERRED: 181 edges (avg confidence: 0.85)
- Token cost: 0 input · 0 output

## Graph Freshness
- Built from commit: `ceff0ecf`
- Run `git rev-parse HEAD` and compare to check if the graph is stale.
- Run `graphify update .` after code changes (no API cost).

## Community Hubs (Navigation)
- gerente/includes/i18n.php
- Cajero Index UI
- Caja Backend
- Turnos y Corte
- Modales de Pago
- aCentavos
- Carrito y Catalogo
- Registro y Contrasena
- Gerentes Admin
- perfil.php
- Core Admin JS
- paginas.js
- inventario.php
- Dictionary
- Login
- renderCatalogo
- Registrar Venta
- componentes.js
- Imagenes
- Cajas Gerente
- formatoDinero
- solicitudes_comun.php
- perfil.js
- profanity_filter.php
- t
- aviso.php
- ventas_sucursal.php
- filtros.php
- Cortes
- t
- {closure#3}
- includes/productos.php
- reglas_contrasena.php
- Result
- initBarraLateral
- PatternDriver.php
- .detect
- MatchedWord
- MaskStrategyInterface
- Severity
- Código de referencia (de la maqueta aprobada)
- PhoneticMatcher
- NombreInvalido
- CallbackMask
- includes/turnos.php

## God Nodes (most connected - your core abstractions)
1. `tr()` - 56 edges
2. `Result` - 37 edges
3. `Dictionary` - 32 edges
4. `formatoDinero()` - 30 edges
5. `t()` - 29 edges
6. `Severity` - 21 edges
7. `MaskStrategyInterface` - 19 edges
8. `MatchedWord` - 19 edges
9. `renderCarrito()` - 19 edges
10. `cerrarModal()` - 17 edges

## Surprising Connections (you probably didn't know these)
- `menuPerfil()` --calls--> `csrfToken()`  [INFERRED]
  perfil/perfil.php → admin/csrf.php
- `perfilIdioma()` --calls--> `idiomaGuardado()`  [INFERRED]
  perfil/perfil.php → admin/preferencia_idioma.php
- `Lo de Koaly (no es de Blasp)` --references--> `tieneGroseriaPegada()`  [INFERRED]
  admin/lib/blasp/ORIGEN.md → admin/profanity_filter.php
- `{closure#6}()` --calls--> `csrfToken()`  [INFERRED]
  gerente/cajas.php → admin/csrf.php
- `{closure#3}()` --calls--> `csrfToken()`  [INFERRED]
  gerente/cajeros.php → admin/csrf.php

## Import Cycles
- None detected.

## Communities (102 total, 65 thin omitted)

### Community 0 - "gerente/includes/i18n.php"
Cohesion: 0.29
Nodes (10): textoRangoActividad(), dinero(), fecha(), i18nParaJs(), i18nReemplazar(), idiomaActual(), numero(), tEs() (+2 more)

### Community 1 - "Cajero Index UI"
Cohesion: 0.07
Nodes (30): aceptarCorte(), carrito, catalogo, categorias, conteoCorte, DENOMINACIONES, filaResumen(), FORMATO_PESOS (+22 more)

### Community 2 - "Caja Backend"
Cohesion: 0.11
Nodes (15): arqueoEnPesos(), arqueoTurno(), bdACentavos(), CajaCerradaException, cajeroConCaja(), leerDesglose(), nombreCaja(), retirosDelTurno() (+7 more)

### Community 3 - "Turnos y Corte"
Cohesion: 0.18
Nodes (23): abrirArqueo(), actualizarCatalogo(), aplicarIdioma(), avisarExcedente(), cargarTurno(), crearSeccion(), disponiblesDe(), estaAgotado() (+15 more)

### Community 4 - "Modales de Pago"
Cohesion: 0.17
Nodes (21): abrirCorte(), abrirLista(), abrirModal(), abrirModalPago(), abrirModalVarios(), actualizarExistencias(), agruparCarrito(), avisoFaltantes() (+13 more)

### Community 5 - "aCentavos"
Cohesion: 0.17
Nodes (23): abrirApertura(), abrirRetiro(), aCentavos(), actualizarAvisoConteo(), actualizarEntrega(), actualizarTotalCorte(), actualizarVariosPreview(), autollenarTarjeta() (+15 more)

### Community 6 - "Carrito y Catalogo"
Cohesion: 0.15
Nodes (22): abrirConfirmacion(), actualizarStockProducto(), agregarAlCarrito(), ajustarADisponible(), animarTarjeta(), avisoAgotado(), avisoInsuficiente(), cambiarCantidad() (+14 more)

### Community 7 - "Registro y Contrasena"
Cohesion: 0.22
Nodes (17): api(), aplicarIdioma(), CONTRASENAS_COMUNES, enviarCodigo(), evaluarFuerza(), iniciarEsperaReenvio(), limpiarMensaje(), mostrarAviso() (+9 more)

### Community 8 - "Gerentes Admin"
Cohesion: 0.13
Nodes (14): arbolHoja(), arbolIcono(), arbolRama(), arbolTexto(), BADGES_ESTADO, {closure#2}(), confirmarCambio(), EMPLEADOS_GERENTE (+6 more)

### Community 9 - "perfil.php"
Cohesion: 0.16
Nodes (20): idiomaGuardado(), perfilError(), perfilResponder(), {closure#3}(), menuPerfil(), perfilAvatar(), perfilCaja(), perfilConsultar() (+12 more)

### Community 10 - "Core Admin JS"
Cohesion: 0.31
Nodes (5): crearModalConfirmacion(), logoutConfirmado(), marcarCampoInvalido(), pedirConfirmacion(), tj()

### Community 11 - "paginas.js"
Cohesion: 0.18
Nodes (16): abrirModalEditarCaja(), abrirModalEditarCajero(), abrirModalEditarSolicitud(), abrirModalNuevaCaja(), abrirModalNuevoCajero(), abrirModalSolicitarProducto(), _actualizarAvisoReasignacion(), _actualizarAyudaEstado() (+8 more)

### Community 12 - "inventario.php"
Cohesion: 0.08
Nodes (29): e(), fecha(), i18nParaJs(), i18nReemplazar(), idiomaActual(), t(), tn(), tValor() (+21 more)

### Community 13 - "Dictionary"
Cohesion: 0.06
Nodes (13): config(), config_path(), Blasp (copia del núcleo), Lo de Koaly (no es de Blasp), Para actualizar, Dictionary, RegexMatcher, EnglishNormalizer (+5 more)

### Community 14 - "Login"
Cohesion: 0.23
Nodes (7): t(), buscarUsuarioPorCorreo(), {closure#1}(), error(), guardarContrasena(), motivoContrasena(), responder()

### Community 15 - "renderCatalogo"
Cohesion: 0.35
Nodes (11): agregarDesdeBusqueda(), agregarYLimpiar(), aplicarFiltros(), buscando(), cargarCatalogo(), coincideBusqueda(), filtrarCatalogo(), limpiarBusqueda() (+3 more)

### Community 17 - "componentes.js"
Cohesion: 0.23
Nodes (13): _aTexto(), _cerrarTodosLosSelects(), _diasEntre(), _fechaUTC(), initCustomSelect(), initCustomSelects(), initFiltros(), cerrar() (+5 more)

### Community 18 - "Imagenes"
Cohesion: 0.08
Nodes (23): Administrador General, Base de datos, Cajas, Cajero, Características de la Plataforma, Contacto, Descripción, 🇲🇽 Español. (+15 more)

### Community 19 - "Cajas Gerente"
Cohesion: 0.20
Nodes (7): {closure#2}(), {closure#3}(), {closure#4}(), {closure#5}(), inicialesCorta(), TITULO_FONDO, validarCajeroDeCaja()

### Community 20 - "formatoDinero"
Cohesion: 0.21
Nodes (17): abrirModalEfectivo(), actualizarCambioPreview(), calcularTotales(), crearTarjetaDinero(), denominacion(), escaparHtml(), fechaHoraCorta(), filasDesglose() (+9 more)

### Community 21 - "solicitudes_comun.php"
Cohesion: 0.17
Nodes (13): actualizarSolicitud(), contarSolicitudesParaAdmin(), crearProductoDesdeSolicitud(), diferenciasSolicitud(), guardarImagenProducto(), guardarImagenSolicitud(), historialSolicitudes(), leerFichaSolicitud() (+5 more)

### Community 22 - "perfil.js"
Cohesion: 0.24
Nodes (15): abrirMenu(), abrirPerfil(), avisar(), cargarNotificaciones(), cerrarMenu(), itemNotificacion(), mensajeLista(), mostrarNotificaciones() (+7 more)

### Community 24 - "profanity_filter.php"
Cohesion: 0.36
Nodes (10): blaspEncuentraProfano(), {closure#1}(), compactarTexto(), consultarProfanityFilter(), datosFiltro(), obtenerApiVerveKey(), rechazarSiProfano(), tieneGroseriaComun() (+2 more)

### Community 25 - "t"
Cohesion: 0.16
Nodes (18): csrfToken(), {closure#1}(), {closure#6}(), {closure#1}(), {closure#2}(), {closure#3}(), {closure#4}(), {closure#5}() (+10 more)

### Community 26 - "aviso.php"
Cohesion: 0.09
Nodes (25): _bloqueAcceso(), _correoContacto(), datosParaCorreos(), enviarAvisoCambioCorreo(), enviarAvisoCambioEstado(), enviarAvisoPasswordRestablecida(), enviarAvisoPrivacidad(), _firma() (+17 more)

### Community 27 - "ventas_sucursal.php"
Cohesion: 0.16
Nodes (6): tValor(), {closure#2}(), {closure#3}(), {closure#4}(), {closure#5}(), {closure#8}()

### Community 28 - "filtros.php"
Cohesion: 0.27
Nodes (7): atajosDePeriodo(), diasConActividad(), leerFechaFiltro(), leerPeriodo(), rangosDeActividad(), {closure#1}(), {closure#1}()

### Community 49 - "{closure#3}"
Cohesion: 0.31
Nodes (6): esCambioPrecio(), etiquetaTipo(), {closure#2}(), {closure#3}(), {closure#5}(), {closure#7}()

### Community 54 - "includes/productos.php"
Cohesion: 0.48
Nodes (5): agregarProductoAInventario(), badgeStock(), generarCodigoUnico(), leerStockParaActualizar(), registrarMovimiento()

### Community 61 - "reglas_contrasena.php"
Cohesion: 0.47
Nodes (3): generarPasswordTemporal(), passwordTemporalVencida(), ponerPasswordTemporal()

### Community 62 - "Result"
Cohesion: 0.12
Nodes (4): Cambios al código original, {closure#1}(), {closure#2}(), Result

### Community 82 - "PatternDriver.php"
Cohesion: 0.11
Nodes (7): Analyzer, DriverInterface, CharacterMask, {closure#3}(), PatternDriver, PhoneticDriver, PipelineDriver

### Community 83 - ".detect"
Cohesion: 0.21
Nodes (3): CompoundWordDetector, FalsePositiveFilter, RegexDriver

### Community 84 - "MatchedWord"
Cohesion: 0.17
Nodes (5): MatchedWord, Score, {closure#1}(), {closure#1}(), {closure#2}()

### Community 86 - "Severity"
Cohesion: 0.18
Nodes (5): Severity, Extreme, High, Mild, Moderate

### Community 87 - "Código de referencia (de la maqueta aprobada)"
Cohesion: 0.20
Nodes (9): Contexto, CSS, Código de referencia (de la maqueta aprobada), HTML del modal (cabecera con el botón nuevo), JS de construcción del árbol, Resultado esperado, Tarea: mostrar los empleados del gerente como árbol de archivos (Koaly), Verificación (+1 more)

### Community 89 - "NombreInvalido"
Cohesion: 0.53
Nodes (3): NombreInvalido, normalizarNombrePersona(), normalizarParteNombre()

## Knowledge Gaps
- **4 isolated node(s):** `Mild`, `Moderate`, `High`, `Extreme`
  These have ≤1 connection - possible missing edges or undocumented components. (Counts symbols only; 258 node(s) total have ≤1 connection when file, concept and rationale nodes are included.)
- **65 thin communities (<3 nodes) omitted from report** — run `graphify query` to explore isolated nodes.

## Suggested Questions
_Questions this graph is uniquely positioned to answer:_

- **Why does `{closure#5}()` connect `ventas_sucursal.php` to `MaskStrategyInterface`?**
  _High betweenness centrality (0.075) - this node is a cross-community bridge._
- **Are the 26 inferred relationships involving `t()` (e.g. with `{closure#1}()` and `{closure#2}()`) actually correct?**
  _`t()` has 26 INFERRED edges - model-reasoned connections that need verification._
- **What connects `Mild`, `Moderate`, `High` to the rest of the system?**
  _4 weakly-connected nodes found - possible documentation gaps or missing edges._
- **Should `Cajero Index UI` be split into smaller, more focused modules?**
  _Cohesion score 0.07226890756302522 - nodes in this community are weakly interconnected._
- **Why does `t()` connect `t` to `gerente/includes/i18n.php`, `t`, `{closure#3}`, `Cajas Gerente`, `includes/productos.php`, `ventas_sucursal.php`, `filtros.php`?**
  _High betweenness centrality (0.045) - this node is a cross-community bridge._
- **Should `Caja Backend` be split into smaller, more focused modules?**
  _Cohesion score 0.11076923076923077 - nodes in this community are weakly interconnected._
- **Why does `{closure#7}()` connect `gerente/includes/i18n.php` to `t`, `ventas_sucursal.php`?**
  _High betweenness centrality (0.042) - this node is a cross-community bridge._