# Grafo de conocimiento del proyecto Koaly (formato para IA)

836 nodos, 1591 relaciones, 102 comunidades. Generado con graphify desde el código (AST). Rutas relativas a la raíz del repo.

## Nodos más conectados

- cajero/index.php (cajero/index.php L1) — 151 conexiones
- tr() (cajero/index.php L478) — 56 conexiones
- Result (admin/lib/blasp/src/Core/Result.php L10) — 37 conexiones
- Dictionary (admin/lib/blasp/src/Core/Dictionary.php L14) — 32 conexiones
- formatoDinero() (cajero/index.php L506) — 30 conexiones
- t() (gerente/includes/i18n.php L37) — 29 conexiones
- paginas.js (gerente/js/paginas.js L1) — 21 conexiones
- Severity (admin/lib/blasp/src/Enums/Severity.php L5) — 21 conexiones
- .detect() (admin/lib/blasp/src/Drivers/RegexDriver.php L20) — 20 conexiones
- MatchedWord (admin/lib/blasp/src/Core/MatchedWord.php L8) — 19 conexiones
- renderCarrito() (cajero/index.php L1316) — 19 conexiones
- gerentes.php (admin/gerentes.php L1) — 19 conexiones
- login.js (login/login.js L1) — 19 conexiones
- MaskStrategyInterface (admin/lib/blasp/src/Core/Contracts/MaskStrategyInterface.php L5) — 19 conexiones
- perfil.php (perfil/perfil.php L1) — 19 conexiones
- .detect() (admin/lib/blasp/src/Drivers/PhoneticDriver.php L25) — 18 conexiones
- cerrarModal() (cajero/index.php L514) — 17 conexiones
- inventario.php (admin/inventario.php L1) — 16 conexiones
- solicitudes_comun.php (admin/solicitudes_comun.php L1) — 16 conexiones
- caja.php (cajero/includes/caja.php L1) — 16 conexiones
- .count() (admin/lib/blasp/src/Core/Result.php L51) — 15 conexiones
- perfil.js (perfil/perfil.js L1) — 15 conexiones
- e() (gerente/includes/i18n.php L98) — 15 conexiones
- abrirModal() (cajero/index.php L510) — 14 conexiones
- mostrarAviso() (cajero/index.php L1595) — 14 conexiones

## Relaciones entre archivos (origen → destino : tipo, cantidad)

- gerente/cajas.php → gerente/includes/i18n.php : calls ×14
- gerente/cajeros.php → gerente/includes/i18n.php : calls ×13
- gerente/ventas_sucursal.php → gerente/includes/i18n.php : calls ×12
- gerente/movimientos.php → gerente/includes/i18n.php : calls ×9
- admin/lib/blasp/src/Drivers/RegexDriver.php → admin/lib/blasp/src/Core/Matchers/FalsePositiveFilter.php : calls ×5
- admin/inventario.php → admin/i18n.php : calls ×5
- admin/lib/blasp/src/Drivers/PhoneticDriver.php → admin/lib/blasp/src/Core/Dictionary.php : calls ×5
- admin/lib/blasp/src/Drivers/RegexDriver.php → admin/lib/blasp/src/Core/Dictionary.php : calls ×5
- gerente/includes/correos_empleado.php → privacidad/aviso.php : calls ×5
- gerente/includes/productos.php → gerente/includes/i18n.php : calls ×5
- gerente/includes/vistas.php → gerente/includes/i18n.php : calls ×5
- gerente/movimientos.php → gerente/includes/productos.php : calls ×5
- admin/lib/blasp/src/Core/Dictionary.php → admin/lib/blasp/src/Core/Normalizers/StringNormalizer.php : references ×4
- admin/lib/blasp/src/Core/Dictionary.php → admin/lib/blasp/arranque.php : calls ×4
- admin/lib/blasp/src/Drivers/PatternDriver.php → admin/lib/blasp/src/Core/Dictionary.php : calls ×4
- gerente/includes/correos_empleado.php → login/correo.php : calls ×4
- gerente/productos.php → gerente/includes/i18n.php : calls ×4
- admin/lib/blasp/src/Drivers/PhoneticDriver.php → admin/lib/blasp/src/Core/Matchers/FalsePositiveFilter.php : calls ×3
- admin/lib/blasp/src/Core/Dictionary.php → admin/lib/blasp/src/Enums/Severity.php : references_constant ×3
- admin/ventas.php → admin/i18n.php : calls ×3
- gerente/cajeros.php → gerente/includes/vistas.php : calls ×3
- gerente/includes/filtros.php → gerente/includes/i18n.php : calls ×3
- admin/lib/blasp/src/Core/Dictionary.php → admin/lib/blasp/src/Core/Normalizers/EnglishNormalizer.php : calls ×2
- admin/lib/blasp/src/Drivers/PhoneticDriver.php → admin/lib/blasp/src/Core/Matchers/PhoneticMatcher.php : calls ×2
- admin/lib/blasp/src/Drivers/RegexDriver.php → admin/lib/blasp/src/Core/Matchers/CompoundWordDetector.php : calls ×2
- admin/lib/blasp/src/Core/MatchedWord.php → admin/lib/blasp/src/Enums/Severity.php : references ×2
- admin/lib/blasp/src/Core/Result.php → admin/lib/blasp/src/Core/MatchedWord.php : references ×2
- admin/lib/blasp/src/Core/Result.php → admin/lib/blasp/src/Enums/Severity.php : references_constant ×2
- admin/profanity_filter.php → admin/lib/blasp/src/Core/Analyzer.php : calls ×2
- admin/solicitudes_comun.php → admin/lib/blasp/src/Core/Result.php : calls ×2
- cajero/includes/caja.php → cajero/includes/i18n.php : calls ×2
- gerente/cajas.php → gerente/includes/vistas.php : calls ×2
- gerente/cajeros.php → admin/csrf.php : calls ×2
- login/login.php → login/i18n.php : calls ×2
- privacidad/aviso.php → privacidad/aviso_en.php : calls ×2
- admin/lib/blasp/ORIGEN.md → admin/lib/blasp/arranque.php : references ×2
- admin/lib/blasp/src/Core/Analyzer.php → admin/lib/blasp/src/Core/Masking/CharacterMask.php : calls ×1
- admin/lib/blasp/src/Core/Dictionary.php → admin/lib/blasp/src/Core/Matchers/RegexMatcher.php : calls ×1
- admin/lib/blasp/src/Core/Dictionary.php → admin/lib/blasp/src/Core/Normalizers/SpanishNormalizer.php : calls ×1
- admin/lib/blasp/src/Core/Dictionary.php → admin/lib/blasp/src/Core/Normalizers/GermanNormalizer.php : calls ×1
- admin/lib/blasp/src/Core/Dictionary.php → admin/lib/blasp/src/Core/Normalizers/FrenchNormalizer.php : calls ×1
- admin/lib/blasp/src/Core/Result.php → admin/lib/blasp/src/Core/Score.php : calls ×1
- admin/lib/blasp/src/Drivers/PatternDriver.php → admin/lib/blasp/src/Core/MatchedWord.php : calls ×1
- admin/lib/blasp/src/Drivers/PatternDriver.php → admin/lib/blasp/src/Core/Score.php : calls ×1
- admin/lib/blasp/src/Drivers/PhoneticDriver.php → admin/lib/blasp/src/Core/Score.php : calls ×1
- admin/lib/blasp/src/Drivers/PhoneticDriver.php → admin/lib/blasp/src/Core/MatchedWord.php : calls ×1
- admin/lib/blasp/src/Drivers/PipelineDriver.php → admin/lib/blasp/src/Core/Score.php : calls ×1
- admin/lib/blasp/src/Drivers/RegexDriver.php → admin/lib/blasp/src/Core/MatchedWord.php : calls ×1
- admin/lib/blasp/src/Drivers/RegexDriver.php → admin/lib/blasp/src/Core/Score.php : calls ×1
- admin/profanity_filter.php → admin/lib/blasp/src/Core/Dictionary.php : calls ×1
- admin/lib/blasp/src/Drivers/RegexDriver.php → admin/lib/blasp/src/Core/Matchers/FalsePositiveFilter.php : references ×1
- admin/lib/blasp/src/Drivers/RegexDriver.php → admin/lib/blasp/src/Core/Matchers/CompoundWordDetector.php : references ×1
- admin/lib/blasp/src/Core/Analyzer.php → admin/lib/blasp/src/Core/Contracts/DriverInterface.php : imports ×1
- admin/lib/blasp/src/Core/Analyzer.php → admin/lib/blasp/src/Core/Contracts/MaskStrategyInterface.php : imports ×1
- admin/lib/blasp/src/Core/Analyzer.php → admin/lib/blasp/src/Core/Masking/CharacterMask.php : imports ×1
- admin/lib/blasp/src/Core/Contracts/DriverInterface.php → admin/lib/blasp/src/Core/Dictionary.php : imports ×1
- admin/lib/blasp/src/Core/Contracts/DriverInterface.php → admin/lib/blasp/src/Core/Result.php : imports ×1
- admin/lib/blasp/src/Core/Dictionary.php → admin/lib/blasp/src/Core/Normalizers/GermanNormalizer.php : imports ×1
- admin/lib/blasp/src/Core/Dictionary.php → admin/lib/blasp/src/Core/Normalizers/FrenchNormalizer.php : imports ×1
- admin/lib/blasp/src/Core/Dictionary.php → admin/lib/blasp/src/Enums/Severity.php : imports ×1
- admin/lib/blasp/src/Core/Dictionary.php → admin/lib/blasp/src/Core/Matchers/RegexMatcher.php : imports ×1
- admin/lib/blasp/src/Core/Dictionary.php → admin/lib/blasp/src/Core/Normalizers/StringNormalizer.php : imports ×1
- admin/lib/blasp/src/Core/Dictionary.php → admin/lib/blasp/src/Core/Normalizers/EnglishNormalizer.php : imports ×1
- admin/lib/blasp/src/Core/Dictionary.php → admin/lib/blasp/src/Core/Normalizers/SpanishNormalizer.php : imports ×1
- admin/lib/blasp/src/Core/Masking/CallbackMask.php → admin/lib/blasp/src/Core/Contracts/MaskStrategyInterface.php : imports ×1
- admin/lib/blasp/src/Core/Masking/CharacterMask.php → admin/lib/blasp/src/Core/Contracts/MaskStrategyInterface.php : imports ×1
- admin/lib/blasp/src/Core/Masking/GrawlixMask.php → admin/lib/blasp/src/Core/Contracts/MaskStrategyInterface.php : imports ×1
- admin/lib/blasp/src/Core/MatchedWord.php → admin/lib/blasp/src/Enums/Severity.php : imports ×1
- admin/lib/blasp/src/Core/Result.php → admin/lib/blasp/src/Enums/Severity.php : imports ×1
- admin/lib/blasp/src/Drivers/PatternDriver.php → admin/lib/blasp/src/Core/Score.php : imports ×1
- admin/lib/blasp/src/Drivers/PatternDriver.php → admin/lib/blasp/src/Enums/Severity.php : imports ×1
- admin/lib/blasp/src/Drivers/PatternDriver.php → admin/lib/blasp/src/Core/Contracts/DriverInterface.php : imports ×1
- admin/lib/blasp/src/Drivers/PatternDriver.php → admin/lib/blasp/src/Core/Contracts/MaskStrategyInterface.php : imports ×1
- admin/lib/blasp/src/Drivers/PatternDriver.php → admin/lib/blasp/src/Core/Dictionary.php : imports ×1
- admin/lib/blasp/src/Drivers/PatternDriver.php → admin/lib/blasp/src/Core/MatchedWord.php : imports ×1
- admin/lib/blasp/src/Drivers/PatternDriver.php → admin/lib/blasp/src/Core/Result.php : imports ×1
- admin/lib/blasp/src/Drivers/PhoneticDriver.php → admin/lib/blasp/src/Core/Score.php : imports ×1
- admin/lib/blasp/src/Drivers/PhoneticDriver.php → admin/lib/blasp/src/Core/Matchers/FalsePositiveFilter.php : imports ×1
- admin/lib/blasp/src/Drivers/PhoneticDriver.php → admin/lib/blasp/src/Core/Matchers/PhoneticMatcher.php : imports ×1
- admin/lib/blasp/src/Drivers/PhoneticDriver.php → admin/lib/blasp/src/Enums/Severity.php : imports ×1
- admin/lib/blasp/src/Drivers/PhoneticDriver.php → admin/lib/blasp/src/Core/Contracts/DriverInterface.php : imports ×1
- admin/lib/blasp/src/Drivers/PhoneticDriver.php → admin/lib/blasp/src/Core/Contracts/MaskStrategyInterface.php : imports ×1
- admin/lib/blasp/src/Drivers/PhoneticDriver.php → admin/lib/blasp/src/Core/Dictionary.php : imports ×1
- admin/lib/blasp/src/Drivers/PhoneticDriver.php → admin/lib/blasp/src/Core/MatchedWord.php : imports ×1
- admin/lib/blasp/src/Drivers/PhoneticDriver.php → admin/lib/blasp/src/Core/Result.php : imports ×1
- admin/lib/blasp/src/Drivers/PipelineDriver.php → admin/lib/blasp/src/Core/Score.php : imports ×1
- admin/lib/blasp/src/Drivers/PipelineDriver.php → admin/lib/blasp/src/Core/Contracts/DriverInterface.php : imports ×1
- admin/lib/blasp/src/Drivers/PipelineDriver.php → admin/lib/blasp/src/Core/Contracts/MaskStrategyInterface.php : imports ×1
- admin/lib/blasp/src/Drivers/PipelineDriver.php → admin/lib/blasp/src/Core/Dictionary.php : imports ×1
- admin/lib/blasp/src/Drivers/PipelineDriver.php → admin/lib/blasp/src/Core/MatchedWord.php : imports ×1
- admin/lib/blasp/src/Drivers/PipelineDriver.php → admin/lib/blasp/src/Core/Result.php : imports ×1
- admin/lib/blasp/src/Drivers/RegexDriver.php → admin/lib/blasp/src/Core/Score.php : imports ×1
- admin/lib/blasp/src/Drivers/RegexDriver.php → admin/lib/blasp/src/Core/Matchers/FalsePositiveFilter.php : imports ×1
- admin/lib/blasp/src/Drivers/RegexDriver.php → admin/lib/blasp/src/Core/Matchers/CompoundWordDetector.php : imports ×1
- admin/lib/blasp/src/Drivers/RegexDriver.php → admin/lib/blasp/src/Enums/Severity.php : imports ×1
- admin/lib/blasp/src/Drivers/RegexDriver.php → admin/lib/blasp/src/Core/Contracts/DriverInterface.php : imports ×1
- admin/lib/blasp/src/Drivers/RegexDriver.php → admin/lib/blasp/src/Core/Contracts/MaskStrategyInterface.php : imports ×1
- admin/lib/blasp/src/Drivers/RegexDriver.php → admin/lib/blasp/src/Core/Dictionary.php : imports ×1
- admin/lib/blasp/src/Drivers/RegexDriver.php → admin/lib/blasp/src/Core/MatchedWord.php : imports ×1
- admin/lib/blasp/src/Drivers/RegexDriver.php → admin/lib/blasp/src/Core/Result.php : imports ×1
- admin/lib/blasp/src/Core/Analyzer.php → admin/lib/blasp/src/Core/Contracts/DriverInterface.php : references ×1
- admin/lib/blasp/src/Core/Analyzer.php → admin/lib/blasp/src/Core/Contracts/MaskStrategyInterface.php : references ×1
- admin/lib/blasp/src/Core/Analyzer.php → admin/lib/blasp/src/Core/Dictionary.php : references ×1
- admin/lib/blasp/src/Core/Contracts/DriverInterface.php → admin/lib/blasp/src/Core/Contracts/MaskStrategyInterface.php : references ×1
- admin/lib/blasp/src/Core/Contracts/DriverInterface.php → admin/lib/blasp/src/Core/Dictionary.php : references ×1
- admin/lib/blasp/src/Drivers/PatternDriver.php → admin/lib/blasp/src/Core/MatchedWord.php : references ×1
- admin/lib/blasp/src/Drivers/PatternDriver.php → admin/lib/blasp/src/Core/Contracts/MaskStrategyInterface.php : references ×1
- admin/lib/blasp/src/Drivers/PatternDriver.php → admin/lib/blasp/src/Core/Dictionary.php : references ×1
- admin/lib/blasp/src/Drivers/PhoneticDriver.php → admin/lib/blasp/src/Core/MatchedWord.php : references ×1
- admin/lib/blasp/src/Drivers/PhoneticDriver.php → admin/lib/blasp/src/Core/Contracts/MaskStrategyInterface.php : references ×1
- admin/lib/blasp/src/Drivers/PhoneticDriver.php → admin/lib/blasp/src/Core/Dictionary.php : references ×1
- admin/lib/blasp/src/Drivers/PipelineDriver.php → admin/lib/blasp/src/Core/MatchedWord.php : references ×1
- admin/lib/blasp/src/Drivers/PipelineDriver.php → admin/lib/blasp/src/Core/Contracts/MaskStrategyInterface.php : references ×1
- admin/lib/blasp/src/Drivers/PipelineDriver.php → admin/lib/blasp/src/Core/Dictionary.php : references ×1
- admin/lib/blasp/src/Drivers/RegexDriver.php → admin/lib/blasp/src/Core/MatchedWord.php : references ×1
- admin/lib/blasp/src/Drivers/RegexDriver.php → admin/lib/blasp/src/Core/Contracts/MaskStrategyInterface.php : references ×1
- admin/lib/blasp/src/Drivers/RegexDriver.php → admin/lib/blasp/src/Core/Dictionary.php : references ×1
- admin/lib/blasp/src/Core/Analyzer.php → admin/lib/blasp/src/Core/Result.php : references ×1
- admin/lib/blasp/src/Core/Contracts/DriverInterface.php → admin/lib/blasp/src/Core/Result.php : references ×1
- admin/lib/blasp/src/Core/Result.php → admin/lib/blasp/src/Enums/Severity.php : references ×1
- admin/lib/blasp/src/Drivers/PatternDriver.php → admin/lib/blasp/src/Core/Result.php : references ×1
- admin/lib/blasp/src/Drivers/PhoneticDriver.php → admin/lib/blasp/src/Core/Result.php : references ×1
- admin/lib/blasp/src/Drivers/PipelineDriver.php → admin/lib/blasp/src/Core/Result.php : references ×1
- admin/lib/blasp/src/Drivers/RegexDriver.php → admin/lib/blasp/src/Core/Result.php : references ×1
- admin/lib/blasp/src/Core/Masking/CallbackMask.php → admin/lib/blasp/src/Core/Contracts/MaskStrategyInterface.php : implements ×1
- admin/lib/blasp/src/Core/Masking/CharacterMask.php → admin/lib/blasp/src/Core/Contracts/MaskStrategyInterface.php : implements ×1
- admin/lib/blasp/src/Core/Masking/GrawlixMask.php → admin/lib/blasp/src/Core/Contracts/MaskStrategyInterface.php : implements ×1
- admin/lib/blasp/src/Core/Normalizers/EnglishNormalizer.php → admin/lib/blasp/src/Core/Normalizers/StringNormalizer.php : implements ×1
- admin/lib/blasp/src/Core/Normalizers/FrenchNormalizer.php → admin/lib/blasp/src/Core/Normalizers/StringNormalizer.php : implements ×1
- admin/lib/blasp/src/Core/Normalizers/GermanNormalizer.php → admin/lib/blasp/src/Core/Normalizers/StringNormalizer.php : implements ×1
- admin/lib/blasp/src/Core/Normalizers/NullNormalizer.php → admin/lib/blasp/src/Core/Normalizers/StringNormalizer.php : implements ×1
- admin/lib/blasp/src/Core/Normalizers/SpanishNormalizer.php → admin/lib/blasp/src/Core/Normalizers/StringNormalizer.php : implements ×1
- admin/lib/blasp/src/Drivers/PatternDriver.php → admin/lib/blasp/src/Core/Contracts/DriverInterface.php : implements ×1
- admin/lib/blasp/src/Drivers/PhoneticDriver.php → admin/lib/blasp/src/Core/Contracts/DriverInterface.php : implements ×1
- admin/lib/blasp/src/Drivers/PipelineDriver.php → admin/lib/blasp/src/Core/Contracts/DriverInterface.php : implements ×1
- admin/lib/blasp/src/Drivers/RegexDriver.php → admin/lib/blasp/src/Core/Contracts/DriverInterface.php : implements ×1
- admin/gerentes.php → admin/i18n.php : calls ×1
- admin/lib/blasp/src/Core/Analyzer.php → admin/lib/blasp/arranque.php : calls ×1
- admin/lib/blasp/src/Core/Analyzer.php → admin/lib/blasp/src/Core/Contracts/DriverInterface.php : calls ×1
- admin/lib/blasp/src/Core/Masking/GrawlixMask.php → admin/lib/blasp/src/Core/Result.php : calls ×1
- admin/lib/blasp/src/Core/Matchers/FalsePositiveFilter.php → admin/lib/blasp/src/Core/Result.php : calls ×1
- admin/lib/blasp/src/Core/Score.php → admin/lib/blasp/src/Core/Result.php : calls ×1
- admin/lib/blasp/src/Drivers/PatternDriver.php → admin/lib/blasp/src/Core/Contracts/MaskStrategyInterface.php : calls ×1
- admin/lib/blasp/src/Drivers/PatternDriver.php → admin/lib/blasp/src/Core/Result.php : calls ×1
- admin/lib/blasp/src/Drivers/PhoneticDriver.php → admin/lib/blasp/src/Core/Contracts/MaskStrategyInterface.php : calls ×1
- admin/lib/blasp/src/Drivers/PhoneticDriver.php → admin/lib/blasp/src/Core/Result.php : calls ×1
- admin/lib/blasp/src/Drivers/PipelineDriver.php → admin/lib/blasp/src/Core/Contracts/MaskStrategyInterface.php : calls ×1
- admin/lib/blasp/src/Drivers/PipelineDriver.php → admin/lib/blasp/src/Core/Result.php : calls ×1
- admin/lib/blasp/src/Drivers/RegexDriver.php → admin/lib/blasp/src/Core/Contracts/MaskStrategyInterface.php : calls ×1
- admin/lib/blasp/src/Drivers/RegexDriver.php → admin/lib/blasp/src/Core/Result.php : calls ×1

## Comunidades

### [13] Dictionary (59 nodos)
Archivos: admin/lib/blasp/ORIGEN.md, admin/lib/blasp/arranque.php, admin/lib/blasp/src/Core/Dictionary.php, admin/lib/blasp/src/Core/Matchers/RegexMatcher.php, admin/lib/blasp/src/Core/Normalizers/EnglishNormalizer.php, admin/lib/blasp/src/Core/Normalizers/FrenchNormalizer.php …
Símbolos: Dictionary, RegexMatcher, EnglishNormalizer, FrenchNormalizer, GermanNormalizer, NullNormalizer, SpanishNormalizer, StringNormalizer, {closure#1}(), config(), config_path(), {closure#1}(), .buildSeverityMap(), .clearCache(), .__construct(), .forAllLanguages(), .forLanguage(), .forLanguages(), .getAvailableLanguages(), .getCache(), .getNormalizer(), .getNormalizerForLanguage(), .getSeparators(), .getSubstitutions(), .loadGlobalConfig() …

### [12] inventario.php (41 nodos)
Archivos: admin/i18n.php, admin/inventario.php, admin/sucursal_gerente.php, admin/ventas.php
Símbolos: e(), fecha(), i18nParaJs(), i18nReemplazar(), idiomaActual(), t(), tn(), tValor(), abrirFicha(), cancelarEdicionFicha(), {closure#1}(), {closure#2}(), {closure#3}(), {closure#4}(), confirmarSolicitud(), detener(), editarFicha(), iniciar(), modoFicha(), obtenerInventario(), rechazarSolicitud(), refrescar(), renderFilasInventario(), urlActual(), asignarSucursalAGerente() …

### [26] aviso.php (39 nodos)
Archivos: gerente/includes/correos_empleado.php, login/correo.php, privacidad/aviso.php, privacidad/aviso_en.php, privacidad/index.php
Símbolos: _bloqueAcceso(), _correoContacto(), datosParaCorreos(), enviarAvisoCambioCorreo(), enviarAvisoCambioEstado(), enviarAvisoPasswordRestablecida(), enviarAvisoPrivacidad(), _firma(), {closure#1}(), configCorreo(), enviarCorreo(), smtpEnviar(), avisoAceptado(), cambiosAvisoPrivacidad(), {closure#1}(), crearEnlaceAviso(), cambiosAvisoPrivacidadEn(), seccionesAvisoPrivacidadEn(), enviarConstanciaAviso(), fechaVersionAviso(), folioAviso(), seccionesAvisoPrivacidad(), tAviso(), textoPlanoAviso(), urlSitio() …

### [1] Cajero Index UI (35 nodos)
Archivos: cajero/index.php
Símbolos: aceptarCorte(), filaResumen(), formatearMiles(), htmlTicket(), imprimirArqueo(), imprimirCorte(), imprimirHtml(), imprimirRetiro(), imprimirTicket(), initCustomCursor(), logout(), mostrarResultadoCorte(), nombreMetodo(), puedeCobrarVarios(), sinEscape(), cajero/index.php, carrito, catalogo, categorias, conteoCorte, DENOMINACIONES, FORMATO_PESOS, ICONOS_AVISO, inputBuscar, inputDejado …

### [9] perfil.php (27 nodos)
Archivos: admin/preferencia_idioma.php, perfil/api.php, perfil/perfil.php
Símbolos: guardarIdioma(), idiomaGuardado(), perfilError(), perfilResponder(), {closure#1}(), {closure#2}(), {closure#3}(), menuPerfil(), perfilAvatar(), perfilCaja(), perfilConsultar(), perfilDinero(), perfilE(), perfilFotoUrl(), perfilIdioma(), perfilIniciales(), perfilNotificaciones(), perfilParaJs(), perfilResultado(), perfilT(), perfilTextos(), perfilTonoCorte(), perfilUsuario(), preferencia_idioma.php, api.php …

### [2] Caja Backend (26 nodos)
Archivos: cajero/includes/caja.php, cajero/includes/i18n.php
Símbolos: CajaCerradaException, SinCambioException, arqueoEnPesos(), arqueoTurno(), bdACentavos(), cajeroConCaja(), centavosABd(), {closure#1}(), leerDesglose(), montoEnCentavos(), nombreCaja(), retirosDelTurno(), retiroSugerido(), turnoAbierto(), ultimoTurnoCerrado(), usarHoraLocal(), dinero(), e(), i18nReemplazar(), idiomaActual(), t(), textosCajaParaJs(), caja.php, PDO, cajero/includes/i18n.php …

### [62] Result (25 nodos)
Archivos: admin/lib/blasp/ORIGEN.md, admin/lib/blasp/src/Core/Result.php
Símbolos: Result, {closure#1}(), {closure#2}(), .clean(), .__construct(), .getCleanString(), .getProfanitiesCount(), .getSourceString(), .getUniqueProfanitiesFound(), .hasProfanity(), .isClean(), .isOffensive(), .jsonSerialize(), .original(), .score(), .severity(), .toArray(), .toJson(), .__toString(), .uniqueWords(), .words(), Result.php, Countable, Stringable, Cambios al código original

### [25] t (24 nodos)
Archivos: admin/csrf.php, gerente/cajas.php, gerente/cajeros.php, gerente/includes/i18n.php, gerente/includes/vistas.php
Símbolos: csrfToken(), csrfValido(), {closure#1}(), {closure#6}(), {closure#1}(), {closure#2}(), {closure#3}(), {closure#4}(), {closure#5}(), {closure#6}(), iniciales(), liberarCajasDeCajero(), puedeCambiarCorreo(), e(), t(), antiguedad(), datoDetalle(), duracionTurno(), piezaAhora(), selectorVistas(), csrf.php, cajeros.php, PDO, vistas.php

### [18] Imagenes (24 nodos)
Archivos: README.md
Símbolos: README.md, Administrador General, Base de datos, Cajas, Cajero, Características de la Plataforma, Contacto, Descripción, 🇲🇽 Español., Función de agregar nuevo producto, Gerente, Gestión de inventario, Gestión de sucursales, Global inventory, Idiomas, Imagenes, 🇺🇸 Inglés., Inicio de sesion, Koaly, Link de la pagina, Movimientos de inventario, Panel de sucursal, Punto de Venta, Roles del sistema

### [3] Turnos y Corte (23 nodos)
Archivos: cajero/index.php
Símbolos: abrirArqueo(), actualizarCatalogo(), aplicarIdioma(), avisarExcedente(), cargarTurno(), crearSeccion(), disponiblesDe(), estaAgotado(), finalizarVenta(), guardarEstadoTurno(), hayExcedente(), mensajeCatalogo(), modalSuperior(), nombreCategoria(), pintarArqueo(), pintarAyudaLista(), pintarEstadoCaja(), pintarExcedente(), refrescarArqueo(), renderCatalogo(), renderFiltroCategorias(), textoExcedente(), tr()

### [5] aCentavos (23 nodos)
Archivos: cajero/index.php
Símbolos: abrirApertura(), abrirRetiro(), aCentavos(), actualizarAvisoConteo(), actualizarEntrega(), actualizarTotalCorte(), actualizarVariosPreview(), autollenarTarjeta(), cargarPlantillaFondo(), centavosDe(), centavosDeTexto(), confirmarCierre(), confirmarRetiro(), contarArqueo(), continuarCierre(), limpiarConteo(), montosVarios(), mostrarCorte(), pedirLimpiarCorte(), pedirPlantillaFondo(), renderCorte(), sumarCorte(), totalPlantillaFondo()

### [11] paginas.js (22 nodos)
Archivos: gerente/js/paginas.js
Símbolos: abrirModalAgregarInventario(), abrirModalBaja(), abrirModalCancelarSolicitud(), abrirModalEditarCaja(), abrirModalEditarCajero(), abrirModalEditarSolicitud(), abrirModalNuevaCaja(), abrirModalNuevoCajero(), abrirModalSolicitarProducto(), _actualizarAvisoReasignacion(), _actualizarAyudaEstado(), _bloquearCorreo(), initFiltrosProductos(), initFormularioSolicitud(), initScrollInfinito(), modoFormularioSolicitud(), mostrarCamposProveedor(), mostrarVistaPreviaSolicitud(), prepararFormularioSolicitud(), _prepararModalCajero(), validarContactoProveedor(), paginas.js

### [6] Carrito y Catalogo (22 nodos)
Archivos: cajero/index.php
Símbolos: abrirConfirmacion(), actualizarStockProducto(), agregarAlCarrito(), ajustarADisponible(), animarTarjeta(), avisoAgotado(), avisoInsuficiente(), cambiarCantidad(), cantidadDeProducto(), crearBotonCantidad(), crearTarjeta(), etiquetaExistencias(), limpiarTabla(), mostrarToast(), pedirEliminarProducto(), quitarFila(), quitarProducto(), renderCarrito(), restarUno(), seleccionarFila(), trn(), trReemplazar()

### [82] PatternDriver.php (21 nodos)
Archivos: admin/lib/blasp/src/Core/Analyzer.php, admin/lib/blasp/src/Core/Contracts/DriverInterface.php, admin/lib/blasp/src/Core/Masking/CharacterMask.php, admin/lib/blasp/src/Drivers/PatternDriver.php, admin/lib/blasp/src/Drivers/PhoneticDriver.php, admin/lib/blasp/src/Drivers/PipelineDriver.php
Símbolos: Analyzer, DriverInterface, CharacterMask, PatternDriver, PhoneticDriver, PipelineDriver, .analyze(), .detect(), .__construct(), .mask(), {closure#1}(), {closure#2}(), {closure#3}(), {closure#4}(), {closure#5}(), .__construct(), .__construct(), Analyzer.php, DriverInterface.php, CharacterMask.php, PatternDriver.php

### [4] Modales de Pago (21 nodos)
Archivos: cajero/index.php
Símbolos: abrirCorte(), abrirLista(), abrirModal(), abrirModalPago(), abrirModalVarios(), actualizarExistencias(), agruparCarrito(), avisoFaltantes(), bloquearCobro(), cerrarModal(), cobrarEfectivo(), cobrarVarios(), confirmarApertura(), confirmarEliminar(), corteParaSalir(), mostrarAviso(), mostrarVentaCompletada(), pagarConTarjeta(), pedirSalir(), registrarVenta(), volverAMetodos()

### [8] Gerentes Admin (21 nodos)
Archivos: admin/gerentes.php
Símbolos: arbolHoja(), arbolIcono(), arbolRama(), arbolTexto(), asignarSucursal(), cerrar(), {closure#1}(), {closure#2}(), confirmarCambio(), editarGerente(), parrafo(), registrarCambioEstado(), verEmpleados(), verHistorial(), gerentes.php, BADGES_ESTADO, EMPLEADOS_GERENTE, HISTORIAL_ESTADOS, ICONOS_ARBOL, NOMBRES_ESTADO, PDO

### [7] Registro y Contrasena (20 nodos)
Archivos: login/login.js
Símbolos: animarKoala(), api(), aplicarIdioma(), correoValido(), enviarCodigo(), evaluarFuerza(), iniciarEsperaReenvio(), limpiarMensaje(), mostrarAviso(), mostrarMensaje(), mostrarVista(), ocupado(), pintarBotonTema(), problemaContrasena(), tl(), validarNueva(), verContrasena(), vigilarContrasena(), CONTRASENAS_COMUNES, login.js

### [21] solicitudes_comun.php (18 nodos)
Archivos: admin/solicitudes_comun.php
Símbolos: actualizarSolicitud(), borrarImagenSolicitud(), contarSolicitudesParaAdmin(), crearProductoDesdeSolicitud(), diferenciasSolicitud(), guardarImagenProducto(), guardarImagenSolicitud(), historialSolicitudes(), leerFichaSolicitud(), leerProveedorSolicitud(), leerSolicitudBloqueada(), marcarSolicitudAprobada(), nivelAvisoSolicitudes(), registrarHistorialSolicitud(), ultimaEdicionSolicitud(), valorComparableSolicitud(), solicitudes_comun.php, PDO

### [83] .detect (17 nodos)
Archivos: admin/lib/blasp/src/Core/Dictionary.php, admin/lib/blasp/src/Core/Matchers/CompoundWordDetector.php, admin/lib/blasp/src/Core/Matchers/FalsePositiveFilter.php, admin/lib/blasp/src/Drivers/PhoneticDriver.php, admin/lib/blasp/src/Drivers/RegexDriver.php
Símbolos: CompoundWordDetector, FalsePositiveFilter, RegexDriver, .getFalsePositives(), .getLanguage(), .getProfanityExpressions(), .getSeverity(), .isPureAlphaSubstring(), .__construct(), .getFullWordContext(), .isFalsePositive(), .isInsideHexToken(), .isSpanningWordBoundary(), .detect(), .detect(), CompoundWordDetector.php, FalsePositiveFilter.php

### [84] MatchedWord (17 nodos)
Archivos: admin/lib/blasp/src/Core/MatchedWord.php, admin/lib/blasp/src/Core/Score.php, admin/lib/blasp/src/Drivers/PhoneticDriver.php, admin/lib/blasp/src/Drivers/PipelineDriver.php, admin/lib/blasp/src/Drivers/RegexDriver.php
Símbolos: MatchedWord, Score, .__construct(), .jsonSerialize(), .toArray(), {closure#1}(), {closure#2}(), {closure#1}(), {closure#1}(), {closure#2}(), {closure#3}(), MatchedWord.php, Score.php, PhoneticDriver.php, PipelineDriver.php, RegexDriver.php, JsonSerializable

### [20] formatoDinero (17 nodos)
Archivos: cajero/index.php
Símbolos: abrirModalEfectivo(), actualizarCambioPreview(), calcularTotales(), crearTarjetaDinero(), denominacion(), escaparHtml(), fechaHoraCorta(), filasDesglose(), filaTicket(), formatoDinero(), htmlTicketArqueo(), htmlTicketCorte(), htmlTicketRetiro(), lineaResumen(), renderResumen(), textoRetiro(), textoTurnoAnterior()

### [17] componentes.js (16 nodos)
Archivos: gerente/js/componentes.js
Símbolos: _aTexto(), _cerrarTodosLosSelects(), _diasEntre(), _fechaUTC(), initCustomSelect(), initCustomSelects(), initFilasDesplegables(), initFiltros(), cerrar(), initVistas(), _maxF(), _minF(), _sumarDias(), componentes.js, _selectCustomActivos, VISTAS_VALIDAS

### [22] perfil.js (16 nodos)
Archivos: perfil/perfil.js
Símbolos: abrirMenu(), abrirPerfil(), avisar(), cargarNotificaciones(), cerrarMenu(), itemNotificacion(), mensajeLista(), mostrarNotificaciones(), pintarAvatar(), pintarContador(), pintarTema(), pt(), temaActual(), verContrasena(), verVista(), perfil.js

### [27] ventas_sucursal.php (14 nodos)
Archivos: gerente/includes/i18n.php, gerente/ventas_sucursal.php
Símbolos: tValor(), {closure#10}(), {closure#11}(), {closure#12}(), {closure#13}(), {closure#14}(), {closure#2}(), {closure#3}(), {closure#4}(), {closure#5}(), {closure#6}(), {closure#8}(), {closure#9}(), ventas_sucursal.php

### [85] MaskStrategyInterface (12 nodos)
Archivos: admin/lib/blasp/src/Core/Contracts/MaskStrategyInterface.php, admin/lib/blasp/src/Core/Dictionary.php, admin/lib/blasp/src/Core/Masking/GrawlixMask.php, admin/lib/blasp/src/Core/Result.php, admin/lib/blasp/src/Core/Score.php, admin/lib/blasp/src/Drivers/PatternDriver.php …
Símbolos: MaskStrategyInterface, GrawlixMask, .mask(), .getProfanities(), .mask(), .count(), .withMatches(), .calculate(), .detect(), .detect(), MaskStrategyInterface.php, GrawlixMask.php

### [86] Severity (12 nodos)
Archivos: admin/lib/blasp/src/Core/Result.php, admin/lib/blasp/src/Enums/Severity.php
Símbolos: Severity, .fromArray(), .none(), .isAtLeast(), .weight(), self, Severity.php, self, Extreme, High, Mild, Moderate

### [0] gerente/includes/i18n.php (12 nodos)
Archivos: gerente/includes/filtros.php, gerente/includes/i18n.php, gerente/ventas_sucursal.php
Símbolos: textoRangoActividad(), dinero(), fecha(), i18nParaJs(), i18nReemplazar(), idiomaActual(), numero(), tEs(), tn(), {closure#7}(), DateTimeInterface, gerente/includes/i18n.php

### [14] Login (12 nodos)
Archivos: login/i18n.php, login/login.php
Símbolos: e(), t(), textosLoginParaJs(), buscarUsuarioPorCorreo(), {closure#1}(), error(), guardarContrasena(), motivoContrasena(), responder(), login/i18n.php, login.php, PDO

### [24] profanity_filter.php (12 nodos)
Archivos: admin/profanity_filter.php
Símbolos: blaspEncuentraProfano(), {closure#1}(), {closure#2}(), compactarTexto(), consultarProfanityFilter(), datosFiltro(), obtenerApiVerveKey(), rechazarSiProfano(), tieneGroseriaComun(), tieneGroseriaPegada(), variantesTexto(), profanity_filter.php

### [15] renderCatalogo (11 nodos)
Archivos: cajero/index.php
Símbolos: agregarDesdeBusqueda(), agregarYLimpiar(), aplicarFiltros(), buscando(), cargarCatalogo(), coincideBusqueda(), filtrarCatalogo(), limpiarBusqueda(), normalizar(), palabrasDe(), productoDeBusqueda()

### [19] Cajas Gerente (11 nodos)
Archivos: gerente/cajas.php
Símbolos: abrirModalFondo(), {closure#2}(), {closure#3}(), {closure#4}(), {closure#5}(), {closure#7}(), inicialesCorta(), validarCajeroDeCaja(), cajas.php, PDO, TITULO_FONDO

### [28] filtros.php (11 nodos)
Archivos: gerente/includes/filtros.php, gerente/movimientos.php, gerente/ventas_sucursal.php
Símbolos: atajosDePeriodo(), {closure#1}(), diasConActividad(), leerFechaFiltro(), leerPeriodo(), rangosDeActividad(), {closure#1}(), {closure#1}(), DateTimeImmutable, filtros.php, PDO

### [10] Core Admin JS (10 nodos)
Archivos: gerente/js/core.js
Símbolos: abrirModal(), cerrarModal(), crearModalConfirmacion(), initCustomCursor(), limpiarAvisosCampos(), logoutConfirmado(), marcarCampoInvalido(), pedirConfirmacion(), tj(), core.js

### [87] Código de referencia (de la maqueta aprobada) (10 nodos)
Archivos: prompt-arbol-empleados-koaly.md
Símbolos: prompt-arbol-empleados-koaly.md, Contexto, CSS, Código de referencia (de la maqueta aprobada), HTML del modal (cabecera con el botón nuevo), JS de construcción del árbol, Resultado esperado, Tarea: mostrar los empleados del gerente como árbol de archivos (Koaly), Verificación, Íconos SVG (viewBox 0 0 24 24, solo trazo)

### [49] {closure#3} (9 nodos)
Archivos: gerente/includes/productos.php, gerente/movimientos.php
Símbolos: esCambioPrecio(), etiquetaTipo(), {closure#2}(), {closure#3}(), {closure#4}(), {closure#5}(), {closure#6}(), {closure#7}(), movimientos.php

### [16] Registrar Venta (7 nodos)
Archivos: cajero/registrar_venta.php
Símbolos: {closure#1}(), {closure#2}(), columnasTabla(), insertarFila(), responder(), registrar_venta.php, PDO

### [54] includes/productos.php (7 nodos)
Archivos: gerente/includes/productos.php
Símbolos: agregarProductoAInventario(), badgeStock(), generarCodigoUnico(), leerStockParaActualizar(), registrarMovimiento(), includes/productos.php, PDO

### [88] PhoneticMatcher (6 nodos)
Archivos: admin/lib/blasp/src/Core/Matchers/PhoneticMatcher.php
Símbolos: PhoneticMatcher, {closure#1}(), .buildIndex(), .__construct(), .match(), PhoneticMatcher.php

### [89] NombreInvalido (6 nodos)
Archivos: admin/reglas_nombre.php
Símbolos: NombreInvalido, .__construct(), normalizarNombrePersona(), normalizarParteNombre(), reglas_nombre.php, RuntimeException

### [61] reglas_contrasena.php (6 nodos)
Archivos: login/reglas_contrasena.php
Símbolos: generarPasswordTemporal(), passwordIsTooSimple(), passwordTemporalVencida(), ponerPasswordTemporal(), reglas_contrasena.php, PDO

### [90] CallbackMask (5 nodos)
Archivos: admin/lib/blasp/src/Core/Masking/CallbackMask.php
Símbolos: CallbackMask, .__construct(), .mask(), CallbackMask.php, Closure

### [91] includes/turnos.php (5 nodos)
Archivos: gerente/includes/turnos.php
Símbolos: cajaConTurnoAbierto(), dineroConSigno(), montoFormularioEnCentavos(), includes/turnos.php, PDO

### [23] Panel (4 nodos)
Archivos: admin/panel.php
Símbolos: {closure#1}(), {closure#2}(), {closure#3}(), panel.php

### [37] t (4 nodos)
Archivos: gerente/productos.php
Símbolos: {closure#1}(), {closure#2}(), {closure#3}(), gerente/productos.php

### [29] Cerrar Turno (3 nodos)
Archivos: cajero/turno_cerrar.php
Símbolos: {closure#1}(), responder(), turno_cerrar.php

### [30] Cortes (3 nodos)
Archivos: gerente/cortes.php
Símbolos: abrirRecibirCorte(), cortes.php, TEXTOS_RECIBIR

### [31] admin.js (3 nodos)
Archivos: admin/admin.js
Símbolos: initCustomCursor(), tj(), admin.js

### [63] initBarraLateral (3 nodos)
Archivos: estilos/barra-lateral.js
Símbolos: initBarraLateral(), ocultarTip(), barra-lateral.js

### [64] index.php (3 nodos)
Archivos: index.php
Símbolos: {closure#1}(), {closure#2}(), index.php

### [32] sucursales.php (2 nodos)
Archivos: admin/sucursales.php
Símbolos: filtrarSucursales(), sucursales.php

### [33] turno_abrir.php (2 nodos)
Archivos: cajero/turno_abrir.php
Símbolos: responder(), turno_abrir.php

### [34] turno_estado.php (2 nodos)
Archivos: cajero/turno_estado.php
Símbolos: responder(), turno_estado.php

### [35] verificar_existencias.php (2 nodos)
Archivos: cajero/verificar_existencias.php
Símbolos: {closure#1}(), verificar_existencias.php

### [36] csv.php (2 nodos)
Archivos: gerente/includes/csv.php
Símbolos: enviarCsv(), csv.php

### [69] turno_retiro.php (2 nodos)
Archivos: cajero/turno_retiro.php
Símbolos: responder(), turno_retiro.php

### [70] barra_filtros.php (2 nodos)
Archivos: gerente/includes/barra_filtros.php
Símbolos: {closure#1}(), barra_filtros.php

### [73] notificaciones.php (2 nodos)
Archivos: perfil/notificaciones.php
Símbolos: {closure#1}(), notificaciones.php
