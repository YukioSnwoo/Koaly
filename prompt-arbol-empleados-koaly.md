# Tarea: mostrar los empleados del gerente como árbol de archivos (Koaly)

## Contexto

En el panel de administración de Koaly (`admin/gerentes.php`), cada fila de la tabla de gerentes tiene un botón **"Empleados del gerente"** (ícono de personas, entre "Asignar sucursal" e "Historial de estados"). Al pulsarlo se abre un modal con los empleados de la sucursal del gerente.

Hoy el modal los muestra como una **lista plana** (nombre + badge de estado, y debajo "correo · Caja X"). Quiero cambiarlo para que se vea como un **árbol de archivos**, igual que un explorador de archivos.

Antes de editar, localiza:

1. El HTML del modal de empleados en `admin/gerentes.php` (o en el parcial o vista que lo contenga).
2. El JS que llena el modal: el que hace el fetch o recibe los datos y arma la lista.
3. El CSS del panel admin, para reutilizar sus variables de color, las clases `badge`/`badge--*` y los botones `btn`.

**No cambies** el endpoint, la consulta SQL ni la forma de los datos. Solo cambia cómo se pintan.

## Resultado esperado

```
▾ 👤 Ana Torres                      ← raíz: el gerente
  └ ▾ 📂 Sucursal Centro (3 empleados)
      ├ ▾ 👤 Carlos Mendoza        [Activo]
      │   ├ ✉ carlos.mendoza@koaly.com
      │   └ 🖨 Caja 01 · Principal
      ├ ▾ 👤 Sofía Ramírez         [Vacaciones]
      │   ├ ✉ sofia.ramirez@koaly.com
      │   └ 🖨 Sin caja asignada   (gris, en cursiva)
      └ ▾ 👤 Diego Luna            [Baja]
          ├ ✉ diego.luna@koaly.com
          └ 🖨 Caja 02
```

Requisitos:

- **Niveles:** gerente → carpeta de la sucursal (con conteo "(N empleados)", y en singular "(1 empleado)") → un nodo por empleado con su badge de estado alineado a la derecha → dos hojas por empleado: correo y caja.
- **Líneas guía:** cada nivel lleva las líneas de conexión (├ └ │) dibujadas con CSS (`border-left` y `border-top` en `::before`/`::after`), no con caracteres de texto.
- **Plegar y desplegar:** cada rama se abre y se cierra con una flecha (chevron) que rota 90°. Usa `<details>`/`<summary>` nativos, que ya son accesibles con teclado. La carpeta de la sucursal muestra un ícono de carpeta cerrada o abierta según su estado.
- **Abierto por defecto:** todo empieza desplegado.
- **Botón "Contraer todo / Expandir todo":** va en la cabecera del modal, junto al título. Pliega o despliega solo los nodos de los empleados; la raíz y la sucursal se quedan abiertas. Cada vez que se abre el modal, el árbol vuelve a estar todo desplegado y el botón dice "Contraer todo".
- **Subtítulo del modal:** `Nombre del gerente · Sucursal X`.
- **Sin empleados:** se sigue mostrando el árbol (gerente → sucursal), con una hoja gris en cursiva: "Este gerente aún no tiene empleados en su sucursal."
- **Seguridad:** construye los nodos con `document.createElement` + `textContent`, nunca concatenando datos del servidor en `innerHTML`, para evitar XSS. El `innerHTML` solo se permite para los SVG de íconos, que son constantes.
- **Temas:** debe verse bien en tema claro y oscuro, usando las variables CSS que ya existan en el proyecto. Agrega una variable para el color de las líneas, por ejemplo `--line` (`#cfc8e0` en claro, `#433d58` en oscuro), si no hay una equivalente.
- **Cerrar:** el modal se sigue cerrando como antes (botón Cerrar, clic en el fondo y tecla Esc).
- **Lo demás no se toca:** el botón de empleados se mantiene deshabilitado cuando el gerente no tiene sucursal. No toques la tabla ni las otras acciones.

## Código de referencia (de la maqueta aprobada)

Adáptalo a los nombres reales de los campos y a la estructura del proyecto. En la maqueta, cada empleado es `{nombre, email, estado, caja}`, donde `caja` puede ser `null`, y el gerente es `{nombre, sucursal}`. `ESTADOS` mapea cada estado a su clase de badge: `Activo → badge--success`; `Vacaciones`, `Incapacidad` y `Licencia → badge--warning`; `Baja → badge--danger`. Si el proyecto ya tiene ese mapeo, reutilízalo.

### HTML del modal (cabecera con el botón nuevo)

```html
<div class="modal__head">
  <div><h2 id="tit">Empleados del gerente</h2><p class="sub" id="gNombre"></p></div>
  <button class="btn btn--sm" type="button" id="alternar">Contraer todo</button>
</div>
<div class="lista" id="lista"></div>
```

### CSS

```css
.modal__head{display:flex;align-items:flex-start;justify-content:space-between;gap:8px;flex-wrap:wrap;margin-bottom:12px}
.btn--sm{padding:4px 10px;font-size:12px}
.lista{overflow:auto;padding:8px 4px}

.arbol,.arbol ul{list-style:none;margin:0;padding:0}
.arbol ul{padding-left:22px}
.arbol li{position:relative}
.arbol ul>li{padding-left:16px}
.arbol ul>li::before{content:"";position:absolute;left:0;top:0;bottom:0;border-left:1px solid var(--line)}
.arbol ul>li:last-child::before{bottom:auto;height:15px}
.arbol ul>li::after{content:"";position:absolute;left:0;top:15px;width:12px;border-top:1px solid var(--line)}
.nodo{display:flex;align-items:center;gap:6px;min-height:30px;padding:2px 6px;border-radius:6px;min-width:0}
.arbol summary{list-style:none;cursor:pointer}
.arbol summary::-webkit-details-marker{display:none}
.arbol summary:hover{background:var(--accent-soft)}
.chev{width:14px;height:14px;flex:none;color:var(--muted);fill:none;stroke:currentColor;stroke-width:2;stroke-linecap:round;stroke-linejoin:round;transition:transform .15s}
details[open]>summary .chev{transform:rotate(90deg)}
.sin-chev{width:14px;flex:none}
.ico{width:16px;height:16px;flex:none;fill:none;stroke:currentColor;stroke-width:1.8;stroke-linecap:round;stroke-linejoin:round}
.ico--carpeta{color:var(--accent)}
.ico--archivo{color:var(--muted)}
.ico--abierta{display:none}
details[open]>summary .ico--cerrada{display:none}
details[open]>summary .ico--abierta{display:block}
.nodo__txt{overflow-wrap:anywhere;min-width:0}
.nodo__txt--fuerte{font-weight:500}
.nodo__txt--dato{font-size:13px;color:var(--secondary)}
.nodo__txt--vacio{font-size:13px;color:var(--muted);font-style:italic}
.conteo{font-size:12px;color:var(--muted)}
.nodo .badge{margin-left:auto;flex:none}
```

### Íconos SVG (viewBox 0 0 24 24, solo trazo)

```js
const ICONOS_ARBOL={
  chev:'<path d="M9 6l6 6-6 6"/>',
  carpeta:'<path d="M3 7a2 2 0 0 1 2-2h4l2 2h8a2 2 0 0 1 2 2v8a2 2 0 0 1-2 2H5a2 2 0 0 1-2-2z"/>',
  carpetaAbierta:'<path d="M3 7a2 2 0 0 1 2-2h4l2 2h7a2 2 0 0 1 2 2v1"/><path d="M3 7v10a2 2 0 0 0 2 2h12.5a2 2 0 0 0 1.9-1.4L21.8 12a1.5 1.5 0 0 0-1.4-2H7.6a2 2 0 0 0-1.9 1.4L3 19"/>',
  persona:'<circle cx="12" cy="8" r="4"/><path d="M4 21v-1a6 6 0 0 1 6-6h4a6 6 0 0 1 6 6v1"/>',
  correo:'<rect x="3" y="5" width="18" height="14" rx="2"/><path d="M3 7l9 6 9-6"/>',
  caja:'<rect x="4" y="10" width="16" height="10" rx="2"/><path d="M7 10V5h10v5"/><path d="M8 14h2M12 14h4M8 17h8"/>'
};
```

### JS de construcción del árbol

```js
function icono(k,clase){
  const s=document.createElementNS('http://www.w3.org/2000/svg','svg');
  s.setAttribute('viewBox','0 0 24 24');s.setAttribute('aria-hidden','true');
  s.setAttribute('class',clase);s.innerHTML=ICONOS_ARBOL[k];return s;
}
function texto(t,mod){const s=document.createElement('span');s.className='nodo__txt'+(mod?' nodo__txt--'+mod:'');s.textContent=t;return s}
// Rama desplegable. iconoFijo: si se da, reemplaza a la carpeta abierta/cerrada.
function rama({etiqueta,iconoFijo,extra,hijos}){
  const li=document.createElement('li'),d=document.createElement('details'),sm=document.createElement('summary');
  d.open=true;sm.className='nodo';
  sm.append(icono('chev','chev'));
  if(iconoFijo){sm.append(icono(iconoFijo,'ico ico--carpeta'))}
  else{sm.append(icono('carpeta','ico ico--carpeta ico--cerrada'),icono('carpetaAbierta','ico ico--carpeta ico--abierta'))}
  sm.append(texto(etiqueta,'fuerte'));
  (extra||[]).forEach(x=>sm.append(x));
  const ul=document.createElement('ul');hijos.forEach(h=>ul.append(h));
  d.append(sm,ul);li.append(d);return li;
}
// Hoja (archivo)
function hoja(k,t,mod){
  const li=document.createElement('li'),n=document.createElement('div');n.className='nodo';
  const sp=document.createElement('span');sp.className='sin-chev';
  n.append(sp,icono(k,'ico ico--archivo'),texto(t,mod||'dato'));li.append(n);return li;
}

// Llamar esto donde hoy se pinta la lista, con el gerente y sus empleados ya cargados.
function pintarArbol(g,regs){
  const lista=document.getElementById('lista'),alternar=document.getElementById('alternar');
  document.getElementById('gNombre').textContent=g.nombre+' · Sucursal '+g.sucursal;
  const nodosEmp=regs.map(r=>{
    const b=document.createElement('span');b.className='badge '+ESTADOS[r.estado];b.textContent=r.estado;
    return rama({etiqueta:r.nombre,iconoFijo:'persona',extra:[b],hijos:[
      hoja('correo',r.email),
      r.caja?hoja('caja','Caja '+r.caja):hoja('caja','Sin caja asignada','vacio')
    ]});
  });
  if(!nodosEmp.length)nodosEmp.push(hoja('persona','Este gerente aún no tiene empleados en su sucursal.','vacio'));
  const c=document.createElement('span');c.className='conteo';
  c.textContent='('+regs.length+(regs.length===1?' empleado)':' empleados)');
  const sucursal=rama({etiqueta:'Sucursal '+g.sucursal,extra:[c],hijos:nodosEmp});
  const raiz=document.createElement('ul');raiz.className='arbol';raiz.setAttribute('aria-label','Empleados de '+g.nombre);
  raiz.append(rama({etiqueta:g.nombre,iconoFijo:'persona',hijos:[sucursal]}));
  lista.replaceChildren(raiz);
  alternar.textContent='Contraer todo';
}

// Registrar una sola vez (no dentro de pintarArbol)
document.getElementById('alternar').addEventListener('click',e=>{
  const ds=[...document.querySelectorAll('#lista details')].slice(2); // raíz y sucursal quedan abiertas
  const abrir=e.currentTarget.textContent==='Expandir todo';
  ds.forEach(d=>d.open=abrir);
  e.currentTarget.textContent=abrir?'Contraer todo':'Expandir todo';
});
```

Si el modal ya muestra un estado de carga o de error mientras llegan los datos, consérvalo: solo reemplaza el pintado de la lista cuando los datos ya están listos.

## Verificación

1. Abre el modal de un gerente con varios empleados y comprueba la estructura, las líneas guía, los badges y que "Sin caja asignada" sale en gris.
2. Pliega y despliega nodos sueltos y prueba el botón "Contraer todo / Expandir todo". Cierra y vuelve a abrir el modal: todo debe aparecer desplegado otra vez.
3. Abre el modal de un gerente con sucursal pero sin empleados y comprueba que sale el mensaje vacío dentro del árbol.
4. Revisa el tema oscuro y un ancho de móvil de unos 375 px: los correos largos deben partirse sin generar scroll horizontal.
5. Navega con el teclado: Tab llega a cada `summary`, Enter o Espacio pliegan y despliegan, y Esc cierra el modal.
6. Comprueba que no hay errores en la consola.

Al terminar, muéstrame un resumen de los archivos que cambiaste. **No hagas commit** hasta que lo revise.
